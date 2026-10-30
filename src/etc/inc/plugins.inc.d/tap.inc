<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

function tap_configure()
{
    return [
        'tap' => ['tap_configure_do']
    ];
}

function tap_devices()
{
    $names = [];

    foreach (iterator_to_array((new \OPNsense\Interfaces\Tap())->tap->iterateItems()) as $tap) {
        $names[$tap->deviceName->getValue()] = [
            'descr' => sprintf('%s (%s)', $tap->deviceName->getValue(), $tap->description),
            'ifdescr' => sprintf('%s', $tap->description),
            'name' => $tap->deviceName->getValue(),
        ];
    }

    return [[
        'function' => 'tap_configure_device',
        'typedescr' => gettext('TAP'),
        'configurable' => true,
        'pattern' => '^tap0[0-9]+$',
        'volatile' => true,
        'type' => 'tap',
        'names' => $names,
    ]];
}

function tap_configure_do($verbose = false, $device = null)
{
    $configured_interfaces = legacy_interface_listget();
    $configured_devices = [];
    $taps = iterator_to_array((new \OPNsense\Interfaces\Tap())->tap->iterateItems());

    if (!empty($taps)) {
        service_log(sprintf('Configuring TAP interface%s...', empty($device) ? 's' : " {$device}"), $verbose);
    }

    // (re)configure tap devices
    foreach ($taps as $tap) {
        $device_name = $tap->deviceName->getValue();
        $configured_devices[] = $device_name;

        if ($device !== null && $device != $device_name) {
            continue;
        }

        if (!in_array($device_name, $configured_interfaces)) {
            mwexecf('/sbin/ifconfig tap create name %s', [$device_name]);
        }
    }

    // destroy nonexistent interfaces
    foreach ($configured_interfaces as $intf) {
        if (preg_match('/^tap0[0-9]+$/', $intf)) {
            if (!in_array($intf, $configured_devices)) {
                mwexecf('/sbin/ifconfig %s destroy', [$intf]);
            }
        }
    }

    if (!empty($taps)) {
        service_log("done.\n", $verbose);
    }
}

function tap_configure_device($device)
{
    tap_configure_do(false, $device);
}
