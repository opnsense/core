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
require_once __DIR__ . '/Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\RegexField;

class RegexFieldTest extends Field_Framework_TestCase
{
    public function testCanBeCreated()
    {
        $this->assertInstanceOf(RegexField::class, new RegexField());
    }

    public function testIsContainer()
    {
        $field = new RegexField();
        $this->assertFalse($field->isContainer());
    }

    public function testRequiredEmpty()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $field = new RegexField();
        $field->setRequired("Y");
        $field->setValue("");
        $field->eventPostLoading();
        $this->validateThrow($field);
    }

    public function testRequiredNotEmpty()
    {
        $field = new RegexField();
        $field->setRequired("Y");
        $field->setValue("^test$");
        $field->eventPostLoading();
        $this->assertEmpty($this->validate($field));
    }

    public function testValidPatternsWithoutDelimiters()
    {
        $field = new RegexField();
        $field->setRequireDelimiters("N");
        $field->eventPostLoading();
        foreach (["^test$", "[a-z]+", "\\d{3}", "foo|bar", "(?i)case.*insensitive"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field), "$value should be valid");
        }
    }

    public function testValidPatternsWithDelimiters()
    {
        $field = new RegexField();
        $field->setRequireDelimiters("Y");
        $field->eventPostLoading();
        foreach (["/^test$/", "/[a-z]+/i", "#\\d{3}#", "/foo|bar/"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field), "$value should be valid");
        }
    }

    public function testInvalidPatternsWithoutDelimiters()
    {
        $field = new RegexField();
        $field->setRequireDelimiters("N");
        $field->eventPostLoading();
        foreach (["[unclosed", "bad)", "(?bad-group"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field), "$value should be invalid");
        }
    }

    public function testInvalidPatternsWithDelimiters()
    {
        $field = new RegexField();
        $field->setRequireDelimiters("Y");
        $field->eventPostLoading();
        foreach (["/[unclosed", "/bad)/", "no-delimiters", "/(?bad-group/"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field), "$value should be invalid");
        }
    }

    // Test patterns with delimiters and trailing modifiers (PHP PCRE2 style)
    public function testAllowDelimitersWithModifiers()
    {
        $field = new RegexField();
        $field->setRequireDelimiters("Y");
        $field->eventPostLoading();
        foreach (["/^test$/im", "#[a-z]+#iu", "~\\d{3}~ms"] as $value) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field), "$value should be valid");
        }
    }

    public function testRejectDelimitersWithModifiers()
    {
        $field = new RegexField();
        $field->setRequireDelimiters("N");
        $field->eventPostLoading();
        foreach (["/^test$/im", "#[a-z]+#iu", "~\\d{3}~ms"] as $value) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field), "$value should be invalid (has delimiters)");
        }
    }

    public function testDefaultBehaviorNoDelimiters()
    {
        $field = new RegexField();
        $field->eventPostLoading();

        // Default should be no delimiters required
        $field->setValue("^test$");
        $this->assertEmpty($this->validate($field), "Default should accept patterns without delimiters");

        $field->setValue("/^test$/");
        $this->assertNotEmpty($this->validate($field), "Default should reject PHP-style delimiters");
    }

    public function testEmptyValue()
    {
        $field = new RegexField();
        $field->eventPostLoading();
        $field->setValue("");
        $this->assertEmpty($this->validate($field), "Empty value should be valid when not required");
    }
}
