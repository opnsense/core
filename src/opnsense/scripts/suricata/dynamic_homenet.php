#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Logan Galloway
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

/*
 * Update the DYNAMIC_HOME_NET file for Suricata. Called without arguments before Suricata starts
 * and before the rules are reloaded, with "reload" on a new IPv6 prefix, in which case a changed
 * file also reloads the rules. This only prepares optional data, so it never fails the caller:
 * without selected interfaces it returns right away and the exit code is always 0.
 */

require_once('config.inc');

use OPNsense\Core\Backend;
use OPNsense\IDS\DynamicHomenet;

if (!empty($config['OPNsense']['IDS']['general']['homenet_interfaces'])) {
    try {
        if (DynamicHomenet::sync() && in_array('reload', $argv ?? [])) {
            $backend = new Backend();
            /* same sequence as applying the Intrusion Detection rules, render and reload, never restart */
            if (trim($backend->configdRun('template reload OPNsense/IDS')) == 'OK') {
                if (strpos($backend->configdRun('ids status'), 'is running') !== false) {
                    $backend->configdRun('ids reload');
                }
            }
        }
    } catch (\Throwable $e) {
        syslog(LOG_ERR, 'unable to update the dynamic home networks: ' . $e->getMessage());
    }
}

exit(0);
