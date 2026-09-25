<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace tests\OPNsense\Interfaces;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Interfaces\AddressSettings;

class AddressSettingsTest extends \PHPUnit\Framework\TestCase
{
    private const TODO_FILE = '/tmp/.interfaces.todo';
    private static $configDir = __DIR__ . '/AddressSettingsTest';

    protected function setUp(): void
    {
        @unlink(self::TODO_FILE);
        (new AppConfig())->update('application.configDir', self::$configDir);
        (new AppConfig())->update('application.configDefault', self::$configDir . '/config.xml');
        Config::getInstance()->forceReload();
    }

    protected function tearDown(): void
    {
        @unlink(self::TODO_FILE);
    }

    public function testInterfacesAreExposed()
    {
        $model = new AddressSettings();

        $this->assertEquals('staticv4', (string)$model->settings->lan->type);
        $this->assertEquals('192.0.2.1', (string)$model->settings->lan->ipaddr);
        $this->assertEquals('24', (string)$model->settings->lan->subnet);
        $this->assertEquals('none', (string)$model->settings->opt1->type);
        $this->assertEquals('0', (string)$model->settings->opt1->enable);
    }

    public function testStaticAddressIsStagedForApply()
    {
        $model = new AddressSettings();
        $model->settings->opt1->enable = '1';
        $model->settings->opt1->type = 'staticv4';
        $model->settings->opt1->ipaddr = '203.0.113.10';
        $model->settings->opt1->subnet = '24';
        $model->settings->opt1->gateway = 'none';

        $this->assertCount(0, iterator_to_array($model->performValidation(true)));
        $this->assertFalse($model->serializeToConfig());

        $todo = json_decode(file_get_contents(self::TODO_FILE), true);
        $this->assertEquals('configure', $todo['opt1']['pending_action']);
        $this->assertEquals('1', $todo['opt1']['enable']);
        $this->assertEquals('203.0.113.10', $todo['opt1']['pending']['ipaddr']);
        $this->assertEquals('24', $todo['opt1']['pending']['subnet']);
        $this->assertArrayNotHasKey('gateway', $todo['opt1']['pending']);
    }

    public function testStaticNetworkAddressIsRejected()
    {
        $model = new AddressSettings();
        $model->settings->opt1->type = 'staticv4';
        $model->settings->opt1->ipaddr = '203.0.113.0';
        $model->settings->opt1->subnet = '24';

        $messages = iterator_to_array($model->performValidation(true));
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('network address', implode("\n", array_map(static function ($message) {
            return $message->getMessage();
        }, $messages)));
    }
}
