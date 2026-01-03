#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023-2025 Franco Fichtner <franco@opnsense.org>
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
$cache_file = '/tmp/gateways.status';

/* clear stale file before continuing */
@unlink($cache_file);

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

    $alarm_gateways = [];

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

        /* the outcome for both is the same so simplify the status for our checks */
        $rprev = $mode[$report['name']] != 'force_down' ? $mode[$report['name']] : 'down';
        $rcurr = $report['status'] != 'force_down' ? $report['status'] : 'down';

        if (isset($config['system']['gw_switch_default'])) {
            /* only consider down state transition in this case */
            if (!empty($rprev) && $rprev != $rcurr && ($rprev == 'down' || $rcurr == 'down')) {
                $ralarm = true;
            }
        }

        foreach (config_read_array('gateways', 'gateway_group') as $group) {
            foreach ($group['item'] as $item) {
                $itemsplit = explode('|', $item);
                if ($itemsplit[0] == $report['name']) {
                    /* consider all state transitions as they depend on individual trigger setting */
                    if (!empty($rprev) && $rprev != $rcurr) {
                        /* XXX consider trigger conditions later on */
                        $ralarm = true;
                        break;
                    }
                }
            }
        }

        if ($ralarm) {
            $alarm_gateways[] = $report['name'];
        }

        /* diagnostics block as we may have no $ralarm but still want to log the transition */
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

            /* update cached state now based on the original state, not our simplified one */
            $mode[$report['name']] = $report['status'];
        }
    }

    $cache_data = serialize($mode);
    $cache_rewrite = true;

    if (file_exists($cache_file)) {
         $cache_rewrite = file_get_contents($cache_file) !== $cache_data;
    }

    if ($cache_rewrite) {
        file_safe($cache_file, $cache_data);
    }

    if (count($alarm_gateways) && $action != null) {
        configdp_run($action, [implode(',', $alarm_gateways)]);
    }

    sleep(count($alarm_gateways) ? $wait : $poll);
}
