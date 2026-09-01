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

namespace OPNsense\Firewall\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Firewall\Filter;
use OPNsense\Firewall\Util;

class MFP1_0_8 extends BaseModelMigration
{
    private function addressToNetwork($address, $mask)
    {
        $address = (string)$address;
        if (empty($address) || $address === 'any') {
            return 'any';
        }
        if (Util::isIpAddress($address) && (string)$mask !== '') {
            return $address . '/' . (string)$mask;
        }
        return $address;
    }

    private function normalizePort($port)
    {
        /* Legacy port ranges use a colon, while PortField expects a dash. */
        return str_replace(':', '-', (string)$port);
    }

    private function protocolFamily($rule, $protocol)
    {
        if ($protocol === 'ICMP') {
            return 'inet';
        }
        if ($protocol === 'IPV6-ICMP') {
            return 'inet6';
        }
        foreach ([(string)$rule->src, (string)$rule->dst] as $address) {
            if (Util::isIpv4Address($address)) {
                return 'inet';
            }
            if (Util::isIpv6Address($address)) {
                return 'inet6';
            }
        }
        return 'inet46';
    }

    private function hasScrubOptions($rule)
    {
        foreach (['no-df', 'random-id', 'max-mss', 'min-ttl', 'set-tos'] as $fieldname) {
            if (!empty($rule->$fieldname)) {
                return true;
            }
        }
        return false;
    }

    public function run($model)
    {
        if ($model instanceof Filter) {
            $config = Config::getInstance()->object();
            foreach ($config?->filter?->scrub?->rule ?? [] as $legacy) {
                /* "no scrub" and reassembly-only rules have no match-rule equivalent. */
                if (!empty($legacy->noscrub) || !$this->hasScrubOptions($legacy)) {
                    continue;
                }

                $protocol = !empty($legacy->proto) ? strtoupper((string)$legacy->proto) : 'any';

                $rule = $model->rules->rule->Add();
                /* Duplicate sequences are intentional; migrated match rules should remain at the beginning. */
                $rule->setNodes([
                    'enabled' => empty($legacy->disabled) ? '1' : '0',
                    'statetype' => 'none',
                    'sequence' => '1',
                    'action' => 'match',
                    'quick' => '0',
                    'interface' => (string)$legacy->interface,
                    'direction' => !empty($legacy->direction) ? (string)$legacy->direction : 'any',
                    'ipprotocol' => $this->protocolFamily($legacy, $protocol),
                    'protocol' => $protocol,
                    'source_net' => $this->addressToNetwork($legacy->src, $legacy->srcmask),
                    'source_not' => !empty($legacy->srcnot) ? '1' : '0',
                    'source_port' => $this->normalizePort($legacy->srcport),
                    'destination_net' => $this->addressToNetwork($legacy->dst, $legacy->dstmask),
                    'destination_not' => !empty($legacy->dstnot) ? '1' : '0',
                    'destination_port' => $this->normalizePort($legacy->dstport),
                    'description' => (string)$legacy->descr,
                    'scrub_no_df' => !empty($legacy->{'no-df'}) ? '1' : '0',
                    'scrub_random_id' => !empty($legacy->{'random-id'}) ? '1' : '0',
                    'scrub_max_mss' => (string)$legacy->{'max-mss'},
                    'scrub_min_ttl' => (string)$legacy->{'min-ttl'},
                    'scrub_set_tos' => (string)$legacy->{'set-tos'},
                ]);
            }
        }

        parent::run($model);
    }
}
