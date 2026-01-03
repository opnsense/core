<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

namespace tests\OPNsense\Base\FieldTypes;

// @CodingStandardsIgnoreStart
require_once 'Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\NetworkField;

class NetworkFieldTest extends Field_Framework_TestCase
{
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\NetworkField', new NetworkField());
    }

    /**
     * generic property tests
     */
    public function testGeneric()
    {
        $field = new NetworkField();

        $this->assertFalse($field->isContainer());
        $this->assertFalse($field->isList());
    }

    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("PresenceOf");
        $field = new NetworkField();
        $field->setRequired("Y");
        $field->setValue("");
        $this->validateThrow($field);
    }

    public function testRequiredNotEmpty()
    {
        $field = new NetworkField();
        $field->setRequired("Y");
        $field->setValue("192.168.1.1");
        $this->assertEmpty($this->validate($field));
    }

    public function testValidValuesV4()
    {
        $field = new NetworkField();
        $field->setNetMaskRequired("Y");
        $field->setAddressFamily("ipv4");
        foreach (["192.168.1.1/24", "10.0.0.1/24"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
    }

    public function testValidValuesMulti()
    {
        $field = new NetworkField();
        $value = "192.168.1.1\n2000::1";
        $field->setFieldSeparator("\n");
        $field->setAsList('Y');
        $field->setValue($value);
        $this->assertEmpty($this->validate($field));
        $this->assertTrue($field->isEqual($value));
        $this->assertEquals(2, count($field->getValues()));
        $this->assertEquals('192.168.1.1', $field->getValues()[0]);
        $this->assertEquals('2000::1', $field->getValues()[1]);
    }

    public function testValidValuesV6()
    {
        $field = new NetworkField();
        $field->setNetMaskRequired("Y");
        $field->setAddressFamily("ipv6");
        foreach (["2000::1/128", "fe80::5a8c:fcff:0001:ffe2/64"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
    }

    public function testInValidValuesV4()
    {
        $field = new NetworkField();
        $field->setNetMaskRequired("Y");
        $field->setAddressFamily("ipv4");
        foreach (["192.168.1.1", "2000::1", "A"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
    }

    public function testInValidValuesV6()
    {
        $field = new NetworkField();
        $field->setNetMaskRequired("Y");
        $field->setAddressFamily("ipv6");
        foreach (["192.168.1.1", "2000::1", "A"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
    }

    public function testValidValuesStrict()
    {
        $field = new NetworkField();
        $field->setNetMaskRequired("Y");
        $field->setStrict("Y");
        foreach (["192.168.1.0/24", "2000::0/64"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
    }

    public function testInValidValuesStrict()
    {
        $field = new NetworkField();
        $field->setNetMaskRequired("Y");
        $field->setStrict("Y");
        foreach (["192.168.1.1/24", "2000::1:1/64"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
    }

    public function testIsContainer()
    {
        $field = new NetworkField();
        $this->assertFalse($field->isContainer());
    }
}
