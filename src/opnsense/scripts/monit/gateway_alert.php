#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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
require_once 'interfaces.inc';
require_once 'util.inc';
require_once 'plugins.inc.d/dpinger.inc';

/**
 * @param string $status
 * @param string $gwname
 * @param array $group
 * @return string|null A string detailing the error if there is one, null if there is no error.
 */
function get_gateway_error(string $status, string $gwname, array $group)
{
    if (stristr($status, 'down') !== false) {
        return sprintf(gettext('MONITOR: %s is down, removing from routing group %s'), $gwname, $group['name']);
    } elseif (stristr($status, 'loss') !== false && stristr($group['trigger'], 'loss') !== false) {
        return sprintf(gettext('MONITOR: %s has packet loss, removing from routing group %s'), $gwname, $group['name']);
    } elseif (stristr($status, 'delay') !== false && stristr($group['trigger'], 'latency') !== false) {
        return sprintf(gettext('MONITOR: %s has high latency, removing from routing group %s'), $gwname, $group['name']);
    } else {
        return null;
    }
}

$gateways_status = dpinger_status();
$clean = true;

if (isset($config['gateways']['gateway_group'])) {
    foreach ($config['gateways']['gateway_group'] as $group) {
        $tiers_online = 0;
        foreach ($group['item'] as $item) {
            $gwname = explode("|", $item)[0];

            if (!empty($gateways_status[$gwname])) {
                $msg = get_gateway_error($gateways_status[$gwname]['status'], $gwname, $group);
                if (!empty($msg)) {
                    echo $msg . PHP_EOL;
                    $clean = false;
                } else {
                    $tiers_online++;
                }
            }
        }
        if ($tiers_online == 0) {
            /* Oh dear, we have no members!*/
            $msg = sprintf(gettext('Gateways status could not be determined, considering all as up/active. (Group: %s)'), $group['name']);
            echo $msg . PHP_EOL;
            $clean = false;
        }
    }
}

if ($clean) {
    exit(0);
} else {
    exit(1);
}
