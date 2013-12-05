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
use biologis\HV\Net_URL2;

// TODO Remove this
include("URL2.php");

class HVRawConnector extends AbstractHVRawConnector implements LoggerAwareInterface
{
    public static $version = 'HVRawConnector1.2.0';

    // Passed in via the constructor
    private $appId;
    private $thumbPrint;
    private $privateKey;
    private $config; // array of additional parameters

    // Generated in the constructor
    private $sharedSecret;
    private $digest;

    // Saved responses from HealthVault
    private $rawResponse;
    private $qpResponse;
    private $responseCode;

    // Call setLogger to change this from the default NullLogger
    private $logger = NULL;

    // This gets set in the connect method.
    private $authToken;

    /** Constructor
     * @param string $appId
     *   HealthVault Application ID
     * @param string $thumbPrint
     *   Certificate thumb print
     * @param string $privateKey
     *   Private key as string or file path to load private key from
     * @param array $config
     *   Config array
     */
    public function __construct($appId, $thumbPrint, $privateKey, $config)
    {
        $this->appId = $appId;
        $this->thumbPrint = $thumbPrint;
        $this->privateKey = $privateKey;
        $this->config = $config;
        $this->logger = new NullLogger();

        if (empty($this->config['healthVault']['sharedSecret'])) {
            $this->sharedSecret = $this->hash(uniqid());
            $this->digest = $this->hmacSha1($this->sharedSecret, $this->sharedSecret);
        }

        if (!empty($this->config['authToken'])) {
            $this->authToken = $this->config['authToken'];
        }

    }


    /** Connect
     * If we have a wctoken in the session, connect to HV and get a userauthtoken
     *
     * @throws HVRawConnectorUserNotAuthenticatedException
     *
     */
    public function connect()
    {

        // Grab an authToken from HV
        if (empty($this->authToken)) {
            $info = qp(HVRawConnector::getAuthenticatedSessionTokenTemplateXml(), NULL, array('use_parser' => 'xml'))
                ->xpath('auth-info/app-id')->text($this->appId)->top()
                ->xpath('//content/app-id')->text($this->appId)->top()
                ->find('sig')->attr('thumbprint', $this->thumbPrint)->top()
                ->find('hmac-alg')->text($this->digest)->top();

            $content = $info->find('content')->xml();

            $xml = $info->top()->find('sig')->text($this->sign($content))->top()->innerXML();

            // throws HVRawConnectorAnonymousWcRequestException
            $this->anonymousWcRequest('CreateAuthenticatedSessionToken', '1', $xml);
            $this->authToken = $this->qpResponse->find('token')->text();
        }
        return $this->authToken;
    }

    /** Anonymouse WC Request
     * @param $method
     * @param string $methodVersion
     * @param string $info
     * @param array $additionalHeaders
     */
    public function anonymousWcRequest($method, $methodVersion = '1', $info = '', $additionalHeaders = array())
    {
        $header = $this->getBasicCommandQueryPath(HVRawConnector::getAnonymousWcRequestTemplateXML(), $method, $methodVersion, $info)
            ->find('header app-id')->text($this->appId)->top();

        $this->addAdditionalHeadersToWcRequest($header, $additionalHeaders);

        $this->doWcRequest($header);
    }

    /** Make Request
     * @param $method
     * @param string $methodVersion
     * @param string $info
     * @param array $additionalHeaders
     * @param null $personId
     * @throws HVRawConnectorUserNotAuthenticatedException
     * * Currently only used for getPersonInfo on inital login.  That information should be stored for later use of
     * offline connect and request.
     */
    public function makeRequest($method, $methodVersion = '1', $info = '', $additionalHeaders = array(), $personId = null)
    {
        $starterXML = null;
        $offline = true;

        if (empty($this->config['wctoken'])) {
            // Quick test to ensure we have a $personId
            if (empty($personId)) {
                throw new HVRawConnectorUserNotAuthenticatedException();
            }
            // Offline Request
            $offline = true;
            $starterXML = HVRawConnector::getOfflineRequestXML();
        } else {
            // wctoken is empty, make an Online Request
            $offline = false;
            $starterXML = HVRawConnector::getAuthenticatedWcRequestXML();
        }

        $requestXMLObj = $this->getBasicCommandQueryPath($starterXML, $method, $methodVersion, $info);

        // Fill in the placeholder strings with the actual data. Probably would be a lot more efficient
        // to do an actual string replacement rather than parse XML, replace, and then create an XML string again.
        // Just sayin...
        $requestXMLObj->find('header hash-data')->text($this->hash(empty($info) ? '<info/>' : '<info>' . $info . '</info>'))->top()
            ->find('header auth-token')->text($this->authToken)->top();

        // This is the sole difference between an offline and online request, make the string replacement
        if ($offline) {
            $requestXMLObj->find('header offline-person-id')->text($personId)->top();
        } else {
            $requestXMLObj->find('header user-auth-token')->text($this->config['wctoken'])->top();
        }

        $this->addAdditionalHeadersToWcRequest($requestXMLObj, $additionalHeaders);
        $headerRawXml = $requestXMLObj->find('header')->xml();
        $this->doWcRequest(
            $requestXMLObj->top()->find('hmac-data')
                ->text($this->hmacSha1($headerRawXml, base64_decode($this->digest)))
        );
    }


    /** Get Basic Command Query Path
     * @param $wcRequestXML
     * @param $method
     * @param $methodVersion
     * @param $info
     * @return mixed
     */
    protected function getBasicCommandQueryPath($wcRequestXML, $method, $methodVersion, $info)
    {
        return qp($wcRequestXML)
            ->find('method')->text($method)->top()
            ->find('method-version')->text($methodVersion)->top()
            ->find('msg-time')->text(gmdate("Y-m-d\TH:i:s"))->top()
            ->find('version')->text(HVRawConnector::$version)->top()
            ->find('info')->append($info)->top();
    }

    /** Add Additional Header To WC Request
     * @param $header
     * @param $additionalHeaders
     */
    private function addAdditionalHeadersToWcRequest($header, $additionalHeaders)
    {
        if (!empty($this->config['language'])) {
            $header->top()->find('header language')->text($this->config['language']);
        }
        if (!empty($this->config['country'])) {
            $header->top()->find('header language')->text($this->config['country']);
        }
        if (!empty($additionalHeaders)) {
            foreach ($additionalHeaders as $element => $text) {
                $header->top()->find('method-version')->after('<' . $element . '>' . $text . '</' . $element . '>');
            }
        }
        $header->top();
    }

    /** Do WC Request
     * @param $qpObject
     * @throws HVRawConnectorAuthenticationExpiredException
     * @throws HVRawConnectorWcRequestException
     * @throws \Exception
     */
    protected function doWcRequest($qpObject)
    {

        $params = array(
            'http' => array(
                'method' => 'POST',
                // remove line breaks and spaces between elements, otherwise the signature check will fail
                'content' => preg_replace('/>\s+</', '><', $qpObject->top()->xml()),
            ),
        );

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
        $this->responseCode = (int)$this->qpResponse->find('response>status>code')->text();

        if ($this->responseCode > 0) {
           $this->HandleStatusCodes($this->responseCode);
        }

        $this->qpResponse->top();
    }

    /** Handle Status Codes
     *      Handles exceptions for status codes returned
     *      from health vault HTTP Requests
     * @param $respCode
     * @throws HVRawConnectorAuthenticationExpiredException
     * @throws HVRawConnectorWcRequestException
     */
    private function HandleStatusCodes($respCode){
        //Switch on the response code to handle specific status-codes
        switch ($this->responseCode)
        {
            case 7: // The user authenticated session token has expired.
            case 65: // The authenticated session token has expired.
                HVRawConnector::invalidateSession($this->config);
                throw new HVRawConnectorAuthenticationExpiredException($this->qpResponse->top()->find('error message')->text(), $this->responseCode);
            default: // Handle all status's without a particular case
                throw new HVRawConnectorAuthenticationExpiredException($this->qpResponse->top()->find('error message')->text(), $this->responseCode);
        }
    }

    /** Sign
     * @param $str
     * @return string
     * @throws Exception
     */
    protected function sign($str)
    {
        static $privateKey = NULL;

        if (is_null($privateKey)) {
            if (is_file($this->privateKey)) {
                if (is_readable($this->privateKey)) {
                    $privateKey = @file_get_contents($this->privateKey);
                } else {
                    throw new Exception('Unable to read private key file.');
                }
            } else {
                $privateKey = $this->privateKey;
            }
        }

        // TODO check if $privateKey really is a key (format)


        openssl_sign(
        // remove line breaks and spaces between elements, otherwise the signature check will fail
            preg_replace('/>\s+</', '><', $str),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA1);

        return trim(base64_encode($signature));
    }

    /** Get Authentication URL
     * @param $appId App ID to return the user to.
     * @param $redirect URL to return the user to after successful/unsuccessful HealthVault call.
     * @param $config PHP Session object. Should look to NOT rely upon this at all.
     * @param string $healthVaultAuthInstance URL to use, defaults to the HealthVault PPE account.
     * @param string $target Should be "AUTH/APPAUTH". See http://msdn.microsoft.com/en-us/healthvault/bb871492.aspx for when
     * to use each one.
     * @param array $additionalTargetQSParams Associative array of key/values for additional targetqs params for HealthVault
     * @return string
     */
    public static function getAuthenticationURL($appId, $redirect, $config,
                                                $healthVaultAuthInstance = 'https://account.healthvault-ppe.com/redirect.aspx',
                                                $target = "AUTH", $additionalTargetQSParams = null)
    {
        $config['healthVault']['redirectToken'] = md5(uniqid());

        // $redirectUrl = $redirect;
        $redirectUrl = new Net_URL2($redirect);

        // TODO: Form this using PHP functions and not the Net_URL2 class
        // $queryStr

        $redirectUrl->setQueryVariable('redirectToken', $config['healthVault']['redirectToken']);

        $healthVaultUrl = new Net_URL2($healthVaultAuthInstance);
        $targetQS = '?appid=' . $appId . '&redirect=' . $redirectUrl->getURL();


        if (!empty($additionalTargetQSParams)) {
            foreach ($additionalTargetQSParams as $key => $val) {
                $targetQS .= "&" . urlencode($key) . "=" . urlencode($val);
            }
        }

        $healthVaultUrl->setQueryVariables(array(
            'target' => $target,
            'targetqs' => $targetQS
        ));

        return $healthVaultUrl->getURL();
    }

    /** Hash
     * @param $str
     * @return string
     */
    protected function hash($str)
    {
        return trim(base64_encode(sha1(preg_replace('/>\s+</', '><', $str), TRUE)));
    }

    /** Hmac Sha 1
     * @param $str
     * @param $key
     * @return string
     */
    protected function hmacSha1($str, $key)
    {
        return trim(base64_encode(hash_hmac('sha1', preg_replace('/>\s+</', '><', $str), $key, TRUE)));
    }

    /** Invalidate Session
     * @param $config
     */
    public static function invalidateSession(&$config)
    {
        unset($config['healthVault']);
    }

    /** Get Raw Response
     * @return mixed
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /** Get Query Path Response
     * @return mixed
     */
    public function getQueryPathResponse()
    {
        return $this->qpResponse->top();
    }

    /** Set Logger
     * @param LoggerInterface $logger
     * @return null|void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

}

/**
 * Class HVRawConnectorUserNotAuthenticatedException
 * @package biologis\HV
 */
class HVRawConnectorUserNotAuthenticatedException extends \Exception
{
}

/**
 * Class HVRawConnectorAppNotAuthenticatedException
 * @package biologis\HV
 */
class HVRawConnectorAppNotAuthenticatedException extends \Exception
{
}

/**
 * Class HVRawConnectorAuthenticationExpiredException
 * @package biologis\HV
 */
class HVRawConnectorAuthenticationExpiredException extends \Exception
{
}


/**
 * Class HVRawConnectorWcRequestException
 * @package biologis\HV
 */
class HVRawConnectorWcRequestException extends \Exception
{
}
