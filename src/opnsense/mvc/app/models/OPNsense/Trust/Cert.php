<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Trust;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class Cert
 * @package OPNsense\Trust
 */
class Cert extends BaseModel
{
    /**
     * compare subject to issuer (subject fits within issuer DN)
     * @param array $subject to find
     * @param array $issuer to match on
     * @return bool
     */
    private function compare_issuer(array $subject, array $issuer): bool
    {
        return empty(array_diff(
            array_map('serialize', $subject),
            array_map('serialize', $issuer)
        ));
    }

    /**
     * link certificates to ca's in our trust store (based on issuer)
     * @param string $refid optional certificate reference id to link
     * @param null|Ca $ca_mdl optional Ca model to use, comstructs one when not offered
     * @return void
     */
    public function linkCaRefs(?string $refid = null, mixed $ca_mdl = null): void
    {
        $ca_subjects = [];
        foreach ($this->cert->iterateItems() as $cert) {
            if ($refid != null && $cert->refid != $refid) {
                continue;
            }
            $cert_x509 = openssl_x509_parse((string)$cert->crt_payload);
            if ($cert_x509 === false) {
                continue;
            }
            if (empty($ca_subjects)) {
                /* collect on first item for matching */
                $mdl = $ca_mdl == null ? new Ca() : $ca_mdl;
                foreach ($mdl->ca->iterateItems() as $ca) {
                    $x509 = openssl_x509_parse((string)$ca->crt_payload);
                    if ($x509 === false) {
                        continue;
                    }
                    /* add sort key, longer paths should match earlier. e.g. NL,ZH should precede NL */
                    $key = sprintf('%04d-%s', count($x509['subject']), $ca->refid);
                    $ca_subjects[$key] = ['subject' => $x509['subject'], 'caref' => (string)$ca->refid];
                }
                krsort($ca_subjects);
            }
            foreach ($ca_subjects as $caref => $item) {
                if ($this->compare_issuer($item['subject'], $cert_x509['issuer'])) {
                    $cert->caref = $item['caref'];
                    break;
                }
            }
        }
    }
}
