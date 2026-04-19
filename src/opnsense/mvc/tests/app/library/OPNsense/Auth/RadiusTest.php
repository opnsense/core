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

namespace tests\OPNsense\Auth;

use OPNsense\Auth\Radius;
use PHPUnit\Framework\TestCase;

if (!defined('RADIUS_FRAMED_IP_ADDRESS')) {
    define('RADIUS_FRAMED_IP_ADDRESS', 8);
}

class RadiusTest extends TestCase
{
    /**
     * @dataProvider framedAddressProvider
     */
    public function testGetFramedAddressAttribute($ipAddress, $expected)
    {
        $subject = new Radius();
        $method = new \ReflectionMethod($subject, 'getFramedAddressAttribute');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invoke($subject, $ipAddress));
    }

    public static function framedAddressProvider()
    {
        return [
            'ipv4' => ['192.0.2.10', [
                'handler' => 'addr',
                'attribute' => RADIUS_FRAMED_IP_ADDRESS,
                'value' => '192.0.2.10',
            ]],
            'trimmed ipv4' => [' 198.51.100.8 ', [
                'handler' => 'addr',
                'attribute' => RADIUS_FRAMED_IP_ADDRESS,
                'value' => '198.51.100.8',
            ]],
            'ipv6' => ['2001:db8::1', [
                'handler' => 'attr',
                'attribute' => 97,
                'value' => chr(0) . chr(128) . inet_pton('2001:db8::1'),
            ]],
            'link-local ipv6' => ['fe80::1', [
                'handler' => 'attr',
                'attribute' => 97,
                'value' => chr(0) . chr(128) . inet_pton('fe80::1'),
            ]],
            'empty' => ['', null],
            'invalid' => ['not-an-ip', null],
        ];
    }

    public function testIpv6AddressUsesFramedIpv6PrefixPayload()
    {
        $subject = new Radius();
        $method = new \ReflectionMethod($subject, 'getFramedAddressAttribute');
        $method->setAccessible(true);

        $attribute = $method->invoke($subject, '2001:db8::1');

        $this->assertSame('attr', $attribute['handler']);
        $this->assertSame(97, $attribute['attribute']);
        $this->assertSame(18, strlen($attribute['value']));
        $this->assertSame(0, ord($attribute['value'][0]));
        $this->assertSame(128, ord($attribute['value'][1]));
        $this->assertSame(inet_pton('2001:db8::1'), substr($attribute['value'], 2));
    }
}
