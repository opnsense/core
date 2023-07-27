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

namespace OPNsense\DHCPv4\Api;

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
        $online = [];

        /* get ARP data to match on */
        $arp_data = json_decode($backend->configdRun('interface list arp json'), true);

        foreach ($arp_data as $arp_entry) {
            if (!$arp_entry['expired']) {
                array_push($online, $arp_entry['mac'], $arp_entry['ip']);
            }
        }

        /* get configured static leases */
        $sleases = json_decode($backend->configdRun('dhcpd list static 0'), true);

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
            $leases[$idx]['state'] = $lease['binding'] == 'free' ? 'expired' : $lease['binding'];

            if (array_key_exists('hardware', $lease)) {
                $mac = $lease['hardware']['mac-address'];
                $leases[$idx]['mac'] = $mac;
                $leases[$idx]['status'] = in_array(strtolower($lease['address']), $online) ? 'online' : 'offline';
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

        /* handle static leases */
        $statics = [];
        foreach ($sleases["dhcpd"] as $slease) {
            $static = [];
            $static['address'] = $slease['ipaddr'];
            $static['type'] = 'static';
            $static['mac'] = $slease['mac'];
            $static['starts'] = '';
            $static['ends'] = '';
            $static['hostname'] = $slease['hostname'];
            $static['descr'] = $slease['descr'];
            $static['if_descr'] = '';
            $static['if'] = $slease['interface'];
            $static['state'] = 'active';
            $static['status'] = in_array(strtolower($static['mac']), $online) ? 'online' : 'offline';
            $statics[] = $static;
        }

        $leases = array_merge($leases, $statics);

        $mac_man = json_decode($backend->configdRun('interface list macdb json'), true);
        $interfaces = [];

        /* fetch interfaces ranges so we can match leases to interfaces */
        $if_ranges = [];
        foreach ($config->dhcpd->children() as $dhcpif => $dhcpifconf) {
            $if = $config->interfaces->$dhcpif;
            if (!empty((string)$if->ipaddr) && !empty((string)$if->subnet)) {
                $if_ranges[$dhcpif] = (string)$if->ipaddr . '/' . (string)$if->subnet;
            }
        }

        foreach ($leases as $idx => $lease) {
            /* include manufacturer info */
            $leases[$idx]['man'] = '';
            if ($lease['mac'] != '') {
                $mac_hi = strtoupper(substr(str_replace(':', '', $lease['mac']), 0, 6));
                $leases[$idx]['man'] = $mac_man[$mac_hi];
            }

            /* include interface */
            $intf = '';
            $intf_descr = '';
            if (!empty($lease['if'])) {
                /* interface already included */
                $if = $config->interfaces->{$lease['if']};
                if (!empty((string)$if->ipaddr)) {
                    $intf = $lease['if'];
                    $intf_descr = (string)$if->descr ?: strtoupper($intf);
                }
            } else {
                /* interface not known, check range */
                foreach ($if_ranges as $if_name => $if_range) {
                    if (!empty($lease['address']) && Util::isIPInCIDR($lease['address'], $if_range)) {
                        $intf = $if_name;
                        $intf_descr = (string)$config->interfaces->$if_name->descr ?: strtoupper($if_name);
                        break;
                    }
                }
            }

            $leases[$idx]['if'] = $intf;
            $leases[$idx]['if_descr'] = $intf_descr;

            if (!empty($intf_descr) && !array_key_exists($intf, $interfaces)) {
                $interfaces[$intf] = $intf_descr;
            }
        }

        $response = $this->searchRecordsetBase($leases, null, 'address', function ($key) use ($selected_interfaces) {
            if (empty($selected_interfaces) || in_array($key['if'], $selected_interfaces)) {
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
