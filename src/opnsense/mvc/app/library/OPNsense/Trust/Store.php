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

namespace OPNsense\Trust;

use OPNsense\Core\Config;

/**
 * Wrapper around legacy trust store
 * @package OPNsense\Trust
 */
class Store
{
    /**
     * find CA record
     * @param string $caref
     * @return mixed
     */
    private static function getCA($caref)
    {
        if (isset(Config::getInstance()->object()->ca)) {
            foreach (Config::getInstance()->object()->ca as $cert) {
                if (isset($cert->refid) && (string)$caref == $cert->refid) {
                    return $cert;
                }
            }
        }
        return null;
    }

    /**
     * @param string $text certificate text
     * @return certificate with single unix line endings
     */
    private static function cleanCert($text)
    {
        $response = [];
        foreach (explode("\n", str_replace("\r", "", $text)) as $item) {
            if (!empty($item)) {
                $response[] = $item;
            }
        }
        return implode("\n", $response) . "\n";
    }

    /**
     * Extract certificate and its details from the store
     * @param string $certref reference number
     * @return array|bool structure or boolean false if not found
     */
    public static function getCertificate($certref)
    {
        if (isset(Config::getInstance()->object()->cert)) {
            foreach (Config::getInstance()->object()->cert as $cert) {
                if (isset($cert->refid) && $certref == $cert->refid) {
                    $response = ['is_server' => false];
                    // certificate CN
                    $str_crt = base64_decode((string)$cert->crt);
                    $inf_crt = openssl_x509_parse($str_crt);
                    if (is_array($inf_crt)) {
                        foreach ($inf_crt as $key => $val) {
                            $response[$key] = $val;
                        }
                        if (
                            isset($inf_crt['extensions']['extendedKeyUsage']) &&
                            strstr($inf_crt['extensions']['extendedKeyUsage'], 'TLS Web Server Authentication') !== false &&
                            isset($inf_crt['extensions']['keyUsage']) &&
                            strpos($inf_crt['extensions']['keyUsage'], 'Digital Signature') !== false &&
                            (strpos($inf_crt['extensions']['keyUsage'], 'Key Encipherment') !== false ||
                                strpos($inf_crt['extensions']['keyUsage'], 'Key Agreement') !== false)
                        ) {
                            $response['is_server'] = true;
                        }
                    }
                    $response['crt'] = self::cleanCert(base64_decode((string)$cert->crt));
                    if (!empty((string)$cert->prv)) {
                        $response['prv'] = self::cleanCert(base64_decode((string)$cert->prv));
                    }
                    if (!empty($chain = self::getCaChain((string)$cert->caref))) {
                        $response['ca'] = ['crt' => self::cleanCert($chain)];
                    }
                    return $response;
                }
            }
        }
        return false;
    }


    /**
     * Extract certificate chain
     * @param string $caref reference number
     * @return array|bool structure or boolean false if not found
     */
    public static function getCaChain($caref)
    {
        $chain = [];
        while (($item = self::getCA(!isset($item) ? $caref : $item->caref)) != null) {
            $chain[] = base64_decode((string)$item->crt);
        }
        return implode("\n", $chain);
    }
}
