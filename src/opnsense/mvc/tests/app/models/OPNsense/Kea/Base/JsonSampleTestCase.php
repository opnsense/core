<?php

/*
 * Copyright (C) 2025 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

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
