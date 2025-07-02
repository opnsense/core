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

use OPNsense\Base\FieldTypes\HostnameField;

class HostnameFieldTest extends Field_Framework_TestCase
{
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\HostnameField', new HostnameField());
    }

    /**
     * generic property tests
     */
    public function testGeneric()
    {
        $field = new HostnameField();

        $this->assertFalse($field->isContainer());
        $this->assertFalse($field->isList());
    }

    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("PresenceOf");
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
        $field->setValue("test.opnsense.org");
        $field->eventPostLoading();
        $this->assertEmpty($this->validate($field));
    }

    public function testValidValues()
    {
        $field = new HostnameField();
        $field->eventPostLoading();
        $field->setIsDNSName("Y");
        foreach (["test", "test.opnsense.org", "_test.opnsense.org"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
    }

    public function testZoneRoot()
    {
        $field = new HostnameField();
        $field->eventPostLoading();
        $field->setZoneRootAllowed("Y");
        foreach (["@", "@.test"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
        $field->setZoneRootAllowed("N");
        foreach (["@", "@.test"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
    }

    public function testWildcards()
    {
        $field = new HostnameField();
        $field->eventPostLoading();
        foreach (["*", "*.opnsense.org"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
        $field->setHostWildcardAllowed("Y");
        $field->setFqdnWildcardAllowed("Y");
        foreach (["*", "*.opnsense.org"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
    }

    public function testAsList()
    {
        $field = new HostnameField();
        $field->eventPostLoading();
        $field->setValue("test|test.opnsense.org");
        $this->assertNotEmpty($this->validate($field));
        $this->assertIsString($field->getNodeData());
        $field->setAsList("Y");
        $field->setFieldSeparator("|");
        $field->setValue("test|test.opnsense.org");
        $this->assertEmpty($this->validate($field));
        $this->assertIsArray($field->getNodeData());
    }

    public function testInValidValues()
    {
        $field = new HostnameField();
        $field->setIsDNSName("N");
        foreach (["!2121", "x2&x", "_88766-1234", "@", "*.test", "@.test"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
    }

    public function testIpAddress()
    {
        $field = new HostnameField();
        $field->setIpAllowed("N");
        $field->setValue("192.168.1.1");
        $this->assertNotEmpty($this->validate($field));
        $field->setIpAllowed("Y");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new HostnameField();
        $this->assertFalse($field->isContainer());
    }
}
