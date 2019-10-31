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
         'vxlan_prepare' => array('vxlan_configure_interface')
     );
 }


function vxlan_configure_interface()
{
    $cnf = OPNsense\Core\Config::getInstance()->object();
    $interface_details = legacy_interfaces_details();
    $configured_devices = array();

    // (re)configure vxlan devices
    foreach ((new \OPNsense\Interfaces\VxLan())->vxlans->vxlan->iterateItems() as $vxlan) {
        $device_name = "vxlan.{$vxlan->deviceId}" ;
        $configured_devices[] = $device_name;
        if (empty($interface_details[$device_name])) {
            // new device
            mwexecf('/sbin/ifconfig vxlan create name %s', array($device_name));
        }
        // gather settings
        // XXX: it might be necessary to compare with current settings to avoid a reconfigure
        $ifcnfcmd = '/sbin/ifconfig %s';
        $ifcnfcmdp = array($device_name);
        foreach (array('vxlanid', 'vxlanlocal', 'vxlanremote', 'vxlangroup', 'vxlandev') as $param) {
            $value = '';
            if ($param == 'vxlandev') {
                $intfnm = (string)$vxlan->$param;
                if (!empty($cnf->interfaces->$intfnm)) {
                    $value = $cnf->interfaces->$intfnm->if->__toString();
                }
            } else {
                $value = (string)$vxlan->$param;
            }
            if ($value != '') {
                $ifcnfcmd .= " {$param} %s ";
                $ifcnfcmdp[] = $value;
            }
        }
        mwexecf($ifcnfcmd, $ifcnfcmdp);
    }
    // destroy non existing interfaces
    foreach ($interface_details as $intf => $data) {
        if (strpos($intf, "vxlan.") === 0) {
            if (!in_array($intf, $configured_devices)) {
                mwexecf('/sbin/ifconfig %s destroy', array($intf));
            }
        }
    }
}
