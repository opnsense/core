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

namespace tests\OPNsense\Base\FieldTypes;

// @CodingStandardsIgnoreStart
require_once 'Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\StrictTextField;

class StrictTextFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\StrictTextField', new StrictTextField());
    }

    /**
     * spaces and tabs fail validation by default
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testAllowSpacesDefault()
    {
        $field = new StrictTextField();
        $field->setValue("foo bar\tbaz");

        $this->assertNotEmpty($this->validate($field));
    }

    /**
     * spaces and tabs pass validation when AllowSpaces=Y
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testAllowSpacesEnabled()
    {
        $field = new StrictTextField();
        $field->setAllowSpaces('Y');
        $field->setValue("foo bar\tbaz");

        $this->assertEmpty($this->validate($field));
    }

    /**
     * newlines fail validation by default
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testAllowNewlinesDefault()
    {
        $field = new StrictTextField();
        $field->setValue("foo\nbar\rbaz");

        $this->assertNotEmpty($this->validate($field));
    }

    /**
     * newlines pass validation when AllowNewlines=Y
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testAllowNewlinesEnabled()
    {
        $field = new StrictTextField();
        $field->setAllowNewlines('Y');
        $field->setValue("foo\nbar\rbaz");

        $this->assertEmpty($this->validate($field));
    }

    /**
     * special control characters fail validation by default
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testAllowSpecialDefault()
    {
        $field = new StrictTextField();
        $field->setValue("foo\0bar\vbaz\fqux");

        $this->assertNotEmpty($this->validate($field));
    }

    /**
     * special control characters pass validation when AllowSpecial=Y
     * no mask (TextField default)
     * not required (BaseField default)
     */
    public function testAllowSpecialEnabled()
    {
        $field = new StrictTextField();
        $field->setAllowSpecial('Y');
        $field->setValue("foo\0bar\vbaz\fqux");

        $this->assertEmpty($this->validate($field));
    }
}
