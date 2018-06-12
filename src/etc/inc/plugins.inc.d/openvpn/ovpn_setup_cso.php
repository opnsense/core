#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
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

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/openvpn.inc");

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);
$common_name = getenv("common_name");
$vpnid = filter_var($argv[1], FILTER_SANITIZE_NUMBER_INT);
if (isset($config['openvpn']['openvpn-server'])) {
    foreach ($config['openvpn']['openvpn-server'] as $server) {
        if ("{$server['vpnid']}" === "$vpnid") {
            $all_cso = openvpn_fetch_csc_list();
            if (!empty($all_cso[$vpnid][$common_name])) {
                $cso = $all_cso[$vpnid][$common_name];
            } else {
                $cso = array("common_name" => $common_name);
            }
            // $argv[2] contains the temporary file used for the profile specified by client-connect
            $cso_filename = openvpn_csc_conf_write($cso, $server, $argv[2]);
            if (!empty($cso_filename)) {
                syslog(LOG_NOTICE, "client config created @ {$cso_filename}");
            }
            break;
        }
    }
}

closelog();
exit(0);
