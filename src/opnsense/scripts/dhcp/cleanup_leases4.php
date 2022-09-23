#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2022 Deciso B.V.
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

require_once("config.inc");
require_once("util.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/dhcpd.inc");

$dhcp_lease_file = "/var/dhcpd/var/db/dhcpd.leases";
$opts = getopt('d::f::hms', []);

if (isset($opts['h']) || empty($opts)) {
    echo "Usage: cleanup_leases4.php [-h]\n\n";
    echo "\t-h show this help text and exit\n";
    echo "\t-m cleanup static mac addresses\n";
    echo "\t-s restart service (required when service is active)\n";
    echo "\t-d=xxx remove ip address\n";
    echo "\t-f=dhcpd.leases file (default = /var/dhcpd/var/db/dhcpd.leases)\n";
    exit(0);
}


if (!empty($opts['f'])) {
    $dhcp_lease_file = $opts['f'];
}
// collect map of addresses to remove
$addresses = [];
if (isset($opts['m'])) {
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (!empty($dhcpifconf['staticmap']) && !empty($dhcpifconf['enable'])) {
            foreach ($dhcpifconf['staticmap'] as $static) {
                if (!empty($static['mac'])) {
                    $addresses[$static['mac']] = !empty($static['ipaddr']) ? $static['ipaddr'] : "";
                }
            }
        }
    }
}
if (!empty($opts['d'])) {
    $addresses[] = $opts['d'];
}

if (isset($opts['s'])) {
    killbypid('/var/dhcpd/var/run/dhcpd.pid', 'TERM', true);
} elseif (isvalidpid('/var/dhcpd/var/run/dhcpd.pid')) {
    echo "dhcpd active, can't update lease file";
    exit(1);
}



$removed_leases = 0;
$fin = @fopen($dhcp_lease_file, 'r+');
$fout = @fopen($dhcp_lease_file . '.new', 'w');
if ($fin && flock($fin, LOCK_EX)) {
    $lease = '';
    $lease_ip = '';
    $lease_mac = ';';
    while (($line = fgets($fin, 4096)) !== false) {
        $fields = explode(' ', trim($line));
        if (strpos($line, 'lease ') === 0) {
            $lease_ip = trim($fields[1]);
        } elseif (strpos($line, 'hardware ethernet ') > 0) {
            $lease_mac = strtolower(trim($fields[2], ' \n;'));
        }
        $lease .= $line;

        if ($line == "}\n") {
            // end of segment, flush when relevant
            $exact_match = isset($addresses[$lease_mac]) && $addresses[$lease_mac] == $lease_ip;
            if ((!isset($addresses[$lease_mac]) && !in_array($lease_ip, $addresses)) || $exact_match) {
                fputs($fout, $lease);
            } else {
                $removed_leases++;
            }
            $lease = '';
            $lease_ip = '';
            $lease_mac = ';';
        }
    }
    flock($fin, LOCK_UN);
    fclose($fin);
    fclose($fout);
    @unlink($dhcp_lease_file);
    @rename($dhcp_lease_file . '.new', $dhcp_lease_file);
}
if (isset($opts['s'])) {
    dhcpd_dhcp4_configure();
}

echo json_encode(["removed_leases" => $removed_leases]);
