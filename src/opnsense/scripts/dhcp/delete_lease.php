#!/usr/local/bin/php
<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
 *
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
 *
 */

require_once 'config.inc';
require_once 'interfaces.inc';
require_once 'services.inc';
require_once 'util.inc';

if ($argc < 2) {
    echo 'Too few arguments!' . PHP_EOL;
    exit(1);
} else {
    $ip_to_remove = $argv[1];
    if (is_ipaddr($ip_to_remove)) {
        $leasesfile = services_dhcpd_leasesfile();
        // delete dhcp lease
        /* Stop DHCPD */
        killbyname('dhcpd');
        $fin = @fopen($leasesfile, 'r');
        $fout = @fopen($leasesfile . '.new', 'w');
        if ($fin) {
            $lease = '';
            while (($line = fgets($fin, 4096)) !== false) {
                $fields = explode(' ', $line);
                if ($fields[0] == 'lease') {
                    // lease segment, record ip
                    $lease = trim($fields[1]);
                }

                if ($lease != $ip_to_remove) {
                    fputs($fout, $line);
                }

                if (trim($line) == '}') {
                    // end of segment
                    $lease = '';
                }
            }
            fclose($fin);
            fclose($fout);
            @unlink($leasesfile);
            @rename($leasesfile . '.new', $leasesfile);
            /* Restart DHCP Service */
            services_dhcpd_configure();
        }

        echo 'ok' . PHP_EOL;
        exit(0);
    } else {
        echo 'Invalid IP Address!' . PHP_EOL;
        exit(2);
    }
}