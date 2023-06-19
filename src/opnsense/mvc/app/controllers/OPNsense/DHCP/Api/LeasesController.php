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

namespace OPNsense\DHCP\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class LeasesController extends ApiControllerBase
{
    public function searchLeaseAction()
    {
        $this->sessionClose();
        $inactive = $this->request->get('inactive');
        $selected_interfaces = $this->request->get('selected_interfaces');
        $backend = new Backend();
        $config = Config::getInstance()->object();
        $arp_macs = [];
        $arp_ips = [];

        /* get ARP data to match on */
        $arp_data = json_decode($backend->configdRun('interface list arp json'), true);

        foreach ($arp_data as $arp_entry) {
            if ($arp_entry['expired'] == false) {
                $arp_macs[] = strtolower($arp_entry['mac']);
                $arp_ips[] = $arp_entry['ip'];
            }
        }

        $onlineStatus = function($val, $use_mac=false) use ($arp_macs, $arp_ips) {
            $online = false;
            if ($use_mac) {
                $online = in_array(strtolower($val), $arp_macs);
            } else {
                $online = in_array($val, $arp_ips);
            }

            return $online ? 'online' : 'offline';
        };

        /* get configured static leases */
        $sleases = json_decode($backend->configdRun('dhcpd list static'), true);

        /* include inactive leases if requested */
        $leases = json_decode($backend->configdpRun('dhcpd list leases', [$inactive]), true);
        foreach ($leases as $idx => $lease) {
            /* set defaults */
            $leases[$idx]['type'] = 'dynamic';
            $leases[$idx]['status'] = 'offline';
            $leases[$idx]['descr'] = '';
            $leases[$idx]['mac'] = '';
            $leases[$idx]['starts'] = '';
            $leases[$idx]['ends'] = '';
            $leases[$idx]['hostname'] = '';

            switch ($lease['binding']) {
                case 'active':
                    $leases[$idx]['state'] = 'active';
                    break;
                case 'free':
                    $leases[$idx]['state'] = 'expired';
                    break;
                case 'backup':
                    $leases[$idx]['state'] = 'backup';
                    break;
            }

            if (array_key_exists('hardware', $lease)) {
                $mac = $lease['hardware']['mac-address'];
                $leases[$idx]['mac'] = $mac;
                $leases[$idx]['status'] = $onlineStatus($lease['address']);
                unset($leases[$idx]['hardware']);
            }

            if (array_key_exists('starts', $lease)) {
                $leases[$idx]['starts'] = date('Y/m/d H:i:s', $lease['starts']);
            }

            if (array_key_exists('ends', $lease)) {
                $leases[$idx]['ends'] = date('Y/m/d H:i:s', $lease['ends']);
            }

            if (array_key_exists('client-hostname', $lease)) {
                $leases[$idx]['hostname'] = $lease['client-hostname'];
            }
        }

        $macs = [];
        foreach ($leases as $idx => $lease) {
            if (!empty($lease['mac'])) {
                if (!isset($macs[$lease['mac']])) {
                    $macs[$lease['mac']] = [];
                }
                $macs[$lease['mac']][] = $idx;
            }
        }

        /* handle static leases */
        foreach ($sleases as $slease) {
            $static = [];
            $static['address'] = $slease['ipaddr'];
            $static['type'] = 'static';
            $static['mac'] = $slease['mac'];
            $static['starts'] = '';
            $static['ends'] = '';
            $static['hostname'] = $slease['hostname'];
            $static['descr'] = $slease['descr'];
            $static['state'] = 'active';
            $static['status'] = $onlineStatus($static['mac'], true);

            if (isset($macs[$static['mac']])) {
                foreach ($slease as $key => $value) {
                    if (!empty($value)) {
                        foreach ($macs[$slease['mac']] as $idx) {
                            $leases[$idx][$key] = $value;
                        }
                    }
                }
            } else {
                $leases[] = $static;
            }
        }

        $mac_man = json_decode($backend->configdRun('interface list macdb json'), true);
        $interfaces = [];
        foreach ($leases as $idx => $lease) {
            /* include manufacturer info */
            $leases[$idx]['man'] = '';
            if ($lease['mac'] != '') {
                $mac_hi = strtoupper($lease['mac'][0] . $lease['mac'][1] . $lease['mac'][3] .
                    $lease['mac'][4] . $lease['mac'][6] . $lease['mac'][7]);
                $leases[$idx]['man'] = $mac_man[$mac_hi];
            }

            /* include interface */
            $leases[$idx]['int'] = '';
            $leases[$idx]['if'] = '';
            foreach ($config->dhcpd->children() as $dhcpif => $dhcpifconf) {
                $if = $config->interfaces->$dhcpif;
                if (!empty((string)$if->ipaddr) && Util::isIpAddress((string)$if->ipaddr)) {
                    if (Util::isIPInCIDR($lease['address'], (string)$if->ipaddr . '/' . (string)$if->subnet)) {
                        $intf = (string)$if->descr;
                        $leases[$idx]['interface'] = $intf;
                        $leases[$idx]['if'] = $dhcpif;

                        if (!array_key_exists($dhcpif, $interfaces)) {
                            $interfaces[$dhcpif] = $intf;
                        }
                    }
                }
            }
        }

        $response = $this->searchRecordsetBase($leases, null, 'address', function ($key) use ($selected_interfaces) {
            if (empty($selected_interfaces)) {
                return true;
            }

            if (in_array($key['if'], $selected_interfaces)) {
                return true;
            }

            return false;
        });

        /* present relevant interfaces to the view so they can be sorted on */
        $response['interfaces'] = $interfaces;
        return $response;
    }

    public function delLeaseAction($ip)
    {
        $result = ["result" => "failed"];

        if ($this->request->isPost()) {
            $this->sessionClose();
            $response = json_decode((new Backend())->configdpRun("dhcpd remove lease", [$ip]), true);
            if ($response["removed_leases"] != "0") {
                $result["result"] = "deleted";
            }
        }


        return $result;
    }
}
