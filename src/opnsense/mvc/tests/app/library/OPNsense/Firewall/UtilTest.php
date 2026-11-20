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

namespace tests\OPNsense\Firewall;

use OPNsense\Firewall\Util;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    public function testSplitIPv6Prefix()
    {
        $this->assertSame([
            '2001:db8::/58',
            '2001:db8:0:40::/58',
        ], Util::splitIPv6Prefix('2001:db8::/57'));
    }

    public function testExcludeOnLinkBlockLeavesSevenDelegations()
    {
        // A /57 contains eight /60s. Reserve 2001:db8::/60 for on-link use.
        // The remainder needs three CIDR blocks; one PD entry cannot describe all seven /60s.
        $prefixes = Util::excludeIPv6Subnet('2001:db8::/57', 60);
        $this->assertSame([
            '2001:db8:0:40::/58', // Four /60 delegations.
            '2001:db8:0:20::/59', // Two /60 delegations.
            '2001:db8:0:10::/60', // One /60 delegation.
        ], $prefixes);

        $delegations = 0;
        foreach ($prefixes as $prefix) {
            $prefix_len = (int)explode('/', $prefix, 2)[1];
            $delegations += 1 << (60 - $prefix_len);
            $this->assertFalse(Util::isIPv6PrefixInPrefix('2001:db8::/60', $prefix));
        }
        $this->assertSame(7, $delegations);
    }

    public function testExcludeEntirePrefixLeavesNoDelegations()
    {
        $this->assertSame([], Util::excludeIPv6Subnet('2001:db8::/60', 60));
    }
}
