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
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace tests\OPNsense\Interfaces\FieldTypes;

// @CodingStandardsIgnoreStart
/* NetworkInterfaceContainerField shares a file with NetworkInterfaceField, so the PSR-4
   autoloader cannot reach it by name. */
require_once __DIR__ . '/../../../../../../app/models/OPNsense/Interfaces/FieldTypes/NetworkInterfaceField.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\BooleanField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Interfaces\FieldTypes\NetworkInterfaceContainerField;
use PHPUnit\Framework\TestCase;

/*
 * The four hex ID fields are shown in the GUI as hex ("0x9") but are stored in the legacy
 * config as a decimal string, as documented in
 * tests/app/library/OPNsense/Interface/IdassocTest.php and relied on by
 * src/etc/inc/interfaces.inc, which guards them with is_numeric().
 *
 *   dhcp6-prefix-id, dhcp6_ifid, track6-prefix-id, track6_ifid
 *
 * toLegacy() must therefore emit a decimal integer for a field the operator set, and must not
 * emit a key at all for one they left alone.
 */
class NetworkInterfaceFieldTest extends TestCase
{
    private function text(string $value = ''): TextField
    {
        $node = new TextField();
        $node->setValue($value);
        return $node;
    }

    private function boolean(string $value = '0'): BooleanField
    {
        $node = new BooleanField();
        $node->setValue($value);
        return $node;
    }

    /**
     * Minimal container carrying only what toLegacy() reads.
     */
    private function container(array $overrides = []): NetworkInterfaceContainerField
    {
        $defaults = [
            'type4' => 'dhcp',
            'type6' => 'dhcp6',
            'ipaddr' => '',
            'ipaddrv6' => '',
            'media' => '',
            'dhcp6-prefix-id' => '',
            'dhcp6_ifid' => '',
            'track6-prefix-id' => '',
            'track6_ifid' => '',
        ];
        $values = array_merge($defaults, $overrides);

        $container = new NetworkInterfaceContainerField();
        foreach ($values as $key => $value) {
            $container->addChildNode($key, $this->text($value));
        }
        $container->addChildNode('dhcp6_request_dns', $this->boolean('1'));
        return $container;
    }

    public function testHexIdIsStoredAsDecimal()
    {
        $result = $this->container(['dhcp6_ifid' => '0x9'])->toLegacy();
        $this->assertSame(9, $result['dhcp6_ifid']);
    }

    public function testLargeHexIdIsStoredAsDecimal()
    {
        $result = $this->container(['track6_ifid' => '0xfff'])->toLegacy();
        $this->assertSame(4095, $result['track6_ifid']);
    }

    public function testExplicitZeroIsStored()
    {
        $result = $this->container(['dhcp6-prefix-id' => '0x0'])->toLegacy();
        $this->assertSame(0, $result['dhcp6-prefix-id']);
    }

    public function testUntouchedIdsAreNotWritten()
    {
        $result = $this->container(['dhcp6_ifid' => '0x9'])->toLegacy();
        foreach (['dhcp6-prefix-id', 'track6-prefix-id', 'track6_ifid'] as $field) {
            $this->assertArrayNotHasKey($field, $result, "{$field} should not be written");
        }
    }

    public function testNoIdKeysOnAnUntouchedInterface()
    {
        $result = $this->container()->toLegacy();
        foreach (['dhcp6-prefix-id', 'dhcp6_ifid', 'track6-prefix-id', 'track6_ifid'] as $field) {
            $this->assertArrayNotHasKey($field, $result, "{$field} should not be written");
        }
    }

    public function testStoredValueIsNeverTheHexString()
    {
        $result = $this->container([
            'dhcp6_ifid' => '0x9',
            'track6_ifid' => '0x1',
            'track6-prefix-id' => '0x2',
        ])->toLegacy();
        foreach (['dhcp6_ifid', 'track6_ifid', 'track6-prefix-id'] as $field) {
            $this->assertIsNotString($result[$field], "{$field} must not keep the 0x form");
            $this->assertTrue(is_numeric($result[$field]), "{$field} must satisfy is_numeric()");
        }
    }
}
