#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Greelan
 * Copyright (c) 2015-2021 Franco Fichtner <franco@opnsense.org>
 * Copyright (c) 2008 Tellnet AG
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
require_once('plugins.inc.d/opendns.inc');

use OPNsense\OpenDNS\OpenDNS;

$mdl = new OpenDNS();
$enabled = !$mdl->enable->isEmpty();
$standalone = !$mdl->standalone->isEmpty();
$has_backup = (string)$mdl->backup->has_backup == '1';

if ($enabled) {
    $result = trim(opendns_register([
        'username' => (string)$mdl->username,
        'password' => (string)$mdl->password,
        'host' => (string)$mdl->host,
    ]));
    $errors = [];
    foreach (explode("\n", $result) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, 'good') === 0 || $line === 'noop') {
            continue;
        }
        $errors[] = $line;
    }
    if (!empty($errors)) {
        echo "OpenDNS.com registration failed: " . implode("\n", $errors);
        exit(1);
    }
}

$system = &config_read_array('system');

if ($enabled && $standalone) {
    /* standalone mode: do not alter DNS server settings */
} elseif ($enabled) {
    /* capture current DNS settings before overwriting,
     * but only if we don't already have a backup
     * (avoids re-capturing OpenDNS servers on subsequent applies) */
    if (!$has_backup) {
        $mdl->backup->has_backup = '1';
        $mdl->backup->dnsservers = implode(',', $system['dnsserver'] ?? []);
        $mdl->backup->dnsallowoverride = $system['dnsallowoverride'] ?? '1';
        $mdl->serializeToConfig(false, true);
    }

    $system['dnsserver'] = [];
    $v4_server = ['208.67.222.222', '208.67.220.220'];
    $v6_server = ['2620:119:35::35', '2620:119:53::53'];
    if (isset($system['prefer_ipv4'])) {
        $system['dnsserver'][] = $v4_server[0];
        $system['dnsserver'][] = $v4_server[1];
        if (is_ipv6_allowed()) {
            $system['dnsserver'][] = $v6_server[0];
            $system['dnsserver'][] = $v6_server[1];
        }
    } else {
        if (is_ipv6_allowed()) {
            $system['dnsserver'][] = $v6_server[0];
            $system['dnsserver'][] = $v6_server[1];
        }
        $system['dnsserver'][] = $v4_server[0];
        $system['dnsserver'][] = $v4_server[1];
    }
    $system['dnsallowoverride'] = '0';
} else {
    /* disabled: restore backup if available, otherwise fall back to defaults */
    if ($has_backup) {
        $servers = explode(',', (string)$mdl->backup->dnsservers);
        $system['dnsserver'] = !empty(array_filter($servers)) ? $servers : [''];
        $system['dnsallowoverride'] = (string)$mdl->backup->dnsallowoverride;

        /* clear the backup */
        $mdl->backup->has_backup = '0';
        $mdl->backup->dnsservers = '';
        $mdl->backup->dnsallowoverride = '1';
        $mdl->serializeToConfig(false, true);
    } else {
        $system['dnsserver'] = [''];
        $system['dnsallowoverride'] = '1';
    }
}

write_config('OpenDNS filter configuration change');
