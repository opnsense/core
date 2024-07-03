<?php

/**
 *    Copyright (C) 2024 Deciso B.V.
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

use OPNsense\Base\FieldTypes\IPPortField;

class IPPortFieldTest extends Field_Framework_TestCase
{
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\IPPortField', new IPPortField());
    }

    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("PresenceOf");
        $field = new IPPortField();
        $field->setRequired("Y");
        $field->setValue("");
        $this->validateThrow($field);
    }

    public function testNotRequiredEmpty()
    {
        $field = new IPPortField();
        $field->setValue("");
        $this->assertEmpty($this->validate($field));
    }

    public function testRequiredNotEmpty()
    {
        $field = new IPPortField();
        $field->setRequired("Y");
        $field->setValue("127.0.0.1:2056");
        $this->assertEmpty($this->validate($field));
    }

    public function testValidValueIpv4()
    {
        $field = new IPPortField();
        $field->setValue("127.0.0.1:2056");
        $this->assertEmpty($this->validate($field));
    }

    public function testValidValueAsListIpv4()
    {
        $field = new IPPortField();
        $field->setAsList("Y");
        $field->setValue("127.0.0.1:2056,192.168.1.1:1111");
        $this->assertEmpty($this->validate($field));
    }

    public function testValidValueIpv6()
    {
        $field = new IPPortField();
        $field->setValue("[::1]:2056");
        $this->assertEmpty($this->validate($field));
    }

    public function testValidValueAsListIpv6()
    {
        $field = new IPPortField();
        $field->setAsList("Y");
        $field->setValue("[::1]:2056,[fe80::]:1111");
        $this->assertEmpty($this->validate($field));
    }

    public function testInvalidValueIpv4()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new IPPortField();
        $field->setValue("abcdefg");
        $this->validateThrow($field);
    }

    public function testInvalidValueAsListIpv4()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new IPPortField();
        $field->setAsList("Y");
        $field->setValue("127.0.0.1:2056,abcdefg");
        $this->validateThrow($field);
    }

    public function testInvalidValueIpv6()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new IPPortField();
        $field->setValue("[::1]");
        $this->validateThrow($field);
    }

    public function testInvalidValueAsListIpv6()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new IPPortField();
        $field->setAsList("Y");
        $field->setValue("[::1]:2056,[fe80::]");
        $this->validateThrow($field);
    }

    public function testAddressFamilyIpv4()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new IPPortField();
        $field->setAddressFamily("ipv4");
        $field->setValue("[::1]:2056");
        $this->validateThrow($field);
    }

    public function testAddressFamilyIpv6()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new IPPortField();
        $field->setAddressFamily("ipv6");
        $field->setValue("192.168.1.1:1111");
        $this->validateThrow($field);
    }
}
