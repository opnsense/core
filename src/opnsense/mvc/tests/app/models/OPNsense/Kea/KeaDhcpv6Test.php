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

namespace tests\OPNsense\Kea;

require_once __DIR__ . '/Base/JsonSampleTestCase.php';

use OPNsense\Kea\KeaDhcpv6;

class KeaDhcpv6Test extends JsonSampleTestCase
{
    protected function getSnapshotFile(): string
    {
        return 'KeaDhcpv6Test.json';
    }

    protected function getModelInstance()
    {
        return new KeaDhcpv6();
    }

    protected function getJsonRootKey(): string
    {
        return 'Dhcp6';
    }

    public function testJsonSample(): void
    {
        $this->runJsonSampleTest();
    }

    public function testIsEnabled(): void
    {
        $m = $this->getModelInstance();

        $m->general->enabled = "0";
        $m->general->interfaces = "";

        $this->assertFalse($m->isEnabled());

        $m->general->enabled = "1";
        $this->assertFalse($m->isEnabled());

        $m->general->interfaces = "lan";
        $this->assertTrue($m->isEnabled());
    }

    public function testFwRulesEnabled(): void
    {
        $m = $this->getModelInstance();

        $m->general->enabled = "0";
        $m->general->fwrules = "0";
        $m->general->interfaces = "";

        $this->assertFalse($m->fwrulesEnabled());

        $m->general->enabled = "1";
        $this->assertFalse($m->fwrulesEnabled());

        $m->general->fwrules = "1";
        $this->assertFalse($m->fwrulesEnabled());

        $m->general->interfaces = "lan";
        $this->assertTrue($m->fwrulesEnabled());
    }

    public function testPhysicalInterfacesParsing(): void
    {
        $xml = <<<XML
    <opnsense>
    <interfaces>
        <lan><if>igc0</if></lan>
        <wan><if>igc1</if></wan>
    </interfaces>
    </opnsense>
    XML;

        \OPNsense\Core\Config::getInstance()->setXml($xml);

        $m = $this->getModelInstance();
        $m->general->interfaces = "lan,wan";

        $ifs = (new \ReflectionClass($m))
            ->getMethod('getConfigPhysicalInterfaces')
            ->invoke($m);

        $this->assertSame(['igc0','igc1'], $ifs);
    }
}
