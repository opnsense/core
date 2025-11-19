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

namespace OPNsense\Kea\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Kea\KeaDhcpv4;
use OPNsense\Kea\KeaDhcpv6;

abstract class LeasesController extends ApiControllerBase
{
    protected $configd_fetch_leases = null;

    public function searchAction()
    {
        if (empty($this->configd_fetch_leases)) {
            return [];
        }

        $selected_interfaces = $this->request->get('selected_interfaces');
        $backend = new Backend();
        $interfaces = [];

        $leases = json_decode($backend->configdpRun($this->configd_fetch_leases), true) ?? [];
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true);
        $mac_db = json_decode($backend->configdRun('interface list macdb'), true) ?? [];

        $ifmap = [];
        foreach (Config::getInstance()->object()->interfaces->children() as $if => $if_props) {
            $ifmap[(string)$if_props->if] = [
                'descr' => (string)$if_props->descr ?: strtoupper($if),
                'key' => $if
            ];
        }

        // Mark records as reserved based on hwaddr (IPv4) or duid (IPv6) match

        $resv4 = [];
        $resv6 = [];

        foreach ((new KeaDhcpv4())->reservations->reservation->iterateItems() as $reservation) {
            $resv4[strtolower((string)$reservation->hw_address)] = true;
        }

        foreach ((new KeaDhcpv6())->reservations->reservation->iterateItems() as $reservation) {
            $resv6[strtolower((string)$reservation->duid)] = true;
        }

        if (!empty($leases) && isset($leases['records'])) {
            $records = $leases['records'];
            foreach ($records as &$record) {
                // Interface
                $record['if_descr'] = '';
                $record['if_name'] = '';
                if (!empty($record['if']) && isset($ifmap[$record['if']])) {
                    $record['if_descr'] = $ifmap[$record['if']]['descr'];
                    $record['if_name'] = $ifmap[$record['if']]['key'];
                    $interfaces[$ifmap[$record['if']]['key']] = $ifmap[$record['if']]['descr'];
                }
                // Vendor
                $mac = strtoupper(substr(str_replace(':', '', $record['hwaddr']), 0, 6));
                $record['mac_info'] = isset($mac_db[$mac]) ? $mac_db[$mac] : '';
                // Reservation
                $addr = $record['address'] ?? '';
                if (strpos($addr, ':') !== false) {
                    $duid = strtolower($record['duid'] ?? '');
                    $record['is_reserved'] = isset($resv6[$duid]) ? '1' : '0';
                } else {
                    $mac_lower = strtolower($record['hwaddr'] ?? '');
                    $record['is_reserved'] = isset($resv4[$mac_lower]) ? '1' : '0';
                }
            }
        } else {
            $records = [];
        }

        $response = $this->searchRecordsetBase($records, null, 'address', function ($key) use ($selected_interfaces) {
            return empty($selected_interfaces) || in_array($key['if_name'], $selected_interfaces);
        });

        $response['interfaces'] = $interfaces;
        return $response;
    }
}
