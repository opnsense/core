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
    private function addDdnsDomain(&$domains, $name, $server, $keyname)
    {
        if (!isset($domains[$name])) {
            $domains[$name] = ['name' => $name];
            if ($keyname) {
                $domains[$name]['key-name'] = $keyname;
            }
            $domains[$name]['dns-servers'] = [];
        }

        $server_entry = [
            'ip-address' => $server,
            'port' => 53,
        ];
        if (!in_array($server_entry, $domains[$name]['dns-servers'], true)) {
            $domains[$name]['dns-servers'][] = $server_entry;
        }
    }

    private function getReverseZoneForSubnet($subnet)
    {
        if (empty($subnet) || strpos($subnet, '/') === false) {
            return null;
        }

        [$address, $prefix_length] = explode('/', $subnet, 2);
        if (!is_numeric($prefix_length)) {
            return null;
        }

        $prefix_length = (int)$prefix_length;
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if ($prefix_length < 8 || $prefix_length > 32 || $prefix_length % 8 !== 0) {
                return null;
            }
            $octets = explode('.', $address);
            return implode('.', array_reverse(array_slice($octets, 0, $prefix_length / 8))) . '.in-addr.arpa.';
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            if ($prefix_length < 4 || $prefix_length > 128 || $prefix_length % 4 !== 0) {
                return null;
            }
            $packed = inet_pton($address);
            if ($packed === false) {
                return null;
            }
            $nibbles = str_split(substr(bin2hex($packed), 0, $prefix_length / 4));
            return implode('.', array_reverse($nibbles)) . '.ip6.arpa.';
        }

        return null;
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp-ddns.conf')
    {
        if ($this->general->enabled->isEmpty()) {
            return;
        }
        $forward_domains = [];
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
                $this->addDdnsDomain($forward_domains, $forward_zone, $server, $keyname);

                $reverse_zone = $this->getReverseZoneForSubnet($subnet->subnet->getValue());
                if ($reverse_zone !== null) {
                    $this->addDdnsDomain($reverse_domains, $reverse_zone, $server, $keyname);
                }
            }
        }
        $cnf = [
            'DhcpDdns' => [
                'ip-address' => $this->general->server_ip->getValue(),
                'port' => $this->general->server_port->asInt(),
                'tsig-keys' => array_values($keys),
                'forward-ddns' => [
                    'ddns-domains' => array_values($forward_domains)
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
