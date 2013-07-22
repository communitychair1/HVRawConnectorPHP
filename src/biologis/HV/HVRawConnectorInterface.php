<?php

/**
 * @copyright Copyright 2012-2013 Markus Kalkbrenner, bio.logis GmbH (https://www.biologis.com)
 * @license GPLv2
 * @author Markus Kalkbrenner <info@bio.logis.de>
 */

namespace biologis\HV;

/**
 * Class HVRawConnectorInterface
 * @package biologis\HV
 */
interface HVRawConnectorInterface {

  public function setHealthVaultPlatform($healthVaultPlatform);

  public function connect();

  public function makeRequest($method, $methodVersion , $additionalHeaders, $personId );

  public static function getAuthenticationURL($appId, $redirect, $config,
                                              $healthVaultAuthInstance,
                                              $target, $additionalTargetQSParams);

  public static function invalidateSession(&$session);

  public function getRawResponse();

  public function getQueryPathResponse();

}
