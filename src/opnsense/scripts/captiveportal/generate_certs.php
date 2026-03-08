#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2015-2026 Deciso B.V.
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

use OPNsense\CaptivePortal\CaptivePortal;
use OPNsense\Core\File;
use OPNsense\Trust\Store;

$filenames = [];
foreach ((new CaptivePortal())->zones->zone->iterateItems() as $zone) {
    if (($cert = Store::getCertificate($zone->certificate->getValue())) && isset($cert['prv'])) {
        $filename = "/var/etc/cert-cp-zone{$zone->zoneid}.pem";
        File::file_update_contents($filename, $cert['crt'] . $cert['prv'], 0600);
        $filenames[] = $filename;
        echo "certificate generated " . $filename . "\n";
        if (!empty($cert['ca'])) {
            $filename = "/var/etc/ca-cp-zone{$zone->zoneid}.pem";
            File::file_update_contents($filename, $cert['ca']['crt'], 0600);
            $filenames[] = $filename;
            echo "certificate generated " . $filename . "\n";
        }
    }
}

// cleanup old/unused certs
foreach (glob("/var/etc/*-cp-zone*.pem", GLOB_BRACE) as $filename) {
    if (!in_array($filename, $filenames)) {
        unlink($filename);
    }
}
