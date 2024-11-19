<?php

/*
* Copyright (C) 2023 Deciso B.V.
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

class Leases4Controller extends ApiControllerBase
{
    public function searchAction()
    {
        $selected_interfaces = $this->request->get('selected_interfaces');
        $backend = new Backend();
        $interfaces = [];

        $leases = json_decode($backend->configdpRun('kea list leases4'), true) ?? [];
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true);
        $mac_db = json_decode($backend->configdRun('interface list macdb'), true) ?? [];

        // Get current reservations to check status
        $model = new KeaDhcpv4();
        $reservations = [];
        foreach ($model->reservations->reservation->iterateItems() as $reservation) {
            $reservations[strtolower((string)$reservation->hw_address)] = true;
        }

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
                // Add reservation status
                $record['is_reserved'] = isset($reservations[strtolower($record['hwaddr'])]);
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

    public function addReservationAction()
    {
        if ($this->request->isPost()) {
            $mac = $this->request->getPost('mac');
            $ip = $this->request->getPost('ip');
            $hostname = $this->request->getPost('hostname');

            if (empty($mac) || empty($ip)) {
                return ['status' => 'error', 'message' => 'Missing required fields'];
            }

            // Get model and check for existing reservation
            $model = new KeaDhcpv4();
            foreach ($model->reservations->reservation->iterateItems() as $reservation) {
                if (strtolower((string)$reservation->hw_address) === strtolower($mac)) {
                    return ['status' => 'error', 'message' => 'MAC address already reserved'];
                }
            }

            // Find matching subnet
            $subnet_uuid = null;
            foreach ($model->subnets->subnet4->iterateItems() as $key => $subnet) {
                if (\OPNsense\Firewall\Util::isIPInCIDR($ip, (string)$subnet->subnet)) {
                    $subnet_uuid = $key;
                    break;
                }
            }

            if ($subnet_uuid === null) {
                return ['status' => 'error', 'message' => 'No matching subnet found'];
            }

            // Add new reservation
            $node = $model->reservations->reservation->Add();
            $node->subnet = $subnet_uuid;
            $node->ip_address = $ip;
            $node->hw_address = $mac;
            if (!empty($hostname)) {
                $node->hostname = $hostname;
            }

            // Validate and save model
            $valMsgs = $model->performValidation();
            if (count($valMsgs) > 0) {
                return ['status' => 'error', 'message' => implode(', ', $valMsgs)];
            }

            // Save config if validated
            if ($model->serializeToConfig()) {
                Config::getInstance()->save();
                

                return [
                    'status' => 'ok',
                    'message' => 'Reservation added successfully. Please apply changes.'
                ];
            }
            
            return ['status' => 'error', 'message' => 'Failed to save configuration'];
        }
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
}