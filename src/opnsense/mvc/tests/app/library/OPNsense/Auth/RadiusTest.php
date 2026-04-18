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

class RadiusTest extends TestCase
{
    /**
     * @dataProvider framedIpAddressProvider
     */
    public function testShouldSendFramedIpAddress($ipAddress, $expected)
    {
        $subject = new Radius();
        $method = new \ReflectionMethod($subject, 'shouldSendFramedIpAddress');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($subject, $ipAddress));
    }

    public static function framedIpAddressProvider()
    {
        return [
            'ipv4' => ['192.0.2.10', true],
            'trimmed ipv4' => [' 198.51.100.8 ', true],
            'ipv6' => ['2001:db8::1', false],
            'link-local ipv6' => ['fe80::1', false],
            'empty' => ['', false],
            'invalid' => ['not-an-ip', false],
        ];
    }
}
