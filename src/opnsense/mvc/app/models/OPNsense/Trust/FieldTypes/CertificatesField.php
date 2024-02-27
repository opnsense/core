<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Trust\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;

class CertificatesField extends ArrayField
{
    protected function actionPostLoadingEvent()
    {
        $issue_map = [
            'L' => 'city',
            'ST' => 'state',
            'O' => 'organization',
            'C' => 'country',
            'emailAddress' => 'email',
            'CN' => 'commonname'
        ];
        $altname_map = [
            'IP Address' => 'altnames_ip',
            'DNS' => 'altnames_dns',
            'email' => 'altnames_email',
            'URI' => 'altnames_uri',
        ];
        foreach ($this->internalChildnodes as $node) {
            $cert_data = base64_decode($node->crt);
            if (!empty($cert_data)) {
                $crt = @openssl_x509_parse($cert_data);
                if ($crt !== null) {
                    // valid from/to
                    $node->valid_from = $crt['validFrom_time_t'];
                    $node->valid_to = $crt['validTo_time_t'];
                    foreach ($issue_map as $key => $target) {
                        if (!empty($crt['issuer'][$key])) {
                            $node->$target = $crt['issuer'][$key];
                        }
                    }
                    // OCSP URI
                    if (!empty($crt['extensions']) && !empty($crt['extensions']['authorityInfoAccess'])) {
                        foreach (explode("\n", $crt['extensions']['authorityInfoAccess']) as $line) {
                            if (str_starts_with($line, 'OCSP - URI')) {
                                $node->ocsp_uri = explode(":", $line, 2)[1];
                            }
                        }
                    }
                    // Altnames
                    if (!empty($crt['extensions']) && !empty($crt['extensions']['subjectAltName'])) {
                        $altnames = [];
                        foreach (explode(',', trim($crt['extensions']['subjectAltName'])) as $altname) {
                            $parts = explode(':', trim($altname), 2);
                            $target = $altname_map[$parts[0]];
                            if (isset($altnames[$target])) {
                                $altnames[$target] = [];
                            }
                            $altnames[$target][] = $parts[1];
                        }
                        foreach ($altnames as $key => $values) {
                            $node->$target = implode('\n', $values);
                        }
                    }
                }
            }
        }
        return parent::actionPostLoadingEvent();
    }
}
