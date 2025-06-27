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

use OPNsense\Base\FieldTypes\TextField;

class TextFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\TextField', new TextField());
    }

    /**
     * type is not a container (TextField default)
     */
    public function testIsContainer()
    {
        $field = new TextField();

        $this->assertFalse($field->isContainer());
    }

    /**
     * field is not an ArrayField (default)
     */
    public function testIsArrayType()
    {
        $field = new TextField();

        $this->assertFalse($field->isArrayType());
    }

    /**
     * field is not virtual (BaseField default)
     */
    public function testIsVirtualFalse()
    {
        $field = new TextField();

        $this->assertFalse($field->getInternalIsVirtual());
    }

    /**
     * field can be set as virtual
     */
    public function testIsVirtualTrue()
    {
        $field = new TextField();
        $field->setInternalIsVirtual();

        $this->assertTrue($field->getInternalIsVirtual());
    }

    /**
     * no value set
     * not required (BaseField default)
     */
    public function testIsEmptyAndRequiredN()
    {
        $field = new TextField();

        $this->assertFalse($field->isEmptyAndRequired());
    }

    /**
     * no value set, but required
     */
    public function testIsEmptyAndRequiredY()
    {
        $field = new TextField();
        $field->setRequired("Y");

        $this->assertTrue($field->isEmptyAndRequired());
    }

    /**
     * required, value not empty
     */
    public function testRequiredNotEmpty()
    {
        $field = new TextField();
        $value = "Not empty string value";
        $field->setRequired("Y");
        $field->setValue($value);

        $this->assertEquals($field->getNodeData(), $value);
        $this->assertEquals(1, count($field->getValues()));
        $this->assertEquals($value, $field->getValues()[0]);
        $this->assertEmpty($this->validate($field));
    }

    /**
     * required, but empty value
     */
    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("PresenceOf");
        $field = new TextField();
        $field->setRequired("Y");
        $field->setValue("");

        $this->validateThrow($field);
    }

    /**
     * empty value passes validation
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testEmptyValue()
    {
        $field = new TextField();
        $field->setValue("");

        $this->assertEmpty($this->validate($field));
        $this->assertEquals(0, count($field->getValues()));
    }

    /**
     * empty value passes validation with regex pattern set (regex validator doesn't apply)
     * not required (BaseField default)
     */
    public function testEmptyValueWithMask()
    {
        $field = new TextField();
        $field->setMask('/^regexpattern$/');
        $field->setValue("");

        $this->assertEmpty($this->validate($field));
    }

    /**
     * non-empty value fails validation against regex pattern
     * not required (BaseField default)
     */
    public function testValueWithMask()
    {
        $field = new TextField();
        $field->setMask('/^[a-z]{8}$/');
        $field->setValue("4bcd3fgh");

        $this->assertContains('Regex', $this->validate($field));
    }

    /**
     * non-empty value passes validation against regex pattern
     * not required (BaseField default)
     */
    public function testNonEmptyStringWithNonEmptyMask()
    {
        $field = new TextField();
        $field->setMask('/^[a-z]{8}$/');
        $field->setValue("abcdefgh");

        $this->assertEmpty($this->validate($field));
    }

    /**
     * integer value passes validation
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testIntegerStringValue()
    {
        $field = new TextField();
        $field->setValue("1234");

        $this->assertEmpty($this->validate($field));
    }

    /**
     * literal integer passes validation, and is retrieved as string
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testIntegerValue()
    {
        $field = new TextField();
        $field->setValue(1234);

        $this->assertEquals($field->getNodeData(), "1234");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * literal integer passes validation with regex pattern, and is retrieved as string
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testIntegerValueWithMaskPass()
    {
        $field = new TextField();
        $field->setMask('/^[0-9]{4}$/');
        $field->setValue(1234);

        $this->assertEquals($field->getNodeData(), "1234");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * literal integer fails validation with regex pattern, and is retrieved as string
     * not required (BaseField default)
     * no mask (TextiFled default)
     */
    public function testIntegerValueWithMaskFail()
    {
        $field = new TextField();
        $field->setMask('/^[a-z]{4}$/');
        $field->setValue(1234);

        $this->assertEquals($field->getNodeData(), "1234");
        $this->assertContains('Regex', $this->validate($field));
    }

    /**
     * non-empty value changes case UPPER
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testValueChangeCaseUpper()
    {
        $field = new TextField();
        $field->setChangeCase('UPPER');
        $field->setValue("aB cdE fgH");

        $this->assertEquals($field->getNodeData(), "AB CDE FGH");
    }

    /**
     * non-empty value changes case LOWER
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testValueChangeCaseLower()
    {
        $field = new TextField();
        $field->setChangeCase('LOWER');
        $field->setValue("aB cdE fgH");

        $this->assertEquals($field->getNodeData(), "ab cde fgh");
    }

    /**
     * non-empty value changes case UPPER
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testIntegerValueChangeCaseUpper()
    {
        $field = new TextField();
        $field->setChangeCase('UPPER');
        $field->setValue("igb0");

        $this->assertEquals($field->getNodeData(), "IGB0");
    }

    /**
     * non-empty value changes case UPPER
     * not required (BaseField default)
     * no mask (TextField default)
     */
    public function testIntegerValueChangeCaseLower()
    {
        $field = new TextField();
        $field->setChangeCase('LOWER');
        $field->setValue("1024 Gigabytes");

        $this->assertEquals($field->getNodeData(), "1024 gigabytes");
    }

    /**
     * non-empty value changes case UPPER with regex mask matching after case change.
     * not required (BaseField default)
     */
    public function testValueChangeCaseUpperWithMaskMatch()
    {
        $field = new TextField();
        $field->setMask('/^[A-Z ]{10}$/');
        $field->setChangeCase('UPPER');
        $field->setValue("aB cdE fgH");

        $this->assertEquals($field->getNodeData(), "AB CDE FGH");
    }

    /**
     * non-empty value changes case LOWER with regex mask matching after case change.
     * not required (BaseField default)
     */
    public function testValueChangeCaseLowerWithMaskMatch()
    {
        $field = new TextField();
        $field->setMask('/^[a-z ]{10}$/');
        $field->setChangeCase('LOWER');
        $field->setValue("aB cdE fgH");

        $this->assertEquals($field->getNodeData(), "ab cde fgh");
    }

    /**
     * non-empty value changes case UPPER with regex mask failing after case change.
     * not required (BaseField default)
     */
    public function testValueChangeCaseUpperWithMaskFail()
    {
        $field = new TextField();
        $field->setMask('/^[a-z ]{10}$/');
        $field->setChangeCase('UPPER');
        $field->setValue("ab cde fgh");

        $this->assertContains('Regex', $this->validate($field));
    }
}
