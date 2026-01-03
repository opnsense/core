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

namespace tests\OPNsense\Base\FieldTypes;

// @CodingStandardsIgnoreStart
require_once 'Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\CSVListField;

class CSVListFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\CSVListField', new CSVListField());
    }

    /**
     * generic property tests
     */
    public function testGeneric()
    {
        $field = new CSVListField();

        $this->assertFalse($field->isContainer());
        $this->assertTrue($field->isList());
    }

    /**
     * value combination tests
     */
    public function testValues()
    {
        $field = new CSVListField();

        $this->assertTrue($field->isEmpty());
        $this->assertTrue($field->isEqual(''));
        $this->assertEquals(0, count($field->getValues()));
        $field->setValue('foo');
        $this->assertFalse($field->isEmpty());
        $field->setValue('foo,bar');
        $this->assertFalse($field->isEmpty());
        $this->assertTrue($field->isEqual('foo,bar'));
        $this->assertEquals(2, count($field->getValues()));
        $this->assertEquals('foo', $field->getValues()[0]);
        $this->assertEquals('bar', $field->getValues()[1]);
        $field->setValue('foo,,bar');
        $this->assertEquals('foo,,bar', $field->isEqual('foo,,bar'));
        $this->assertEquals(2, count($field->getValues()));
        $this->assertEquals('foo', $field->getValues()[0]);
        $this->assertEquals('bar', $field->getValues()[1]);
    }

    /**
     * mask tests
     */
    public function testMask()
    {
        $field = new CSVListField();

        $field->setValue('bar');
        $this->assertEmpty($this->validate($field));
        $field->setMask('/[^o,]+/');
        $this->assertEmpty($this->validate($field));
        $field->setValue('foo');
        $this->assertNotEmpty($this->validate($field));
        $field->setValue('f,bar');
        $this->assertNotEmpty($this->validate($field));
        $field->setMaskPerItem('Y');
        $this->assertEmpty($this->validate($field));
    }
}
