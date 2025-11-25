<?php

namespace tests\OPNsense\Kea;

use OPNsense\Core\Config;
use OPNsense\Core\AppConfig;
use OPNsense\Kea\KeaDhcpv4;
use PHPUnit\Framework\TestCase;

class KeaDhcpv4Test extends TestCase
{
    private function loadSnapshotConfig(string $snapshotDir): void
    {
        (new AppConfig())->update(
            'application.configDir',
            $snapshotDir
        );
        Config::getInstance()->forceReload();
    }

    /**
     * Throw what is exactly wrong in the generated json.
     */
    private function assertJsonEqualWithPath(array $expected, array $actual, string $path = ''): void
    {
        // validate expected keys
        foreach ($expected as $key => $expValue) {
            $currentPath = ($path === '') ? $key : "$path.$key";

            $this->assertArrayHasKey(
                $key,
                $actual,
                "Missing key at: $currentPath"
            );

            $actValue = $actual[$key];

            // recursive arrays
            if (is_array($expValue)) {
                $this->assertIsArray(
                    $actValue,
                    "Type mismatch at: $currentPath (expected array)"
                );
                $this->assertJsonEqualWithPath($expValue, $actValue, $currentPath);
            } else {
                $this->assertSame(
                    $expValue,
                    $actValue,
                    "Value mismatch at: $currentPath"
                );
            }
        }

        // detect unexpected keys
        foreach ($actual as $key => $actValue) {
            $currentPath = ($path === '') ? $key : "$path.$key";

            $this->assertArrayHasKey(
                $key,
                $expected,
                "Unexpected key at: $currentPath"
            );
        }
    }

    public function testGoldenSampleEnabled(): void
    {
        $snapshot = __DIR__ . '/KeaDhcpv4Test/enabled';

        $this->loadSnapshotConfig($snapshot);

        $model = new KeaDhcpv4();

        $tmp = tempnam('/tmp', 'kea4');
        $model->generateConfig($tmp);

        $expected = json_decode(file_get_contents("$snapshot/expected.json"), true);
        $actual   = json_decode(file_get_contents($tmp), true);

        $this->assertJsonEqualWithPath(
            $expected['Dhcp4'],
            $actual['Dhcp4'],
            'Dhcp4'
        );
    }
}
