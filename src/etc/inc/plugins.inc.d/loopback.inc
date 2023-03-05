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

function loopback_configure()
{
    return [
        'loopback' => ['loopback_configure_do']
    ];
}

function loopback_interfaces()
{
    return [
        'lo0' => [
            'descr' => gettext('Loopback'),
            'enable' => true,
            'if' => 'lo0',
            'ipaddr' => '127.0.0.1',
            'ipaddrv6' => '::1',
            'subnet' => '8',
            'subnetv6' => '128',
            'type' => 'none',
            'virtual' => true,
        ]
    ];
}

function loopback_devices()
{
    $names = [];

    foreach (iterator_to_array((new \OPNsense\Interfaces\Loopback())->loopback->iterateItems()) as $loopback) {
        $names["lo{$loopback->deviceId}"] = [
            'descr' => sprintf('lo%s (%s)', $loopback->deviceId, $loopback->description),
            'ifdescr' => sprintf('%s', $loopback->description),
            'name' => "lo{$loopback->deviceId}",
        ];
    }

    return [[
        'function' => 'loopback_configure_device',
        'configurable' => true,
        'pattern' => '^lo',
        'volatile' => true,
        'type' => 'loopback',
        'names' => $names,
    ]];
}

function loopback_configure_do($verbose = false, $device = null)
{
    $cnf = OPNsense\Core\Config::getInstance()->object();
    $configured_interfaces = legacy_interface_listget();
    $configured_devices = ['lo0']; // lo0 should always be configured
    $loopbacks = iterator_to_array((new \OPNsense\Interfaces\Loopback())->loopback->iterateItems());

    if (!empty($loopbacks)) {
        service_log(sprintf('Configuring Loopback interface%s...', empty($device) ? 's' : " {$device}"), $verbose);
    }

    // (re)configure loopback devices
    foreach ($loopbacks as $loopback) {
        $device_name = "lo{$loopback->deviceId}";
        $configured_devices[] = $device_name;

        if ($device !== null && $device != $device_name) {
            continue;
        }

        if (!in_array($device_name, $configured_interfaces)) {
            mwexecf('/sbin/ifconfig %s create', [$device_name]);
        }
    }

    // destroy nonexistent interfaces
    foreach ($configured_interfaces as $intf) {
        if (strpos($intf, "lo") === 0) {
            if (!in_array($intf, $configured_devices)) {
                mwexecf('/sbin/ifconfig %s destroy', [$intf]);
            }
        }
    }

    if (!empty($loopbacks)) {
        service_log("done.\n", $verbose);
    }
}

function loopback_configure_device($device)
{
    loopback_configure_do(false, $device);
}
