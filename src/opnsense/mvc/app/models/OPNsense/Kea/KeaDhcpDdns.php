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
    public function isEnabled()
    {
        return $this->general->enabled->isEqual('1');
    }

    private function getTsigKeys() {
        return array_values($this->tsig_keys->getNodes());
    }

    private function buildDomains($domainsNode) {
        $tsigKeys = array_column(array_values($this->tsig_keys->getNodes()), 'name', '__uuid__');

        return array_map(function ($domain) use ($tsigKeys) {
            $server = [
                'ip-address' => $domain->ip_address->getValue(),
                'port' => $domain->port->asInt()
            ];
            if (!empty($tsigKeys[$domain->key_name->getValue()])) {
                $server['key-name'] = $tsigKeys[$domain->key_name->getValue()];
            }
            return [
                'name' => $domain->name->getValue(),
                'dns-servers' => [$server]
            ];
        }, iterator_to_array($domainsNode->iterateItems()));
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp-ddns.conf')
    {
        $result = ['DhcpDdns' => [
            'ip-address' => '127.0.0.1',
            'port' => 53001,
            'control-socket' => [
                'socket-type' => 'unix',
                'socket-name' => '/var/run/kea/kea-ddns-ctrl-socket'
            ],
            'loggers' => [[
                'name' => 'kea-dhcp-ddns',
                'output_options' => [['output' => 'syslog']],
                'severity' => 'INFO',
            ]],
            'tsig-keys' => $this->getTsigKeys(),
            'forward-ddns' => ['ddns-domains' => $this->buildDomains($this->forward_ddns->ddns_domains)],
            'reverse-ddns' => ['ddns-domains' => $this->buildDomains($this->reverse_ddns->ddns_domains)]
        ]];

        File::file_put_contents($target, json_encode($result, JSON_PRETTY_PRINT), 0600);
    }
}
