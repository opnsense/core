#!/usr/local/bin/php
<?php

/*
 * Copyright (c) 2025 EDNT GmbH
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
 *
 * Put this script in /usr/local/opnsense/scripts/OPNsense/Monit/toptalker.php
 * and make it executable: chmod 755 toptalker.php
 * NetFlow needs to be up and running with 'Capture local'
 * You need 'advance mode' in Monit Service Settings to set 'Poll Time'
 * Name: choose as you want, but you should add the interface in some way. (TopTalker_LAN)
 * Type: needs to be 'Custom'
 * Path: path to this script with the real interface name as parameter:
 * /usr/local/opnsense/scripts/OPNsense/Monit/toptalker.php vtnet0
 * Poll Time: for testing use '1 cycles'
 * Results in one e-mail every 120 seconds if Polling Interval is not changed.
 * For normal work use something like '0-2 0 * * *' as Poll Time
 * cron style, but a minute range is required, since Monit is not exact.
 * With a minute range of 3 (0-2) you get one e-mail,
 * if Polling Interval has the default value of 120 seconds (2 minutes).
 *
 * For testing simply call this script from the commandline: ./toptalker.php
 *
 */

if($argc > 1) {
    $flowd = exec('sockstat -l | grep flowd');
    if ($flowd != "") {
        $if = $argv[1];
        //echo $if;

        $endtime = strtotime("now");
        $starttime = $endtime - 86400;

        $cmd = "/usr/local/opnsense/scripts/netflow/get_top_usage.py --provider FlowSourceAddrTotals --start_time " . $starttime . " --end_time " . $endtime . " --key_fields src_addr --value_field octets --max_hits 10 --filter 'if=$if'";
        //echo $cmd;

        exec($cmd, $output, $retval);
        //echo "\n" . $output[0] . "\n";

        //$res = '';
        $res = date('d.m.Y H:i:s', $starttime) . ' - ' . date('d.m.Y H:i:s', $endstime) . "  " . $if . "\n\n";
        $listelements = json_decode($output[0]);
        foreach($listelements as $entry) {
            if($entry->last_seen != "") {
                $res = $res . str_pad($entry->src_addr, 16) . str_pad(number_format($entry->total, 0, ',', '.'), 15, " ", STR_PAD_LEFT) . " " . date('Y.m.d H:i:s', $entry->last_seen) . "\n";
            }
        }
        $res = $res . "\nCoded by EDNT GmbH (c) 2025 https://www.ednt.de/\n";
    } else {
        $res = "NetFlow does not Capture local\n";
    }
} else {
    $res = "You need an interface as parameter\n";
}

echo ">\n\n" . $res . "\n";

exit(rand(1, 255));
