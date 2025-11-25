<?php

namespace tests\OPNsense\Kea;

require_once __DIR__ . '/Base/JsonSampleTestCase.php';

use OPNsense\Kea\KeaDhcpv4;

class KeaDhcpv4Test extends JsonSampleTestCase
{
    protected function getSnapshotDir(): string
    {
        return __DIR__ . '/KeaDhcpv4Test';
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
}
