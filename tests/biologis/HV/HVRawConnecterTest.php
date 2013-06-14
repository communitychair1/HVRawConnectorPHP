<?php

namespace biologis\HV;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use biologis\HV\HVRawConnector;
use biologis\HV\HVClient;

class HVRawConnectorTest extends \PHPUnit_Framework_TestCase
{
    private $onlineConnector = null;
    private $offlineConnector = null;
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
        $this->personId = '3933614a-92bc-4da5-95c0-6085f7aef4aa';
        $this->recordId = '97cb6d50-8c8e-4aff-8818-483efdfed7d5';
        $config = array();
        $this->hv = new HVClient($this->thumbPrint, $this->privateKey, $this->appId, $this->personId, $config );

        /*
        $this->onlineConnector = new HVRawConnector(
            $this->appId,
            $this->thumbPrint,
            $this->privateKey,
            $this->session,
            true
        );

        $this->offlineConnector = new HVRawConnector(
            $this->appId,
            $this->thumbPrint,
            $this->privateKey,
            $this->session,
            false
        );
        */
    }

    public function testConnect()
    {
        $authToken = $this->hv->connect();
        $this->assertNotEmpty( $authToken, "Unable to connect to HealthVault.");
    }

    public function testAnonymousWcRequest()
    {
        //TODO: Figure out how to handle online testing
        /*
         *
         */

        //$this->offlineConnector()
        // $this->offlineConnector->connect();
    }

    public function testAuthenticatedWcRequest()
    {
        //TODO: Figure out online connection
        //$this->onlineConnector->connect();
    }

    public function testGetPersonInfo()
    {
        $this->hv->connect();
        $personInfo = $this->hv->getPersonInfo();
        $this->assertNotNull($personInfo, "Unable to retrieve PersonInfo");
        $personInfo = $this->hv->getPersonInfo();
        $this->assertNotNull($personInfo, "Unable to retrieve PersonInfo");
        /*
        $typeId = HealthRecordItemFactory::getTypeId("Personal Demographic Information");
        $this->offlineConnector->connect();
        $this->offlineConnector->offlineRequest(
            'GetThings',
            '3',
            '<group max="30"><filter><type-id>' . $typeId . '</type-id></filter><format><section>core</section><xml/></format></group>',
            array('record-id' => $this->recordId),
            $this->personId
        );

        $things = array();
        $qp = $this->offlineConnector->getQueryPathResponse();
        $qpThings = $qp->branch()->find('thing');
        foreach ($qpThings as $qpThing)
        {
            $things[] = HealthRecordItemFactory::getThing(qp('<?xml version="1.0"?>' . $qpThing->xml(), NULL, array('use_parser' => 'xml')));
        }
        $this->assertObjectHasAttribute('name', $things[0]->personal);
        $this->assertObjectHasAttribute('birthdate', $things[0]->personal);
        */
    }

}
