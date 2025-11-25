<?php

namespace tests\OPNsense\Kea;

require_once __DIR__ . '/Base/JsonSampleTestCase.php';

use OPNsense\Kea\KeaCtrlAgent;

class KeaCtrlAgentTest extends JsonSampleTestCase
{
    protected function getSnapshotFile(): string
    {
        return 'KeaCtrlAgentTest.json';
    }

    protected function getModelInstance()
    {
        return new KeaCtrlAgent();
    }

    protected function getJsonRootKey(): string
    {
        return 'Control-agent';
    }

    public function testJsonSample(): void
    {
        $this->runJsonSampleTest();
    }
}
