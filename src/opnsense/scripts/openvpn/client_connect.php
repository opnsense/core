#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018-2023 Deciso B.V.
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

require_once("legacy_bindings.inc");
require_once("util.inc");
require_once("plugins.inc.d/openvpn.inc");

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);
$common_name = getenv("common_name");
$vpnid = getenv("auth_server");
$config_file = getenv("config_file");
$server = (new OPNsense\OpenVPN\OpenVPN())->getInstanceById($vpnid, 'server');
if ($server) {
    $cso = (new OPNsense\OpenVPN\OpenVPN())->getOverwrite($vpnid, $common_name);
    if (empty($cso)) {
        $cso = array("common_name" => $common_name);
    }
    if (!empty($config_file)) {
        $cso_filename = openvpn_csc_conf_write($cso, $server, $config_file);
        if (!empty($cso_filename)) {
            syslog(LOG_NOTICE, "client config created @ {$cso_filename}");
        }
    } else {
        syslog(LOG_NOTICE, "unable to write client config for {$common_name}, missing target filename");
    }
}

closelog();
exit(0);
