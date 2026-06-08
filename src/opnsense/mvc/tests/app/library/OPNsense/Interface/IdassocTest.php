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

namespace tests\OPNsense\Interface;

use OPNsense\Interface\Idassoc;
use PHPUnit\Framework\TestCase;

/*
 * Test value mapping:
 *
 * $track6_interface_prefix:
 *   Runtime IPv6 prefix associated with the source interface selected by:
 *   interfaces.<ifname>.track6-interface
 *   Format: IPv6 CIDR prefix, for example "2001:db8:1234:ab00::/56"
 *
 * $track6_interface_prefix_len:
 *   Prefix length extracted from $track6_interface_prefix.
 *   Format: decimal integer, for example 56
 *
 * $track6_prefix_id:
 *   Configuration value:
 *   interfaces.<ifname>.track6-prefix-id
 *   Format: decimal string, for example "0", "10", "16", "255"
 *   The prefix ID is stored as decimal string in the config, even though the GUI overlays it as hex.
 *   https://github.com/opnsense/core/blob/fb514217bae6525f36834ebd7279a02ad8626f0f/src/www/interfaces.php#L327
 *
 * $track6_prefix_range:
 *   Configuration value:
 *   interfaces.<ifname>.track6_prefix_range
 *   Format: decimal string, interpreted as number of /64 slots,
 *   for example "1", "2", "4", "16"
 *
 * $expected:
 *   Calculated output value consumed by services:
 *   prefix_on_link or prefix_allocated
 *   Format: IPv6 CIDR prefix, for example "2001:db8:1234:ab10::/64"
 */
class IdassocTest extends TestCase
{
    private function callPrivateStatic(string $method, array $args = [])
    {
        $reflection = new \ReflectionClass(Idassoc::class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $args);
    }

    private function assertCalculatedPrefix(
        string $track6_interface_prefix,
        string $track6_prefix_id,
        string $expected
    ) {
        $this->assertEquals(
            $expected,
            $this->callPrivateStatic('calculatePrefix', [$track6_interface_prefix, $track6_prefix_id]),
            sprintf(
                'track6_interface_prefix=%s track6_prefix_id=%s',
                $track6_interface_prefix,
                $track6_prefix_id
            )
        );
    }

    private function assertUsablePrefixLength(
        int $track6_interface_prefix_len,
        string $track6_prefix_id,
        string $track6_prefix_range,
        int $expected
    ) {
        $this->assertEquals(
            $expected,
            $this->callPrivateStatic(
                'calculateUsablePrefixLength',
                [$track6_interface_prefix_len, $track6_prefix_id, $track6_prefix_range]
            ),
            sprintf(
                'track6_interface_prefix_len=%s track6_prefix_id=%s track6_prefix_range=%s',
                $track6_interface_prefix_len,
                $track6_prefix_id,
                $track6_prefix_range
            )
        );
    }

    public function testPrefixId()
    {
        $track6_interface_prefix = '2001:db8:1234:ab00::/56';

        $this->assertCalculatedPrefix($track6_interface_prefix, '0', '2001:db8:1234:ab00::/64');
        $this->assertCalculatedPrefix($track6_interface_prefix, '1', '2001:db8:1234:ab01::/64');
        $this->assertCalculatedPrefix($track6_interface_prefix, '10', '2001:db8:1234:ab0a::/64');
        $this->assertCalculatedPrefix($track6_interface_prefix, '15', '2001:db8:1234:ab0f::/64');

        // Configuration stores decimal 16; GUI may display this as hexadecimal "10".
        $this->assertCalculatedPrefix($track6_interface_prefix, '16', '2001:db8:1234:ab10::/64');

        $this->assertCalculatedPrefix($track6_interface_prefix, '255', '2001:db8:1234:abff::/64');
    }

    public function testDelegationSize()
    {
        // /52 leaves 12 bits for the subnet ID until /64.
        $this->assertCalculatedPrefix('2001:db8:1234:a000::/52', '16', '2001:db8:1234:a010::/64');

        // /48 leaves 16 bits for the subnet ID until /64.
        $this->assertCalculatedPrefix('2001:db8:1234::/48', '16', '2001:db8:1234:10::/64');
        $this->assertCalculatedPrefix('2001:db8:1234::/48', '48879', '2001:db8:1234:beef::/64');
    }

    public function testPrefixLength()
    {
        // Empty range or 1 means a single /64 slot.
        $this->assertUsablePrefixLength(56, '0', '', 64);
        $this->assertUsablePrefixLength(56, '0', '1', 64);

        // Larger ranges can be aggregated when starting at an aligned prefix ID.
        $this->assertUsablePrefixLength(56, '0', '2', 63);
        $this->assertUsablePrefixLength(56, '0', '4', 62);
        $this->assertUsablePrefixLength(56, '0', '16', 60);

        // Configuration stores decimal 16; GUI displays this as hexadecimal 10.
        $this->assertUsablePrefixLength(56, '16', '16', 60);

        // Decimal 10 is hexadecimal 0x0a and only aligned to 2 slots.
        $this->assertUsablePrefixLength(56, '10', '16', 63);
    }
}
