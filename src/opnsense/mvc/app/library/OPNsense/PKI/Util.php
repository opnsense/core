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
    public static function cert_get_issuer($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $inf_crt = openssl_x509_parse($str_crt);
        $components = (isset($inf_crt['issuer'])) ? $inf_crt['issuer'] : "";

        return self::certs_build_name($components);
    }

    public static function cert_get_subject($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $inf_crt = openssl_x509_parse($str_crt);
        $components = (isset($inf_crt['subject'])) ? $inf_crt['subject'] : "";

        return self::certs_build_name($components);
    }

    public static function csr_get_subject($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $components = openssl_csr_get_subject($str_crt);

        return self::certs_build_name($components);
    }

    public static function cert_get_dates($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }
        $crt_details = openssl_x509_parse($str_crt);
        $start = (!empty($crt_details['validFrom_time_t'])) ? $crt_details['validFrom_time_t'] : 0;
        $end = (!empty($crt_details['validTo_time_t'])) ? $crt_details['validTo_time_t'] : 0;
        return [$start, $end];
    }

    public static function lookup_ca($refid)
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

    public static function count_certs_of_ca($refid)
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

    public static function count_certs_of_crl($refid)
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

    private static function certs_build_name($dn)
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
     * Get purposes of a X.509 certificate
     * @param $str_crt mixed the certificate in binary or PEM format
     * @param $decode boolean wheter the $str_crt shall be Base64 decoded
     * @param $bool boolean wheter the result should contain boolean values or "Yes"/"No"
     *   for backwards compatibility
     * @return array associative array purposes with values true/false or "Yes"/"No"
     */
    public static function cert_get_purpose($str_crt, $decode = true, $bool = false)
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

    private static function lookup_crl($refid)
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

    public static function is_crl_internal($crl)
    {
        return (!(!empty($crl->text) && empty($crl->cert)) || ($crl->method == "internal"));
    }

    public static function is_openvpn_server_crl($crlref)
    {
        $config = Config::getInstance()->object();
        if (!isset($config->openvpn) || !isset($config->openvpn->{'openvpn-server'})) {
            return;
        }
        foreach ($config->openvpn->{'openvpn-server'} as $ovpns) {
            if (!empty($ovpns->crlref) && ((string)$ovpns->crlref == $crlref)) {
                return true;
            }
        }
        return false;
    }

    private static function cert_get_serial($str_crt, $decode = true)
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
     * this function works on x509 (crt), rsa key (prv), and req(csr)
     */
    private static function cert_get_modulus($str_crt, $decode = true, $type = 'crt')
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
     *  Compare two certificates to see if they match.
     */
    private static function cert_compare($cert1, $cert2)
    {
        /* Ensure two certs are identical by first checking that their issuers match, then
          subjects, then serial numbers, and finally the moduli. Anything less strict
          could accidentally count two similar, but different, certificates as
          being identical. */
        $c1 = base64_decode((string)$cert1->crt);
        $c2 = base64_decode((string)$cert2->crt);
        if (
            self::cert_get_issuer($c1, false) == self::cert_get_issuer($c2, false)
            && self::cert_get_subject($c1, false) == self::cert_get_subject($c2, false)
            && self::cert_get_serial($c1, false) == self::cert_get_serial($c2, false)
            && self::cert_get_modulus($c1, false) == self::cert_get_modulus($c2, false)
        ) {
            return true;
        } else {
            return false;
        }
    }

    public static function is_webgui_cert($certref)
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
        return $config->system->webgui->{'ssl-certref'} == $certref && $config->system->webgui->protocol == "https";
    }

    public static function is_cert_revoked($cert, $crlref = "")
    {
        $config = Config::getInstance()->object();
        if (!isset($config->crl)) {
            return false;
        }

        if (!empty($crlref)) {
            $crl = self::lookup_crl($crlref);
            if (!isset($crl->cert)) {
                return false;
            }
            foreach ($crl->cert as $rcert) {
                if (self::cert_compare($rcert, $cert)) {
                    return true;
                }
            }
        } else {
            foreach ($config->crl as $crl) {
                if (!isset($crl->cert)) {
                    continue;
                }
                foreach ($crl->cert as $rcert) {
                    if (self::cert_compare($rcert, $cert)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function is_user_cert($certref)
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
                if ($certref == $cert) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function is_openvpn_server_cert($certref)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->openvpn) || !isset($config->openvpn->{'openvpn-server'})) {
            return false;
        }

        foreach ($config->openvpn->{'openvpn-server'} as $ovpns) {
            if (isset($ovpns->certref) && $ovpns->certref == $certref) {
                return true;
            }
        }

        return false;
    }

    public static function is_openvpn_client_cert($certref)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->openvpn) || !isset($config->openvpn->{'openvpn-client'})) {
            return false;
        }

        foreach ($config->openvpn->{'openvpn-client'} as $ovpnc) {
            if (isset($ovpnc->certref) && $ovpnc->certref == $certref) {
                return true;
            }
        }

        return false;
    }

    public static function is_ipsec_cert($certref)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->ipsec) || !isset($config->ipsec->phase1)) {
            return false;
        }

        foreach ($config->ipsec->phase1 as $ipsec) {
            if ($ipsec->certref == $certref) {
                return true;
            }
        }

        return false;
    }

    public static function cert_in_use($certref)
    {
        return (
            self::is_webgui_cert($certref)
            || self::is_user_cert($certref)
            || self::is_openvpn_server_cert($certref)
            || self::is_openvpn_client_cert($certref)
            || self::is_ipsec_cert($certref)
        );
    }
}