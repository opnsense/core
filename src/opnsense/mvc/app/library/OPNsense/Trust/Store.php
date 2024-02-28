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
 * Wrapper around [legacy] trust store
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
     * Create a new certificate, when signed by a CA, make sure to serialize the config after doing so,
     * it's the callers responsibility to update the serial number administration of the supplied CA.
     * A call to \OPNsense\Core\Config::getInstance()->save(); would persist the new serial.
     *
     * @param string $keylen_curve rsa key length or elliptic curve name to use
     * @param int $lifetime in number of days
     * @param array $dn subject to use
     * @param string $digest_alg digest algorithm
     * @param string $caref key to certificate authority
     * @param string $x509_extensions openssl section to use
     * @param array $extns template fragments to replace in openssl.cnf
     * @return array containing generated certificate or returned errors
     */
    public static function createCert(
        $keylen_curve,
        $lifetime,
        $dn,
        $digest_alg,
        $caref = null,
        $x509_extensions = 'usr_cert',
        $extns = []
    ) {
        $result = [];
        $ca = null;
        $ca_res_crt = null;
        $old_err_level = error_reporting(0); /* prevent openssl error from going to stderr/stdout */
        if ($caref !== null) {
            $ca = self::getCA($caref);
            if ($ca == null || empty((string)$ca->prv)) {
                $result = ['error' => 'missing CA key'];
            }
            $ca_res_crt = openssl_x509_read(base64_decode($ca->crt));
            $ca_res_key = openssl_pkey_get_private(array(0 => base64_decode($ca->prv), 1 => ""));
            if (!$ca_res_key) {
                $result = ['error' => 'invalid CA'];
            }
        }
        if (!empty($result)) {
            error_reporting($old_err_level);
            return $result;
        }

        // handle parameters which can only be set via the configuration file
        $config_filename = create_temp_openssl_config($extns);
        $args = [
            'config' => $config_filename,
            'x509_extensions' => $x509_extensions,
            'digest_alg' => $digest_alg,
            'encrypt_key' => false
        ];
        if (is_numeric($keylen_curve)) {
            $args['private_key_type'] = OPENSSL_KEYTYPE_RSA;
            $args['private_key_bits'] = (int)$keylen_curve;
        } else {
            $args['private_key_type'] = OPENSSL_KEYTYPE_EC;
            $args['curve_name'] = $keylen_curve;
        }

        // generate a new key pair
        $res_key = openssl_pkey_new($args);
        if ($res_key !== false) {
            $res_csr = openssl_csr_new($dn, $res_key, $args);
            if ($res_csr !== false) {
                // self sign the certificate
                $res_crt = openssl_csr_sign(
                    $res_csr,
                    $ca_res_crt,
                    $ca_res_key ?? $res_key,
                    $lifetime,
                    $args,
                    $ca_serial
                );
                if (openssl_pkey_export($res_key, $str_key) && openssl_x509_export($res_crt, $str_crt)) {
                    $result = ['caref' => $caref, 'crt' => $str_crt, 'prv' =>  $str_key];
                    if ($ca !== null) {
                        $ca->serial = (int)$ca->serial + 1;
                    }
                }
            }
        }
        /* something went wrong, return openssl error */
        if (empty($result)){
            $result['error'] = '';
            while ($ssl_err = openssl_error_string()) {
                $result['error'] .= " " . $ssl_err;
            }
        }

        // remove tempfile (template)
        @unlink($config_filename);
        error_reporting($old_err_level);
        return $result;
    }

    /**
     * Extract certificate info into easy to use flattened chunks
     * @return array|bool
     */
    public static function parseX509($cert)
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

        $crt = @openssl_x509_parse($cert);
        if ($crt !== null) {
            $result = [];
            // valid from/to and name of this cert
            $result['valid_from'] = $crt['validFrom_time_t'];
            $result['valid_to'] = $crt['validTo_time_t'];
            $result['name'] = $crt['name'];
            foreach ($issue_map as $key => $target) {
                if (!empty($crt['issuer'][$key])) {
                    $result[$target] = $crt['issuer'][$key];
                }
            }
            // OCSP URI
            if (!empty($crt['extensions']) && !empty($crt['extensions']['authorityInfoAccess'])) {
                foreach (explode("\n", $crt['extensions']['authorityInfoAccess']) as $line) {
                    if (str_starts_with($line, 'OCSP - URI')) {
                        $result['ocsp_uri'] = explode(":", $line, 2)[1];
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
                    $result[$target] = implode('\n', $values);
                }
            }
            return $result;
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

    /**
     * Create a temporary config file, to help with calls that require properties that can only be set via the config file.
     *
     * @param $dn
     * @return string The name of the temporary config file.
     */
    public static function createTempOpenSSLconfig($extns = [])
    {
        // define temp filename to use for openssl.cnf and add extensions values to it
        $configFilename = tempnam(sys_get_temp_dir(), 'ssl');

        $template = file_get_contents('/usr/local/etc/ssl/opnsense.cnf');

        foreach (array_keys($extns) as $extnTag) {
            $template_extn = $extnTag . ' = ' . str_replace(array("\r", "\n"), '', $extns[$extnTag]);
            // Overwrite the placeholders for this property
            $template = str_replace('###OPNsense:' . $extnTag . '###', $template_extn, $template);
        }
        file_put_contents($configFilename, $template);
        return $configFilename;
    }
}
