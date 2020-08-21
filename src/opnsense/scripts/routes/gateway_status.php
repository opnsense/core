#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016-2020 Deciso B.V.
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

require_once 'config.inc';
require_once 'util.inc';
require_once 'interfaces.inc';


$result = array();
$gateways_status = return_gateways_status();
foreach ((new \OPNsense\Routing\Gateways(legacy_interfaces_details()))->gatewaysIndexedByName() as $gname => $gw) {
    $gatewayItem = array('name' => $gname);
    $gatewayItem['address'] = !empty($gw['gateway']) ? $gw['gateway'] : "~";
    if (!empty($gateways_status[$gname])) {
        $gatewayItem['status'] = strtolower($gateways_status[$gname]['status']);
        $gatewayItem['loss'] = $gateways_status[$gname]['loss'];
        $gatewayItem['delay'] = $gateways_status[$gname]['delay'];
        $gatewayItem['stddev'] = $gateways_status[$gname]['stddev'];
        switch ($gatewayItem['status']) {
            case 'none':
                $gatewayItem['status_translated'] = gettext('Online');
                break;
            case 'force_down':
                $gatewayItem['status_translated'] = gettext('Offline (forced)');
                break;
            case 'down':
                $gatewayItem['status_translated'] = gettext('Offline');
                break;
            case 'delay':
                $gatewayItem['status_translated'] = gettext('Latency');
                break;
            case 'loss':
                $gatewayItem['status_translated'] = gettext('Packetloss');
                break;
            default:
                $gatewayItem['status_translated'] = gettext('Pending');
                break;
        }
    } else {
        $gatewayItem['status'] = 'none';
        $gatewayItem['status_translated'] =  gettext('Online');
        $gatewayItem['loss'] = '~';
        $gatewayItem['stddev'] = '~';
        $gatewayItem['delay'] = '~';
    }
    $result[] = $gatewayItem;
}
echo json_encode($result) . PHP_EOL;
