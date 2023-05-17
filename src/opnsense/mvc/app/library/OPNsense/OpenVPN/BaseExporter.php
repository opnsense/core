<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\OpenVPN;

/**
 * Export stub file, contains shared logic for all types
 * @package OPNsense\Backup
 */
abstract class BaseExporter
{
    /**
     * @var array export config
     */
    protected $config = array();

    /**
     * @param array $conf configuration to use
     */
    public function setConfig($conf)
    {
        $this->config = $conf;
    }

    /**
     * @param string $crt X.509 certificate
     * @param string $prv PEM formatted private key
     * @param string $pass password
     * @param string|null $cas list of CA-certificates
     * @return string pkcs12
     */
    protected function export_pkcs12($crt, $prv, $pass = '', $cas = null)
    {
        $p12 = null;
        $crt = openssl_x509_read($crt);
        $prv = openssl_get_privatekey($prv);
        $args = [];
        if ($cas !== null) {
            $p12_cas = null;
            // split certificate list into separate certs
            preg_match_all(
                '/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/si',
                $cas,
                $matches
            );
            if (!empty($matches) && !empty($matches[0])) {
                $p12_cas = $matches[0];
            }

            $args = [
                'extracerts' => $p12_cas
            ];
        }
        openssl_pkcs12_export($crt, $p12, $prv, $pass, $args);
        return $p12;
    }
}
