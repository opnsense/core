#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2025 Deciso B.V.
 * Copyright (C) 2022-2024 Franco Fichtner <franco@opnsense.org>
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

require_once 'config.inc';
require_once 'interfaces.inc';
require_once 'util.inc';

$leases_file = '/var/db/kea/kea-leases6.csv';
if (!file_exists($leases_file)) {
    exit(1);
}

$duid_arr = [];
$now = time();
if (($fh = fopen($leases_file, 'r')) !== false) {
    $header = fgetcsv($fh);

    while (($row = fgetcsv($fh)) !== false) {
        $lease = @array_combine($header, $row);
        if (empty($lease['duid']) || empty($lease['lease_type']) || empty($lease['address'])) {
            continue;
        }

        $type = trim($lease['lease_type']);
        $prefix_len = (int)$lease['prefix_len'];
        $expire = (int)$lease['expire'];
        $duid = strtolower(trim($lease['duid']));
        $address = trim($lease['address']);

        /* Skip expired leases */
        if ($expire <= $now) {
            continue;
        }

        if (!isset($duid_arr[$duid])) {
            $duid_arr[$duid] = [];
        }

        /* IA_NA: type 0, prefix_len 128 - used as gateway */
        if ($type === '0' && $prefix_len === 128) {
            $duid_arr[$duid]['address'] = $address;
        }
        /* IA_PD: type 2, prefix_len <= 64 - the delegated prefix */
        elseif ($type === '2' && $prefix_len <= 64) {
            $prefix = "{$address}/{$prefix_len}";
            $duid_arr[$duid]['prefix'][] = $prefix;
        }
    }
    fclose($fh);
}

$routes = [];

/* collect active leases */
foreach ($duid_arr as $entry) {
    if (!empty($entry['prefix']) && !empty($entry['address'])) {
        foreach ($entry['prefix'] as $prefix) {
            /* new or reassigned takes priority */
            $routes[$prefix] = $entry['address'];
        }
    }
}

/* expire all first */
foreach (array_keys($routes) as $prefix) {
    mwexecf('/sbin/route delete -inet6 %s', [$prefix], true);
}

/* active route apply */
foreach ($routes as $prefix => $address) {
    if (!empty($address)) {
        mwexecf('/sbin/route add -inet6 %s %s', [$prefix, $address]);
    }
}
