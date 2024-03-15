#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018-2023 Deciso B.V.
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
require_once("script/load_phalcon.php");
require_once("util.inc");

/**
 * verify certificate depth
 * @param string $serverid server identifier
 * @return string|bool an error string or true when properly authenticated
 */
function do_verify($serverid)
{
    $a_server = (new OPNsense\OpenVPN\OpenVPN())->getInstanceById($serverid, 'server');
    if ($a_server === null) {
        return "OpenVPN '$serverid' was not found. Denying authentication for user {$username}";
    }
    $certificate_depth = getenv('certificate_depth') !== false ? getenv('certificate_depth') : 0;
    $allowed_depth = !empty($a_server['cert_depth']) ? $a_server['cert_depth'] : $certificate_depth;
    if ($certificate_depth > $allowed_depth) {
        return "Certificate depth {$certificate_depth} exceeded max allowed depth of {$allowed_depth}.";
    } elseif ($a_server['use_ocsp'] && $certificate_depth == 0) {
        $serial = getenv('tls_serial_' . $certificate_depth);
        $ocsp_response = OPNsense\Trust\Store::ocsp_validate("/var/etc/openvpn/instance-" . $serverid . ".ca", $serial);
        if (!$ocsp_response['pass']) {
            return sprintf(
                "[serial : %s] @ %s - %s (%s)",
                $serial,
                $ocsp_response['uri'],
                $ocsp_response['response'],
                $ocsp_response['verify']
            );
        } else {
            syslog(LOG_INFO, sprintf(
                "tls-verify : [serial : %s] @ %s - %s",
                $serial,
                $ocsp_response['uri'],
                $ocsp_response['response']
            ));
        }
    }
    return true;
}

openlog("openvpn", LOG_ODELAY, LOG_AUTH);
$response = do_verify(getenv('auth_server'));
if ($response !== true) {
    syslog(LOG_WARNING, "tls-verify : {$response}");
    closelog();
    exit(1);
} else {
    closelog();
    exit(0);
}
