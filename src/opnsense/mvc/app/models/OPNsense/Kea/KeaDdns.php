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
        $reverse_domains = [];
        $keys = [];
        foreach ([(new KeaDhcpv4())->subnets->subnet4, (new KeaDhcpv6())->subnets->subnet6] as $subnets) {
            foreach ($subnets->iterateItems() as $subnet) {
                if ($subnet->ddns_forward_zone->isEmpty() || $subnet->ddns_dns_server->isEmpty()) {
                    continue;
                }
                $forward_zone = $subnet->ddns_forward_zone->getValue();
                $server = $subnet->ddns_dns_server->getValue();
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
                    'port' => 53,
                ];
                if (!in_array($server_entry, $domains[$forward_zone]['dns-servers'], true)) {
                    $domains[$forward_zone]['dns-servers'][] = $server_entry;
                }

                /* Build reverse-ddns domains from the subnet's CIDR */
                $subnet_cidr = (string)$subnet->subnet;
                if (!empty($subnet_cidr) && str_contains($subnet_cidr, '.')) {
                    /* IPv4: derive in-addr.arpa zone(s) from subnet */
                    $parts = explode('/', $subnet_cidr);
                    $octets = explode('.', $parts[0]);
                    $mask = isset($parts[1]) ? intval($parts[1]) : 24;
                    $rev_zones = [];
                    if ($mask >= 24) {
                        $rev_zones[] = sprintf('%s.%s.%s.in-addr.arpa.', $octets[2], $octets[1], $octets[0]);
                    } elseif ($mask >= 16) {
                        /* For /16-/23 subnets, enumerate each /24 reverse zone */
                        $base = intval($octets[2]);
                        $count = 1 << (24 - $mask);
                        for ($i = 0; $i < $count; $i++) {
                            $rev_zones[] = sprintf('%d.%s.%s.in-addr.arpa.', $base + $i, $octets[1], $octets[0]);
                        }
                    } else {
                        $rev_zones[] = sprintf('%s.in-addr.arpa.', $octets[0]);
                    }
                    foreach ($rev_zones as $rev_zone) {
                        if (!isset($reverse_domains[$rev_zone])) {
                            $reverse_domain = ['name' => $rev_zone, 'dns-servers' => []];
                            if ($keyname) {
                                $reverse_domain['key-name'] = $keyname;
                            }
                            $reverse_domain['dns-servers'][] = $server_entry;
                            $reverse_domains[$rev_zone] = $reverse_domain;
                        }
                    }
                } elseif (!empty($subnet_cidr) && str_contains($subnet_cidr, ':')) {
                    /* IPv6: derive ip6.arpa zone from subnet prefix.
                     * Use /48 boundary for the reverse zone since that is the
                     * standard delegation size for ULA and most ISP allocations.
                     * Multiple /64 subnets under the same /48 share one reverse zone. */
                    $parts = explode('/', $subnet_cidr);
                    $expanded = @inet_pton($parts[0]);
                    if ($expanded !== false) {
                        $hex = bin2hex($expanded);
                        /* Use /48 (12 nibbles) as the reverse zone boundary */
                        $nibbles = 12;
                        $prefix_nibbles = substr($hex, 0, $nibbles);
                        $rev_zone = implode('.', array_reverse(str_split($prefix_nibbles))) . '.ip6.arpa.';
                        if (!isset($reverse_domains[$rev_zone])) {
                            $reverse_domain = ['name' => $rev_zone, 'dns-servers' => []];
                            if ($keyname) {
                                $reverse_domain['key-name'] = $keyname;
                            }
                            $reverse_domain['dns-servers'][] = $server_entry;
                            $reverse_domains[$rev_zone] = $reverse_domain;
                        }
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
