<?php

namespace tests\OPNsense\Kea;

require_once __DIR__ . '/Base/JsonSampleTestCase.php';

use OPNsense\Kea\KeaDhcpv6;

class KeaDhcpv6Test extends JsonSampleTestCase
{
    protected function getSnapshotFile(): string
    {
        return 'KeaDhcpv6Test.json';
    }

    protected function getModelInstance()
    {
        return new KeaDhcpv6();
    }

    protected function getJsonRootKey(): string
    {
        return 'Dhcp6';
    }

    public function testJsonSample(): void
    {
        $this->runJsonSampleTest();
    }
}
