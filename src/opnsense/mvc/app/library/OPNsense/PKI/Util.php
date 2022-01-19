<?php

/*
 * Copyright (C) 2022 Manuel Faux <mfaux@conf.at>
 * Copyright (C) 2021 Deciso B.V.
 * Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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

namespace OPNsense\PKI;

use OPNsense\Core\Config;

/**
 * Class Util, PEM functions
 * @package OPNsense\PKI
 */
class Util
{

    /**
     * Return number of certificates in config signed by a specific CA.
     * @param string $refid the refid of the CA
     * @return int
     */
    public static function countCertsOfCa($refid)
    {
        $config = Config::getInstance()->object();
        $certcount = 0;

        if (isset($config->cert)) {
            foreach ($config->cert as $cert) {
                if ((string)$cert->caref == $refid) {
                    $certcount++;
                }
            }
        }

        if (isset($config->ca)) {
            foreach ($config->ca as $ca) {
                if (isset($ca->caref)) {
                    if ((string)$ca->caref == $refid) {
                        $certcount++;
                    }
                }
            }
        }

        return $certcount;
    }

    /**
     * Return number of certificates listed in specific CRL.
     * @param string $refid the refid of the CRL
     * @return int
     */
    public static function countCertsOfCrl($refid)
    {
        $config = Config::getInstance()->object();
        $certcount = 0;

        if (isset($config->crl)) {
            foreach ($config->crl as $crl) {
                if ((string)$crl->refid == $refid && isset($crl->cert)) {
                    foreach ($crl->cert as $cert) {
                        if (!empty($cert)) {
                            $certcount++;
                        }
                    }
                }
            }
        }

        return $certcount;
    }

    /**
     * Search CA in config and return CA config object.
     * Implementation of legacy function lookup_ca.
     * @param string $refid the refid of the CA
     * @return object CA config object
     */
    public static function getCaByRefid($refid)
    {
        $config = Config::getInstance()->object();

        if (isset($config->ca)) {
            foreach ($config->ca as $ca) {
                if ((string)$ca->refid == $refid) {
                    return $ca;
                }
            }
        }

        return false;
    }

    public static function getCertDates($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }
        $crt_details = openssl_x509_parse($str_crt);
        $start = (!empty($crt_details['validFrom_time_t'])) ? $crt_details['validFrom_time_t'] : 0;
        $end = (!empty($crt_details['validTo_time_t'])) ? $crt_details['validTo_time_t'] : 0;
        return [$start, $end];
    }

    /**
     * Get issuer name of X.509 certificate.
     * Implementation of legacy function cert_get_issuer.
     * @param string $str_crt X.509 certificate in PEM format
     * @param bool $decode true if $str_crt is base64 encoded
     * @return string issuer of certificate
     */
    public static function getCertIssuer($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $inf_crt = openssl_x509_parse($str_crt);
        $components = (isset($inf_crt['issuer'])) ? $inf_crt['issuer'] : "";

        return self::buildDistinguishedName($components);
    }

    /**
     * Get purposes of a X.509 certificate.
     * Implementation of legacy function cert_get_purpose.
     * @param string $str_crt the certificate in binary or PEM format
     * @param bool $decode wether the $str_crt shall be Base64 decoded
     * @param bool $bool wether the result should contain bool values or "Yes"/"No"
     *   for backwards compatibility
     * @return array associative array purposes with values true/false or "Yes"/"No"
     */
    public static function getCertPurpose($str_crt, $decode = true, $bool = false)
    {
        $yes = ($bool) ? true : "Yes";
        $no = ($bool) ? false : "No";
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $crt_details = openssl_x509_parse($str_crt);
        $purpose = [];
        foreach (['basicConstraints', 'extendedKeyUsage', 'keyUsage'] as $ext) {
            $purpose[$ext] = [];
            if (!empty($crt_details['extensions'][$ext])) {
                foreach (explode(",", $crt_details['extensions'][$ext]) as $item) {
                    $item = trim($item);
                    if ($bool && ($item == "Yes" || $item == "No")) {
                        $item = ($item == "Yes") ? $yes : $no;
                    }
                    $purpose[$ext][] = $item;
                }
            }
        }
        $purpose['ca'] = in_array('CA:TRUE', $purpose['basicConstraints']) ? $yes : $no;
        $purpose['client'] = in_array('TLS Web Client Authentication', $purpose['extendedKeyUsage']) ? $yes : $no;
        $purpose['server'] = in_array('TLS Web Server Authentication', $purpose['extendedKeyUsage']) ? $yes : $no;
        // rfc3280 extended key usage
        if (
            in_array('TLS Web Server Authentication', $purpose['extendedKeyUsage']) &&
            in_array('Digital Signature', $purpose['keyUsage']) && (
                in_array('Key Encipherment', $purpose['keyUsage']) ||
                in_array('Key Agreement', $purpose['keyUsage'])
            )
        ) {
            $purpose['id-kp-serverAuth'] = $yes;
        } else {
            $purpose['id-kp-serverAuth'] = $no;
        }
        return $purpose;
    }

    /**
     * Get subject of X.509 certificate.
     * Implementation of legacy function cert_get_subject.
     * @param string $str_crt X.509 certificate in PEM format
     * @param bool $decode true if $str_crt is base64 encoded
     * @return string subject of certificate
     */
    public static function getCertSubject($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $inf_crt = openssl_x509_parse($str_crt);
        $components = (isset($inf_crt['subject'])) ? $inf_crt['subject'] : "";

        return self::buildDistinguishedName($components);
    }

    public static function getCsrSubject($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $components = openssl_csr_get_subject($str_crt);

        return self::buildDistinguishedName($components);
    }

    /**
     * Check if certificate is used for WebGUI, user, OpenVPN or IPsec.
     * Implementation of legacy function cert_in_use.
     * @param string $certref the refid of the certificate
     * @return bool
     */
    public static function isCertInUse($certref)
    {
        return (
            self::isWebguiCert($certref)
            || self::isUserCert($certref)
            || self::isOpenVPNServerCert($certref)
            || self::isOpenVPNClientCert($certref)
            || self::isIPsecCert($certref)
        );
    }

    /**
     * Implementation of legacy function is_cert_revoked.
     * @param object $cert Config object of certificate
     * @param string $crlref refid of CRL to limit search to CRL
     * @return bool
     */
    public static function isCertRevoked($cert, $crlref = "")
    {
        $config = Config::getInstance()->object();
        if (!isset($config->crl)) {
            return false;
        }

        if (!empty($crlref)) {
            $crl = self::getCrlByRefid($crlref);
            if (!isset($crl->cert)) {
                return false;
            }
            foreach ($crl->cert as $rcert) {
                if (self::certCompare($rcert, $cert)) {
                    return true;
                }
            }
        } else {
            foreach ($config->crl as $crl) {
                if (!isset($crl->cert)) {
                    continue;
                }
                foreach ($crl->cert as $rcert) {
                    if (self::certCompare($rcert, $cert)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Implementation of legacy function is_crl_internal.
     * @param object $crl config object of CRL
     * @return bool
     */
    public static function isInternalCrl($crl)
    {
        return (!(!empty($crl->text) && empty($crl->cert)) || ($crl->method == "internal"));
    }

    /**
     * Check if certificate is used for IPsec.
     * Implementation of legacy function is_ipsec_cert.
     * @param string $certref the refid of the certificate
     * @return bool
     */
    public static function isIPsecCert($certref)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->ipsec) || !isset($config->ipsec->phase1)) {
            return false;
        }

        foreach ($config->ipsec->phase1 as $ipsec) {
            if ((string)$ipsec->certref == $certref) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if certificate is used for an OpenVPN client.
     * Implementation of legacy function is_openvpn_client_cert.
     * @param string $certref the refid of the certificate
     * @return bool
     */
    public static function isOpenVPNClientCert($certref)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->openvpn) || !isset($config->openvpn->{'openvpn-client'})) {
            return false;
        }

        foreach ($config->openvpn->{'openvpn-client'} as $ovpnc) {
            if (isset($ovpnc->certref) && (string)$ovpnc->certref == $certref) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if certificate is used for an OpenVPN server.
     * Implementation of legacy function is_openvpn_server_cert.
     * @param string $certref the refid of the certificate
     * @return bool
     */
    public static function isOpenVPNServerCert($certref)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->openvpn) || !isset($config->openvpn->{'openvpn-server'})) {
            return false;
        }

        foreach ($config->openvpn->{'openvpn-server'} as $ovpns) {
            if (isset($ovpns->certref) && (string)$ovpns->certref == $certref) {
                return true;
            }
        }

        return false;
    }

    /**
     * Implementation of legacy function is_openvpn_server_crl.
     * @param string $crlref refid of CRL to limit search to CRL
     * @return bool
     */
    public static function isOpenVPNServerCrl($crlref)
    {
        $config = Config::getInstance()->object();
        if (!isset($config->openvpn) || !isset($config->openvpn->{'openvpn-server'})) {
            return false;
        }
        foreach ($config->openvpn->{'openvpn-server'} as $ovpns) {
            if (!empty($ovpns->crlref) && ((string)$ovpns->crlref == $crlref)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if certificate is used as an user certificate.
     * Implementation of legacy function is_user_cert.
     * @param string $certref the refid of the certificate
     * @return bool
     */
    public static function isUserCert($certref)
    {
        $config = Config::getInstance()->object();
        if (!isset($config->system) || !isset($config->system->user)) {
            return false;
        }

        foreach ($config->system->user as $user) {
            if (!isset($user->cert)) {
                continue;
            }
            foreach ($user->cert as $cert) {
                if ((string)$certref == $cert) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if certificate is used as a WebGUI certificate.
     * Implementation of legacy function is_webgui_cert.
     * @param string $certref the refid of the certificate
     * @return bool
     */
    public static function isWebguiCert($certref)
    {
        $config = Config::getInstance()->object();
        if (
            !isset($config->system)
            || !isset($config->system->webgui)
            || !isset($config->system->webgui->{'ssl-certref'})
            || !isset($config->system->webgui->protocol)
        ) {
            return false;
        }
        return (string)$config->system->webgui->{'ssl-certref'} == $certref && $config->system->webgui->protocol == "https";
    }

    /**
     * Construct DN string from array returned by openssl functions.
     * Implementation of legacy function certs_build_name.
     */
    private static function buildDistinguishedName($dn)
    {
        if (empty($dn) || !is_array($dn)) {
            return 'unknown';
        }

        $subject = '';
        ksort($dn);

        foreach ($dn as $a => $v) {
            if (is_array($v)) {
                ksort($v);
                foreach ($v as $w) {
                    $subject = strlen($subject) ? "{$a}={$w}, {$subject}" : "{$a}={$w}";
                }
            } else {
                $subject = strlen($subject) ? "{$a}={$v}, {$subject}" : "{$a}={$v}";
            }
        }

        return $subject;
    }

    /**
     * Compare two certificates to see if they match.
     * Implementation of legacy function cert_compare.
     * @param object $cert1 config object of first certificate
     * @param object $cert2 config object of second certificate
     * @return bool true if $cert1 equals $cert2
     */
    private static function certCompare($cert1, $cert2)
    {
        /* Ensure two certs are identical by first checking that their issuers match, then
          subjects, then serial numbers, and finally the moduli. Anything less strict
          could accidentally count two similar, but different, certificates as
          being identical. */
        $c1 = base64_decode((string)$cert1->crt);
        $c2 = base64_decode((string)$cert2->crt);
        if (
            self::getCertIssuer($c1, false) == self::getCertIssuer($c2, false)
            && self::getCertSubject($c1, false) == self::getCertSubject($c2, false)
            && self::getCertSerial($c1, false) == self::getCertSerial($c2, false)
            && self::getCertModulus($c1, false) == self::getCertModulus($c2, false)
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * This function works on x509 (crt), rsa key (prv), and req (csr).
     * Implementation of legacy function cert_get_modulus.
     * @param string $str_crt the certificate in binary or PEM format
     * @param bool $decode wether the $str_crt shall be Base64 decoded
     * @param string $type "crt", "prv" or "csr" for certificate, private key or CSR
     * @return string
     */
    private static function getCertModulus($str_crt, $decode = true, $type = 'crt')
    {
        $type_list = ['crt', 'prv', 'csr'];
        $type_cmd = ['x509', 'rsa', 'req'];
        $modulus = '';

        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        if (in_array($type, $type_list)) {
            $type = str_replace($type_list, $type_cmd, $type);
            $modulus = exec(sprintf(
                'echo %s | /usr/local/bin/openssl %s -noout -modulus',
                escapeshellarg($str_crt),
                escapeshellarg($type)
            ));
        }

        return $modulus;
    }

    /**
     * Implementation of legacy function cert_get_serial.
     * @param string $str_crt the certificate in binary or PEM format
     * @param bool $decode wether the $str_crt shall be Base64 decoded
     * @return string|null
     */
    private static function getCertSerial($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }
        $crt_details = openssl_x509_parse($str_crt);
        if (isset($crt_details['serialNumber']) && !empty($crt_details['serialNumber'])) {
            return $crt_details['serialNumber'];
        } else {
            return null;
        }
    }

    /**
     * Search CRL in config and return CRL config object.
     * Implementation of legacy function lookup_crl.
     * @param string $refid the refid of the CRL
     * @return object CRL config object
     */
    private static function getCrlByRefid($refid)
    {
        $config = Config::getInstance()->object();

        if (isset($config->crl)) {
            foreach ($config->crl as & $crl) {
                if ((string)$crl->refid == $refid) {
                    return $crl;
                }
            }
        }

        return false;
    }
}