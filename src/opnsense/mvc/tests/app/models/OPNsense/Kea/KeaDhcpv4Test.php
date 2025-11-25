<?php

namespace tests\OPNsense\Kea;

use OPNsense\Core\Config;
use OPNsense\Core\AppConfig;
use OPNsense\Kea\KeaDhcpv4;
use PHPUnit\Framework\TestCase;

class KeaDhcpv4Test extends TestCase
{
    private function loadConfig(string $dir): void
    {
        (new AppConfig())->update('application.configDir', $dir);
        Config::getInstance()->forceReload();
    }

    public function testGoldenSampleEnabled(): void
    {
        $base = __DIR__ . '/KeaDhcpv4Test/enabled';
        $this->loadConfig($base);

        $model = new KeaDhcpv4();

        $tmp = tempnam('/tmp', 'kea4');
        $model->generateConfig($tmp);

        $expected = json_decode(file_get_contents("$base/expected.json"), true);
        $actual   = json_decode(file_get_contents($tmp), true);

        $this->assertSame($expected, $actual, "Generated JSON does not match golden sample (all DHCPv4 options enabled).");
    }
}
