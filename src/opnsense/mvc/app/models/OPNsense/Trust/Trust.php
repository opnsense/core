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

namespace OPNsense\Trust;

use OPNsense\Base\BaseModel;
use \OPNsense\Core\Certs;

/**
 * Class Trust implements methods for creating and importing CA, certificates and CRL.
 * @package OPNsense\Certificate
 */
class Trust extends BaseModel
{
    /**
     * Search certificate for a given subject
     * @param $subject
     * @return bool|string
     */
    public function lookup_ca_by_subject($subject)
    {
        foreach ($this->cas->ca->getChildren() as $uuid => $ca) {
            $ca_subject = Certs::cert_get_subject($ca->crt->__toString());
            if ($ca_subject == $subject) {
                return $uuid;
            }
        }
        return false;
    }

    /**
     * Obtain all certificates that are included in the specified CRL
     * @param $uuid
     * @return array
     */
    public function get_crls_cert($uuid)
    {
        $crl_certs = [];
        foreach ($this->crl_certs->cert->getChildren() as $cert) {
            if ($cert->crluuid->__toString() == $uuid) {
                $crl_certs[] = $cert;
            }
        }
        return $crl_certs;
    }

    /**
     * Check the revocation status of a given certificate
     * @param $cert
     * @param string $crluuid
     * @return bool
     */
    public function is_cert_revoked($cert, $crluuid = "")
    {
        if (!empty($crluuid)) {
            foreach ($this->crl_certs->cert->getChildren() as $rcert) {
                if ($rcert->crluuid->__toString() == $crluuid && Certs::cert_compare($rcert, $cert)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($this->crl_certs->cert->getChildren() as $rcert) {
            if (Certs::cert_compare($rcert, $cert)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The data for the dropdown list with the list of CA
     * @param null $select
     * @return array
     */
    public function list_ca($select = null)
    {
        $cas = [];
        foreach ($this->cas->ca->getChildren() as $uuid => $ca) {
            $cas[$uuid] = ["value" => $ca->descr->__toString(), "selected" => "0"];
        }
        if ($select) {
            $cas[$select]["selected"] = "1";
        }
        return $cas;
    }

    /**
     * The update of the CRL
     * @param $crl
     * @return bool
     */
    public function crl_update($crl)
    {
        if (!($ca = $this->cas->ca->{$crl->cauuid->__toString()})) {
            return false;
        }
        $certs = $this->get_crls_cert($crl->getAttributes()["uuid"]);
        // If we have text but no certs, it was imported and cannot be updated.
        if ($crl->method->__toString() != "internal" && !empty($crl->text->__toString()) && count($certs) == 0) {
            return $crl;
        }
        $crl->serial = $crl->serial->__toString() + 1;
        $ca_str_crt = base64_decode($ca->crt->__toString());
        $ca_str_key = base64_decode($ca->prv->__toString());
        $crl_res = openssl_crl_new($ca_str_crt, $crl->serial->__toString(), (int) $crl->lifetime->__toString());
        foreach ($certs as $cert) {
            openssl_crl_revoke_cert($crl_res, base64_decode($cert->crt->__toString()), $cert->revoke_time->__toString(),
                $cert->reason->__toString());
        }
        openssl_crl_export($crl_res, $crl_text, $ca_str_key);
        $crl->text = base64_encode($crl_text);
        return $crl;
    }

    /**
     * Obtaining a certificate field
     * @param $crt
     * @param bool $isref
     * @return string
     */
    public function cert_get_cn($crt, $isref = false)
    {
        /* If this is a certref, not an actual cert, look up the cert first */
        if ($isref) {
            $cert = $this->certs->cert->{$crt};
            /* If it's not a valid cert, bail. */
            $cert = $cert->crt->__toString();
            if (empty($cert)) {
                return "";
            }
        } else {
            $cert = $crt;
        }
        $sub = Certs::cert_get_subject_array($cert);
        if (is_array($sub)) {
            foreach ($sub as $s) {
                if (strtoupper($s['a']) == "CN") {
                    return $s['v'];
                }
            }
        }
        return "";
    }

    /**
     * Import or update the certificate with the specified crt and key
     * @param $descr
     * @param $crt
     * @param $key
     * @param null $uuid
     * @return mixed
     */
    public function cert_import($descr, $crt, $key, $uuid = null)
    {
        if ($uuid) {
            $cert = $cert = $this->certs->cert->{$uuid};
        }
        else {
            $cert = $this->certs->cert->Add();
        }
        $cert->descr = $descr;
        $cert->crt = base64_encode($crt);
        $cert->prv = base64_encode($key);

        $subject = Certs::cert_get_subject($crt, false);
        $issuer = Certs::cert_get_issuer($crt, false);

        // Find my issuer unless self-signed
        if ($issuer <> $subject) {
            $issuer_crt = $this->lookup_ca_by_subject($issuer);
            if ($issuer_crt) {
                $cert->cauuid = $issuer_crt;
            }
        }
        return $cert;
    }

    /**
     * Create a new certificate
     * @param $descr
     * @param $cauuid
     * @param $keylen
     * @param $lifetime
     * @param $dn
     * @param string $digest_alg
     * @param string $x509_extensions
     * @return bool
     */
    function cert_create(
        $descr,
        $cauuid,
        $keylen,
        $lifetime,
        $dn,
        $digest_alg = 'sha256',
        $x509_extensions = 'usr_cert'
    ) {
        $cert = $this->certs->cert->Add();
        $cert->descr = $descr;
        if (!($ca = $this->cas->ca->{$cauuid})) {
            return false;
        }

        // define temp filename to use for openssl.cnf
        $config_filename = tempnam(sys_get_temp_dir(), 'ssl');

        $ca_str_crt = base64_decode($ca->crt->__toString());
        $ca_str_key = base64_decode($ca->prv->__toString());
        $ca_res_crt = openssl_x509_read($ca_str_crt);
        $ca_res_key = openssl_pkey_get_private([0 => $ca_str_key, 1 => ""]);
        if (!$ca_res_key) {
            return false;
        }
        $ca_serial = $ca->serial->__toString() + 1;
        $ca->serial = $ca_serial;

        $template = file_get_contents('/usr/local/etc/ssl/opnsense.cnf');
        // handle parameters which can only be set via the configuration file
        $template_dn = "";
        foreach (["subjectAltName"] as $dnTag) {
            if (isset($dn[$dnTag])) {
                $template_dn .= $dnTag . "=" . $dn[$dnTag] . "\n";
                unset($dn[$dnTag]);
            }
        }
        $template = str_replace("###OPNsense:" . $x509_extensions . "###", $template_dn, $template);
        file_put_contents($config_filename, $template);

        $args = [
            'config' => $config_filename,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => (int)$keylen,
            'x509_extensions' => $x509_extensions,
            'digest_alg' => $digest_alg,
            'encrypt_key' => false
        ];

        // generate a new key pair
        $res_key = openssl_pkey_new($args);
        if (!$res_key) {
            return false;
        }

        // generate a certificate signing request
        $res_csr = openssl_csr_new($dn, $res_key, $args);
        if (!$res_csr) {
            return false;
        }

        // self sign the certificate
        $res_crt = openssl_csr_sign($res_csr, $ca_res_crt, $ca_res_key, $lifetime,
            $args, $ca_serial);
        if (!$res_crt) {
            return false;
        }

        // export our certificate data
        if (!openssl_pkey_export($res_key, $str_key) ||
            !openssl_x509_export($res_crt, $str_crt)) {
            return false;
        }

        // return our certificate information
        $cert->cauuid = $cauuid;
        $cert->crt = base64_encode($str_crt);
        $cert->prv = base64_encode($str_key);

        // remove tempfile (template)
        unlink($config_filename);

        return $cert;
    }

    /**
     * Create a new signing request
     * @param $descr
     * @param $keylen
     * @param $dn
     * @param string $digest_alg
     * @return bool
     */
    public function csr_generate($descr, $keylen, $dn, $digest_alg = 'sha256')
    {
        $args = [
            'config' => '/usr/local/etc/ssl/opnsense.cnf',
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => (int)$keylen,
            'x509_extensions' => 'v3_req',
            'digest_alg' => $digest_alg,
            'encrypt_key' => false
        ];

        $cert = $this->certs->cert->Add();
        $cert->descr = $descr;

        // generate a new key pair
        if (!($res_key = openssl_pkey_new($args))) {
            return false;
        }

        // generate a certificate signing request
        if (!($res_csr = openssl_csr_new($dn, $res_key, $args))) {
            return false;
        }

        // export our request data
        if (!openssl_pkey_export($res_key, $str_key) ||
            !openssl_csr_export($res_csr, $str_csr)) {
            return false;
        }

        // return our request information
        $cert->csr = base64_encode($str_csr);
        $cert->prv = base64_encode($str_key);

        return $cert;
    }

    /**
     * Import or update CA by the given public and private keys
     * @param $descr
     * @param $crt
     * @param string $key
     * @param int $serial
     * @param null $uuid
     * @return mixed
     */
    public function ca_import($descr, $crt, $key = "", $serial = 0, $uuid = null)
    {
        if ($uuid) {
            $ca = $this->cas->ca->{$uuid};
        } else {
            $ca = $this->cas->ca->Add();
        }
        $ca->descr = $descr;
        $ca->crt = base64_encode($crt);
        if (!empty($key)) {
            $ca->prv = base64_encode($key);
        }
        if (!empty($serial)) {
            $ca->serial = $serial;
        }
        $subject = Certs::cert_get_subject($crt, false);
        $issuer = Certs::cert_get_issuer($crt, false);

        // Find my issuer unless self-signed
        if ($issuer <> $subject) {
            $issuer_uuid = self::lookup_ca_by_subject($issuer);
            if ($issuer_uuid) {
                $ca->cauuid = $issuer_uuid;
            }
        }

        /* Correct if child certificate was loaded first */
        $ca_uuid = $ca->getAttributes()["uuid"];
        foreach ($this->cas->ca->getChildren() as $uuid => $oca) {
            $issuer = Certs::cert_get_issuer($oca->crt->__toString());
            if ($ca_uuid <> $uuid && $issuer == $subject) {
                $this->cas->ca->{$uuid}->cauuid = $ca_uuid;
            }
        }
        foreach ($this->certs->cert->getChildren() as $uuid => $cert) {
            $issuer = Certs::cert_get_issuer($cert->crt->__toString());
            if ($issuer == $subject) {
                $this->certs->cert->{$uuid}->cauuid = $ca_uuid;
            }
        }
        return $ca;
    }

    /**
     * The creation of a new CA
     * @param $descr
     * @param $keylen
     * @param $lifetime
     * @param $dn
     * @param string $digest_alg
     * @return bool
     */
    public function ca_create(
        $descr,
        $keylen,
        $lifetime,
        $dn,
        $digest_alg = 'sha256'
    ) {
        $ca = $this->cas->ca->Add();
        $ca->descr = $descr;
        $args = [
            'config' => '/usr/local/etc/ssl/opnsense.cnf',
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => (int)$keylen,
            'x509_extensions' => 'v3_ca',
            'digest_alg' => $digest_alg,
            'encrypt_key' => false
        ];

        // generate a new key pair
        if (!($res_key = openssl_pkey_new($args))) {
            return false;
        }

        // generate a certificate signing request
        if (!($res_csr = openssl_csr_new($dn, $res_key, $args))) {
            return false;
        }

        // self sign the certificate
        if (!($res_crt = openssl_csr_sign($res_csr, null, $res_key, $lifetime, $args))) {
            return false;
        }

        // export our certificate data
        if (!openssl_pkey_export($res_key, $str_key) || !openssl_x509_export($res_crt, $str_crt)) {
            return false;
        }

        // return our ca information
        $ca->crt = base64_encode($str_crt);
        $ca->prv = base64_encode($str_key);
        $ca->serial = 0;

        return $ca;
    }

    /**
     * Creating an intermediate CA
     * @param $descr
     * @param $cauuid
     * @param $keylen
     * @param $lifetime
     * @param $dn
     * @param string $digest_alg
     * @return bool
     */
    public function ca_inter_create(
        $descr,
        $cauuid,
        $keylen,
        $lifetime,
        $dn,
        $digest_alg = 'sha256'
    ) {
        $ca = $this->cas->ca->Add();
        $ca->descr = $descr;
        if (!($signing_ca = $this->cas->ca->{$cauuid})) {
            return false;
        }

        $signing_ca_res_crt = openssl_x509_read(base64_decode($signing_ca->crt->__toString()));
        $signing_ca_res_key = openssl_pkey_get_private([0 => base64_decode($signing_ca->prv->__toString()), 1 => ""]);
        if (!$signing_ca_res_crt || !$signing_ca_res_key) {
            return false;
        }
        $signing_ca_serial = $signing_ca->serial->__toString() + 1;
        $signing_ca->serial = $signing_ca_serial;

        $args = [
            'config' => '/usr/local/etc/ssl/opnsense.cnf',
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => (int)$keylen,
            'x509_extensions' => 'v3_ca',
            'digest_alg' => $digest_alg,
            'encrypt_key' => false
        ];

        // generate a new key pair
        if (!($res_key = openssl_pkey_new($args))) {
            return false;
        }

        // generate a certificate signing request
        if (!($res_csr = openssl_csr_new($dn, $res_key, $args))) {
            return false;
        }

        // Sign the certificate
        if (!($res_crt = openssl_csr_sign($res_csr, $signing_ca_res_crt, $signing_ca_res_key, $lifetime, $args,
            $signing_ca_serial))) {
            return false;
        }

        // export our certificate data
        if (!openssl_pkey_export($res_key, $str_key) ||
            !openssl_x509_export($res_crt, $str_crt)) {
            return false;
        }

        // return our ca information
        $ca->crt = base64_encode($str_crt);
        $ca->cauuid = $cauuid;
        $ca->prv = base64_encode($str_key);
        $ca->serial = 0;

        return $ca;
    }

    /**
     * Certificate revocation
     * @param $cert
     * @param $crl
     * @param $reason
     * @return bool
     */
    public function cert_revoke($cert, $crl, $reason=OCSP_REVOKED_STATUS_UNSPECIFIED)
    {
        $crl_uuid = $crl->getAttributes()["uuid"];
        if ($this->is_cert_revoked($cert, $crl_uuid)) {
            return true;
        }
        $revoce_cert = $this->crl_certs->cert->Add();
        // If we have text but no certs, it was imported and cannot be updated.
        if (!Certs::is_crl_internal($crl)) {
            return false;
        }
        $revoce_cert->cauuid = $cert->cauuid->__toString();
        $revoce_cert->descr = $cert->descr->__toString();
        $revoce_cert->crt = $cert->crt->__toString();
        $revoce_cert->prv = $cert->prv->__toString();
        $revoce_cert->crluuid = $crl_uuid;
        $revoce_cert->reason = $reason;
        $revoce_cert->revoke_time = time();
        $this->crl_update($crl);
        return true;
    }

    /**
     * Getting a chain of CA
     * @param $cert
     * @return mixed|string
     */
    public function ca_chain($cert)
    {
        $ca = '';
        if (empty($cert->cauuid->__toString())) {
            return $ca;
        }

        foreach ($this->ca_chain_array($cert) as $ca_cert) {
            $ca .= base64_decode($ca_cert->crt->__toString());
            $ca .= "\n";
        }

        /* sanitise output to make sure we generate clean files */
        return str_replace("\n\n", "\n", str_replace("\r", "", $ca));
    }

    /**
     * Getting a chain of CA
     * @param $cert
     * @return array
     */
    public function ca_chain_array($cert)
    {
        $chain = [];
        while ($cert) {
            $cauuid = $cert->cauuid->__toString();
            if (empty($cauuid) || !($cert = $this->cas->ca->{$cauuid})) {
                break;
            }
            $chain[] = $cert;

        }
        return $chain;
    }
}
