<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

function vxlan_configure()
{
    return array(
        'vxlan_prepare' => array('vxlan_configure_interface'),
        'newwanip' => array('vxlan_configure_interface'),
    );
}

function vxlan_devices()
{
    $devices = array();

    $devices[] = array('pattern' => '^vxlan', 'volatile' => true, 'configurable' => true);

    return $devices;
}

function vxlan_configure_interface($verbose = false)
{
    $cnf = OPNsense\Core\Config::getInstance()->object();
    $interfaces_details = legacy_interfaces_details();
    $configured_devices = array();
    $changed_devices = array();
    $vxlans = iterator_to_array((new \OPNsense\Interfaces\VxLan())->vxlan->iterateItems());
    if ($verbose && !empty($vxlans)) {
        echo 'Configuring VXLAN interfaces...';
        flush();
    }
    $all_addresses = array();
    $known_addresses = array();
    foreach ($interfaces_details as $intf) {
        foreach (['ipv4', 'ipv6'] as $ipproto) {
            if (!empty($intf[$ipproto])) {
                foreach ($intf[$ipproto] as $net) {
                    $known_addresses[] = $net['ipaddr'];
                }
            }
        }
    }

    // (re)configure vxlan devices
    foreach ($vxlans as $vxlan) {
        if (!in_array((string)$vxlan->vxlanlocal, $known_addresses)) {
            // skip when interface address is not assigned (yet)
            continue;
        }
        $device_name = "vxlan{$vxlan->deviceId}";
        $configured_devices[] = $device_name;
        $current_settings = array(
            "vxlanid" => null,
            "vxlanlocal" => null,
            "vxlanremote" => null,
            "vxlangroup" => null
        );
        if (empty($interfaces_details[$device_name])) {
            // new device
            mwexecf('/sbin/ifconfig vxlan create name %s', array($device_name));
            $isChanged = true;
        } else {
            $isChanged = false;
            $current_settings['vxlanid'] = $interfaces_details[$device_name]['vxlan']['vni'];
            $current_settings['vxlanlocal'] = explode(":", $interfaces_details[$device_name]['vxlan']['local'])[0];
            $current_settings['vxlanremote'] = explode(":", $interfaces_details[$device_name]['vxlan']['remote'])[0];
            $current_settings['vxlangroup'] = explode(":", $interfaces_details[$device_name]['vxlan']['group'])[0];
        }
        // gather settings, detect changes
        $ifcnfcmd = '/sbin/ifconfig %s';
        $ifcnfcmdp = array($device_name);
        foreach (array('vxlanid', 'vxlanlocal', 'vxlanremote', 'vxlangroup', 'vxlandev') as $param) {
            $value = '';
            if ($param == 'vxlandev') {
                $intfnm = (string)$vxlan->$param;
                if (!empty($cnf->interfaces->$intfnm)) {
                    $value = (string)$cnf->interfaces->$intfnm->if;
                }
            } else {
                $value = (string)$vxlan->$param;
            }
            if ($value != '') {
                $ifcnfcmd .= " {$param} %s ";
                $ifcnfcmdp[] = $value;
            }
            if (isset($current_settings[$param]) && $current_settings[$param] != $value) {
                $isChanged = true;
            }
        }
        if ($isChanged) {
            mwexecf($ifcnfcmd, $ifcnfcmdp);
            $changed_devices[] = $device_name;
        }
    }
    // destroy non existing interfaces
    foreach ($interfaces_details as $intf => $data) {
        if (strpos($intf, "vxlan") === 0) {
            if (!in_array($intf, $configured_devices)) {
                mwexecf('/sbin/ifconfig %s destroy', array($intf));
            }
        }
    }
    if ($verbose && !empty($vxlans)) {
        echo "done.\n";
    }
    // configure interface when device has changed
    foreach ($changed_devices as $device_name) {
        $friendly_if = convert_real_interface_to_friendly_interface_name($device_name);
        if (!empty($friendly_if)) {
            interface_configure($verbose, $friendly_if);
        }
    }
}
