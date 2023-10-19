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
require_once 'plugins.inc.d/dpinger.inc';

function signalhandler($signal)
{
    global $config;

    OPNsense\Core\Config::getInstance()->forceReload();
    $config = parse_config();

    syslog(LOG_NOTICE, 'Reloaded gateway watcher configuration on SIGHUP');
}

openlog('dpinger', LOG_DAEMON, LOG_LOCAL4);
pcntl_signal(SIGHUP, 'signalhandler');

$action = !empty($argv[1]) ? $argv[1] : null;

$poll = 1; /* live poll interval */
$wait = 10; /* startup and alarm delay */

$mode = [];

sleep($wait);

while (1) {
    pcntl_signal_dispatch();

    try {
        $status = dpinger_status();
    } catch (\Error $e) {
        sleep($poll);
        continue;
    }

    $alarm = false;

    /* clear known gateways in first step to flush unknown in second step */
    $cleanup = $mode;
    foreach ($status as $report) {
        unset($cleanup[$report['name']]);
    }
    foreach (array_keys($cleanup) as $stale) {
        unset($mode[$stale]);
    }

    /* run main watcher pass */
    foreach ($status as $report) {
        $ralarm = false;

        if ($report['loss'] == '~') {
            /* wait for valid data before triggering an alarm */
            continue;
        }

        if (empty($mode[$report['name']])) {
            /* skip one round and assume gateway is down */
            $mode[$report['name']] = 'down';
            continue;
        }

        if ($report['status'] == 'force_down') {
            /* the outcome is the same so simplify the status */
            $report['status'] = 'down';
        }

        if (isset($config['system']['gw_switch_default'])) {
            /* only consider down state transition in this case */
            if (!empty($mode[$report['name']]) && $mode[$report['name']] != $report['status'] && ($mode[$report['name']] == 'down' || $report['status'] == 'down')) {
                $ralarm = true;
            }
        }

        foreach (config_read_array('gateways', 'gateway_group') as $group) {
            foreach ($group['item'] as $item) {
                $itemsplit = explode('|', $item);
                if ($itemsplit[0] == $report['name']) {
                    /* consider all state transitions as they depend on individual trigger setting */
                    if (!empty($mode[$report['name']]) && $mode[$report['name']] != $report['status']) {
                        /* XXX consider trigger conditions later on */
                        $ralarm = true;
                        break;
                    }
                }
            }
        }

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

        if ($mode[$report['name']] != $report['status']) {
            syslog(LOG_NOTICE, sprintf(
                "%s: %s (Addr: %s Alarm: %s RTT: %s RTTd: %s Loss: %s)",
                $ralarm ? 'ALERT' : 'MONITOR',
                $report['name'],
                $report['monitor'],
                $mode[$report['name']] . ' -> ' . $report['status'],
                $report['delay'],
                $report['stddev'],
                $report['loss']
            ));

            /* update cached state now */
            $mode[$report['name']] = $report['status'];
        }
    }

    if ($alarm && $action != null) {
        configd_run($action);
    }

    sleep($alarm ? $wait : $poll);
}
