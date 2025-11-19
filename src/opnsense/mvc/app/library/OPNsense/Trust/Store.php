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

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

/**
 * Wrapper around [legacy] trust store
 * @package OPNsense\Trust
 */
class Store
{
    private static $issuer_map = [
        'L' => 'city',
        'ST' => 'state',
        'O' => 'organization',
        'OU' => 'organizationalunit',
        'C' => 'country',
        'emailAddress' => 'email',
        'CN' => 'commonname',
    ];

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
     * Return
     * @param string $caref reference number
     * @return array|bool structure or boolean false if not found
     */
    public static function getCACertificate($caref)
    {
        $ca = self::getCA($caref);
        if ($ca !== null) {
            $result = ['cert' => self::cleanCert(base64_decode($ca->crt))];
            if (!empty((string)$ca->prv)) {
                $result['prv'] = self::cleanCert(base64_decode($ca->prv));
            }
            return $result;
        }
        return false;
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
     * Sign a certificate, when signed by a CA, make sure to serialize the config after doing so,
     * it's the callers responsibility to update the serial number administration of the supplied CA.
     * A call to \OPNsense\Core\Config::getInstance()->save(); would persist the new serial.
     *
     * @param OpenSSLCertificateSigningRequest|string $csr CSR to sign
     * @param string|OpenSSLAsymmetricKey $caref key to certificate authority, OpenSSLAsymmetricKey for self signed
     * @param int $lifetime in number of days
     * @param array|null You can by options. See openssl_csr_new() for more information about options.
     * @return array containing generated certificate or returned errors
     */
    private static function _signCert($csr, $caref, $lifetime, $options = null)
    {
        $ca = null;
        $ca_res_crt = null;
        $ca_res_key = $caref; /* squelch a coverity report about unreachable value check */
        if (is_string($caref)) {
            $ca = self::getCA($caref);
            if ($ca == null || empty((string)$ca->prv)) {
                return ['error' => 'missing CA key'];
            }
            $ca_res_crt = openssl_x509_read(base64_decode($ca->crt));
            $ca_res_key = openssl_pkey_get_private(array(0 => base64_decode($ca->prv), 1 => ""));
            if (!$ca_res_key) {
                return ['error' => 'invalid CA'];
            }
        }
        if ($ca !== null) {
            $ca->serial = (int)$ca->serial + 1;
        }

        //  sign the certificate, either with a supplied ca or self-signed
        $res_crt = openssl_csr_sign(
            $csr,
            $ca_res_crt,
            $ca_res_key,
            $lifetime,
            $options,
            $ca !== null ? (int)$ca->serial : 0
        );
        if (openssl_x509_export($res_crt, $str_crt)) {
            return ['crt' =>  $str_crt];
        }
        return [];
    }

    /**
     * create openssl options config including configuration file to use
     * @param string $keylen_curve rsa key length or elliptic curve name to use
     * @param string $digest_alg digest algorithm
     * @param string $x509_extensions openssl section to use
     * @param array $extns template fragments to replace in openssl.cnf
     * @return array needed for certificate generation, caller is responsible for removing ['filename'] after use
     */
    private static function _createSSLOptions($keylen_curve, $digest_alg, $x509_extensions = 'usr_cert', $extns = [])
    {
        // define temp filename to use for openssl.cnf and add extensions values to it
        $configFilename = tempnam((new AppConfig())->application->tempDir, 'ssl');

        $template = file_get_contents('/usr/local/etc/ssl/opnsense.cnf');
        foreach (array_keys($extns) as $extnTag) {
            $template_extn = $extnTag . ' = ' . str_replace(array("\r", "\n"), '', $extns[$extnTag]);
            // Overwrite the placeholders for this property
            $template = str_replace('###OPNsense:' . $extnTag . '###', $template_extn, $template);
        }
        file_put_contents($configFilename, $template);

        $args = [
            'config' => $configFilename,
            'digest_alg' => $digest_alg,
            'encrypt_key' => false
        ];
        if ($x509_extensions == 'v3_req') {
            /* v3_req is a request template, feed into req_extensions */
            $args['req_extensions'] = $x509_extensions;
        } else {
            $args['x509_extensions'] = $x509_extensions;
        }
        if (is_numeric($keylen_curve)) {
            $args['private_key_type'] = OPENSSL_KEYTYPE_RSA;
            $args['private_key_bits'] = (int)$keylen_curve;
        } else {
            $args['private_key_type'] = OPENSSL_KEYTYPE_EC;
            $args['curve_name'] = $keylen_curve;
        }

        return $args;
    }

    /**
     * @param array to add 'error' result to when openssl_error_string returns data
     */
    private static function _addSSLErrors(&$arr)
    {
        $data = '';
        while ($ssl_err = openssl_error_string()) {
            $data .= " " . $ssl_err;
        }
        if (!empty(trim($data))) {
            if (!isset($arr['error'])) {
                $arr['error'] = $data;
            } else {
                $arr['error'] .=  ("\n" . $data);
            }
        }
    }

    /**
     * Sign a certificate, when signed by a CA, make sure to serialize the config after doing so,
     * it's the callers responsibility to update the serial number administration of the supplied CA.
     * A call to \OPNsense\Core\Config::getInstance()->save(); would persist the new serial.
     *
     * @param string $keylen_curve rsa key length or elliptic curve name to use
     * @param int $lifetime in number of days
     * @param string $csr certificate signing request
     * @param string $digest_alg digest algorithm
     * @param string $caref key to certificate authority
     * @param string $x509_extensions openssl section to use
     * @param array $extns template fragments to replace in openssl.cnf
     * @return array containing generated certificate or returned errors
     */
    public static function signCert(
        $keylen_curve,
        $lifetime,
        $csr,
        $digest_alg,
        $caref,
        $x509_extensions = 'usr_cert',
        $extns = []
    ) {
        $old_err_level = error_reporting(0); /* prevent openssl error from going to stderr/stdout */

        $args = self::_createSSLOptions($keylen_curve, $digest_alg, $x509_extensions, $extns);
        $result = self::_signCert($csr, $caref, $lifetime, $args);

        self::_addSSLErrors($result);

        // remove tempfile (template)
        @unlink($args['filename']);
        error_reporting($old_err_level);
        return $result;
    }

    /**
     * re-issue a certificate, when signed by a CA, make sure to serialize the config after doing so,
     * it's the callers responsibility to update the serial number administration of the supplied CA.
     * A call to \OPNsense\Core\Config::getInstance()->save(); would persist the new serial.
     *
     * @param string $keylen_curve rsa key length or elliptic curve name to use
     * @param int $lifetime in number of days
     * @param array $dn subject to use
     * @param string $prv private key
     * @param string $digest_alg digest algorithm
     * @param string $caref key to certificate authority
     * @param string $x509_extensions openssl section to use
     * @param array $extns template fragments to replace in openssl.cnf
     * @return array containing generated certificate or returned errors
     */
    public static function reIssueCert(
        $keylen_curve,
        $lifetime,
        $dn,
        $prv,
        $digest_alg,
        $caref,
        $x509_extensions = 'usr_cert',
        $extns = []
    ) {
        $old_err_level = error_reporting(0); /* prevent openssl error from going to stderr/stdout */

        $args = self::_createSSLOptions($keylen_curve, $digest_alg, $x509_extensions, $extns);
        $csr = openssl_csr_new($dn, $prv, $args);
        if ($csr !== false) {
            $result = self::_signCert($csr, $caref, $lifetime, $args);
        } else {
            $result = [];
        }
        self::_addSSLErrors($result);

        // remove tempfile (template)
        @unlink($args['filename']);
        error_reporting($old_err_level);
        return $result;
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
     * @param string|bool $caref key to certificate authority, null for self signed, false for csr only
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
        $old_err_level = error_reporting(0); /* prevent openssl error from going to stderr/stdout */
        $args = self::_createSSLOptions($keylen_curve, $digest_alg, $x509_extensions, $extns);

        // generate a new key pair
        $res_key = openssl_pkey_new($args);
        if ($res_key !== false && openssl_pkey_export($res_key, $str_key)) {
            $res_csr = openssl_csr_new($dn, $res_key, $args);
            if ($res_csr !== false) {
                if ($caref !== false) {
                    $tmp = self::_signCert($res_csr, !empty($caref) ? $caref : $res_key, $lifetime, $args);
                    if (!empty($tmp['crt'])) {
                        $result = ['caref' => $caref, 'crt' => $tmp['crt'], 'prv' =>  $str_key];
                    } else {
                        $result = $tmp;
                    }
                } else {
                    // return signing request (externally signed)
                    if (openssl_csr_export($res_csr, $str_csr)) {
                        $result = ['caref' => $caref, 'csr' => $str_csr, 'prv' =>  $str_key];
                    }
                }
            }
        }
        self::_addSSLErrors($result);

        // remove tempfile (template)
        @unlink($args['filename']);
        error_reporting($old_err_level);
        return $result;
    }

    /**
     * Extract csr info into easy to use flattened chunks
     * @param string csr
     * @return array|bool
     */
    public static function parseCSR($csr_str)
    {
        $csr_subj = @openssl_csr_get_subject($csr_str);
        if ($csr_subj) {
            $result = ['name' => ''];
            foreach (self::$issuer_map as $key => $target) {
                if (!empty($csr_subj[$key])) {
                    $result[$target] = $csr_subj[$key];
                }
            }
            foreach ($csr_subj as $key => $value) {
                $result['name'] .= ('/' . $key . '=' . $value);
            }

            return $result;
        }
        return false;
    }

    /**
     * Extract certificate info into easy to use flattened chunks
     * @param string certificate
     * @return array|bool
     */
    public static function parseX509($cert)
    {
        $altname_map = [
            'IP Address' => 'altnames_ip',
            'DNS' => 'altnames_dns',
            'email' => 'altnames_email',
            'URI' => 'altnames_uri',
        ];

        $crt = @openssl_x509_parse($cert);
        if ($crt) {
            $result = [];
            // valid from/to and name of this cert
            $result['valid_from'] = $crt['validFrom_time_t'];
            $result['valid_to'] = $crt['validTo_time_t'];
            foreach (['name', 'serialNumber'] as $cpy) {
                $result[$cpy] = $crt[$cpy] ?? null;
            }
            foreach (self::$issuer_map as $key => $target) {
                if (!empty($crt['subject'][$key])) {
                    $result[$target] = $crt['subject'][$key];
                }
                if (!empty($crt['issuer']) && !empty($crt['issuer'][$key])) {
                    if (empty($result['issuer'])) {
                        $result['issuer'] = [];
                    }
                    $result['issuer'][$target] = $crt['issuer'][$key];
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
                    if (!isset($altnames[$target])) {
                        $altnames[$target] = [];
                    }
                    $altnames[$target][] = $parts[1];
                }
                foreach ($altnames as $key => $values) {
                    $result[$target] = implode("\n", $values);
                }
            }

            /* Extract certificate purpose */
            $purpose = [];
            foreach (['basicConstraints', 'extendedKeyUsage', 'keyUsage', 'authorityInfoAccess'] as $ext) {
                $purpose[$ext] = [];
                if (!empty($crt['extensions'][$ext])) {
                    foreach (explode(",", $crt['extensions'][$ext]) as $item) {
                        $purpose[$ext][] = trim($item);
                    }
                }
            }

            // rfc3280 purpose definitions (+ cert_type derivative field)
            $result['rfc3280_purpose'] = '';
            if (
                in_array('TLS Web Server Authentication', $purpose['extendedKeyUsage']) &&
                in_array('Digital Signature', $purpose['keyUsage']) && (
                    in_array('Key Encipherment', $purpose['keyUsage']) ||
                    in_array('Key Agreement', $purpose['keyUsage'])
                )
            ) {
                $result['rfc3280_purpose'] = 'id-kp-serverAuth';
                $both = in_array('TLS Web Client Authentication', $purpose['extendedKeyUsage']);
                $result['cert_type'] = $both ? 'combined_server_client' : 'server_cert';
            } elseif (
                in_array('TLS Web Client Authentication', $purpose['extendedKeyUsage']) &&
                in_array('Digital Signature', $purpose['keyUsage'])
            ) {
                $result['rfc3280_purpose'] = 'id-kp-clientAuth';
                $result['cert_type'] = 'usr_cert';
            } elseif (
                in_array('OCSP Signing', $purpose['extendedKeyUsage']) &&
                in_array('Digital Signature', $purpose['keyUsage'])
            ) {
                $result['rfc3280_purpose'] = 'id-kp-OCSPSigning';
            }

            return $result;
        }
        return false;
    }

    /**
     * @param string $certificate
     * @param string $private_key
     * @param string $friendly_name
     * @param string $passphrase
     * @param string $caref
     * @return string
     */
    public static function getPKCS12(
        $certificate,
        $private_key,
        $friendly_name = null,
        $passphrase = null,
        $caref = null
    ) {
        $old_err_level = error_reporting(0); /* prevent openssl error from going to stderr/stdout */
        $options = [];
        if (!empty($friendly_name)) {
            $options['friendly_name'] = $friendly_name;
        }
        if (!empty($caref) && !empty(($cas = self::getCaChain($caref, true)))) {
            $options['extracerts'] = $cas;
        }
        $result = [];
        if (!openssl_pkcs12_export($certificate, $result['payload'], $private_key, $passphrase, $options)) {
            self::_addSSLErrors($result);
        }
        error_reporting($old_err_level);
        return $result;
    }

    /**
     * wrapper around proc_open()
     * @param string $cmd command to execute
     * @param string $stdin data to push into <stdin>
     * @return array [stdout|stderr|exit_status]
     */
    private static function proc_open(string $cmd, string $stdin)
    {
        $result = ['exit_status' => -1, 'stderr' => '', 'stdout' => ''];
        $process = proc_open(
            $cmd,
            [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]],
            $pipes
        );
        if (is_resource($process)) {
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
            $result['stdout'] = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $result['stderr'] = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $result['exit_status'] = proc_close($process);
        }
        return $result;
    }

    /**
     * verify offered cert agains local trust store
     * @param string $cert certificate
     * @return array [stdout|stderr|exit_status]
     */
    public static function verify($cert)
    {
        return static::proc_open('/usr/local/bin/openssl verify', $cert);
    }

    /**
     * @param string $cert certificate
     * @return array [stdout|stderr]
     */
    public static function dumpX509($cert)
    {
        return static::proc_open('/usr/local/bin/openssl x509 -fingerprint -sha256 -text', $cert);
    }

    /**
     * @param string $csr CSR
     * @return array [stdout|stderr]
     */
    public static function dumpCSR($csr)
    {
        return static::proc_open('/usr/local/bin/openssl req -text -noout', $csr);
    }

    /**
     * @param string $cert certificate
     * @return array [stdout|stderr]
     */
    public static function dumpCRL($cert)
    {
        return static::proc_open('/usr/local/bin/openssl crl -fingerprint -sha256 -text', $cert);
    }

    /**
     * Extract certificate chain
     * @param string $caref reference number
     * @param bool $aslist return array
     * @return array|string list of certificates as single string or array
     */
    public static function getCaChain($caref, $aslist = false)
    {
        $chain = [];
        while (($item = self::getCA(!isset($item) ? $caref : $item->caref)) != null) {
            $data = base64_decode((string)$item->crt);
            if (in_array($data, $chain)) {
                break; /* exit endless loop */
            }
            $chain[] = $data;
        }
        return !$aslist ? implode("\n", $chain) : $chain;
    }

    /**
     * @param $ca_filename string filename
     * @param $serial serial number to check
     * @return array
     */
    public static function ocsp_validate($ca_filename, $serial)
    {
        if (!is_file($ca_filename)) {
            return [
                'pass' => false,
                'uri' => null,
                'response' => "missing_CA_file ({$ca_filename})"
            ];
        }
        $ocsp_uri = null;
        $crt_details = openssl_x509_parse(file_get_contents($ca_filename));
        if (!empty($crt_details['extensions']) && !empty($crt_details['extensions']['authorityInfoAccess'])) {
            foreach (explode("\n", $crt_details['extensions']['authorityInfoAccess']) as $line) {
                if (str_starts_with($line, 'OCSP - URI')) {
                    $ocsp_uri = explode(":", $line, 2)[1];
                }
            }
        }
        if ($ocsp_uri !== null) {
            $verdict_pass = false;
            $result = exec(
                exec_safe(
                    "%s ocsp -resp_no_certs -timeout 10 -nonce -CAfile %s -issuer %s -url %s -serial %s 2>&1",
                    ['/usr/bin/openssl', $ca_filename, $ca_filename, $ocsp_uri, $serial]
                ),
                $output,
                $retval
            );
            foreach ($output as $line) {
                if (str_starts_with($line, "{$serial}:")) {
                    $status = trim(explode(':', $line, 2)[1]);
                    return [
                        'pass' => $status == 'good' && trim($output[0]) == 'Response verify OK',
                        'response' => $status,
                        'uri' => $ocsp_uri,
                        'verify' => $output[0]
                    ];
                }
            }
            $verdict_msg  = $output[0] ?? '';
        } else {
            $verdict_pass = true;
            $verdict_msg  = 'no OCSP configured';
        }

        return [
            'pass' => $verdict_pass,
            'uri' => $ocsp_uri,
            'response' => $verdict_msg
        ];
    }
}
