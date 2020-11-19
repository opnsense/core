#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

// use legacy code to generate certs and ca's
require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");

use OPNsense\Core\Config;

// Our template systems stores the ca certid into /usr/local/etc/squid/ca.pem.id
// Which makes it easier for the setup script to detect cert changes (which should flush the stored cache)
if (is_file('/usr/local/etc/squid/ca.pem.id')) {
    $cert_refid = trim(file_get_contents('/usr/local/etc/squid/ca.pem.id'));
    if (!empty($config['ca'])) {
        foreach ($config['ca'] as $ca) {
            if (isset($ca['refid']) && $ca['refid'] == $cert_refid) {
                $pem_contents = '';
                $pem_contents .= trim(base64_decode($ca['prv'])) . "\n";
                $pem_contents .= trim(base64_decode($ca['crt'])) . "\n";
                $pem_contents .= ca_chain($ca);
                echo "certificate generated\n";
                file_put_contents('/var/squid/ssl/ca.pem', $pem_contents);
            }
        }
    }
}
