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

use OPNsense\Core\Config;
use OPNsense\OpenDNS\OpenDNS;

$mdl = new OpenDNS();
$enabled = !$mdl->enable->isEmpty();
$managed = $enabled && $mdl->standalone->isEmpty();

/* register before taking the config lock, remote call may take a while */
$error = $enabled ? opendns_register() : null;
if ($error !== null && $managed) {
    echo "OpenDNS.com registration failed: {$error}\n";
    exit(1);
}

/* lock reloads the config, reload the model along with it */
Config::getInstance()->lock();
$mdl = new OpenDNS();
$system = Config::getInstance()->object()->system;
$current = [];
foreach ($system->dnsserver as $server) {
    if ((string)$server !== '') {
        $current[] = (string)$server;
    }
}
$changed = false;

if ($managed) {
    if ($mdl->backup->has_backup->isEmpty()) {
        /* taking over the system DNS, keep the settings to restore later */
        $mdl->backup->has_backup = '1';
        $mdl->backup->dnsservers = implode(',', $current);
        $mdl->backup->dnsallowoverride = empty((string)$system->dnsallowoverride) ? '0' : '1';
        $changed = true;
    }
    $v4_server = ['208.67.222.222', '208.67.220.220'];
    $v6_server = is_ipv6_allowed() ? ['2620:119:35::35', '2620:119:53::53'] : [];
    $servers = isset($system->prefer_ipv4) ? array_merge($v4_server, $v6_server) : array_merge($v6_server, $v4_server);
    $allowoverride = '0';
} elseif (!$mdl->backup->has_backup->isEmpty()) {
    /* no longer managing the system DNS, restore the settings kept at takeover */
    $servers = array_values(array_filter(explode(',', (string)$mdl->backup->dnsservers)));
    $allowoverride = (string)$mdl->backup->dnsallowoverride;
    $mdl->backup->has_backup = '0';
    $mdl->backup->dnsservers = '';
    $mdl->backup->dnsallowoverride = '1';
    $changed = true;
}

if (isset($servers) && ($servers !== $current || (string)$system->dnsallowoverride !== $allowoverride)) {
    unset($system->dnsserver);
    foreach (!empty($servers) ? $servers : [''] as $server) {
        $system->addChild('dnsserver', $server);
    }
    $system->dnsallowoverride = $allowoverride;
    $changed = true;
}

if ($changed) {
    $mdl->serializeToConfig(false, true);
    Config::getInstance()->save(['description' => 'OpenDNS filter configuration change']);
} else {
    Config::getInstance()->unlock();
}

if ($error !== null) {
    echo "OpenDNS.com registration failed: {$error}\n";
    exit(1);
}

echo "OK\n";
