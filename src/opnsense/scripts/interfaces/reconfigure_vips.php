#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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
$anyproxyarp = false;
foreach (legacy_interfaces_details() as $ifname => $ifcnf) {
    foreach (['ipv4', 'ipv6'] as $proto) {
        if (!empty($ifcnf[$proto])) {
            foreach ($ifcnf[$proto] as $address) {
                $addresses[$address['ipaddr']] = [
                    'subnetbits' => $address['subnetbits'],
                    'if' => $ifname,
                    'vhid' => $address['vhid'] ?? '',
                    'advbase' => '',
                    'advskew' => ''
                ];
                if (!empty($address['vhid'])) {
                    foreach ($ifcnf['carp'] as $vhid) {
                        if ($vhid['vhid'] == $address['vhid']) {
                            $addresses[$address['ipaddr']]['advbase'] = $vhid['advbase'];
                            $addresses[$address['ipaddr']]['advskew'] = $vhid['advskew'];
                        }
                    }
                }
            }
        }
    }
}

// remove deleted vips
foreach (glob("/tmp/delete_vip_*.todo") as $filename) {
    $address = trim(file_get_contents($filename));
    if (isset($addresses[$address])) {
        legacy_interface_deladdress($addresses[$address]['if'], $address, is_ipaddrv6($address) ? 6 : 4);
    } else {
        // not found, likely proxy arp
        $anyproxyarp = true;
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
            $subnet_bits = $vipent['subnet_bits'];
            $vhid = $vipent['vhid'] ?? '';
            $advbase = !empty($vipent['vhid']) ? $vipent['advbase'] : '';
            $advskew = !empty($vipent['vhid']) ? $vipent['advskew'] : '';
            if ($vipent['mode'] == 'proxyarp') {
                $anyproxyarp = true;
            }
            if (in_array($vipent['mode'], ['proxyarp', 'other'])) {
                if (isset($addresses[$subnet])) {
                    legacy_interface_deladdress($addresses[$subnet]['if'], $subnet, is_ipaddrv6($subnet) ? 6 : 4);
                }
                continue;
            } elseif (
                isset($addresses[$subnet]) &&
                $addresses[$subnet]['subnetbits'] == $subnet_bits &&
                $addresses[$subnet]['if'] == $if &&
                $addresses[$subnet]['vhid'] == $vhid &&
                $addresses[$subnet]['advbase'] == $advbase &&
                $addresses[$subnet]['advskew'] == $advskew
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

if ($anyproxyarp) {
    interface_proxyarp_configure();
}
