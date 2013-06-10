<?php

namespace biologis\HV;

use biologis\HV\HVRawConnector;
use biologis\HV\HVClient;
use PHPUnit_Extensions_SeleniumTestCase;

require('SeleniumTestCase.php');


class HVRawConnectorFunctionalTest extends PHPUnit_Extensions_SeleniumTestCase
{

    private $connector = null;
    private $appId = null;
    private $thumbPrint = null;
    private $privateKey = null;
    private $recordId = null;
    private $hv;

    protected function setUp()
    {
        $baseConfigPath = realpath("../app/Resources/HealthVault/dev");
        $this->appId = file_get_contents($baseConfigPath . '/app.id');
        $this->thumbPrint = file_get_contents($baseConfigPath . '/app.fp');
        $this->privateKey = file_get_contents($baseConfigPath . '/app.pem');
        $this->session = & $_SESSION;
        $this->personId = '3933614a-92bc-4da5-95c0-6085f7aef4aa';
        $this->recordId = '97cb6d50-8c8e-4aff-8818-483efdfed7d5';
        $this->hv = new HVClient($this->appId, $this->session, $this->personId, true);

        $this->connector = new HVRawConnector(
            $this->appId,
            $this->thumbPrint,
            $this->privateKey,
            $this->session,
            true
        );

        $this->setBrowser('chrome');
        $this->setBrowserUrl('http://mentis.local.com/');


    }

    public function testConnect()
    {
        $this->open('http://mentis.local.com/');
        $this->waitForPageToLoad();
        $this->click('id=live_button');
        $this->waitForPageToLoad();
        $this->type('id=idDiv_PWD_UsernameExample', 'mentis.test1@gmail.com');
        $this->type('id=idDiv_PWD_PasswordExample', 'MentisTest1');
        $this->click('id=idSIButton9');
        $this->waitForPageToLoad();
        //$this->connector->connect();
        //$this->assertNotEmpty($this->session['healthVault']['authToken']);
        //$this->assertNotEmpty($this->session['healthVault']['userAuthToken']);
    }


}