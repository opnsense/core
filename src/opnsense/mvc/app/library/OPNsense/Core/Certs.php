<?php

/**
 *    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 *    Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
 *    Copyright (C) 2017 Smart-Soft
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Core;

use \OPNsense\Core\Backend;

/**
 * Manipulation with certificates
 * Class Certs
 * @package OPNsense\Core
 */
class Certs
{
    /**
     * The status of certificate revocation
     * @var array
     */
    public static $openssl_crl_status = array(
        OCSP_REVOKED_STATUS_NOSTATUS => "No Status (default)",
        OCSP_REVOKED_STATUS_UNSPECIFIED => "Unspecified",
        OCSP_REVOKED_STATUS_KEYCOMPROMISE => "Key Compromise",
        OCSP_REVOKED_STATUS_CACOMPROMISE => "CA Compromise",
        OCSP_REVOKED_STATUS_AFFILIATIONCHANGED => "Affiliation Changed",
        OCSP_REVOKED_STATUS_SUPERSEDED => "Superseded",
        OCSP_REVOKED_STATUS_CESSATIONOFOPERATION => "Cessation of Operation",
        OCSP_REVOKED_STATUS_CERTIFICATEHOLD => "Certificate Hold"
    );

    /**
     * Obtaining information about the certificate Issuer
     * @param $str_crt
     * @param bool $decode
     * @return string
     */
    public static function cert_get_issuer($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }
        $inf_crt = openssl_x509_parse($str_crt);
        $components = $inf_crt['issuer'];

        if (empty($components) || !is_array($components)) {
            return "unknown";
        }
        ksort($components);
        $issuer = "";
        foreach ($components as $a => $v) {
            if (is_array($v)) {
                ksort($v);
                foreach ($v as $w) {
                    $aissuer = "{$a}={$w}";
                    $issuer = (isset($issuer)) ? "{$aissuer}, {$issuer}" : $aissuer;
                }
            } else {
                $aissuer = "{$a}={$v}";
                $issuer = (isset($issuer)) ? "{$aissuer}, {$issuer}" : $aissuer;
            }
        }

        return $issuer;
    }

    /**
     * Obtaining a certificate subject
     * @param $str_crt
     * @param bool $decode
     * @return string
     */
    public static function cert_get_subject($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $inf_crt = openssl_x509_parse($str_crt);
        $components = $inf_crt['subject'];

        if (empty($components) || !is_array($components)) {
            return "unknown";
        }

        ksort($components);
        $subject = "";
        foreach ($components as $a => $v) {
            if (is_array($v)) {
                ksort($v);
                foreach ($v as $w) {
                    $asubject = "{$a}={$w}";
                    $subject = (isset($subject)) ? "{$asubject}, {$subject}" : $asubject;
                }
            } else {
                $asubject = "{$a}={$v}";
                $subject = (isset($subject)) ? "{$asubject}, {$subject}" : $asubject;
            }
        }

        return $subject;
    }

    /**
     * Obtaining of the certificate's validity period
     * @param $str_crt
     * @param bool $decode
     * @return array
     */
    public static function cert_get_dates($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }
        $crt_details = openssl_x509_parse($str_crt);
        $start = "";
        if ($crt_details['validFrom_time_t'] > 0) {
            $start = date('r', $crt_details['validFrom_time_t']);
        }
        $end = "";
        if ($crt_details['validTo_time_t'] > 0) {
            $end = date('r', $crt_details['validTo_time_t']);
        }
        return array($start, $end);
    }

    /**
     * Get purpose of the certificate
     * @param $str_crt
     * @param bool $decode
     * @return array
     */
    public static function cert_get_purpose($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $crt_details = openssl_x509_parse($str_crt);
        $purpose = array();
        $purpose['ca'] = (stristr($crt_details['extensions']['basicConstraints'], 'CA:TRUE') === false) ? 'No' : 'Yes';
        if (isset($crt_details['extensions']['nsCertType']) && $crt_details['extensions']['nsCertType'] == "SSL Server") {
            $purpose['server'] = 'Yes';
        } else {
            $purpose['server'] = 'No';
        }
        return $purpose;
    }

    /**
     * Obtaining request subject to signature
     * Obtaining a certificate subject
     * @param $str_crt
     * @param bool $decode
     * @return string
     */
    public static function csr_get_subject($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        $components = openssl_csr_get_subject($str_crt);
        if (empty($components) || !is_array($components)) {
            return "unknown";
        }

        $subject = "";
        ksort($components);
        foreach ($components as $a => $v) {
            if (!strlen($subject)) {
                $subject = "{$a}={$v}";
            } else {
                $subject = "{$a}={$v}, {$subject}";
            }
        }

        return $subject;
    }

    /**
     * Obtaining a serial number of the certificate
     * @param $str_crt
     * @param bool $decode
     * @return null
     */
    public static function cert_get_serial($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }
        $crt_details = openssl_x509_parse($str_crt);
        if (isset($crt_details['serialNumber']) && !empty($crt_details['serialNumber'])) {
            return $crt_details['serialNumber'];
        }
        return null;
    }


    /**
     * Receiving modulus of the certificate
     * @param $str_crt
     * @param bool $decode
     * @return string
     * @throws \Exception
     */
    public static function cert_get_modulus($str_crt, $decode = true)
    {
        if ($decode) {
            $str_crt = base64_decode($str_crt);
        }

        if (!($pub_key = openssl_pkey_get_public($str_crt))) {
            return "";
        }
        if (!($keyData = openssl_pkey_get_details($pub_key)) || $keyData["type"] != OPENSSL_KEYTYPE_RSA) {
            return "";
        }
        return $keyData["rsa"]["n"];
    }


    /**
     * Compare certificates
     * @param $cert1
     * @param $cert2
     * @return bool
     * @throws \Exception
     */
    public static function cert_compare($cert1, $cert2)
    {
        $c1 = base64_decode($cert1->crt->__toString());
        $c2 = base64_decode($cert2->crt->__toString());
        return (self::cert_get_issuer($c1, false) == self::cert_get_issuer($c2, false))
            && (self::cert_get_subject($c1, false) == self::cert_get_subject($c2, false))
            && (self::cert_get_serial($c1, false) == self::cert_get_serial($c2, false))
            && (self::cert_get_modulus($c1, false) == self::cert_get_modulus($c2, false));
    }

    /**
     * The certificate is used in Web GUI ?
     * @param $certuuid
     * @return bool
     */
    public static function is_webgui_cert($certuuid)
    {
        $webgui = Config::getInstance()->object()->system->webgui;
        return $webgui->{"ssl-certref"}->__toString() == $certuuid && $webgui->protocol->__toString() != 'http';
    }

    /**
     * The certificate is a user ?
     * @param $certuuid
     * @return bool
     */
    public static function is_user_cert($certuuid)
    {
        foreach (Config::getInstance()->object()->system->user as $user) {
            foreach ($user->cert as $cert) {
                if ($certuuid == $cert) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The certificate is used in OpenVPN server ?
     * @param $certuuid
     * @return bool
     */
    public static function is_openvpn_server_cert($certuuid)
    {
        if (!Config::getInstance()->object()->openvpn->{"openvpn-server"}) {
            return false;
        }
        foreach (Config::getInstance()->object()->openvpn->{"openvpn-server"} as $ovpns) {
            if ($ovpns->certref->__toString() == $certuuid) {
                return true;
            }
        }
        return false;
    }

    /**
     * The certificate is used in OpenVPN client ?
     * @param $certuuid
     * @return bool
     */
    public static function is_openvpn_client_cert($certuuid)
    {
        if (!Config::getInstance()->object()->openvpn->{"openvpn-client"}) {
            return false;
        }
        foreach (Config::getInstance()->object()->openvpn->{"openvpn-client"} as $ovpnc) {
            if ($ovpnc->certref->__toString() == $certuuid) {
                return true;
            }
        }
        return false;
    }

    /**
     * The certificate is used in IPSEC ?
     * @param $certuuid
     * @return bool
     */
    public static function is_ipsec_cert($certuuid)
    {
        if (!Config::getInstance()->object()->ipsec->phase1) {
            return false;
        }

        foreach (Config::getInstance()->object()->ipsec->phase1 as $ipsec) {
            if ($ipsec->certref->__toString() == $certuuid) {
                return true;
            }
        }
        return false;
    }

    /**
     * The certificate is used ?
     * @param $certuuid
     * @return bool
     */
    public static function cert_in_use($certuuid)
    {
        return (self::is_webgui_cert($certuuid) ||
            self::is_user_cert($certuuid) ||
            self::is_openvpn_server_cert($certuuid) ||
            self::is_openvpn_client_cert($certuuid) ||
            self::is_ipsec_cert($certuuid));
    }

    /**
     * Certification revocation list is internal ?
     * @param $crl
     * @return bool
     */
    public static function is_crl_internal($crl)
    {
        return (empty($crl->text->__toString()) || ($crl->method->__toString() == "internal"));
    }

    /**
     * Certification revocation list is used in OpenVPN server ?
     * @param $crlref
     * @return bool
     */
    public static function is_openvpn_server_crl($crlref)
    {
        if (!isset(Config::getInstance()->toArray()['openvpn']['openvpn-server']) || !is_array(Config::getInstance()->toArray()['openvpn']['openvpn-server'])) {
            return false;
        }
        foreach (Config::getInstance()->toArray(array_flip(["openvpn-server"]))['openvpn']['openvpn-server'] as $ovpns) {
            if (!empty($ovpns['crlref']) && ($ovpns['crlref'] == $crlref)) {
                return true;
            }
        }
        return false;
    }

    /**
     * To the list of countries for dropdown
     * @param bool $country
     * @return array
     */
    public static function get_country_codes($country = false)
    {
        $dn_cc = array();

        $iso3166_tab = '/usr/local/opnsense/contrib/tzdata/iso3166.tab';
        if (file_exists($iso3166_tab)) {
            $dn_cc_file = file($iso3166_tab);
            foreach ($dn_cc_file as $line) {
                if (preg_match('/^([A-Z][A-Z])\t(.*)$/', $line, $matches)) {
                    $dn_cc[$matches[1]] = ["value" => $matches[1] . " (" . trim($matches[2]) . ")", "selected" => "0"];
                }
            }
        }
        if ($country) {
            $dn_cc[$country]["selected"] = "1";
        }
        return $dn_cc;
    }

    /**
     * To get a list of subject certificate
     * @param $crt
     * @return array|bool
     */
    public static function cert_get_subject_array($crt)
    {
        $str_crt = base64_decode($crt);
        $inf_crt = openssl_x509_parse($str_crt);
        $components = $inf_crt['subject'];

        if (!is_array($components)) {
            return false;
        }

        $subject_array = array();

        foreach ($components as $a => $v) {
            $subject_array[] = array('a' => $a, 'v' => $v);
        }

        return $subject_array;
    }
}
