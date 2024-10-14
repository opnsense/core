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

 namespace OPNsense\DHCPv6\Api;

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
        $leases = [];
        $interfaces = [];

        /* get NDP data to match online clients */
        $ndp_data = json_decode($backend->configdRun('interface list ndp json'), true);
        /* get static leases */
        $sleases = json_decode($backend->configdRun('dhcpd6 list static 0'), true);
        /* get dynamic leases, inactive leases if requested */
        $raw_leases = json_decode($backend->configdpRun('dhcpd6 list leases', [$inactive]), true);
        /* get manufacturer info */
        $mac_man = json_decode($backend->configdRun('interface list macdb'), true);
        /* get ifconfig info to match IPs to interfaces */
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true);

        /* get all device names and their associated interface names */
        foreach ($config->interfaces->children() as $if => $if_props) {
            $if_devs[$if] = (string)$if_props->if;
            $if_descrs[$if] = (string)$if_props->descr ?: strtoupper($if);
        }

        /* list online IPs and MACs */
        foreach ($ndp_data as $ndp_entry) {
            array_push($online, $ndp_entry['mac'], $ndp_entry['ip']);
        }

        /* gather ip ranges from ifconfig */
        foreach ($ifconfig as $if => $data) {
            if (!empty($data['ipv6'])) {
                foreach ($data['ipv6'] as $ip) {
                    if (!empty($ip['ipaddr']) && !empty($ip['subnetbits'])) {
                        $ip_ranges[$ip['ipaddr'] . '/' . $ip['subnetbits']] = $if;
                    }
                }
            }
        }

        foreach ($raw_leases as $raw_lease) {
            if (!array_key_exists('addresses', $raw_lease)) {
                continue;
            }

            /* set defaults */
            $lease = [];
            $lease['type'] = 'dynamic';
            $lease['lease_type'] = $raw_lease['lease_type'];
            $lease['iaid'] = $raw_lease['iaid'];
            $lease['duid'] = $raw_lease['duid'];
            $lease['iaid_duid'] = $raw_lease['iaid_duid'];
            $lease['descr'] = '';
            $lease['if'] = '';

            if (array_key_exists('cltt', $raw_lease)) {
                $lease['cltt'] = date('Y/m/d H:i:s', $raw_lease['cltt']);
            }

            /* XXX we pick the first address, this will be fine for a typical deployment
            * according to RFC8415 section 6.6, but it should be noted that the protocol
            * (and isc-dhcpv6) is capable of handing over multiple addresses/prefixes to
            * a single client within a single lease. The backend accounts for this.
            */
            $seg = $raw_lease['addresses'][0];
            $lease['state'] = $seg['binding'] == 'free' ? 'expired' : $seg['binding'];
            if (array_key_exists('ends', $seg)) {
                $lease['ends'] =  date('Y/m/d H:i:s', $seg['ends']);
            }

            $lease['address'] = $seg['iaaddr'];
            $lease['status'] = in_array(strtolower($lease['address']), $online) ? 'online' : 'offline';
            $leases[] = $lease;
        }

        $statics = [];
        if ($sleases) {
            foreach ($sleases['dhcpd'] as $slease) {
                $static = [
                    'address' => $slease['ipaddrv6'] ?? '',
                    'type' => 'static',
                    'cltt' => '',
                    'ends' => '',
                    'descr' => $slease['descr'] ?? '',
                    'iaid' => '',
                    'duid' => $slease['duid'] ?? '',
                    'if_descr' => '',
                    'if' => $slease['interface'] ?? '',
                    'state' => 'active',
                    'status' => in_array(strtolower($slease['ipaddrv6']), $online) ? 'online' : 'offline'
                ];
                $statics[] = $static;
            }
        }

        /* merge dynamic and static leases */
        $leases = array_merge($leases, $statics);

        foreach ($leases as $idx => $lease) {
            $leases[$idx]['man'] = '';
            $leases[$idx]['mac'] = '';
            $done = false;
            /* We infer the MAC from NDP data if available, otherwise we extract it out
             * of the DUID. However, RFC8415 section 11 states that an attempt to parse
             * a DUID to obtain a client's link-layer address is unreliable, as there is no
             * guarantee that the client is still using the same link-layer address as when
             * it generated its DUID. Therefore, if we can link it to a manufacturer, chances
             * are fairly high that this is a valid MAC address, otherwise we omit the MAC
             * address.
             */
            if (!empty(['address'])) {
                foreach ($ndp_data as $ndp) {
                    if ($ndp['ip'] == $lease['address']) {
                        $leases[$idx]['mac'] = $ndp['mac'];
                        $leases[$idx]['man'] = empty($ndp['manufacturer']) ? '' : $ndp['manufacturer'];
                        $leases[$idx]['if'] = array_search($ndp['intf'], $if_devs);
                        $leases[$idx]['if_descr'] = $if_descrs[$leases[$idx]['if']];
                        if (!empty($leases[$idx]['if_descr'])) {
                            $interfaces[$leases[$idx]['if']] = $leases[$idx]['if_descr'];
                        }
                        $done = true;
                        break;
                    }
                }
                if ($done) {
                    continue;
                }
            }

            /* include MAC */
            if (!empty($lease['duid'])) {
                $mac = '';
                $duid_type = substr($lease['duid'], 0, 5);
                if ($duid_type === "00:01" || $duid_type === "00:03") {
                    /* DUID generated based on LL addr with or without timestamp */
                    $hw_type = substr($lease['duid'], 6, 5);
                    if ($hw_type == "00:01") { /* HW type ethernet */
                        $mac = substr($lease['duid'], -17, 17);
                    }
                }

                if (!empty($mac)) {
                    $mac_hi = strtoupper(substr(str_replace(':', '', $mac), 0, 6));
                    if (array_key_exists($mac_hi, $mac_man)) {
                        $leases[$idx]['mac'] = $mac;
                        $leases[$idx]['man'] = $mac_man[$mac_hi];
                    }
                }
            }

            /* include interface */
            $intf = '';
            $intf_descr = '';
            if (!empty($lease['if'])) {
                $intf = $lease['if'];
                $intf_descr = $if_descrs[$intf];
            } else {
                foreach ($ip_ranges as $cidr => $if) {
                    if (!empty($lease['address']) && Util::isIPInCIDR($lease['address'], $cidr)) {
                        $intf = array_search($if, $if_devs);
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
        }, SORT_REGULAR);

        $response['interfaces'] = $interfaces;
        return $response;
    }

    public function searchPrefixAction()
    {
        $backend = new Backend();
        $prefixes = [];

        $raw_leases = json_decode($backend->configdpRun('dhcpd6 list leases 1'), true);
        foreach ($raw_leases as $raw_lease) {
            if ($raw_lease['lease_type'] === 'ia-pd' && array_key_exists('prefixes', $raw_lease)) {
                $prefix = [];
                $prefix['lease_type'] = $raw_lease['lease_type'];
                $prefix['iaid'] = $raw_lease['iaid'];
                $prefix['duid'] = $raw_lease['duid'];
                if (array_key_exists('cltt', $raw_lease)) {
                    $prefix['cltt'] = date('Y/m/d H:i:s', $raw_lease['cltt']);
                }

                $prefix_raw = $raw_lease['prefixes'][0];
                $prefix['prefix'] = $prefix_raw['iaprefix'];
                if (array_key_exists('ends', $prefix_raw)) {
                    $prefix['ends'] =  date('Y/m/d H:i:s', $prefix_raw['ends']);
                }
                $prefix['state'] = $prefix_raw['binding'] == 'free' ? 'expired' : $prefix_raw['binding'];
                $prefixes[] = $prefix;
            }
        }

        return $this->searchRecordsetBase($prefixes, null, 'prefix', null, SORT_REGULAR);
    }

    public function delLeaseAction($ip)
    {
        $result = ["result" => "failed"];

        if ($this->request->isPost()) {
            $response = json_decode((new Backend())->configdpRun("dhcpd6 remove lease", [$ip]), true);
            if ($response["removed_leases"] != "0") {
                $result["result"] = "deleted";
            }
        }

        return $result;
    }
}
