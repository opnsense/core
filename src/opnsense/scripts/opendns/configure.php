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

if (!$mdl->enable->isEmpty()) {
    /* register before taking the config lock, remote call may take a while */
    $result = trim(opendns_register([
        'username' => (string)$mdl->username,
        'password' => (string)$mdl->password,
        'host' => (string)$mdl->host,
    ]));
    $errors = [];
    foreach (explode("\n", $result) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, 'good') === 0 || strpos($line, 'nochg') === 0) {
            continue;
        }
        $errors[] = $line;
    }
    if (!empty($errors)) {
        echo "OpenDNS.com registration failed: " . implode("\n", $errors) . "\n";
        exit(1);
    }
}

/* lock reloads the config, reload the model along with it */
Config::getInstance()->lock();
$mdl = new OpenDNS();
$enabled = !$mdl->enable->isEmpty();
$standalone = !$mdl->standalone->isEmpty();
$has_backup = (string)$mdl->backup->has_backup == '1';
$system = Config::getInstance()->object()->system;

/* OpenDNS resolver addresses, also used to detect them in the live DNS */
$v4_server = ['208.67.222.222', '208.67.220.220'];
$v6_server = ['2620:119:35::35', '2620:119:53::53'];
$opendns_servers = array_merge($v4_server, $v6_server);

if ($enabled && !$standalone) {
    /* OpenDNS is managing the system DNS; refresh the backup only when the live
     * DNS isn't the OpenDNS set, so we never record OpenDNS servers (or their
     * override) as the user's own */
    $user_servers = [];
    $has_any = false;
    $has_user_servers = false;
    foreach ($system->dnsserver as $server) {
        $server = (string)$server;
        if ($server === '') {
            continue;
        }
        $has_any = true;
        if (!in_array($server, $opendns_servers)) {
            $user_servers[] = $server;
            $has_user_servers = true;
        }
    }
    if (!$has_any || $has_user_servers) {
        $mdl->backup->has_backup = '1';
        $mdl->backup->dnsservers = implode(',', $user_servers);
        $mdl->backup->dnsallowoverride = (string)$system->dnsallowoverride == '1' ? '1' : '0';
    }

    if (isset($system->prefer_ipv4)) {
        $servers = $v4_server;
        if (is_ipv6_allowed()) {
            $servers = array_merge($servers, $v6_server);
        }
    } else {
        $servers = is_ipv6_allowed() ? $v6_server : [];
        $servers = array_merge($servers, $v4_server);
    }
    unset($system->dnsserver);
    foreach ($servers as $server) {
        $system->addChild('dnsserver', $server);
    }
    $system->dnsallowoverride = '0';
} else {
    /* not managing (disabled, or enabled in standalone mode): restore the
     * user's DNS if we captured a backup */
    if ($has_backup) {
        /* restore the captured DNS settings verbatim and clear the backup */
        $servers = array_values(array_filter(explode(',', (string)$mdl->backup->dnsservers)));
        $allowoverride = (string)$mdl->backup->dnsallowoverride == '1' ? '1' : '0';

        $mdl->backup->has_backup = '0';
        $mdl->backup->dnsservers = '';
        $mdl->backup->dnsallowoverride = '1';

        unset($system->dnsserver);
        foreach (!empty($servers) ? $servers : [''] as $server) {
            $system->addChild('dnsserver', $server);
        }
        $system->dnsallowoverride = $allowoverride;
    } else {
        /* no backup: strip leftover OpenDNS servers, leave
         * everything else untouched */
        $servers = [];
        $removed = false;
        foreach ($system->dnsserver as $server) {
            $server = (string)$server;
            if ($server === '') {
                continue;
            }
            if (in_array($server, $opendns_servers)) {
                $removed = true;
            } else {
                $servers[] = $server;
            }
        }
        if ($removed) {
            unset($system->dnsserver);
            foreach (!empty($servers) ? $servers : [''] as $server) {
                $system->addChild('dnsserver', $server);
            }
            if (empty($servers)) {
                /* cleared the last (OpenDNS) servers with no backup to restore;
                 * allow DHCP to provide DNS instead of leaving none */
                $system->dnsallowoverride = '1';
            }
        }
    }
}

$mdl->serializeToConfig(false, true);
Config::getInstance()->save(['description' => 'OpenDNS filter configuration change']);
