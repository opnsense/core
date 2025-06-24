<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace tests\OPNsense\Base\FieldTypes;

// @CodingStandardsIgnoreStart
require_once 'Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\NetworkAliasField;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

class NetworkAliasFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\NetworkAliasField', new NetworkAliasField());
        // switch config to test set for this type
        (new AppConfig())->update('application.configDir', __DIR__ . '/NetworkAliasFieldTest');
        Config::getInstance()->forceReload();
    }

    /**
     * Local database should always be there
     * @depends testCanBeCreated
     */
    public function testDefaultsExists()
    {
        // init field
        $field = new NetworkAliasField();
        $field->eventPostLoading();
        $field->setValue('any');
        $this->assertEmpty($this->validate($field));
        $field->setValue('(self)');
        $this->assertEmpty($this->validate($field));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testConfigItemsExists()
    {
        // init field
        $field = new NetworkAliasField();
        $field->eventPostLoading();
        $field->setValue('opt1');
        $this->assertEmpty($this->validate($field));
        $field->setValue('opt1ip');
        $this->assertEmpty($this->validate($field));
        $field->setValue('geoip_NL');
        $this->assertEmpty($this->validate($field));
        $field->setValue('network_alias');
        $this->assertEmpty($this->validate($field));
        $field->setValue('external_alias');
        $this->assertEmpty($this->validate($field));
        $field->setValue('host_alias');
        $this->assertEmpty($this->validate($field));
        $field->setValue('urltable_alias');
        $this->assertEmpty($this->validate($field));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testValidHosts()
    {
        $field = new NetworkAliasField();
        $field->eventPostLoading();
        $field->setValue('192.168.0.1');
        $this->assertEmpty($this->validate($field));
        $field->setValue('192.168.0.1/24');
        $this->assertEmpty($this->validate($field));
        $field->setValue('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->assertEmpty($this->validate($field));
        $field->setValue('2001::/32');
        $this->assertEmpty($this->validate($field));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testInvalidHosts()
    {
        $field = new NetworkAliasField();
        $field->eventPostLoading();
        $field->setValue('192.168.0.999');
        $this->assertNotEmpty($this->validate($field));
        $field->setValue('192.168.0.1/64');
        $this->assertNotEmpty($this->validate($field));
        $field->setValue('2001:0db8:85a3:0000:0000:8a2e:0370:xxxx');
        $this->assertNotEmpty($this->validate($field));
        $field->setValue('2001::/300');
        $this->assertNotEmpty($this->validate($field));
    }

    /**
     *
     * @depends testCanBeCreated
     */
    public function testConfigItemsNotExists()
    {
        // init field
        $field = new NetworkAliasField();
        $field->eventPostLoading();
        $field->setValue('enc0ip');
        $this->assertNotEmpty($this->validate($field));
        $field->setValue('port_alias');
        $this->assertNotEmpty($this->validate($field));
    }

    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new NetworkAliasField();
        $this->assertFalse($field->isContainer());
    }
}
