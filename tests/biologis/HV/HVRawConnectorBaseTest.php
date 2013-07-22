<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Syntinel
 * Date: 7/22/13
 * Time: 1:00 PM
 * To change this template use File | Settings | File Templates.
 */

namespace biologis\HV;

use biologis\HV\HVClient;

class HVRawConnectorBaseTest extends \PHPUnit_Framework_TestCase
{
    private $appId = null;
    private $thumbPrint = null;
    private $privateKey = null;
    private $recordId = null;
    private $hv;

    /** Set Up
     *      Configures a test env for php unit
     */
    protected function setUp()
    {
        $baseConfigPath = realpath("app/Resources/HealthVault/dev");
        $this->appId = file_get_contents($baseConfigPath . '/app.id');
        $this->thumbPrint = file_get_contents($baseConfigPath . '/app.fp');
        $this->privateKey = file_get_contents($baseConfigPath . '/app.pem');
        $this->personId = '3933614a-92bc-4da5-95c0-6085f7aef4aa';
        $this->recordId = '97cb6d50-8c8e-4aff-8818-483efdfed7d5';
        $config = array();
        $this->hv = new HVClient($this->thumbPrint, $this->privateKey, $this->appId, $this->personId, $config );
    }

    /** Test Connect
     *      Test the set up.
     */
    public function testConnect()
    {
        $authToken = $this->hv->connect();
        $this->assertNotEmpty( $authToken, "Unable to connect to HealthVault.");
    }



}
