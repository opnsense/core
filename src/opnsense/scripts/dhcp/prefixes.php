#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2012 Seth Mos <seth.mos@dds.nl>
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

require_once 'util.inc';

$leases_file = "/var/dhcpd/var/db/dhcpd6.leases";
if (!file_exists($leases_file)) {
    exit(1);
}

$duid_arr = [];
foreach (new SplFileObject($leases_file) as $line) {
    if (preg_match("/^(ia-[np][ad])[ ]+\"(.*?)\"/i ", $line, $duidmatch)) {
        $type = $duidmatch[1];
        $duid = $duidmatch[2];
        continue;
    }

    if (preg_match("/iaaddr[ ]+([0-9a-f:]+)[ ]+/i", $line, $addressmatch)) {
        $ia_na = $addressmatch[1];
        continue;
    }

    if (preg_match("/iaprefix[ ]+([0-9a-f:\/]+)[ ]+/i", $line, $prefixmatch)) {
        $ia_pd = $prefixmatch[1];
        continue;
    }

    /* closing bracket */
    if (preg_match("/^}/i ", $line)) {
        switch ($type) {
            case "ia-na":
                if (!empty($ia_na)) {
                    $duid_arr[$duid][$type] = $ia_na;
                }
                break;
            case "ia-pd":
                if (!empty($ia_pd)) {
                    $duid_arr[$duid][$type] = $ia_pd;
                }
                break;
        }

        unset($type);
        unset($duid);
        unset($ia_na);
        unset($ia_pd);
    }
}

$routes = [];

foreach ($duid_arr as $entry) {
    if (!empty($entry['ia-pd']) && !empty($entry['ia-na'])) {
        $routes[$entry['ia-na']] = $entry['ia-pd'];
    }
}

foreach ($routes as $address => $prefix) {
    mwexecf('/sbin/route delete -inet6 %s %s', [$prefix, $address], true);
    mwexecf('/sbin/route add -inet6 %s %s', [$prefix, $address]);
}

$dhcpd_log = trim(shell_exec('opnsense-log -n dhcpd'));
$expires = [];

if (empty($dhcpd_log)) {
    exit(1);
}

foreach (new SplFileObject($dhcpd_log) as $line) {
    if (preg_match("/releases[ ]+prefix[ ]+([0-9a-f:]+\/[0-9]+)/i", $line, $expire)) {
        if (in_array($expire[1], $routes)) {
            continue;
        }
        $expires[$expire[1]] = 1;
    }
}

foreach (array_keys($expires) as $prefix) {
    mwexecf('/sbin/route delete -inet6 %s', [$prefix], true);
}
