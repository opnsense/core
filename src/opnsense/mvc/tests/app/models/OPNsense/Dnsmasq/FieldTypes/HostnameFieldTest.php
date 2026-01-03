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

namespace tests\OPNsense\Dnsmasq\FieldTypes;

// @CodingStandardsIgnoreStart
require_once __DIR__ . '/../../Base/FieldTypes/Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use tests\OPNsense\Base\FieldTypes\Field_Framework_TestCase;
use OPNsense\Dnsmasq\FieldTypes\HostnameField;

class HostnameFieldTest extends Field_Framework_TestCase
{
    public function testCanBeCreated()
    {
        $this->assertInstanceOf(HostnameField::class, new HostnameField());
    }

    public function testIsContainer()
    {
        $field = new HostnameField();
        $this->assertFalse($field->isContainer());
    }

    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new HostnameField();
        $field->setRequired("Y");
        $field->setValue("");
        $field->eventPostLoading();
        $this->validateThrow($field);
    }

    public function testRequiredNotEmpty()
    {
        $field = new HostnameField();
        $field->setRequired("Y");
        $field->setValue("example");
        $field->eventPostLoading();
        $this->assertEmpty($this->validate($field));
    }

    public function testAsList()
    {
        $field = new HostnameField();
        $field->eventPostLoading();

        $field->setValue("test,host123");
        $this->assertNotEmpty($this->validate($field));
        $this->assertIsString($field->getNodeData());

        $field->setAsList("Y");
        $field->setFieldSeparator(",");
        $field->setValue("test,host123");
        $this->assertEmpty($this->validate($field));
        $this->assertIsArray($field->getNodeData());
    }

    public function testValidLabels()
    {
        $field = new HostnameField();
        $field->eventPostLoading();
        foreach (["test", "host123", "valid-name", "a1_b2", "1a-2b"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field), "$value should be valid");
        }
    }

    public function testInvalidLabels()
    {
        $field = new HostnameField();
        foreach (["-bad", "_bad", "bad!"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field), "$value should be invalid");
        }

        // label >63 chars
        $tooLong = str_repeat("a", 64);
        $field->setValue($tooLong);
        $this->assertNotEmpty($this->validate($field), "Label >63 chars should be invalid");
    }

    public function testInvalidDomain()
    {
        $field = new HostnameField();
        $field->setIsDomain("Y");

        // domain >255 chars should fail
        $tooLong = str_repeat("a", 256);
        $field->setValue($tooLong);
        $this->assertNotEmpty($this->validate($field), "Domain >255 chars should be invalid");
    }

    public function testWildcardAllowed()
    {
        $field = new HostnameField();
        $field->setIsWildcardAllowed("Y");
        $field->setValue("*");
        $this->assertEmpty($this->validate($field), "Wildcard should be allowed");

        $field->setIsWildcardAllowed("N");
        $field->setValue("*");
        $this->assertNotEmpty($this->validate($field), "Wildcard should be rejected");
    }

    public function testLegacyXML()
    {
        $field = new HostnameField();
        $field->setLegacyXML("Y");
        $field->setIsDomain("Y");

        $xml = new \SimpleXMLElement(
            '<aliases><item><host>www</host><domain>example.com</domain><description>desc</description></item></aliases>'
        );
        $field->setValue($xml);

        // run post-loading event to finalize setup
        $field->eventPostLoading();

        $this->assertEmpty($this->validate($field), "Legacy XML structure should be flattened and validated");
        $this->assertStringContainsString("www.example.com", $field->getValue());
    }
}
