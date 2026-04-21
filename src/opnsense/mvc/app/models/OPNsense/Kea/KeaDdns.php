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
    public function isEnabled()
    {
        return $this->general->enabled->isEqual('1');
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp-ddns.conf')
    {
        if ($this->general->enabled->isEmpty()) {
            return;
        }
        $domains = [];
        $reverse_domains = [];
        $keys = [];
        foreach ([(new KeaDhcpv4())->subnets->subnet4, (new KeaDhcpv6())->subnets->subnet6] as $subnets) {
            foreach ($subnets->iterateItems() as $subnet) {
                if ($subnet->ddns_forward_zone->isEmpty() || $subnet->ddns_dns_server->isEmpty()) {
                    continue;
                }
                $forward_zone = $subnet->ddns_forward_zone->getValue();
                $server = $subnet->ddns_dns_server->getValue();
                $port = !$subnet->ddns_dns_port->isEmpty() ? $subnet->ddns_dns_port->asInt() : 53;
                $keyname = $subnet->ddns_domain_key_name->getValue();
                if ($keyname && !isset($keys[$keyname])) {
                    $keys[$keyname] = [
                        'name' => $keyname,
                        'algorithm' => $subnet->ddns_domain_key_algorithm->getValue(),
                        'secret' => $subnet->ddns_domain_key_secret->getValue(),
                    ];
                }
                if (!isset($domains[$forward_zone])) {
                    $domains[$forward_zone] = ['name' => $forward_zone];
                    if ($keyname) {
                        $domains[$forward_zone]['key-name'] = $keyname;
                    }
                    $domains[$forward_zone]['dns-servers'] = [];
                }
                $server_entry = [
                    'ip-address' => $server,
                    'port' => $port,
                ];
                if (!in_array($server_entry, $domains[$forward_zone]['dns-servers'], true)) {
                    $domains[$forward_zone]['dns-servers'][] = $server_entry;
                }
                $reverse_zone = $subnet->ddns_reverse_zone->getValue();
                if (!empty($reverse_zone)) {
                    if (!isset($reverse_domains[$reverse_zone])) {
                        $reverse_domains[$reverse_zone] = ['name' => $reverse_zone];
                        if ($keyname) {
                            $reverse_domains[$reverse_zone]['key-name'] = $keyname;
                        }
                        $reverse_domains[$reverse_zone]['dns-servers'] = [];
                    }
                    $server_entry = [
                        'ip-address' => $server,
                        'port' => $port,
                    ];
                    if (!in_array($server_entry, $reverse_domains[$reverse_zone]['dns-servers'], true)) {
                        $reverse_domains[$reverse_zone]['dns-servers'][] = $server_entry;
                    }
                }
            }
        }
        $cnf = [
            'DhcpDdns' => [
                'ip-address' => $this->general->server_ip->getValue(),
                'port' => $this->general->server_port->asInt(),
                'tsig-keys' => array_values($keys),
                'forward-ddns' => [
                    'ddns-domains' => array_values($domains)
                ],
                'reverse-ddns' => [
                    'ddns-domains' => array_values($reverse_domains)
                ],
                'loggers' => [[
                    'name' => 'kea-dhcp-ddns',
                    'output_options' => [
                        ['output' => 'syslog']
                    ],
                    'severity' => 'INFO',
                ]]
            ]
        ];

        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT), 0600);
    }
}
