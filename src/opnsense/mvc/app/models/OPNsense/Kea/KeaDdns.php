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

namespace OPNsense\Kea;

use OPNsense\Base\BaseModel;
use OPNsense\Core\File;

class KeaDdns extends BaseModel
{
    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp-ddns.conf')
    {
        if ($this->general->enabled->isEmpty()) {
            return;
        }
        $domains = [];
        $keys = [];
        foreach ([(new KeaDhcpv4())->subnets->subnet4, (new KeaDhcpv6())->subnets->subnet6] as $subnets) {
            foreach ($subnets->iterateItems() as $subnet) {
                if ($subnet->ddns_forward_zone->isEmpty() || $subnet->ddns_dns_server->isEmpty()) {
                    continue;
                }
                $zone = $subnet->ddns_forward_zone->getValue();
                $server = $subnet->ddns_dns_server->getValue();
                $keyname = $subnet->ddns_domain_key_name->getValue();
                if ($keyname && !$subnet->ddns_domain_key->isEmpty() && !isset($keys[$keyname])) {
                    $keys[$keyname] = [
                        'name' => $keyname,
                        'algorithm' => $subnet->ddns_domain_algorithm->getValue(),
                        'secret' => $subnet->ddns_domain_key->getValue()
                    ];
                }
                // Deduplicate zones
                $domains[$zone] ??= [
                    'name' => $zone,
                    'dns-servers' => [[
                        'ip-address' => $server,
                        'port' => 53
                    ] + ($keyname ? ['key-name' => $keyname] : [])]
                ];
            }
        }
        if (!$domains) {
            return;
        }
        $cnf = [
            'DhcpDdns' => [
                'ip-address' => $this->general->http_host->getValue(),
                'port' => $this->general->http_port->asInt(),
                'tsig-keys' => array_values($keys ?: []),
                'forward-ddns' => [
                    'ddns-domains' => array_values($domains)
                ],
                // XXX: Unsure if needed
                'control-socket' => [
                    'socket-type' => 'unix',
                    'socket-name' => '/var/run/kea/kea-ddns-ctrl-socket'
                ],
                'loggers' => [[
                    'name' => 'kea-dhcp-ddns',
                    'output_options' => [
                        ['output' => 'syslog']
                    ],
                    'severity' => 'INFO'
                ]]
            ]
        ];
        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT), 0600);
    }
}
