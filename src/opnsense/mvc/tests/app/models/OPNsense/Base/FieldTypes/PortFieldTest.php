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

use OPNsense\Base\FieldTypes\PortField;

class PortFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\PortField', new PortField());
    }

    /**
     */
    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("PresenceOf");
        $field = new PortField();
        $field->setRequired("Y");
        $field->setValue("");
        $field->eventPostLoading();
        $this->validateThrow($field);
    }

    /**
     * required not empty
     */
    public function testRequiredNotEmpty()
    {
        $field = new PortField();
        $field->setRequired("Y");
        $field->setValue("80");
        $field->eventPostLoading();
        $this->assertEmpty($this->validate($field));
    }

    /**
     * required not empty
     */
    public function testValidValues()
    {
        $field = new PortField();
        $field->setEnableRanges("Y");
        $field->setEnableWellKnown("Y");
        $field->eventPostLoading();
        foreach (array("80", "443", "https", "80-100") as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field));
        }
    }

    /**
     * all items valid
     */
    public function testValidValueList()
    {
        $field = new PortField();
        $field->setEnableRanges("Y");
        $field->setEnableWellKnown("Y");
        $field->setMultiple("Y");
        $field->eventPostLoading();
        $field->setValue("80,443,https,80-100");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * range not expected
     */
    public function testRangeNotExpected()
    {
        $field = new PortField();
        $field->setEnableWellKnown("Y");
        $field->setMultiple("Y");
        $field->eventPostLoading();
        $field->setValue("80;443;https;80-100");
        $this->assertNotEmpty($this->validate($field));
    }

    /**
     * required not empty
     */
    public function testInValidValues()
    {
        $field = new PortField();
        foreach (array("x1", "x2", "999999-88888888") as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field));
        }
    }

    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new PortField();
        $this->assertFalse($field->isContainer());
    }
}
