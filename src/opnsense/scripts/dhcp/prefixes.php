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
foreach (file($leases_file) as $line) {
    if (preg_match("/^(ia-[np][ad])[ ]+\"(.*?)\"/i ", $line, $duidmatch)) {
        $type = $duidmatch[1];
        $duid = $duidmatch[2];
        continue;
    }

    /* is it active? otherwise just discard */
    if (preg_match("/binding state active/i", $line, $activematch)) {
        $active = true;
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
                $duid_arr[$duid][$type] = $ia_na;
                break;
            case "ia-pd":
                $duid_arr[$duid][$type] = $ia_pd;
                break;
        }
        unset($type);
        unset($duid);
        unset($active);
        unset($ia_na);
        unset($ia_pd);
        continue;
    }
}

$routes = [];
foreach ($duid_arr as $entry) {
    if ($entry['ia-pd'] != '') {
        $routes[$entry['ia-na']] = $entry['ia-pd'];
    }
    array_shift($duid_arr);
}

if (count($routes) > 0) {
    foreach ($routes as $address => $prefix) {
        mwexecf('/sbin/route delete -inet6 %s %s', [$prefix, $address], true);
        mwexecf('/sbin/route add -inet6 %s %s', [$prefix, $address]);
    }
}

exec('opnsense-log dhcpd 2> /dev/null', $log, $ret);

if ($ret > 0) {
    $log = [];
}

$expires = [];

foreach ($log as $line) {
    if (preg_match("/releases[ ]+prefix[ ]+([0-9a-f:]+\/[0-9]+)/i", $line, $expire)) {
        if (in_array($expire[1], $routes)) {
            continue;
        }
        $expires[$expire[1]] = $expire[1];
    }
}

if (count($expires) > 0) {
    foreach ($expires as $prefix) {
        if (isset($prefix['prefix'])) {
            mwexecf('/sbin/route delete -inet6 %s', [$prefix['prefix']], true);
        }
    }
}
