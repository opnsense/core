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

function loopback_configure()
{
    return array(
        'loopback_prepare' => array('loopback_configure_interface')
    );
}

function loopback_devices()
{
    $devices = array();

    $devices[] = array('pattern' => '^lo', 'volatile' => true, 'configurable' => true);

    return $devices;
}

function loopback_configure_interface($verbose = false)
{
    $cnf = OPNsense\Core\Config::getInstance()->object();
    $configured_interfaces = legacy_interface_listget();
    $configured_devices = ['lo0']; // lo0 should always be configured
    $loopbacks = iterator_to_array((new \OPNsense\Interfaces\Loopback())->loopback->iterateItems());
    if ($verbose && !empty($loopbacks)) {
        echo 'Configuring Loopback interfaces...';
        flush();
    }

    // (re)configure vxlan devices
    foreach ($loopbacks as $loopback) {
        $device_name = "lo{$loopback->deviceId}";
        $configured_devices[] = $device_name;
        if (!in_array($device_name, $configured_interfaces)) {
            echo $device_name;
            mwexecf('/sbin/ifconfig %s create', array($device_name));
        }
    }
    // destroy non existing interfaces
    foreach ($configured_interfaces as $intf) {
        if (strpos($intf, "lo") === 0) {
            if (!in_array($intf, $configured_devices)) {
                mwexecf('/sbin/ifconfig %s destroy', array($intf));
            }
        }
    }
    if ($verbose && !empty($vxlans)) {
        echo "done.\n";
    }
}
