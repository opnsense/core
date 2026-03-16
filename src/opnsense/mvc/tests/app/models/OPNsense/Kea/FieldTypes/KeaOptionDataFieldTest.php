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

namespace tests\OPNsense\Kea\FieldTypes;

// @CodingStandardsIgnoreStart
require_once __DIR__ . '/../../Base/FieldTypes/Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use tests\OPNsense\Base\FieldTypes\Field_Framework_TestCase;
use OPNsense\Kea\FieldTypes\KeaOptionDataField;

class KeaOptionDataFieldTest extends Field_Framework_TestCase
{
    private function createField(string $encoding, string $value, int $code = 0): KeaOptionDataField
    {
        $node = function ($value) {
            return new class ($value) extends \OPNsense\Base\FieldTypes\BaseField {
                public function __construct($value)
                {
                    parent::__construct();
                    $this->setValue($value);
                }
            };
        };
        $field = new KeaOptionDataField();
        $field->setValue($value);
        // Build a minimal real field tree
        $parent = new class extends \OPNsense\Base\FieldTypes\BaseField{
        };
        $parent->addChildNode('encoding', $node($encoding));
        $parent->addChildNode('code', $node($code));
        $parent->addChildNode('data', $field);
        return $field;
    }

    // The tests are mostly scoped around the encoders, if validators grow more complex they can be added later

    public function testCanBeCreated()
    {
        $this->assertInstanceOf(KeaOptionDataField::class, new KeaOptionDataField());
    }

    public function testIsContainer()
    {
        $field = new KeaOptionDataField();
        $this->assertFalse($field->isContainer());
    }

    public function testEncodeHex()
    {
        $field = $this->createField('hex', 'aa11ff');
        $this->assertEquals('AA11FF', $field->encodeValue());
    }

    public function testEncodeIpv4()
    {
        $this->assertEquals('C0A80101', $this->createField('ipv4-address', '192.168.1.1')->encodeValue());
        $this->assertEquals('C0A801010A000001', $this->createField('ipv4-address', '192.168.1.1,10.0.0.1')->encodeValue());
        $this->assertEquals('C0A801010A000001', $this->createField('ipv4-address', '192.168.1.1, 10.0.0.1')->encodeValue());
    }

    public function testEncodeIpv6()
    {
        $this->assertEquals(
            '20010DB8000000000000000000000001',
            $this->createField('ipv6-address', '2001:db8::1')->encodeValue()
        );
        $this->assertEquals(
            '20010DB800000000000000000000000120010DB8000000000000000000000002',
            $this->createField('ipv6-address', '2001:db8::1,2001:db8::2')->encodeValue()
        );
        $this->assertEquals(
            '20010DB800000000000000000000000120010DB8000000000000000000000002',
            $this->createField('ipv6-address', '2001:db8::1, 2001:db8::2')->encodeValue()
        );
    }

    public function testEncodeUInt()
    {
        $this->assertEquals('FF', $this->createField('uint8', '255')->encodeValue());
        $this->assertEquals('00FF', $this->createField('uint16', '255')->encodeValue());
        $this->assertEquals('000000FF', $this->createField('uint32', '255')->encodeValue());
    }

    public function testEncodeInt32()
    {
        // XXX: Endieness can be weird and platform specific
        $this->assertEquals(
            strtoupper(bin2hex(pack('l', 1))),
            $this->createField('int32', '1')->encodeValue()
        );
        $this->assertEquals(
            strtoupper(bin2hex(pack('l', -1))),
            $this->createField('int32', '-1')->encodeValue()
        );
    }

    public function testEncodeBoolean()
    {
        $this->assertEquals('01', $this->createField('boolean', 'true')->encodeValue());
        $this->assertEquals('01', $this->createField('boolean', '1')->encodeValue());
        $this->assertEquals('00', $this->createField('boolean', 'false')->encodeValue());
        $this->assertEquals('00', $this->createField('boolean', '0')->encodeValue());
    }

    public function testEncodeString()
    {
        $this->assertEquals('68656C6C6F', $this->createField('string', 'hello')->encodeValue());
    }

    public function testEncodeFqdn()
    {
        $this->assertEquals('076578616D706C6503636F6D00', $this->createField('fqdn', 'example.com')->encodeValue());
        $this->assertEquals(
            '09737562646F6D61696E076578616D706C6503636F6D00',
            $this->createField('fqdn', 'subdomain.example.com')->encodeValue()
        );
    }
}
