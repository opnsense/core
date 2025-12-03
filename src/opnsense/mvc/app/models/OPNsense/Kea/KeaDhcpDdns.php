<?php

/*
 * Copyright (C) 2025 Yip Rui Fung <rf@yrf.me>
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

use OPNsense\Core\File;
use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class KeaDhcpDdns extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        // Run default field-level validators first
        $messages = parent::performValidation($validateFullModel);

        // Explicitly validate that forward and reverse domain names end with a dot (FQDN)
        foreach ($this->forward_ddns->ddns_domains->iterateItems() as $domain) {
            if (!$validateFullModel && !$domain->isFieldChanged()) {
                continue;
            }
            if (!($domain->name->isEmpty()) && !str_ends_with($domain->name->getValue(), '.')) {
                $messages->appendMessage(
                    new Message(
                        gettext('Domain must be a fully qualified domain name ending with a dot.'),
                        $domain->__reference . '.name'
                    )
                );
            }
        }
        foreach ($this->reverse_ddns->ddns_domains->iterateItems() as $domain) {
            if (!$validateFullModel && !$domain->isFieldChanged()) {
                continue;
            }
            if (!($domain->name->isEmpty()) && !str_ends_with($domain->name->getValue(), '.')) {
                $messages->appendMessage(
                    new Message(
                        gettext('Domain must be a fully qualified domain name ending with a dot.'),
                        $domain->__reference . '.name'
                    )
                );
            }
        }

        return $messages;
    }

    public function isEnabled()
    {
        return $this->general->enabled->isEqual('1');
    }

    /**
     * Build a map of shared DNS servers defined at root level (dns_servers)
     * keyed by their UUID for quick lookup.
     * @return array<string,array>
     */
    private function getSharedDnsServersMap()
    {
        $map = [];
        $tsigNameMap = $this->getTsigKeyNameMap();
        foreach ($this->dns_servers->iterateItems() as $uuid => $srv) {
            $item = [];
            if (!($srv->ip_address->isEmpty())) {
                $item['ip-address'] = $srv->ip_address->getValue();
            }
            if (!($srv->port->isEmpty())) {
                $item['port'] = $srv->port->asInt();
            }
            if (!($srv->key_name->isEmpty())) {
                $kn = $srv->key_name->getValue();
                // key_name is a ModelRelationField (UUID). Resolve to TSIG key name.
                if (!empty($tsigNameMap[$kn])) {
                    $item['key-name'] = $tsigNameMap[$kn];
                }
            }
            if (!empty($item)) {
                $map[$uuid] = $item;
            }
        }
        return $map;
    }

    /**
     * Build a map uuid => tsig key name for quick lookup when resolving relations.
     * @return array<string,string>
     */
    private function getTsigKeyNameMap()
    {
        $map = [];
        foreach ($this->tsig_keys->iterateItems() as $uuid => $key) {
            if (!($key->name->isEmpty())) {
                $map[$uuid] = $key->name->getValue();
            }
        }
        return $map;
    }

    private function getTsigKeys() {
        $tsig_keys = [];
        foreach ($this->tsig_keys->iterateItems() as $key) {
            $item = [];
            if (!($key->name->isEmpty())) {
                $item['name'] = $key->name->getValue();
            }
            if (!($key->algorithm->isEmpty())) {
                $item['algorithm'] = $key->algorithm->getValue();
            }
            if (!($key->secret->isEmpty())) {
                $item['secret'] = $key->secret->getValue();
            }
            if (!empty($item)) {
                $tsig_keys[] = $item;
            }
        }
        return $tsig_keys;
    }

    private function buildDomains ($domainsNode) {
        $domains = [];
        $serversMap = $this->getSharedDnsServersMap();
        $tsigNameMap = $this->getTsigKeyNameMap();
        foreach ($domainsNode->iterateItems() as $domain) {
            $entry = [];
            if (!($domain->name->isEmpty())) {
                // emit stored value as-is; validation ensures FQDN (trailing dot)
                $entry['name'] = $domain->name->getValue();
            }
            if (!($domain->key_name->isEmpty())) {
                $kn = $domain->key_name->getValue(); // UUID from ModelRelationField
                if (!empty($tsigNameMap[$kn])) {
                    $entry['key-name'] = $tsigNameMap[$kn];
                }
            }

            // dns-servers referenced via ModelRelationField (comma-separated UUIDs)
            $servers = [];
            $refs = !($domain->dns_servers->isEmpty()) ? $domain->dns_servers->getValue() : '';
            if (!empty($refs)) {
                foreach (array_filter(explode(',', $refs)) as $uuid) {
                    if (empty($uuid) || empty($serversMap[$uuid])) {
                        continue;
                    }
                    $servers[] = $serversMap[$uuid];
                }
            }
            if (!empty($servers)) {
                $entry['dns-servers'] = $servers;
            }

            if (!empty($entry)) {
                $domains[] = $entry;
            }
        }
        return $domains;
    }

    private function getForwardDomains() {
        return $this->buildDomains($this->forward_ddns->ddns_domains);
    }

    private function getReverseDomains() {
        return $this->buildDomains($this->reverse_ddns->ddns_domains);
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp-ddns.conf')
    {
        $result = [
            'DhcpDdns' => [
                'ip-address' => '127.0.0.1',
                'port' => 53001,
                'control-socket' => [
                    'socket-type' => 'unix',
                    'socket-name' => '/var/run/kea/kea-ddns-ctrl-socket'
                ],
                'loggers' => [
                    [
                        'name' => 'kea-dhcp-ddns',
                        'output_options' => [
                            [
                                'output' => 'syslog'
                            ]
                        ],
                        'severity' => 'INFO',
                    ]
                ],
                'tsig-keys' => $this->getTsigKeys(),
                'forward-ddns' => [
                    'ddns-domains' => $this->getForwardDomains()
                ],
                'reverse-ddns' => [
                    'ddns-domains' => $this->getReverseDomains()
                ]
            ]
        ];

        File::file_put_contents($target, json_encode($result, JSON_PRETTY_PRINT), 0600);
    }
}
