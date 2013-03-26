<?php

/**
 * @copyright Copyright 2012-2013 Markus Kalkbrenner, bio.logis GmbH (https://www.biologis.com)
 * @license GPLv2
 * @author Markus Kalkbrenner <info@bio.logis.de>
 */

namespace biologis\HV;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class HVRawConnector extends AbstractHVRawConnector implements LoggerAwareInterface {
  public static $version = 'HVRawConnector1.1.0';

  private $session;
  private $appId;
  private $thumbPrint;
  private $privateKeyFile;
  private $sharedSecret;
  private $digest;
  private $authToken;
  private $userAuthToken;
  private $rawResponse;
  private $qpResponse;
  private $responseCode;
  private $logger = NULL;

  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  public function __construct($appId, $thumbPrint, $privateKeyFile, &$session) {
    $this->session = & $session;
    $this->appId = $appId;
    $this->thumbPrint = $thumbPrint;
    $this->privateKeyFile = $privateKeyFile;

    if (empty($this->session['healthVault']['sharedSecret'])) {
      $this->session['healthVault']['sharedSecret'] = $this->hash(uniqid());
      $this->session['healthVault']['digest'] = $this->hmacSha1($this->session['healthVault']['sharedSecret'], $this->session['healthVault']['sharedSecret']);
    }

    $this->sharedSecret = $this->session['healthVault']['sharedSecret'];
    $this->digest = $this->session['healthVault']['digest'];
  }

  public function connect() {
    if (!$this->logger) {
      $this->logger = new NullLogger();
    }

    if (empty($this->session['healthVault']['userAuthToken']) && !empty($_GET['wctoken']) && $_GET['redirectToken'] == $this->session['healthVault']['redirectToken']) {
      // TODO verify wctoken / security check
      $this->session['healthVault']['userAuthToken'] = $_GET['wctoken'];
    }

    if (!empty($this->session['healthVault']['userAuthToken'])) {
      $this->userAuthToken = $this->session['healthVault']['userAuthToken'];
    }
    else {
      throw new HVRawConnectorUserNotAuthenticatedException();
    }

    if (empty($this->session['healthVault']['authToken'])) {
      $info = qp(HVRawConnector::$commandCreateAuthenticatedSessionTokenXML, NULL, array('use_parser' => 'xml'))
        ->xpath('auth-info/app-id')->text($this->appId)
        ->xpath('//content/app-id')->text($this->appId)
        ->find(':root sig')->attr('thumbprint', $this->thumbPrint)
        ->find(':root hmac-alg')->text($this->digest);

      $content = $info->find(':root content')->xml();

      $xml = $info->find(':root sig')->text($this->sign($content))->find(':root')->innerXML();

      // throws HVRawConnectorAnonymousWcRequestException
      $this->anonymousWcRequest('CreateAuthenticatedSessionToken', '1', $xml);

      $this->session['healthVault']['authToken'] = $this->qpResponse->find(':root token')->text();
    }

    if (!empty($this->session['healthVault']['authToken'])) {
      $this->authToken = $this->session['healthVault']['authToken'];
    }
  }


  public function anonymousWcRequest($method, $methodVersion = '1', $info = '', $additionalHeaders = array()) {
    $header = $this->getBasicCommandQueryPath(HVRawConnector::$anonymousWcRequestXML, $method, $methodVersion, $info)
      ->find(':root header app-id')->text($this->appId);

    $this->addAdditionalHeadersToWcRequest($header, $additionalHeaders);

    $this->doWcRequest($header);
  }


  public function authenticatedWcRequest($method, $methodVersion = '1', $info = '', $additionalHeaders = array()) {
    $header = $this->getBasicCommandQueryPath(HVRawConnector::$authenticatedWcRequestXML, $method, $methodVersion, $info)
      ->find(':root header hash-data')->text($this->hash(empty($info) ? '<info/>' : '<info>' . $info . '</info>'))
      ->find(':root header auth-token')->text($this->authToken)
      ->find(':root header user-auth-token')->text($this->userAuthToken);

    $this->addAdditionalHeadersToWcRequest($header, $additionalHeaders);
    $headerRawXml = $header->find(':root header')->xml();

    $this->doWcRequest(
      $header->find(':root hmac-data')->text($this->hmacSha1($headerRawXml, base64_decode($this->session['healthVault']['digest'])))
    );
  }


  protected function getBasicCommandQueryPath($wcRequestXML, $method, $methodVersion, $info) {
    return qp($wcRequestXML)
      ->find(':root method')->text($method)
      ->find(':root method-version')->text($methodVersion)
      ->find(':root msg-time')->text(gmdate("Y-m-d\TH:i:s"))
      ->find(':root version')->text(HVRawConnector::$version)
      ->find(':root info')->append($info)
      ->find(':root');
  }


  private function addAdditionalHeadersToWcRequest($header, $additionalHeaders) {
    if ($this->language) {
      $header->find(':root header language')->text($this->language);
    }
    if ($this->country) {
      $header->find(':root header language')->text($this->country);
    }
    if (!empty($additionalHeaders)) {
      $header->find(':root method-version');
      foreach ($additionalHeaders as $element => $text) {
        $header->after('<' . $element . '>' . $text . '</' . $element . '>');
      }
    }
  }

  protected function doWcRequest($qpObject) {
    $params = array(
      'http' => array(
        'method' => 'POST',
        // remove line breaks and spaces between elements, otherwise the signature check will fail
        'content' => preg_replace('/>\s+</', '><', $qpObject->find(':root')->xml()),
      ),
    );
    var_dump($params['http']['content']);
    $this->logger->debug('Request: ' . $params['http']['content']);
    $ctx = stream_context_create($params);
    $this->rawResponse = @file_get_contents($this->healthVaultPlatform, FALSE, $ctx);
    if (!$this->rawResponse) {
      $this->qpResponse = NULL;
      $this->responseCode = -1;
      throw new \Exception('HealthVault Connection Failure', -1);
    }
    $this->logger->debug('Response: ' . $this->rawResponse);
    $this->qpResponse = qp($this->rawResponse, NULL, array('use_parser' => 'xml'));
    $this->responseCode = (int) $this->qpResponse->xpath('/response/status/code')->text();

    if ($this->responseCode > 0) {
      $this->logger->error('Response Code: ' . $this->responseCode);
      $this->logger->error('Error Message: ' . $this->qpResponse->find(':root error message')->text());
      switch ($this->responseCode) {
        // TODO add more error codes
        case 7: // The user authenticated session token has expired.
        case 65: // The authenticated session token has expired.
          // the easiest solution is to invalidate everything and let the user initialize a new connection @see _construct()
          HVRawConnector::invalidateSession($this->session);
          throw new HVRawConnectorAuthenticationExpiredException($this->qpResponse->find(':root error message')->text(), $this->responseCode);
      }
      throw new HVRawConnectorWcRequestException($this->qpResponse->find(':root error message')->text(), $this->responseCode);
    }
  }


  protected function sign($str) {
    static $privateKey = NULL;

    if (is_null($privateKey)) {
      if (is_file($this->privateKeyFile) && is_readable($this->privateKeyFile)) {
        $privateKey = @file_get_contents($this->privateKeyFile);
      }
      else {
        throw new Exception('Unable to read private key file.');
      }
    }

    openssl_sign(
      // remove line breaks and spaces between elements, otherwise the signature check will fail
      preg_replace('/>\s+</', '><', $str),
      $signature,
      $privateKey,
      OPENSSL_ALGO_SHA1);

    return trim(base64_encode($signature));
  }


  protected function hash($str)
  {
    return trim(base64_encode(sha1(preg_replace('/>\s+</', '><', $str), TRUE)));
  }


  protected function hmacSha1($str, $key) {
    return trim(base64_encode(hash_hmac('sha1', preg_replace('/>\s+</', '><', $str), $key, TRUE)));
  }


  public static function getAuthenticationURL($appId, $redirect, &$session, $healthVaultAuthInstance = 'https://account.healthvault-ppe.com/redirect.aspx') {
    $session['healthVault']['redirectToken'] = md5(uniqid());

    $redirectUrl = new \Net_URL2($redirect);
    $redirectUrl->setQueryVariable('redirectToken', $session['healthVault']['redirectToken']);

    $healthVaultUrl = new \Net_URL2($healthVaultAuthInstance);
    $healthVaultUrl->setQueryVariables(array(
      'target' => 'AUTH',
      'targetqs' => '?appid=' . $appId . '&redirect=' . $redirectUrl->getURL(),
    ));

    return $healthVaultUrl->getURL();
  }

  public static function invalidateSession(&$session) {
    unset($session['healthVault']);
  }


  public function getRawResponse() {
    return $this->rawResponse;
  }


  public function getQueryPathResponse() {
    return $this->qpResponse->find(':root');
  }
}

class HVRawConnectorUserNotAuthenticatedException extends \Exception {}

class HVRawConnectorAppNotAuthenticatedException extends \Exception {}

class HVRawConnectorAuthenticationExpiredException extends \Exception {}

class HVRawConnectorWcRequestException extends \Exception {}