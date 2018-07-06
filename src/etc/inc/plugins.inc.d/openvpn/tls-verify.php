#!/usr/local/bin/php
<?php

/*
    Copyright (C) 2011 Jim Pingle <jimp@pfsense.org>
    Copyright (C) 2018 Deciso B.V.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

/*
 * OpenVPN calls this script to validate a certificate
 *  This script is called ONCE per DEPTH of the certificate chain
 *  Normal operation would have two runs - one for the server certificate
 *  and one for the client certificate. Beyond that, you're dealing with
 *  intermediates.
 */

openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* read data from command line */
$cert_depth = intval($argv[1]);
$cert_subject = $argv[2];

if (isset($allowed_depth) && ($cert_depth > $allowed_depth)) {
    syslog(LOG_WARNING, "Certificate depth {$cert_depth} exceeded max allowed depth of {$allowed_depth}.\n");
    closelog();
    exit(1);
}

closelog();
exit(0);
