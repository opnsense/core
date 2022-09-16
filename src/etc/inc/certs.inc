<?php

/*
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
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

function phpseclib_autoload($namespace, $dir)
{
    $split = '\\';
    $ns = trim($namespace, DIRECTORY_SEPARATOR . $split);

    return spl_autoload_register(
        function ($class) use ($ns, $dir, $split) {
            $prefix = $ns . $split;
            $base_dir = $dir . DIRECTORY_SEPARATOR;
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len)) {
                return;
            }

            $relative_class = substr($class, $len);

            $file = $base_dir .
                str_replace($split, DIRECTORY_SEPARATOR, $relative_class) .
                '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        }
    );
}

/* Autoload phpseclib3 and it's dependencies */
phpseclib_autoload('ParagonIE\ConstantTime', '/usr/local/share/phpseclib/paragonie');
phpseclib_autoload('phpseclib3', '/usr/local/share/phpseclib');

use phpseclib3\File\X509;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\NoKeyLoadedException;

define("CERT_CRL_STATUS_NOSTATUS", -1);
define("CERT_CRL_STATUS_UNSPECIFIED", 0);
define("CERT_CRL_STATUS_KEYCOMPROMISE", 1);
define("CERT_CRL_STATUS_CACOMPROMISE", 2);
define("CERT_CRL_STATUS_AFFILIATIONCHANGED", 3);
define("CERT_CRL_STATUS_SUPERSEDED", 4);
define("CERT_CRL_STATUS_CESSATIONOFOPERATION", 5);
define("CERT_CRL_STATUS_CERTIFICATEHOLD", 6);

function crl_status_code()
{
    /* Array index 0 is a description, index 1 is the key used by phpseclib */
    return array(
        CERT_CRL_STATUS_NOSTATUS              => ["No Status (default)", "unspecified"],
        CERT_CRL_STATUS_UNSPECIFIED           => ["Unspecified", "unspecified"],
        CERT_CRL_STATUS_KEYCOMPROMISE         => ["Key Compromise", "keyCompromise"],
        CERT_CRL_STATUS_CACOMPROMISE          => ["CA Compromise", "cACompromise"],
        CERT_CRL_STATUS_AFFILIATIONCHANGED    => ["Affiliation Changed", "affiliationChanged"],
        CERT_CRL_STATUS_SUPERSEDED            => ["Superseded", "superseded"],
        CERT_CRL_STATUS_CESSATIONOFOPERATION  => ["Cessation of Operation", "cessationOfOperation"],
        CERT_CRL_STATUS_CERTIFICATEHOLD       => ["Certificate Hold", "certificateHold"]
    );
}

function &lookup_ca($refid)
{
    global $config;

    if (is_array($config['ca'])) {
        foreach ($config['ca'] as & $ca) {
            if ($ca['refid'] == $refid) {
                return $ca;
            }
        }
    }

    return false;
}

function &lookup_ca_by_subject($subject)
{
    global $config;

    if (is_array($config['ca'])) {
        foreach ($config['ca'] as & $ca) {
            $ca_subject = cert_get_subject($ca['crt']);
            if ($ca_subject == $subject) {
                return $ca;
            }
        }
    }

    return false;
}

function &lookup_cert($refid)
{
    global $config;

    if (is_array($config['cert'])) {
        foreach ($config['cert'] as & $cert) {
            if ($cert['refid'] == $refid) {
                return $cert;
            }
        }
    }

    return false;
}

function &lookup_crl($refid)
{
    global $config;

    if (is_array($config['crl'])) {
        foreach ($config['crl'] as & $crl) {
            if ($crl['refid'] == $refid) {
                return $crl;
            }
        }
    }

    return false;
}

function ca_chain_array(&$cert)
{
    if ($cert['caref']) {
        $chain = array();
        $crt = lookup_ca($cert['caref']);
        $chain[] = $crt;
        while ($crt) {
            $caref = isset($crt['caref']) ? $crt['caref'] : false;
            if ($caref) {
                $crt = lookup_ca($caref);
            } else {
                $crt = false;
            }
            if ($crt) {
                $chain[] = $crt;
            }
        }
        return $chain;
    }
    return false;
}

function ca_chain(&$cert)
{
    $ca = '';
    if (!isset($cert['caref'])) {
        return $ca;
    }

    $cas = ca_chain_array($cert);
    if (!is_array($cas)) {
        return $ca;
    }

    foreach ($cas as &$ca_cert) {
        $ca .= base64_decode($ca_cert['crt']);
        $ca .= "\n";
    }

    /* sanitise output to make sure we generate clean files */
    return str_replace("\n\n", "\n", str_replace("\r", "", $ca));
}

function ca_create(&$ca, $keylen_curve, $lifetime, $dn, $digest_alg)
{
    $args = array(
        'config' => '/usr/local/etc/ssl/opnsense.cnf',
        'x509_extensions' => 'v3_ca',
        'digest_alg' => $digest_alg,
        'encrypt_key' => false
    );
    if (is_numeric($keylen_curve)) {
        $args['private_key_type'] = OPENSSL_KEYTYPE_RSA;
        $args['private_key_bits'] = (int)$keylen_curve;
    } else {
        $args['private_key_type'] = OPENSSL_KEYTYPE_EC;
        $args['curve_name'] = $keylen_curve;
    }

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
    $res_crt = openssl_csr_sign($res_csr, null, $res_key, $lifetime, $args);
    if (!$res_crt) {
        return false;
    }

    // export our certificate data
    if (!openssl_pkey_export($res_key, $str_key) || !openssl_x509_export($res_crt, $str_crt)) {
        return false;
    }

    // return our ca information
    $ca['crt'] = base64_encode($str_crt);
    $ca['prv'] = base64_encode($str_key);
    $ca['serial'] = 0;

    return true;
}

function cert_import(&$cert, $crt_str, $key_str)
{
    $cert['crt'] = base64_encode($crt_str);
    $cert['prv'] = base64_encode($key_str);

    $subject = cert_get_subject($crt_str, false);
    $issuer = cert_get_issuer($crt_str, false);

    // Find my issuer unless self-signed
    if ($issuer != $subject) {
        $issuer_crt =& lookup_ca_by_subject($issuer);
        if ($issuer_crt) {
            $cert['caref'] = $issuer_crt['refid'];
        }
    }
    return true;
}

function cert_create(&$cert, $caref, $keylen_curve, $lifetime, $dn, $digest_alg, $x509_extensions = 'usr_cert', $extns = [])
{
    $ca = &lookup_ca($caref);
    if (!$ca) {
        return false;
    }

    $ca_str_crt = base64_decode($ca['crt']);
    $ca_str_key = base64_decode($ca['prv']);
    $ca_res_crt = openssl_x509_read($ca_str_crt);
    $ca_res_key = openssl_pkey_get_private(array(0 => $ca_str_key, 1 => ""));
    if (!$ca_res_key) {
        return false;
    }
    $ca_serial = ++$ca['serial'];

    // handle parameters which can only be set via the configuration file
    $config_filename = create_temp_openssl_config($extns);

    $args = array(
        'config' => $config_filename,
        'x509_extensions' => $x509_extensions,
        'digest_alg' => $digest_alg,
        'encrypt_key' => false
    );
    if (is_numeric($keylen_curve)) {
        $args['private_key_type'] = OPENSSL_KEYTYPE_RSA;
        $args['private_key_bits'] = (int)$keylen_curve;
    } else {
        $args['private_key_type'] = OPENSSL_KEYTYPE_EC;
        $args['curve_name'] = $keylen_curve;
    }

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
    $res_crt = openssl_csr_sign(
        $res_csr,
        $ca_res_crt,
        $ca_res_key,
        $lifetime,
        $args,
        $ca_serial
    );
    if (!$res_crt) {
        return false;
    }

    // export our certificate data
    if (
        !openssl_pkey_export($res_key, $str_key) ||
        !openssl_x509_export($res_crt, $str_crt)
    ) {
        return false;
    }

    // return our certificate information
    $cert['caref'] = $caref;
    $cert['crt'] = base64_encode($str_crt);
    $cert['prv'] = base64_encode($str_key);

    // remove tempfile (template)
    unlink($config_filename);

    return true;
}

function sign_cert_csr(&$cert, $caref, $csr, $lifetime, $digest_alg, $extns)
{
    $ca = &lookup_ca($caref);
    if (!$ca) {
        return false;
    }

    $ca_str_crt = base64_decode($ca['crt']);
    $ca_str_key = base64_decode($ca['prv']);
    $ca_res_crt = openssl_x509_read($ca_str_crt);
    $ca_res_key = openssl_pkey_get_private(array(0 => $ca_str_key, 1 => ""));
    if (!$ca_res_key) {
        return false;
    }
    $ca_serial = ++$ca['serial'];

    // handle parameters which can only be set via the configuration file
    $config_filename = create_temp_openssl_config($extns);

    $args = array(
        'config' => $config_filename,
        'digest_alg' => $digest_alg,
        'x509_extensions' => 'sign_csr',
    );

    $res_crt = openssl_csr_sign($csr, $ca_res_crt, $ca_res_key, $lifetime, $args, $ca_serial);
    if ($res_crt === false) {
        return false;
    }

    // export our certificate data
    if (!openssl_x509_export($res_crt, $str_crt)) {
        return false;
    }

    // return our certificate information
    $cert['caref'] = $caref;
    $cert['crt'] = base64_encode($str_crt);

    // remove tempfile (template)
    unlink($config_filename);

    return true;
}

function certs_build_name($dn)
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

function csr_get_subject($str_crt, $decode = true)
{
    if ($decode) {
        $str_crt = base64_decode($str_crt);
    }

    $components = openssl_csr_get_subject($str_crt);

    return certs_build_name($components);
}

function cert_get_subject($str_crt, $decode = true)
{
    if ($decode) {
        $str_crt = base64_decode($str_crt);
    }

    $inf_crt = openssl_x509_parse($str_crt);
    $components = $inf_crt['subject'];

    return certs_build_name($components);
}

function cert_get_subject_array($crt)
{
    $str_crt = base64_decode($crt);
    $inf_crt = openssl_x509_parse($str_crt);
    $components = $inf_crt['subject'];

    if (!is_array($components)) {
        return;
    }

    $subject_array = array();

    foreach ($components as $a => $v) {
        $subject_array[] = array('a' => $a, 'v' => $v);
    }

    return $subject_array;
}

function cert_get_issuer($str_crt, $decode = true)
{
    if ($decode) {
        $str_crt = base64_decode($str_crt);
    }

    $inf_crt = openssl_x509_parse($str_crt);
    $components = $inf_crt['issuer'];

    return certs_build_name($components);
}

/* this function works on x509 (crt), rsa key (prv), and req(csr) */
function cert_get_modulus($str_crt, $decode = true, $type = 'crt')
{
    $type_list = array('crt', 'prv', 'csr');
    $type_cmd = array('x509', 'rsa', 'req');
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


function cert_get_purpose($str_crt, $decode = true)
{
    if ($decode) {
        $str_crt = base64_decode($str_crt);
    }

    $crt_details = openssl_x509_parse($str_crt);
    $purpose = array();
    foreach (['basicConstraints', 'extendedKeyUsage', 'keyUsage'] as $ext) {
        $purpose[$ext] = [];
        if (!empty($crt_details['extensions'][$ext])) {
            foreach (explode(",", $crt_details['extensions'][$ext]) as $item) {
                $purpose[$ext][] = trim($item);
            }
        }
    }
    $purpose['ca'] = in_array('CA:TRUE', $purpose['basicConstraints']) ? 'Yes' : 'No';
    $purpose['server'] = in_array('TLS Web Server Authentication', $purpose['extendedKeyUsage']) ? 'Yes' : 'No';
    // rfc3280 extended key usage
    if (
        in_array('TLS Web Server Authentication', $purpose['extendedKeyUsage']) &&
        in_array('Digital Signature', $purpose['keyUsage']) && (
            in_array('Key Encipherment', $purpose['keyUsage']) ||
            in_array('Key Agreement', $purpose['keyUsage'])
        )
    ) {
        $purpose['id-kp-serverAuth'] = 'Yes';
    } else {
        $purpose['id-kp-serverAuth'] = 'No';
    }
    return $purpose;
}

function cert_get_dates($str_crt, $decode = true)
{
    if ($decode) {
        $str_crt = base64_decode($str_crt);
    }
    $crt_details = openssl_x509_parse($str_crt);
    if ($crt_details['validFrom_time_t'] > 0) {
        $start = date('r', $crt_details['validFrom_time_t']);
    }
    if ($crt_details['validTo_time_t'] > 0) {
        $end = date('r', $crt_details['validTo_time_t']);
    }
    return array($start, $end);
}

function cert_get_serial($str_crt, $decode = true)
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

function is_user_cert($certref)
{
    global $config;
    if (!isset($config['system']['user'])) {
        return;
    }

    foreach ($config['system']['user'] as $user) {
        if (!isset($user['cert']) || !is_array($user['cert'])) {
            continue;
        }
        foreach ($user['cert'] as $cert) {
            if ($certref == $cert) {
                return true;
            }
        }
    }

    return false;
}

function is_openvpn_server_cert($certref)
{
    global $config;

    if (!isset($config['openvpn']['openvpn-server'])) {
        return;
    }

    foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
        if (isset($ovpns['certref']) && $ovpns['certref'] == $certref) {
            return true;
        }
    }

    return false;
}

function is_openvpn_client_cert($certref)
{
    global $config;

    if (!isset($config['openvpn']['openvpn-client'])) {
        return;
    }

    foreach ($config['openvpn']['openvpn-client'] as $ovpnc) {
        if (isset($ovpnc['certref']) && $ovpnc['certref'] == $certref) {
            return true;
        }
    }

    return false;
}

function is_ipsec_cert($certref)
{
    global $config;

    if (!isset($config['ipsec']['phase1'])) {
        return;
    }

    foreach ($config['ipsec']['phase1'] as $ipsec) {
        if ($ipsec['certref'] == $certref) {
            return true;
        }
    }

    return false;
}

function is_webgui_cert($certref)
{
    global $config;
    return $config['system']['webgui']['ssl-certref'] == $certref && $config['system']['webgui']['protocol'] != 'http';
}

function cert_in_use($certref)
{
    return (is_webgui_cert($certref) ||
      is_user_cert($certref) ||
      is_openvpn_server_cert($certref) ||
      is_openvpn_client_cert($certref) ||
      is_ipsec_cert($certref));
}

function crl_update(&$crl)
{
    $ca =& lookup_ca($crl['caref']);
    if (!$ca) {
        return false;
    }
    // If we have text but no certs, it was imported and cannot be updated.
    if (!is_crl_internal($crl)) {
        return false;
    }

    $crl['serial']++;
    $ca_str_crt = base64_decode($ca['crt']);
    $ca_str_key = base64_decode($ca['prv']);
    if (!openssl_x509_check_private_key($ca_str_crt, $ca_str_key)) {
        syslog(LOG_ERR, "Cert revocation error: CA keys mismatch");
        return false;
    }

    if (!class_exists(X509::class)) {
        syslog(LOG_ERR, 'Cert revocation error: phpseclib3 not loaded');
        return false;
    }

    /* Load in the CA's cert */
    $ca_cert = new X509();
    $ca_cert->loadX509($ca_str_crt);

    if (!$ca_cert->validateDate()) {
        syslog(LOG_ERR, 'Cert revocation error: CA certificate invalid: invalid date');
        return false;
    }

    /* get the private key to sign the new (updated) CRL */
    try {
        $ca_key = PublicKeyLoader::loadPrivateKey($ca_str_key);
        if (method_exists($ca_key, 'withPadding')) {
            $ca_key = $ca_key->withPadding(RSA::ENCRYPTION_PKCS1 | RSA::SIGNATURE_PKCS1);
        }
        $ca_cert->setPrivateKey($ca_key);
    } catch (NoKeyLoadedException $e) {
        syslog(LOG_ERR, 'Cert revocation error: Unable to load CA private key');
        return false;
    }

    /* Load the CA for later signature validation */
    $x509_crl = new X509();
    $x509_crl->loadCA($ca_str_crt);

    /*
     * create empty CRL. A quirk with phpseclib is that in order to correctly sign
     * a new CRL, a CA must be loaded using a separate X509 container, which is passed
     * to signCRL(). However, to validate the resulting signature, the original X509
     * CRL container must load the same CA using loadCA() with a direct reference
     * to the CA's public cert.
     */
    $x509_crl->loadCRL($x509_crl->saveCRL($x509_crl->signCRL($ca_cert, $x509_crl)));

    /* Now validate the CRL to see if everything went well */
    try {
        if (!$x509_crl->validateSignature(false)) {
            syslog(LOG_ERR, 'Cert revocation error: CRL signature invalid');
            return false;
        }
    } catch (Exception $e) {
        syslog(LOG_ERR, 'Cert revocation error: CRL signature invalid ' . $e);
        return false;
    }

    if (is_array($crl['cert']) && (count($crl['cert']) > 0)) {
        foreach ($crl['cert'] as $cert) {
            /* load the certificate in an x509 container to get its serial number and validate its signature */
            $x509_cert = new X509();
            $x509_cert->loadCA($ca_str_crt);
            $raw_cert = $x509_cert->loadX509(base64_decode($cert['crt']));
            try {
                if (!$x509_cert->validateSignature(false)) {
                    syslog(LOG_ERR, "Cert revocation error: Revoked certificate validation failed.");
                    return false;
                }
            } catch (Exception $e) {
                syslog(LOG_ERR, 'Cert revocation error: Revoked certificate validation failed ' . $e);
                return false;
            }
            /* Get serial number of cert */
            $sn = $raw_cert['tbsCertificate']['serialNumber']->toString();
            $x509_crl->setRevokedCertificateExtension($sn, 'id-ce-cRLReasons', crl_status_code()[$cert["reason"]][1]);
        }
    }
    $x509_crl->setSerialNumber($crl['serial'], 10);
    /* consider dates after 2050 lifetime in GeneralizedTime format (rfc5280#section-4.1.2.5) */
    $date = new \DateTimeImmutable('+' . $crl['lifetime'] . ' days', new \DateTimeZone(@date_default_timezone_get()));
    if ((int)$date->format("Y") < 2050) {
        $x509_crl->setEndDate($date);
    } else {
        $x509_crl->setEndDate('lifetime');
    }

    $new_crl = $x509_crl->signCRL($ca_cert, $x509_crl);
    $crl_text = $x509_crl->saveCRL($new_crl) . PHP_EOL;

    /* Update the CRL */
    $crl['text'] = base64_encode($crl_text);
    return true;
}

function cert_revoke($cert, &$crl, $reason = CERT_CRL_STATUS_UNSPECIFIED)
{
    if (is_cert_revoked($cert, $crl['refid'])) {
        return true;
    }
    // If we have text but no certs, it was imported and cannot be updated.
    if (!is_crl_internal($crl)) {
        return false;
    }
    $crl_before = $crl;
    $cert["reason"] = $reason;
    $cert["revoke_time"] = time();
    $crl["cert"][] = $cert;
    if (!crl_update($crl)) {
        $crl = $crl_before;
        return false;
    }
    return true;
}


/* Compare two certificates to see if they match. */
function cert_compare($cert1, $cert2)
{
    /* Ensure two certs are identical by first checking that their issuers match, then
      subjects, then serial numbers, and finally the moduli. Anything less strict
      could accidentally count two similar, but different, certificates as
      being identical. */
    $c1 = base64_decode($cert1['crt']);
    $c2 = base64_decode($cert2['crt']);
    if (
        (cert_get_issuer($c1, false) == cert_get_issuer($c2, false))
        && (cert_get_subject($c1, false) == cert_get_subject($c2, false))
        && (cert_get_serial($c1, false) == cert_get_serial($c2, false))
        && (cert_get_modulus($c1, false) == cert_get_modulus($c2, false))
    ) {
        return true;
    } else {
        return false;
    }
}

function is_cert_revoked($cert, $crlref = "")
{
    global $config;
    if (!isset($config['crl']) || !is_array($config['crl'])) {
        return false;
    }

    if (!empty($crlref)) {
        $crl = lookup_crl($crlref);
        if (!isset($crl['cert']) || !is_array($crl['cert'])) {
            return false;
        }
        foreach ($crl['cert'] as $rcert) {
            if (cert_compare($rcert, $cert)) {
                return true;
            }
        }
    } else {
        foreach ($config['crl'] as $crl) {
            if (!is_array($crl['cert'])) {
                continue;
            }
            foreach ($crl['cert'] as $rcert) {
                if (cert_compare($rcert, $cert)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function is_openvpn_server_crl($crlref)
{
    global $config;
    if (!isset($config['openvpn']['openvpn-server']) || !is_array($config['openvpn']['openvpn-server'])) {
        return;
    }
    foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
        if (!empty($ovpns['crlref']) && ($ovpns['crlref'] == $crlref)) {
            return true;
        }
    }
    return false;
}


function is_crl_internal($crl)
{
    return (!(!empty($crl['text']) && empty($crl['cert'])) || ($crl["crlmethod"] ?? '' == "internal"));
}

function cert_get_cn($crt, $isref = false)
{
    /* If this is a certref, not an actual cert, look up the cert first */
    if ($isref) {
        $cert = lookup_cert($crt);
        /* If it's not a valid cert, bail. */
        if (!(is_array($cert) && !empty($cert['crt']))) {
            return "";
        }
        $cert = $cert['crt'];
    } else {
        $cert = $crt;
    }
    $sub = cert_get_subject_array($cert);
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
 * Create a temporary config file, to help with calls that require properties that can only be set via the config file.
 *
 * @param $dn
 * @return bool|string The name of the temporary config file.
 */
function create_temp_openssl_config(&$extns)
{
    // define temp filename to use for openssl.cnf and add extensions values to it
    $configFilename = tempnam(sys_get_temp_dir(), 'ssl');

    $template = file_get_contents('/usr/local/etc/ssl/opnsense.cnf');

    foreach (['subjectAltName', 'keyUsage', 'extendedKeyUsage', 'basicConstraints'] as $extnTag) {
        if (isset($extns[$extnTag])) {
            $template_extn = $extnTag . ' = ' . str_replace(array("\r", "\n"), '', $extns[$extnTag]);
            // Overwrite the placeholders for this property
            $template = str_replace('###OPNsense:' . $extnTag . '###', $template_extn, $template);
        }
    }
    file_put_contents($configFilename, $template);
    return $configFilename;
}
