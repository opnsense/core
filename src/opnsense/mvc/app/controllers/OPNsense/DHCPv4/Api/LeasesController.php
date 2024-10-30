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
        $inactive = $this->request->get('inactive');
        $selected_interfaces = $this->request->get('selected_interfaces');
        $backend = new Backend();
        $config = Config::getInstance()->object();
        $online = [];
        $if_devs = [];
        $if_descrs = [];
        $ip_ranges = [];
        $interfaces = [];

        /* get ARP data to match online clients */
        $arp_data = json_decode($backend->configdRun('dhcpd list arp'), true) ?? [];
        /* get static leases */
        $sleases = json_decode($backend->configdRun('dhcpd list static 0'), true) ?? [];
        /* get dynamic leases, include inactive leases if requested */
        $leases = json_decode($backend->configdpRun('dhcpd list leases', [$inactive]), true) ?? [];
        /* get manufacturer info */
        $mac_man = json_decode($backend->configdRun('interface list macdb'), true) ?? [];
        /* get ifconfig info to match IPs to interfaces */
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true) ?? [];

        /* get all device names and their associated interface names */
        foreach ($config->interfaces->children() as $if => $if_props) {
            $if_devs[$if] = (string)$if_props->if;
            $if_descrs[$if] = (string)$if_props->descr ?: strtoupper($if);
        }

        /* list online IPs and MACs */
        if (is_array($arp_data) && isset($arp_data['arp']) && !empty($arp_data['arp']['arp-cache'])) {
            foreach ($arp_data['arp']['arp-cache'] as $arp_entry) {
                if (!isset($arp_entry['expired'])) {
                    array_push($online, $arp_entry['mac-address'], $arp_entry['ip-address']);
                }
            }
        }

        /* gather ip ranges from ifconfig */
        foreach ($ifconfig as $if => $data) {
            if (!empty($data['ipv4'])) {
                foreach ($data['ipv4'] as $ip) {
                    if (!empty($ip['ipaddr']) && !empty($ip['subnetbits'])) {
                        $ip_ranges[$ip['ipaddr'] . '/' . $ip['subnetbits']] = $if;
                    }
                }
            }
        }

        /* parse dynamic leases */
        foreach ($leases as $idx => $lease) {
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

        /* parse static leases */
        $statics = [];
        if ($sleases) {
            foreach ($sleases["dhcpd"] as $slease) {
                $static = [];
                $static['address'] = $slease['ipaddr'] ?? '';
                $static['type'] = 'static';
                $static['mac'] = $slease['mac'] ?? '';
                $static['starts'] = '';
                $static['ends'] = '';
                $static['hostname'] = $slease['hostname'] ?? '';
                $static['descr'] = $slease['descr'] ?? '';
                $static['if_descr'] = '';
                $static['if'] = $slease['interface'] ?? '';
                $static['state'] = 'active';
                $static['status'] = in_array(strtolower($static['mac']), $online) ? 'online' : 'offline';
                $statics[] = $static;
            }
        }

        /* merge dynamic and static leases */
        $leases = array_merge($leases, $statics);

        foreach ($leases as $idx => $lease) {
            /* include manufacturer info */
            $leases[$idx]['man'] = '';
            if ($lease['mac'] != '') {
                $mac_hi = strtoupper(substr(str_replace(':', '', $lease['mac']), 0, 6));
                if (array_key_exists($mac_hi, $mac_man)) {
                    $leases[$idx]['man'] = $mac_man[$mac_hi];
                }
            }

            /* include interface */
            $intf = '';
            $intf_descr = '';

            if (!empty($lease['if'])) {
                /* interface already included */
                $intf = $lease['if'];
                $intf_descr = $if_descrs[$intf];
            } else {
                /* interface not known, check range */
                foreach ($ip_ranges as $cidr => $if_dev) {
                    if (!empty($lease['address']) && Util::isIPInCIDR($lease['address'], $cidr)) {
                        $intf = array_search($if_dev, $if_devs);
                        $intf_descr = $if_descrs[$intf];
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

        /* present relevant interfaces to the view so they can be filtered on */
        $response['interfaces'] = $interfaces;
        return $response;
    }

    public function delLeaseAction($ip)
    {
        $result = ["result" => "failed"];

        if ($this->request->isPost()) {
            $response = json_decode((new Backend())->configdpRun("dhcpd remove lease", [$ip]), true);
            if ($response["removed_leases"] != "0") {
                $result["result"] = "deleted";
            }
        }


        return $result;
    }
}
