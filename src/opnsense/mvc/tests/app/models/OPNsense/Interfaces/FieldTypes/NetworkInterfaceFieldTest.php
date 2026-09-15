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
 * A BooleanField that is off must not be written to the legacy config as the string "0".
 * Large parts of src/etc/inc and src/www presence-test these keys with isset(), which is true
 * for "0", so a stored "0" reads as enabled. src/www/interfaces.php omits the key entirely.
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
    private function container(array $overrides = [], array $booleans = []): NetworkInterfaceContainerField
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
        foreach (array_merge(['dhcp6_request_dns' => '1'], $booleans) as $key => $value) {
            $container->addChildNode($key, $this->boolean($value));
        }
        return $container;
    }

    /*
     * A BooleanField that is off must not reach the legacy config as "0". Large parts of
     * src/etc/inc and src/www test these keys with isset(), which is true for "0", so a stored
     * "0" reads as enabled. src/www/interfaces.php omits the key entirely in that case, and
     * both writers delete the node when toLegacy() hands them an empty string.
     */
    public function testFalseBooleanIsNotPersisted()
    {
        $result = $this->container([], ['promisc' => '0', 'blockbogons' => '0'])->toLegacy();
        foreach (['promisc', 'blockbogons'] as $field) {
            $this->assertSame('', $result[$field], "{$field} must be emitted as '' when off");
        }
    }

    public function testTrueBooleanIsPersisted()
    {
        $result = $this->container([], ['promisc' => '1'])->toLegacy();
        $this->assertSame('1', $result['promisc']);
    }

    public function testFalseBooleanIsNotTruthyUnderIsset()
    {
        $result = $this->container([], ['dhcp6prefixonly' => '0'])->toLegacy();
        $legacy = array_filter($result, function ($value) {
            return $value !== '';
        });
        $this->assertArrayNotHasKey(
            'dhcp6prefixonly',
            $legacy,
            'an unticked option must not survive into the legacy config'
        );
    }

    /*
     * The inverse must keep working: a non-boolean field whose value happens to be "0" is a
     * real value and has to be written.
     */
    public function testNonBooleanZeroIsStillWritten()
    {
        $result = $this->container(['dhcp6-ia-pd-len' => '0'])->toLegacy();
        $this->assertSame('0', $result['dhcp6-ia-pd-len']);
    }

    /*
     * dhcp6_norequest_dns is derived, not copied: the model stores the positive
     * dhcp6_request_dns and toLegacy() inverts it.
     */
    public function testDerivedNoRequestDnsIsEmptyWhenDnsRequested()
    {
        $result = $this->container([], ['dhcp6_request_dns' => '1'])->toLegacy();
        $this->assertSame('', $result['dhcp6_norequest_dns']);
    }

    public function testDerivedNoRequestDnsIsSetWhenDnsNotRequested()
    {
        $result = $this->container([], ['dhcp6_request_dns' => '0'])->toLegacy();
        $this->assertSame('1', $result['dhcp6_norequest_dns']);
    }

    /*
     * Fixing this in toLegacy() rather than in a per-key list means there is no list left to
     * fall behind the model. This enforces that: it reads every BooleanField declared in
     * NetworkInterface.xml, so a field added to the model later is covered the day it is added,
     * with no edit to this file.
     */
    public function booleanFieldProvider(): array
    {
        $xml = simplexml_load_file(
            __DIR__ . '/../../../../../../app/models/OPNsense/Interfaces/NetworkInterface.xml'
        );
        /* dhcp6_request_dns is deliberately skiplisted: it is emitted, inverted, as
           dhcp6_norequest_dns and is covered by the two tests above instead. */
        $derived = ['dhcp6_request_dns'];
        $cases = [];
        foreach ($xml->xpath('//*[@type="BooleanField"]') as $node) {
            $name = $node->getName();
            if (!in_array($name, $derived)) {
                $cases[$name] = [$name];
            }
        }
        return $cases;
    }

    /**
     * @dataProvider booleanFieldProvider
     */
    public function testEveryBooleanFieldIsOmittedWhenFalse(string $field)
    {
        $result = $this->container([], [$field => '0'])->toLegacy();
        $this->assertSame('', $result[$field], "{$field} must be emitted as '' when off");
    }
}
