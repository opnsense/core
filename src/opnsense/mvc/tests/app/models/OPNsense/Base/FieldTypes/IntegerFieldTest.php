<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

use OPNsense\Base\FieldTypes\IntegerField;

class IntegerFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\IntegerField', new IntegerField());
    }

    /**
     */
    public function testValueLargerThenMax()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("MinMaxValidator");
        $field = new IntegerField();
        $field->setMaximumValue(100);
        $field->setMinimumValue(10);
        $field->setValue("120");

        $this->validateThrow($field);
    }

    /**
     */
    public function testValueSmallerThenMin()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("MinMaxValidator");
        $field = new IntegerField();
        $field->setMaximumValue(100);
        $field->setMinimumValue(10);
        $field->setValue("5");

        $this->validateThrow($field);
    }

    /**
     * not a number
     */
    public function testNotANumber()
    {
        $field = new IntegerField();
        $field->setMaximumValue(100);
        $field->setMinimumValue(10);
        $field->setValue("5x1");

        $this->assertContains('IntegerValidator', $this->validate($field));
    }

    /**
     * number contains decimals
     */
    public function testDecimalNumber()
    {
        $field = new IntegerField();
        $field->setMaximumValue(100);
        $field->setMinimumValue(10);
        $field->setValue("99.1");

        $this->assertContains('IntegerValidator', $this->validate($field));
    }

    /**
     */
    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("PresenceOf");
        $field = new IntegerField();
        $field->setRequired("Y");
        $field->setValue("");
        $this->validateThrow($field);
    }

    /**
     * required not empty
     */
    public function testRequiredNotEmpty()
    {
        $field = new IntegerField();
        $field->setRequired("Y");
        $field->setValue("1");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new IntegerField();
        $this->assertFalse($field->isContainer());
    }
}
