#!/usr/local/bin/php
<?php

/*
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
require_once 'plugins.inc.d/dhcpd.inc';

$leases_file = '/var/dhcpd/var/db/dhcpd6.leases';
if (!file_exists($leases_file)) {
    exit(1);
}

$duid_arr = [];
foreach (new SplFileObject($leases_file) as $line) {
    if (preg_match("/^(ia-[np][ad])[ ]+\"(.*?)\"/i ", $line, $duidmatch)) {
        $type = $duidmatch[1];
        $duid = $duidmatch[2];
    } elseif (preg_match("/iaaddr[ ]+([0-9a-f:]+)[ ]+/i", $line, $addressmatch)) {
        $ia_na = $addressmatch[1];
    } elseif (preg_match("/iaprefix[ ]+([0-9a-f:\/]+)[ ]+/i", $line, $prefixmatch)) {
        $ia_pd = $prefixmatch[1];
    } elseif (preg_match("/binding state active/i", $line, $activematch)) {
        $active = true;
    } elseif (preg_match("/^}/i ", $line)) {
        $iaid_duid = dhcpd_parse_duid($duid);
        $duid = implode(':', $iaid_duid[1]);

        switch ($type) {
            case 'ia-na':
                if (!empty($ia_na) && !empty($active)) {
                    $duid_arr[$duid]['address'] = $ia_na;
                }
                break;
            case 'ia-pd':
                if (!empty($ia_pd) && !empty($active)) {
                    if (empty($duid_arr[$duid]['prefix'])) {
                        $duid_arr[$duid]['prefix'] = [];
                    }
                    $duid_arr[$duid]['prefix'][] = $ia_pd;
                }
                break;
        }

        unset($active);
        unset($duid);
        unset($ia_na);
        unset($ia_pd);
        unset($type);
    }
}

/* since a route requires a gateway address try to derive it from static mapping as well */
foreach (plugins_run('static_mapping:dhcpd') as $map) {
    foreach ($map as $host) {
        if (empty($host['duid'])) {
            continue;
        }

        if (empty($duid_arr[$host['duid']])) {
            continue;
        }

        if (!empty($host['ipaddrv6'])) {
            $ipaddrv6 = $host['ipaddrv6'];

            /* although link-local is not a real static mapping use it to reach the downstream router */
            if (is_linklocal($ipaddrv6) && strpos($ipaddrv6, '%') === false) {
                $ipaddrv6 .= '%' . get_real_interface($host['interface'], 'inet6');
            }

            /* we want static mapping to have a higher priority */
            $duid_arr[$host['duid']]['address'] = $ipaddrv6;
        }
    }
}

$routes = [];

/* collect expired leases */
$dhcpd_log = shell_safe('opnsense-log -n dhcpd');
if (!empty($dhcpd_log)) {
    foreach (new SplFileObject($dhcpd_log) as $line) {
        if (preg_match('/releases prefix ([0-9a-f:]+\/[0-9]+)/i', $line, $expire)) {
            /* expire first, overwritten later when active */
            $routes[$expire[1]] = null;
        }
    }
}

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
