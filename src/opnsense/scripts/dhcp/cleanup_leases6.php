#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2023 Deciso B.V.
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

$dhcp_lease_file = "/var/dhcpd/var/db/dhcpd6.leases";
$opts = getopt('d::f::hs', []);

if (isset($opts['h']) || empty($opts)) {
    echo "Usage: cleanup_leases6.php [-h]\n\n";
    echo "\t-h show this help text and exit\n";
    echo "\t-s restart service (required when service is active)\n";
    echo "\t-d=xxx remove ipv6 address\n";
    echo "\t-f=dhcpd6.leases file (default = /var/dhcpd/var/db/dhcpd6.leases)\n";
    exit(0);
}

if (!empty($opts['f'])) {
    $dhcp_lease_file = $opts['f'];
}

if (!empty($opts['d'])) {
    $ip_to_remove = $opts['d'];
}

if (isset($opts['s'])) {
    killbypid('/var/dhcpd/var/run/dhcpdv6.pid');
} elseif (isvalidpid('/var/dhcpd/var/run/dhcpdv6.pid')) {
    echo "dhcpdv6 active, can't update lease file";
    exit(1);
}

$removed_leases = 0;
$fin = @fopen($dhcp_lease_file, "r+");
$fout = @fopen($dhcp_lease_file . ".new", "w");
if ($fin && flock($fin, LOCK_EX)) {
    $iaaddr = "";
    $content_to_flush = array();
    while (($line = fgets($fin, 4096)) !== false) {
        $fields = explode(' ', trim($line));
        if ($fields[0] == 'iaaddr') {
            // lease segment, record ip
            $iaaddr = trim($fields[1]);
            $content_to_flush[] = $line;
        } elseif ($fields[0] == 'ia-na' || count($content_to_flush) > 0) {
            $content_to_flush[] = $line;
        } else {
            // output data directly if we're not in a "ia-na" section
            fputs($fout, $line);
        }

        if ($line == "}\n") {
            if ($iaaddr != $ip_to_remove) {
                // write ia-na section
                foreach ($content_to_flush as $cached_line) {
                    fputs($fout, $cached_line);
                }
            } else {
                $removed_leases++;
                // skip empty line
                fgets($fin, 4096);
            }
            // end of segment
            $content_to_flush = array();
            $iaaddr = "";
        }
    }
    flock($fin, LOCK_UN);
    fclose($fin);
    fclose($fout);
    @unlink($dhcp_lease_file);
    @rename($dhcp_lease_file . ".new", $dhcp_lease_file);
}

if (isset($opts['s'])) {
    dhcpd_dhcp6_configure();
}

echo json_encode(["removed_leases" => $removed_leases]);
