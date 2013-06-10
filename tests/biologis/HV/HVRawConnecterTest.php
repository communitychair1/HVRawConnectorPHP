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
        $this->session = & $_SESSION;
        $this->personId = '3933614a-92bc-4da5-95c0-6085f7aef4aa';
        $this->recordId = '97cb6d50-8c8e-4aff-8818-483efdfed7d5';
        $this->hv = new HVClient($this->appId, $this->session, $this->personId, true);

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
    }

    public function testConnect()
    {
        //TODO: Figure out how to handle online connect
        /*$this->onlineConnector->connect();
        $this->assertNotEmpty($this->session['healthVault']['authToken']);
        $this->assertNotEmpty($this->session['healthVault']['userAuthToken']);
        unset($this->session['healthVault']);*/

        $this->offlineConnector->connect();
        $this->assertNotEmpty($this->session['healthVault']['authToken']);
        //$this->assertEmpty($this->session['healthVault']['userAuthToken']);
    }

    public function testAnonymousWcRequest()
    {
        //TODO: Figure out how to handle online testing
        /*
         *
         */

        //$this->offlineConnector()
        $this->offlineConnector->connect();
    }

    public function testAuthenticatedWcRequest()
    {
            //TODO: Figure out online connection
            //$this->onlineConnector->connect();
    }

    public function testOfflineRequest()
    {
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

    }

}
