#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2022-2024 Deciso B.V.
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

require_once("config.inc");
require_once("filter.inc");
require_once("interfaces.inc");
require_once("util.inc");

$addresses = [];
$proxyarp = false;

foreach (legacy_interfaces_details() as $ifname => $ifcnf) {
    foreach (['ipv4', 'ipv6'] as $proto) {
        if (!empty($ifcnf[$proto])) {
            foreach ($ifcnf[$proto] as $address) {
                $key = $address['ipaddr'];
                if ($proto == 'ipv6' && $address['link-local']) {
                    $key .= '%' . $ifname;
                }
                $addresses[$key] = [
                    'subnetbits' => $address['subnetbits'],
                    'if' => $ifname,
                    'vhid' => $address['vhid'] ?? '',
                    'advbase' => '',
                    'advskew' => '',
                    'peer' => '',
                    'peer6' => '',
                ];
                if (!empty($address['vhid'])) {
                    foreach ($ifcnf['carp'] as $vhid) {
                        if ($vhid['vhid'] == $address['vhid']) {
                            $addresses[$key]['advbase'] = $vhid['advbase'];
                            $addresses[$key]['advskew'] = $vhid['advskew'];
                            $addresses[$key]['peer'] = !empty($vhid['peer']) ? $vhid['peer'] : '224.0.0.18';
                            $addresses[$key]['peer6'] = !empty($vhid['peer6']) ? $vhid['peer6'] : 'ff02::12';
                        }
                    }
                }
            }
        }
    }
}

// remove deleted vips
foreach (glob("/tmp/delete_vip_*.todo") as $filename) {
    foreach (array_unique(explode("\n", trim(file_get_contents($filename)))) as $address) {
        /* '@' designates an IPv6 link-local scope, but not on network device */
        if (strpos($address, '@') !== false) {
             list($address, $interface) = explode('@', $address);
             /* translate to what ifconfig will understand */
             $address .= '%' . get_real_interface($interface, 'inet6');
        }
        if (isset($addresses[$address])) {
            legacy_interface_deladdress($addresses[$address]['if'], $address, is_ipaddrv6($address) ? 6 : 4);
        } else {
            // not found, likely proxy arp
            $proxyarp = true;
        }
    }
    unlink($filename);
}

// diff model and actual ifconfig
if (!empty($config['virtualip']['vip'])) {
    $interfaces = [];
    foreach (legacy_config_get_interfaces() as $interfaceKey => $itf) {
        if (!empty($itf['if']) && ($itf['type'] ?? '') != 'group') {
            $interfaces[$interfaceKey] = $itf['if'];
        }
    }
    foreach ($config['virtualip']['vip'] as $vipent) {
        if (!empty($vipent['interface']) && !empty($interfaces[$vipent['interface']])) {
            $if = $interfaces[$vipent['interface']];
            $subnet = $vipent['subnet'];
            if (is_linklocal($subnet)) {
                $subnet .= '%' . get_real_interface($if, 'inet6');
            }
            $subnet_bits = $vipent['subnet_bits'];
            $vhid = $vipent['vhid'] ?? '';
            $advbase = !empty($vipent['vhid']) ? $vipent['advbase'] : '';
            $advskew = !empty($vipent['vhid']) ? $vipent['advskew'] : '';
            $peer = !empty($vipent['peer']) ? $vipent['peer'] : '224.0.0.18';
            $peer6 = !empty($vipent['peer6']) ? $vipent['peer6'] : 'ff02::12';
            if ($vipent['mode'] == 'proxyarp') {
                $proxyarp = true;
            }
            if (in_array($vipent['mode'], ['proxyarp', 'other'])) {
                if (isset($addresses[$subnet])) {
                    legacy_interface_deladdress($addresses[$subnet]['if'], $subnet, is_ipaddrv6($subnet) ? 6 : 4);
                }
                continue;
            } elseif (
                $vipent['mode'] == 'ipalias' &&
                isset($addresses[$subnet]) &&
                $addresses[$subnet]['subnetbits'] == $subnet_bits &&
                $addresses[$subnet]['if'] == $if &&
                $addresses[$subnet]['vhid'] == $vhid
            ) {
                // configured and found equal
                continue;
            } elseif (
                isset($addresses[$subnet]) &&
                $addresses[$subnet]['subnetbits'] == $subnet_bits &&
                $addresses[$subnet]['if'] == $if &&
                $addresses[$subnet]['vhid'] == $vhid &&
                $addresses[$subnet]['advbase'] == $advbase &&
                $addresses[$subnet]['advskew'] == $advskew &&
                $addresses[$subnet]['peer'] == $peer &&
                $addresses[$subnet]['peer6'] == $peer6
            ) {
                // configured and found equal
                continue;
            }
            // default configure action depending on type
            switch ($vipent['mode']) {
                case 'ipalias':
                    interface_ipalias_configure($vipent);
                    break;
                case 'carp':
                    interface_carp_configure($vipent);
                    break;
            }
        }
    }
}

if ($proxyarp) {
    interface_proxyarp_configure();
}
