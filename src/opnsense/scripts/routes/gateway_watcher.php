#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Franco Fichtner <franco@opnsense.org>
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

$action = !empty($argv[1]) ? $argv[1] : null;

$poll = 1; /* live poll interval */
$wait = 10; /* startup and alarm delay */

$mode = [];

sleep($wait);

while (1) {
    $alarm = false;

    OPNsense\Core\Config::getInstance()->forceReload();
    $config = parse_config();

    $gw_switch_default = isset($config['system']['gw_switch_default']);
    $status = return_gateways_status();

    foreach ($status as $report) {
        $ralarm = false;

        if (empty($mode[$report['name']])) {
            /* skip one round for baseline */
            continue;
        }

        $gw_group_member = false;
        foreach (config_read_array('gateways', 'gateway_group') as $group) {
            foreach ($group['item'] as $item) {
                $itemsplit = explode('|', $item);
                if ($itemsplit[0] == $report['name']) {
                    /* XXX consider trigger conditions later on */
                    $gw_group_member = true;
                    break;
                }
            }
        }

        /* wait for valid data before triggering an alarm */
        if ($report['loss'] == '~') {
            continue;
        }

        if ($gw_switch_default) {
            /* only consider down state transition in this case */
            if (!empty($mode[$report['name']]) && $mode[$report['name']] != $report['status'] && ($mode[$report['name']] == 'down' || $report['status'] == 'down')) {
                $ralarm = true;
            }
        }

        if ($gw_group_member) {
            /* consider all state transitions as they depend on individual trigger setting */
            if (!empty($mode[$report['name']]) && $mode[$report['name']] != $report['status']) {
                $ralarm = true;
            }
        }

        /* XXX for testing */
        echo sprintf(
            "/usr/local/etc/rc.syshook monitor %s %s %s %s %s %s\n",
            $report['name'],
            $report['monitor'],
            $mode[$report['name']] . ' -> ' . $report['status'],
            $report['delay'],
            $report['stddev'],
            $report['loss']
        );

        if ($ralarm) {
            /* raise an alarm via the rc.syshook monitor facility */
            shell_safe("/usr/local/etc/rc.syshook monitor %s %s %s %s %s %s", [
                $report['name'],
                $report['monitor'],
                $mode[$report['name']] . ' -> ' . $report['status'],
                $report['delay'],
                $report['stddev'],
                $report['loss']
            ]);

            $alarm = true;
        }
    }

   /* react to alarm if backend action was given */
    if ($alarm) {
        if ($action != null) {
            configd_run($action);
        }
        /* XXX this blacks out all alarms for the grace period after alarm */
        sleep($wait);
    } else {
        sleep($poll);
    }

    foreach ($status as $report) {
        $mode[$report['name']] = $report['status'];
    }
}
