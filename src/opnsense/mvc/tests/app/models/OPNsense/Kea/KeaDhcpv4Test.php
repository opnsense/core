<?php

namespace tests\OPNsense\Kea;

require_once __DIR__ . '/Base/JsonSampleTestCase.php';

use OPNsense\Kea\KeaDhcpv4;

class KeaDhcpv4Test extends JsonSampleTestCase
{
    protected function getSnapshotFile(): string
    {
        return 'KeaDhcpv4Test.json';
    }

    protected function getModelInstance()
    {
        return new KeaDhcpv4();
    }

    protected function getJsonRootKey(): string
    {
        return 'Dhcp4';
    }

    public function testJsonSample(): void
    {
        $this->runJsonSampleTest();
    }

    public function testIsEnabled(): void
    {
        $m = $this->getModelInstance();

        $m->general->enabled = "0";
        $m->general->interfaces = "";

        $this->assertFalse($m->isEnabled());

        $m->general->enabled = "1";
        $this->assertFalse($m->isEnabled());

        $m->general->interfaces = "lan";
        $this->assertTrue($m->isEnabled());
    }

    public function testFwRulesEnabled(): void
    {
        $m = $this->getModelInstance();

        $m->general->enabled = "0";
        $m->general->fwrules = "0";
        $m->general->interfaces = "";

        $this->assertFalse($m->fwrulesEnabled());

        $m->general->enabled = "1";
        $this->assertFalse($m->fwrulesEnabled());

        $m->general->fwrules = "1";
        $this->assertFalse($m->fwrulesEnabled());

        $m->general->interfaces = "lan";
        $this->assertTrue($m->fwrulesEnabled());
    }

    public function testPhysicalInterfacesParsing(): void
    {
        $xml = <<<XML
    <opnsense>
    <interfaces>
        <lan><if>igc0</if></lan>
        <wan><if>igc1</if></wan>
    </interfaces>
    </opnsense>
    XML;

        \OPNsense\Core\Config::getInstance()->setXml($xml);

        $m = $this->getModelInstance();
        $m->general->interfaces = "lan,wan";

        $ifs = (new \ReflectionClass($m))
            ->getMethod('getConfigPhysicalInterfaces')
            ->invoke($m);

        $this->assertSame(['igc0','igc1'], $ifs);
    }

}
