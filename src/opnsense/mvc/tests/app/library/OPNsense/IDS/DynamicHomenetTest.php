<?php

/*
 * Copyright (C) 2026 Logan Galloway
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

namespace tests\OPNsense\IDS;

use OPNsense\IDS\DynamicHomenet;
use PHPUnit\Framework\TestCase;

class DynamicHomenetTest extends TestCase
{
    /* shaped like the Idassoc::prefix() result */
    private function idassoc(string $onlink, bool $valid = true): array
    {
        return [
            'prefix_on_link' => $onlink,
            'prefix_allocated' => $onlink,
            'prefix_associated' => '2001:db8:1234:ab00::/56',
            'prefix_valid' => $valid,
            'prefix_source' => 'wan',
        ];
    }

    /* what one run of sync() would write, given the content of the previous run */
    private function written(array $interfaces, array $prefixes, ?string $current): string
    {
        return DynamicHomenet::render(DynamicHomenet::merge($interfaces, $prefixes, DynamicHomenet::parse($current)));
    }

    public function testMergeSelectedOnly()
    {
        $prefixes = [
            'lan' => $this->idassoc('2001:db8:1234:ab00::/64'),
            'opt1' => $this->idassoc('2001:db8:1234:ab01::/64'),
        ];

        $this->assertEquals(['lan' => '2001:db8:1234:ab00::/64'], DynamicHomenet::merge(['lan'], $prefixes, []));
        $this->assertEquals([], DynamicHomenet::merge([], $prefixes, []));
    }

    public function testMergeNeverSeenInterfaceContributesNothing()
    {
        $prefixes = [
            'lan' => $this->idassoc('2001:db8:1234:ab00::/64'),
            /* temporary prefix handed out while the real one is not known yet */
            'opt1' => $this->idassoc('dead:beef:0::/64', false),
        ];

        /* opt2 is not an identity association interface, Idassoc knows nothing about it */
        $this->assertEquals(
            ['lan' => '2001:db8:1234:ab00::/64'],
            DynamicHomenet::merge(['lan', 'opt1', 'opt2'], $prefixes, [])
        );
        $this->assertEquals([], DynamicHomenet::merge(['opt1', 'opt2'], $prefixes, []));
    }

    public function testMergeOneValidOneTemporarilyMissing()
    {
        $prefixes = [
            'lan' => $this->idassoc('2001:db8:5678:cd00::/64'),
            'opt1' => $this->idassoc('dead:beef:0::/64', false),
        ];
        $known = ['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab01::/64'];

        /* lan takes the new prefix, opt1 keeps its last known one */
        $this->assertEquals(
            ['lan' => '2001:db8:5678:cd00::/64', 'opt1' => '2001:db8:1234:ab01::/64'],
            DynamicHomenet::merge(['lan', 'opt1'], $prefixes, $known)
        );
    }

    public function testMergeDropsInterfaceIdassocNoLongerLists()
    {
        $prefixes = [
            'lan' => $this->idassoc('2001:db8:1234:ab00::/64'),
            /* temporary gap, still listed */
            'opt1' => $this->idassoc('dead:beef:0::/64', false),
        ];
        $known = [
            'lan' => '2001:db8:1234:ab00::/64',
            'opt1' => '2001:db8:1234:ab01::/64',
            'opt2' => '2001:db8:1234:ab02::/64',
        ];

        /* opt2 is selected but not an identity association anymore, so Idassoc does not list it */
        $this->assertEquals(
            ['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab01::/64'],
            DynamicHomenet::merge(['lan', 'opt1', 'opt2'], $prefixes, $known)
        );
        $this->assertEquals([], DynamicHomenet::merge(['opt2'], $prefixes, $known));
    }

    public function testMergeDropsDeselectedInterface()
    {
        $prefixes = ['lan' => $this->idassoc('2001:db8:1234:ab00::/64')];
        $known = ['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab01::/64'];

        $this->assertEquals(['lan' => '2001:db8:1234:ab00::/64'], DynamicHomenet::merge(['lan'], $prefixes, $known));
        /* the dropped one stays dropped when the remaining one has no prefix either */
        $this->assertEquals([], DynamicHomenet::merge(['opt2'], [], $known));
    }

    public function testRenderContent()
    {
        $this->assertEquals(
            "%YAML 1.1\n---\n# lan 2001:db8:1234:ab00::/64\n# opt1 2001:db8:1234:ab01::/64\n" .
                "DYNAMIC_HOME_NET: \"[2001:db8:1234:ab00::/64,2001:db8:1234:ab01::/64]\"\n",
            DynamicHomenet::render(['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab01::/64'])
        );
    }

    public function testRenderListsSharedPrefixOnce()
    {
        $this->assertEquals(
            "%YAML 1.1\n---\n# lan 2001:db8:1234:ab00::/64\n# opt1 2001:db8:1234:ab00::/64\n" .
                "DYNAMIC_HOME_NET: \"[2001:db8:1234:ab00::/64]\"\n",
            DynamicHomenet::render(['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab00::/64'])
        );
    }

    public function testRenderEmptyList()
    {
        $this->assertEquals("%YAML 1.1\n---\nDYNAMIC_HOME_NET: \"[]\"\n", DynamicHomenet::render([]));
    }

    public function testDescribe()
    {
        $this->assertEquals(
            'dynamic home networks updated: lan 2001:db8:1234:ab00::/64, opt1 2001:db8:1234:ab01::/64',
            DynamicHomenet::describe(['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab01::/64'])
        );
        $this->assertEquals('dynamic home networks updated: none', DynamicHomenet::describe([]));
    }

    public function testParseRoundTrip()
    {
        $result = ['lan' => '2001:db8:1234:ab00::/64', 'opt1' => '2001:db8:1234:ab01::/64'];

        $this->assertEquals($result, DynamicHomenet::parse(DynamicHomenet::render($result)));
        $this->assertEquals([], DynamicHomenet::parse(DynamicHomenet::render([])));
        $this->assertEquals([], DynamicHomenet::parse(null));
    }

    public function testAllSelectedMissingKeepsOldContents()
    {
        $prefixes = [
            'lan' => $this->idassoc('2001:db8:1234:ab00::/64'),
            'opt1' => $this->idassoc('2001:db8:1234:ab01::/64'),
        ];
        $current = $this->written(['lan', 'opt1'], $prefixes, null);

        /* same selection, no valid prefix on any of them: the content stays exactly as it was */
        $missing = [
            'lan' => $this->idassoc('dead:beef:0::/64', false),
            'opt1' => $this->idassoc('dead:beef:1::/64', false),
        ];
        $this->assertEquals($current, $this->written(['lan', 'opt1'], $missing, $current));
    }

    public function testNoLongerListedInterfacesEmptyFile()
    {
        $prefixes = [
            'lan' => $this->idassoc('2001:db8:1234:ab00::/64'),
            'opt1' => $this->idassoc('2001:db8:1234:ab01::/64'),
        ];
        $current = $this->written(['lan', 'opt1'], $prefixes, null);

        /* Idassoc lists neither anymore, which is not a temporary gap */
        $this->assertEquals(
            "%YAML 1.1\n---\nDYNAMIC_HOME_NET: \"[]\"\n",
            $this->written(['lan', 'opt1'], [], $current)
        );
    }

    public function testSelectionChangedWithoutPrefixEmptiesFile()
    {
        $current = $this->written(['lan'], ['lan' => $this->idassoc('2001:db8:1234:ab00::/64')], null);

        /* lan is deselected and opt1 has no prefix yet */
        $this->assertEquals("%YAML 1.1\n---\nDYNAMIC_HOME_NET: \"[]\"\n", $this->written(['opt1'], [], $current));
    }
}
