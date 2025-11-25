<?php

namespace tests\OPNsense\Kea;

use OPNsense\Core\Config;
use OPNsense\Core\AppConfig;
use PHPUnit\Framework\TestCase;

abstract class JsonSampleTestCase extends TestCase
{
    abstract protected function getSnapshotFile(): string;
    abstract protected function getModelInstance();
    abstract protected function getJsonRootKey(): string;

    private function getSnapshotPath(): string
    {
        return dirname(__DIR__) . '/Snapshots/' . $this->getSnapshotFile();
    }

    private function loadSnapshotConfig(): void
    {
        $snapDir = dirname(__DIR__) . '/Snapshots';

        (new AppConfig())->update('application.configDir', $snapDir);

        Config::getInstance()->forceReload();
    }

    private function loadExpectedSnapshot(): array
    {
        $file = $this->getSnapshotPath();
        $json = json_decode(file_get_contents($file), true);

        $this->assertNotNull($json, 'Failed to decode expected JSON: ' . $file);
        return $json;
    }

    private function assertJsonEqualWithPath(array $expected, array $actual, string $path = ''): void
    {
        // expected keys
        foreach ($expected as $key => $expValue) {
            $current = $path === '' ? $key : "$path.$key";

            $this->assertArrayHasKey($key, $actual, "Missing key at: $current");
            $actValue = $actual[$key];

            if (is_array($expValue)) {
                $this->assertIsArray($actValue, "Type mismatch at: $current");
                $this->assertJsonEqualWithPath($expValue, $actValue, $current);
            } else {
                $this->assertSame($expValue, $actValue, "Value mismatch at: $current");
            }
        }

        // unexpected keys
        foreach ($actual as $key => $actValue) {
            $current = $path === '' ? $key : "$path.$key";
            $this->assertArrayHasKey($key, $expected, "Unexpected key at: $current");
        }
    }

    protected function runJsonSampleTest(): void
    {
        $this->loadSnapshotConfig();
        $model = $this->getModelInstance();

        $tmp = tempnam('/var/lib/php/tmp/', 'json_sample_');
        $model->generateConfig($tmp);

        $expected = $this->loadExpectedSnapshot();
        $actual = json_decode(file_get_contents($tmp), true);

        $this->assertNotNull($actual, 'Generated JSON is invalid.');

        $root = $this->getJsonRootKey();
        $this->assertArrayHasKey($root, $expected);
        $this->assertArrayHasKey($root, $actual);

        $this->assertJsonEqualWithPath($expected[$root], $actual[$root], $root);
    }
}
