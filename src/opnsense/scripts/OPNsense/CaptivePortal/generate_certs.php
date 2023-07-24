#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

/* XXX use legacy code to generate certs and CAs */

require_once("config.inc");
require_once("certs.inc");

use OPNsense\Core\Config;

$store = new OPNsense\Trust\Store();
// traverse captive portal zones
$configObj = Config::getInstance()->object();
if (isset($configObj->OPNsense->captiveportal->zones)) {
    foreach ($configObj->OPNsense->captiveportal->zones->children() as $zone) {
        $cert = $store->getCertificate((string)$zone->certificate);
        // if the zone has a certificate attached, search for its contents
        if ($cert && !empty($cert['prv'])) {
            $output_pem_filename = "/var/etc/cert-cp-zone{$zone->zoneid}.pem";
            touch($output_pem_filename);
            chmod($output_pem_filename, 0600);
            file_put_contents($output_pem_filename, $cert['crt'] . $cert['prv']);
            echo "certificate generated " . $output_pem_filename . "\n";
            if (!empty($cert['ca'])) {
                $output_pem_filename = "/var/etc/ca-cp-zone{$zone->zoneid}.pem";
                touch($output_pem_filename);
                chmod($output_pem_filename, 0600);
                file_put_contents($output_pem_filename, $cert['ca']['crt']);
                echo "certificate generated " . $output_pem_filename  . "\n";
            }
        }
    }
}
