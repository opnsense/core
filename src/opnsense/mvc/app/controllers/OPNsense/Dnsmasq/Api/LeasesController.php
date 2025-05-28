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

namespace OPNsense\Dnsmasq\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Dnsmasq\Dnsmasq;
use OPNsense\Firewall\Util;

class LeasesController extends ApiControllerBase
{
    public function searchAction()
    {
        $selected_interfaces = $this->request->get('selected_interfaces');
        $selected_protocol = $this->request->getPost('selected_protocol') ?? '';
        $backend = new Backend();
        $interfaces = [];

        $leases = json_decode($backend->configdpRun('dnsmasq list leases'), true) ?? [];
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true);
        $mac_db = json_decode($backend->configdRun('interface list macdb'), true) ?? [];

        $ifmap = [];
        foreach (Config::getInstance()->object()->interfaces->children() as $if => $if_props) {
            $ifmap[(string)$if_props->if] = [
                'descr' => (string)$if_props->descr ?: strtoupper($if),
                'key' => $if
            ];
        }

        if (!empty($leases) && isset($leases['records'])) {
            $records = $leases['records'];
            foreach ($records as &$record) {
                $record['if_descr'] = '';
                $record['if_name'] = '';
                if (!empty($record['if']) && isset($ifmap[$record['if']])) {
                    $record['if_descr'] = $ifmap[$record['if']]['descr'];
                    $record['if_name'] = $ifmap[$record['if']]['key'];
                    $interfaces[$ifmap[$record['if']]['key']] = $ifmap[$record['if']]['descr'];
                }
                $mac = strtoupper(substr(str_replace(':', '', $record['hwaddr']), 0, 6));
                $record['mac_info'] = isset($mac_db[$mac]) ? $mac_db[$mac] : '';
            }
        } else {
            $records = [];
        }

        // Mark records as reserved based on hwaddr (IPv4) or client_id (IPv6) match
        $reservedKeys = [];

        foreach ((new Dnsmasq())->hosts->iterateItems() as $host) {
            if (!empty($host->client_id)) {
                $reservedKeys[] = (string)$host->client_id;
            }

            if (!empty($host->hwaddr)) {
                foreach (explode(',', (string)$host->hwaddr) as $hwaddr) {
                    if (!empty($hwaddr)) {
                        $reservedKeys[] = $hwaddr;
                    }
                }
            }
        }

        foreach ($records as &$record) {
            $is_ipv6 = Util::isIpv6Address($record['address'] ?? '');
            $key = $is_ipv6 ? ($record['client_id'] ?? '') : ($record['hwaddr'] ?? '');
            $record['is_reserved'] = in_array($key, $reservedKeys, true) ? '1' : '0';
        }

        $response = $this->searchRecordsetBase(
            $records,
            null,
            'address',
            function ($record) use ($selected_interfaces, $selected_protocol) {
                $interfaceMatch = empty($selected_interfaces)
                    || in_array($record['if_name'], $selected_interfaces, true);

                $protocolMatch = true;
                if (!empty($selected_protocol)) {
                    if ($selected_protocol === 'ipv4') {
                        $protocolMatch = Util::isIpv4Address($record['address']);
                    } else {
                        $protocolMatch = Util::isIpv6Address($record['address']);
                    }
                }

                return $interfaceMatch && $protocolMatch;
            }
        );

        $response['interfaces'] = $interfaces;
        return $response;
    }
}
