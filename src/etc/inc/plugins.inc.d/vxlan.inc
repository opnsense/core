<?php

/*
 * Copyright (C) 2019-2022 Deciso B.V.
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
        'newwanip' => array('vxlan_configure_do'),
        'vxlan' => array('vxlan_configure_do'),
    );
}

function vxlan_devices()
{
    $names = [];

    foreach (iterator_to_array((new \OPNsense\Interfaces\VxLan())->vxlan->iterateItems()) as $vxlan) {
        $names["vxlan{$vxlan->deviceId}"] = [
            'descr' => sprintf('vxlan%s %s (VNI %s)', $vxlan->deviceId, $vxlan->vxlanremote, $vxlan->vxlanid),
            'name' => "vxlan{$vxlan->deviceId}",
        ];
    }

    return [[
        'function' => 'vxlan_configure_device',
        'configurable' => true,
        'pattern' => '^vxlan',
        'volatile' => true,
        'type' => 'vxlan',
        'names' => $names,
    ]];
}

function vxlan_configure_do($verbose = false, $device = null)
{
    $cnf = OPNsense\Core\Config::getInstance()->object();
    $interfaces_details = legacy_interfaces_details();
    $configured_devices = array();
    $changed_devices = array();
    $vxlans = iterator_to_array((new \OPNsense\Interfaces\VxLan())->vxlan->iterateItems());

    if (!empty($vxlans)) {
        service_log(sprintf('Configuring VXLAN interface%s...', empty($device) ? 's' : " {$device}"), $verbose);
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
        $device_name = "vxlan{$vxlan->deviceId}";

        if ($device !== null && $device != $device_name) {
            $configured_devices[] = $device_name;
            continue;
        }

        if (!in_array((string)$vxlan->vxlanlocal, $known_addresses)) {
            // skip when interface address is not assigned (yet)
            continue;
        }

        $configured_devices[] = $device_name;
        $current_settings = [];

        if (empty($interfaces_details[$device_name])) {
            // new device
            mwexecf('/sbin/ifconfig vxlan create name %s', array($device_name));
            $isChanged = true;
        } else {
            $isChanged = false;
            $current_settings['vxlanid'] = $interfaces_details[$device_name]['vxlan']['vni'];
            foreach (['local', 'remote', 'group'] as $target) {
                if (!empty($interfaces_details[$device_name]['vxlan'][$target])) {
                    $tmp = explode(':', $interfaces_details[$device_name]['vxlan'][$target]);
                    $current_settings['vxlan' . $target] = $tmp[0];
                    $current_settings['vxlan' . $target . 'port'] = $tmp[1];
                }
            }
        }
        // gather settings, detect changes
        $ifcnfcmd = '/sbin/ifconfig %s';
        $ifcnfcmdp = array($device_name);
        foreach (
            [
            'vxlanid', 'vxlanlocal', 'vxlanremote', 'vxlanlocalport', 'vxlanremoteport', 'vxlangroup', 'vxlandev'
            ] as $param
        ) {
            $value = '';
            if ($param == 'vxlandev') {
                $intfnm = (string)$vxlan->$param;
                if (!empty($cnf->interfaces->$intfnm)) {
                    $value = (string)$cnf->interfaces->$intfnm->if;
                }
            } elseif (str_ends_with($param, 'port')) {
                $value = !empty((string)$vxlan->$param) ? (string)$vxlan->$param : '4789';
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

    // destroy nonexistent interfaces
    foreach ($interfaces_details as $intf => $data) {
        if (strpos($intf, "vxlan") === 0) {
            if (!in_array($intf, $configured_devices)) {
                mwexecf('/sbin/ifconfig %s destroy', array($intf));
            }
        }
    }

    if (!empty($vxlans)) {
        service_log("done.\n", $verbose);
    }

    // configure attached interface when devices have changed
    interfaces_restart_by_device($verbose, $changed_devices);
}

function vxlan_configure_device($device)
{
    vxlan_configure_do(false, $device);
}
