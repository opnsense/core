<?php

/*
 * Copyright (C) 2026 fa1k3
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
 *  THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *  AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 */

namespace tests\OPNsense\Firewall;

use OPNsense\Firewall\ForwardRule;

class ForwardRuleTest extends \PHPUnit\Framework\TestCase
{
    public static $ifmap = [
        'wan' => ['if' => 'vtnet0', 'gateway' => 'wangw', 'ifconfig' => ['ipv4' => [['ipaddr' => '192.0.2.1']]]],
        'lan' => ['if' => 'vtnet1', 'ifconfig' => ['ipv4' => [['ipaddr' => '10.0.0.1']]]],
    ];

    /**
     * construct a basic tcp port forward on wan with the given overrides
     */
    private function forward($conf)
    {
        return new ForwardRule(self::$ifmap, array_merge([
            'interface' => 'wan',
            'ipprotocol' => 'inet',
            'protocol' => 'tcp',
            'from' => 'any',
            'to' => '192.0.2.1',
            'to_port' => '8000',
            'target' => '10.0.0.5',
        ], $conf));
    }

    /**
     * test local-port translation (numeric, well-known, any, ranges, unmappable values, reflection and inet6)
     */
    public function testLocalPort()
    {
        $range = ['to_port' => '8000:8010'];
        $rules = [];

        $rules[] = $this->forward(['local-port' => '80', 'descr' => 'numeric']);
        $rules[] = $this->forward($range + ['local-port' => '80', 'descr' => 'numeric range']);
        $rules[] = $this->forward(['local-port' => 'nat-stun-port', 'descr' => 'well-known']);
        $rules[] = $this->forward($range + ['local-port' => 'nat-stun-port', 'descr' => 'well-known range']);
        $rules[] = $this->forward(['local-port' => 'any', 'descr' => 'any']);
        $rules[] = $this->forward($range + ['local-port' => 'any', 'descr' => 'any range']);
        $rules[] = $this->forward(['local-port' => '1000', 'to_port' => 'http:https', 'descr' => 'named range']);
        $rules[] = $this->forward($range + ['local-port' => '80:90', 'descr' => 'explicit local range']);
        $rules[] = $this->forward($range + ['local-port' => '65530', 'descr' => 'range overflow']);
        $rules[] = $this->forward(['local-port' => '1000', 'to_port' => '8010:8000', 'descr' => 'descending range']);
        $rules[] = $this->forward($range + ['local-port' => 'kerberos', 'descr' => 'service name range']);
        $rules[] = $this->forward(['local-port' => 'no-such-port', 'descr' => 'unmappable']);
        $rules[] = $this->forward($range + [
            'local-port' => 'nat-stun-port', 'natreflection' => 'enable', 'enablenatreflectionhelper' => '1',
            'descr' => 'reflection'
        ]);
        $rules[] = $this->forward([
            'ipprotocol' => 'inet6', 'to' => '2001:db8::1', 'target' => '2001:db8::5',
            'local-port' => 'nat-stun-port', 'descr' => 'inet6'
        ]);

        $this->assertEquals(
            file_get_contents(__DIR__ . '/ForwardRuleTest/testLocalPort.conf'),
            join('', $rules)
        );
    }
}
