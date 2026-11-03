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

namespace tests\OPNsense\CaptivePortal\FieldTypes;

// @CodingStandardsIgnoreStart
require_once __DIR__ . '/../../Base/FieldTypes/Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\CaptivePortal\FieldTypes\OriginField;
use tests\OPNsense\Base\FieldTypes\Field_Framework_TestCase;

class OriginFieldTest extends Field_Framework_TestCase
{
    public function testValidOrigins()
    {
        $field = new OriginField();
        foreach (
            [
                'https://login.example.com',
                'http://localhost:8080',
                'https://192.0.2.1',
                'https://[2001:db8::1]:8443',
                'https://one.example,http://two.example:8080',
            ] as $value
        ) {
            $field->setValue($value);
            $this->assertEmpty($this->validate($field), "{$value} should be valid");
        }
    }

    public function testInvalidOrigins()
    {
        $field = new OriginField();
        foreach (
            [
                'login.example.com',
                'ftp://login.example.com',
                'https://user@login.example.com',
                'https://login.example.com/',
                'https://login.example.com/path',
                'https://login.example.com?query',
                'https://login.example.com#fragment',
            ] as $value
        ) {
            $field->setValue($value);
            $this->assertNotEmpty($this->validate($field), "{$value} should be invalid");
        }
    }

    public function testNormalizeOrigin()
    {
        $this->assertSame(
            'https://login.example.com',
            OriginField::normalizeOrigin('HTTPS://LOGIN.EXAMPLE.COM:443')
        );
        $this->assertSame('http://localhost', OriginField::normalizeOrigin('http://localhost:80'));
        $this->assertSame(
            'https://[2001:db8::1]:8443',
            OriginField::normalizeOrigin('https://[2001:DB8::1]:8443')
        );
        $this->assertNull(OriginField::normalizeOrigin('null'));
    }

    public function testStoresNormalizedOrigins()
    {
        $field = new OriginField();
        $field->setValue('HTTPS://LOGIN.EXAMPLE.COM:443,http://LOCALHOST:80');
        $this->assertSame('https://login.example.com,http://localhost', $field->getValue());
    }
}
