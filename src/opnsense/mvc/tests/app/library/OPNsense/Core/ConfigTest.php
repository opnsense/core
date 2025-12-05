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
 *  THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *  AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 */

namespace tests\OPNsense\Core;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/ConfigConfig';

    public static function cleanupTestFiles()
    {
        @unlink(self::$configDir . '/config.xml');
    }

    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        self::cleanupTestFiles();

        // switch config to test set for this type
        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();

        $this->assertNotEmpty(Config::getInstance()->toArray(['rule']));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_to_from_array()
    {
        $cnf = Config::getInstance();
        $test = $cnf->toArray(['rule']);

        $test['filter']['rule'][3] = $test['filter']['rule'][0];
        $test['filter']['rule'][5] = $test['filter']['rule'][1];
        unset($test['filter']['rule'][0]);
        unset($test['filter']['rule'][1]);

        $cnf->fromArray($test);

        $this->assertEquals(file_get_contents(self::$configDir . '/backup/config.xml'), (string)$cnf);
    }

    /**
     * @afterClass
     */
    public static function postCleanup()
    {
        self::cleanupTestFiles();
    }
}
