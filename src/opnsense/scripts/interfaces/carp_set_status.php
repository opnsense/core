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

require_once('config.inc');
require_once('util.inc');
require_once("interfaces.inc");

$action = strtolower($argv[1] ?? '');
$a_vip = &config_read_array('virtualip', 'vip');

if ($action == 'maintenance') {
    if (isset($config["virtualip_carp_maintenancemode"])) {
        unset($config["virtualip_carp_maintenancemode"]);
        $carp_demotion_default = '0';
        foreach ($config['sysctl']['item'] as $tunable) {
            if ($tunable['tunable'] == 'net.inet.carp.demotion' && ctype_digit($tunable['value'])) {
                $carp_demotion_default = $tunable['value'];
            }
        }
        $carp_diff = $carp_demotion_default - get_single_sysctl('net.inet.carp.demotion');
        set_single_sysctl('net.inet.carp.demotion', $carp_diff);
        write_config("Leave CARP maintenance mode");
        echo json_encode(['status' => 'ok', 'action' => 'leave_maintenance']);
    } else {
        $config["virtualip_carp_maintenancemode"] = true;
        set_single_sysctl('net.inet.carp.demotion', '240');
        write_config("Enter CARP maintenance mode");
        echo json_encode(['status' => 'ok', 'action' => 'enter_maintenance']);
    }
} elseif ($action == 'disable') {
    set_single_sysctl('net.inet.carp.allow', '0');
    foreach ($a_vip as $vip) {
        if (!empty($vip['vhid'])) {
            interface_vip_bring_down($vip);
        }
    }
    echo json_encode(['status' => 'ok', 'action' => 'disable']);
} elseif ($action == 'enable') {
    interfaces_carp_setup();
    set_single_sysctl('net.inet.carp.allow', '1');
    foreach ($a_vip as $vip) {
        if (!empty($vip['vhid'])) {
            if ($vip['mode'] == 'carp') {
                interface_carp_configure($vip);
            } else {
                interface_ipalias_configure($vip);
            }
        }
    }
    echo json_encode(['status' => 'ok', 'action' => 'enable']);
} else {
    echo json_encode(['status' => 'failed']);
}
