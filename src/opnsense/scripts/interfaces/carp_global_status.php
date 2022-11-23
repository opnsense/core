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

$carpcount = 0;
$a_vip = &config_read_array('virtualip', 'vip');
foreach ($a_vip as $carp) {
    if ($carp['mode'] == "carp") {
        $carpcount++;
        break;
    }
}

$response = [
    'demotion' =>  get_single_sysctl('net.inet.carp.demotion'),
    'allow' => get_single_sysctl('net.inet.carp.allow'),
    'maintenancemode' => !empty($config["virtualip_carp_maintenancemode"]),
    'status_msg' => ''
];

if (empty($response['maintenancemode']) && !empty($response['demotion'])) {
    $response['status_msg'] = gettext("CARP has detected a problem and this unit has been demoted to BACKUP status.");
    $response['status_msg'] .= "<br />" . gettext("Check link status on all interfaces with configured CARP VIPs.");
} elseif ($carpcount == 0) {
    $response['status_msg'] = gettext("Could not locate any defined CARP interfaces.");
}

echo json_encode($response);
