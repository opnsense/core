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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class Leases4Controller extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'dhcpv4';
    protected static $internalModelClass = 'OPNsense\Kea\KeaDhcpv4';

    public function searchAction()
    {
        $selected_interfaces = $this->request->get('selected_interfaces');
        $backend = new Backend();
        $interfaces = [];

        $leases = json_decode($backend->configdpRun('kea list leases4'), true) ?? [];
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true);

        $ifmap = [];
        foreach (Config::getInstance()->object()->interfaces->children() as $if => $if_props) {
            $ifmap[(string)$if_props->if] = [
                'descr' => (string)$if_props->descr ?: strtoupper($if),
                'key' => $if
            ];
        }

        $current_reservations = $this->searchBase("reservations.reservation", null, "reservation")['rows'];
        if (!empty($leases) && isset($leases['records'])) {
            $records = $leases['records'];
            foreach ($records as &$record) {
                $record['if_descr'] = '';
                $record['if_name'] = '';
                if (!empty($record['if']) && isset($ifmap[$record['if']])) {
                    $record['if_descr'] = $ifmap[$record['if']]['descr'];
                    $record['if_name'] = $ifmap[$record['if']]['key'];
                    $record['address'] = trim($record['address']);
                    $interfaces[$ifmap[$record['if']]['key']] = $ifmap[$record['if']]['descr'];

		    // check if this lease has a reservation
		    foreach ($current_reservations as $reservation) {
                        if (in_array($record['address'], $reservation)) {
                            $record['reservation.uuid'] = $reservation['uuid'];
                            $record['reserved'] = true;
			}
		    }
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

    public function addReservationAction()
    {
        $reservation = $this->request->getPost('reservation');

        $result = $this->addBase("reservation", "reservations.reservation");
        if ($result['result'] === 'failed') {
            return $result;
        }

        $svc = new ServiceController();
        $svc->request = $this->request;

        return $svc->reconfigureAction();
    }

    public function getLeaseAction($uuid = null)
    {
        $res = $this->getBase("reservation", "reservations.reservation", $uuid);

        // new lease
        if (empty($res['reservation']['ip_address'])) {
            $lease_ip = $this->request->get('ip_addr');
            $lease_hw = $this->request->get('hw_addr');

            if (empty($lease_ip) || empty($lease_hw)) {
                return array('result' => 'failed');
            }

            $res['reservation']['ip_address'] = $lease_ip;
            $res['reservation']['hw_address'] = $lease_hw;

            foreach($res['reservation']['subnet'] as $subnetkey => $subnetarr) {
                if (Util::isIPInCIDR($lease_ip, $subnetarr['value'])) {
                    $res['reservation']['subnet'][$subnetkey]['selected'] = 1;
                    break;
                }
            }
        }

        return $res;
    }

    public function delReservationAction($uuid)
    {
        $result = $this->delBase("reservations.reservation", $uuid);
        if ($result['result'] !== 'deleted') {
            return $result;
        }

        $svc = new ServiceController();
        $svc->request = $this->request;

        return $svc->reconfigureAction();
    }

    public function setReservationAction($uuid)
    {
        return $this->setBase("reservation", "reservations.reservation", $uuid);
    }
}
