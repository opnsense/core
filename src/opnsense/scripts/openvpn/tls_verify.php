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

require_once("config.inc");

/**
 * verify certificate depth and optional against a ocsp server
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
    $allowed_depth = !empty($a_server['cert_depth']) ? $a_server['cert_depth'] : 1;
    if ($allowed_depth != null && ($certificate_depth > $allowed_depth)) {
        return "Certificate depth {$certificate_depth} exceeded max allowed depth of {$allowed_depth}.";
    }
    if ($certificate_depth < $allowed_depth) {
      # get relevant cfg stuff: ocsp_uri needs to be implemented in the gui
      # optional: get the crlDistributionPoint(s) from CA:
      # openssl x509 -in "/var/etc/openvpn/server" . $serverid . ".ca" -noout -ext crlDistributionPoints
      $ocsp_uri = $a_server['ocsp_uri'] ?? '';
      if (strlen($ocsp_uri) > 0) {
        $cn = getenv('common_name');
        $serial = getenv('tls_serial_' . $certificate_depth);
        $issuer = "/var/etc/openvpn/server" . $serverid . ".ca";
        $nonce = "-nonce"; # make this configurable ?
        syslog(LOG_WARNING, __FILE__ . " : certificate_depth: " . $certificate_depth . " serial: " . $serial . " cn: " . $cn);
        $ocsp_out = null;
        $ocsp_ecode = null;
        $openssl_ocsp = "/usr/bin/openssl ocsp -issuer " . escapeshellarg($issuer)
                . " " . $nonce
                . " -CAfile " . escapeshellarg($issuer)
                . " -url " . escapeshellarg($ocsp_uri)
                . " -serial " . escapeshellarg($serial);
        syslog(LOG_WARNING, __FILE__ . " : ocsp command: '" . $openssl_ocsp ."'");
        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin
           1 => array("pipe", "w"),  // stdout
           2 => array("pipe", "w")   // stderr
        );
        $process = proc_open($openssl_ocsp, $descriptorspec, $pipes);
        if (is_resource($process)) {
          fclose($pipes[0]);
          $ocsp_out = stream_get_contents($pipes[1]);
          fclose($pipes[1]);
          $ocsp_err = stream_get_contents($pipes[2]);
          fclose($pipes[2]);
          $ocsp_ecode = proc_close($process);
        }
        syslog(LOG_WARNING, __FILE__ . " : ocsp ecode: " . $ocsp_ecode . " stdout: '" . $ocsp_out . "' stderr: '" . $ocsp_err . "'");
        if ($ocsp_ecode > 0) {
            return "ocsp check command error";
        }
        # check for crt status in msg
        if (preg_match('/^' . $serial . ': good/', $ocsp_out)) {
            syslog(LOG_WARNING, __FILE__ . " : ocsp status is good ...");
            # check if signature on the OCSP response verified correctly
            if (preg_match('/^Response verify OK/', $ocsp_err)) {
                syslog(LOG_WARNING, __FILE__ . " : ocsp Response verify OK");
            } else {
                syslog(LOG_WARNING, __FILE__ . " : ocsp Response verify ERROR");
                return "signature check on the OCSP response failed";
            }
        } else if (preg_match('/^' . $serial . ': revoked/', $ocsp_out)) {
            syslog(LOG_WARNING, __FILE__ . " : ocsp status is revoked");
            return "ocsp check result is 'revoked' for serial " . $serial;
        } else {
            syslog(LOG_WARNING, __FILE__ . " : ocsp status is undefined");
            return "ocsp check result is 'undefined' for serial " . $serial;
        }
      }
    }

    return true;
}

openlog("openvpn", LOG_ODELAY, LOG_AUTH);
$response = do_verify(getenv('auth_server'));
if ($response !== true) {
    syslog(LOG_WARNING, $response);
    closelog();
    exit(1);
} else {
    closelog();
    exit(0);
}
