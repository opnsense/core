<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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

require_once('guiconfig.inc');
require_once('system.inc');
require_once('phpseclib/File/X509.php');
require_once('phpseclib/File/ASN1.php');
require_once('phpseclib/Math/BigInteger.php');
require_once('phpseclib/File/ASN1/Element.php');
require_once('phpseclib/Crypt/RSA.php');
require_once('phpseclib/Crypt/Hash.php');

function csr_generate(&$cert, $keylen_curve, $dn, $digest_alg, $extns)
{
    $configFilename = create_temp_openssl_config($extns);


    $args = array(
        'config' => $configFilename,
        'req_extensions' => 'v3_req',
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

    // export our request data
    if (!openssl_pkey_export($res_key, $str_key) ||
        !openssl_csr_export($res_csr, $str_csr)) {
        return false;
    }

    // return our request information
    $cert['csr'] = base64_encode($str_csr);
    $cert['prv'] = base64_encode($str_key);

    unlink($configFilename);

    return true;
}

function csr_complete(& $cert, $str_crt)
{
    // return our request information
    $cert['crt'] = base64_encode($str_crt);
    unset($cert['csr']);

    return true;
}

function csr_get_modulus($str_crt, $decode = true)
{
    return cert_get_modulus($str_crt, $decode, 'csr');
}

function parse_csr($csr_str)
{
    $ret = array();
    $ret['parse_success'] = true;
    $ret['subject'] = openssl_csr_get_subject($csr_str);
    if ($ret['subject'] === false) {
        return array('parse_success' => false);
    }

    $x509_lib = new \phpseclib\File\X509();
    $csr = $x509_lib->loadCSR($csr_str);
    if ($csr === false) {
        return array('parse_success' => false);
    }

    foreach ($csr['certificationRequestInfo']['attributes'] as $attr) {
        switch ($attr['type'] ) {
            case 'pkcs-9-at-extensionRequest':
                foreach ($attr['value'] as $value) {
                    foreach ($value as $column) {
                        switch ($column['extnId']) {
                            case 'id-ce-basicConstraints':
                                $ret['basicConstraints'] = array();
                                $ret['basicConstraints']['CA'] = $column['extnValue']['cA'];
                                if (isset($column['extnValue']['pathLenConstraint'])) {
                                    $ret['basicConstraints']['pathlen'] = (int)($column['extnValue']['pathLenConstraint']->toString());
                                }

                                break;

                            case 'id-ce-keyUsage':
                                $ret['keyUsage'] = $column['extnValue'];
                                break;

                            case 'id-ce-extKeyUsage':
                                $ret['extendedKeyUsage'] = array();
                                foreach ($column['extnValue'] as $usage) {
                                    array_push($ret['extendedKeyUsage'], strpos($usage, 'id-kp-') === 0 ? $x509_lib->getOID($usage)
                                                                                                        : $usage);
                                }
                                break;

                            case 'id-ce-subjectAltName':
                                $ret['subjectAltName'] = array();
                                foreach ($column['extnValue'] as $item) {
                                    if (isset($item['dNSName'])) {
                                        array_push($ret['subjectAltName'], array('type'=> 'DNS', 'value'=> $item['dNSName']));
                                    }
                                    if (isset($item['iPAddress'])) {
                                        array_push($ret['subjectAltName'], array('type'=> 'IP', 'value'=> $item['iPAddress']));
                                    }
                                    if (isset($item['rfc822Name'])) {
                                        array_push($ret['subjectAltName'], array('type'=> 'email', 'value'=> $item['rfc822Name']));
                                    }
                                    if (isset($item['uniformResourceIdentifier'])) {
                                        array_push($ret['subjectAltName'], array('type'=> 'URI', 'value'=> $item['uniformResourceIdentifier']));
                                    }
                                }
                                break;

                        }
                    }
                }
                break; // case 'pkcs-9-at-extensionRequest'
        }
    }

    return $ret;
}

// altname expects a type like the following:
// array (
//    'type'   => (string),
//    'value': => (string)
// )
//
// errors are added to $input_errors
// returns true:  on success
//         false: on error, with adding something to $input_errors.
function is_valid_alt_value($altname, &$input_errors) {
    switch ($altname['type']) {
        case "DNS":
            $dns_regex = '/^(?:(?:[a-z0-9_\*]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])\.)*(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i';
            if (!preg_match($dns_regex, $altname['value'])) {
                $input_errors[] = gettext("DNS subjectAltName values must be valid hostnames or FQDNs");
                return false;
            }
            return true;
        case "IP":
            if (!is_ipaddr($altname['value'])) {
                $input_errors[] = gettext("IP subjectAltName values must be valid IP Addresses");
                return false;
            }
            return true;
        case "email":
            if (empty($altname['value'])) {
                $input_errors[] = gettext("You must provide an email address for this type of subjectAltName");
                return false;
            }
            if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $altname['value'])) {
                $input_errors[] = gettext("The email provided in a subjectAltName contains invalid characters.");
                return false;
            }
            return true;
        case "URI":
            if (!is_URL($altname['value'])) {
                $input_errors[] = gettext("URI subjectAltName types must be a valid URI");
                return false;
            }
            return true;
        default:
            $input_errors[] = gettext("Unrecognized subjectAltName type.");
            return false;
    }

}
// types
$cert_methods = array(
    "import" => gettext("Import an existing Certificate"),
    "internal" => gettext("Create an internal Certificate"),
    "external" => gettext("Create a Certificate Signing Request"),
    "sign_cert_csr" => gettext("Sign a Certificate Signing Request"),
);
$cert_keylens = array( "512", "1024", "2048", "3072", "4096", "8192");
$cert_curves = array( "prime256v1", "secp384r1", "secp521r1");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");
$cert_types = array('usr_cert', 'server_cert', 'combined_server_client', 'v3_ca');
$key_usages = array(
    // defined in RFC 5280 section 4.2.1.3
    'digitalSignature' => gettext('digitalSignature'),
    'nonRepudiation'   => gettext('nonRepudiation'),
    'keyEncipherment'  => gettext('keyEncipherment'),
    'dataEncipherment' => gettext('dataEncipherment'),
    'keyAgreement'     => gettext('keyAgreement'),
    'keyCertSign'      => gettext('keyCertSign'),
    'cRLSign'          => gettext('cRLSign'),
    'encipherOnly'     => gettext('encipherOnly'),
    'decipherOnly'     => gettext('decipherOnly'),
);
$extended_key_usages = array(
    // defined in RFC 5280 section 4.2.1.12
    '1.3.6.1.5.5.7.3.1'  => gettext('serverAuth'),
    '1.3.6.1.5.5.7.3.2'  => gettext('clientAuth'),
    '1.3.6.1.5.5.7.3.3'  => gettext('codeSigning'),
    '1.3.6.1.5.5.7.3.4'  => gettext('emailProtection'),
    '1.3.6.1.5.5.7.3.8'  => gettext('timeStamping'),
    '1.3.6.1.5.5.7.3.9'  => gettext('OCSPSigning'),

    // added to support default options
    '1.3.6.1.5.5.8.2.2'  => gettext('iKEIntermediate'),
);

// config reference pointers
$a_user = &config_read_array('system', 'user');
$a_ca = &config_read_array('ca');
$a_cert = &config_read_array('cert');


// handle user GET/POST data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($a_user[$_GET['userid']])) {
        $userid = $_GET['userid'];
        $cert_methods["existing"] = gettext("Choose an existing certificate");
    }
    if (isset($a_cert[$_GET['id']])) {
        $id = $_GET['id'];
    }

    $act = isset($_GET['act']) ? $_GET['act'] : null;

    $pconfig = array();
    if ($act == "new") {
        if (isset($_GET['method'])) {
            $pconfig['certmethod'] = $_GET['method'];
        } else {
            $pconfig['certmethod'] = null;
        }
        if (isset($_GET['caref'])) {
            $pconfig['caref'] = $_GET['caref'];
        }
        $pconfig['keytype'] = "RSA";
        $pconfig['keylen'] = "2048";
        $pconfig['digest_alg'] = "sha256";
        $pconfig['digest_alg_sign_csr'] = "sha256";
        $pconfig['csr_keytype'] = "RSA";
        $pconfig['csr_keylen'] = "2048";
        $pconfig['csr_digest_alg'] = "sha256";
        $pconfig['lifetime'] = "397";
        $pconfig['lifetime_sign_csr'] = "397";
        $pconfig['cert_type'] = "usr_cert";
        $pconfig['cert'] = null;
        $pconfig['key'] = null;
        $pconfig['dn_country'] = null;
        $pconfig['dn_state'] = null;
        $pconfig['dn_city'] = null;
        $pconfig['dn_organization'] = null;
        $pconfig['dn_email'] = null;

        if (isset($userid)) {
            $pconfig['descr'] = $a_user[$userid]['name'];
            $pconfig['dn_commonname'] = $a_user[$userid]['name'];
        } else {
            $pconfig['descr'] = null;
            $pconfig['dn_commonname'] = null;
        }

    } elseif ($act == "exp") {
        // export cert
        if (isset($id)) {
            $exp_name = urlencode("{$a_cert[$id]['descr']}.crt");
            $exp_data = base64_decode($a_cert[$id]['crt']);
            $exp_size = strlen($exp_data);

            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$exp_name}");
            header("Content-Length: $exp_size");
            echo $exp_data;
        }
        exit;
    } elseif ($act == "key") {
        // export key
        if (isset($id)) {
            $exp_name = urlencode("{$a_cert[$id]['descr']}.key");
            $exp_data = base64_decode($a_cert[$id]['prv']);
            $exp_size = strlen($exp_data);

            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$exp_name}");
            header("Content-Length: $exp_size");
            echo $exp_data;
        }
        exit;
    } elseif ($act == "csr") {
        if (!isset($id)) {
            header(url_safe('Location: /system_certmanager.php'));
            exit;
        }
        $pconfig['descr'] = $a_cert[$id]['descr'];
        $pconfig['csr'] = base64_decode($a_cert[$id]['csr']);
        $pconfig['cert'] = null;
    } elseif ($act == "info") {
      if (isset($id)) {
          header("Content-Type: text/plain;charset=UTF-8");
          // use openssl to dump cert in readable format
          $process = proc_open('/usr/local/bin/openssl x509 -fingerprint -sha256 -text', array(array("pipe", "r"), array("pipe", "w")), $pipes);
          if (is_resource($process)) {
             fwrite($pipes[0], base64_decode($a_cert[$id]['crt']));
             fclose($pipes[0]);

             $result = stream_get_contents($pipes[1]);
             fclose($pipes[1]);
             proc_close($process);
             echo $result;
          }
      }
      exit;
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($a_cert[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (isset($a_user[$_POST['userid']])) {
        $userid = $_POST['userid'];
    }
    $act = isset($_POST['act']) ? $_POST['act'] : null;

    if ($act == "del") {
        if (isset($id)) {
            unset($a_cert[$id]);
            write_config();
        }
        header(url_safe('Location: /system_certmanager.php'));
        exit;
    } elseif ($act == 'csr_info') {
      if (!isset($pconfig['csr'])) {
        http_response_code(400);
        header("Content-Type: text/plain;charset=UTF-8");
        echo gettext('Invalid request');
        exit;
      }

      header("Content-Type: text/plain;charset=UTF-8");
      // use openssl to dump csr in readable format
      $process = proc_open('/usr/local/bin/openssl req -text -noout', array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w")), $pipes);
      if (is_resource($process)) {
        fwrite($pipes[0], $pconfig['csr']);
        fclose($pipes[0]);

        $result_stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $result_stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        echo $result_stdout;
        echo $result_stderr;
      }
      exit;
    } elseif ($act == 'csr_info_json') {
      header("Content-Type: application/json;charset=UTF-8");

      if (!isset($pconfig['csr'])) {
        http_response_code(400);
        echo json_encode(array(
          'error' => gettext('Invalid Request'),
          'error_detail' => gettext('No csr parameter in query')
        ));
        exit;
      }

      $parsed_result = parse_csr($pconfig['csr']);

      if ($parsed_result['parse_success'] !== true) {
        http_response_code(400);
        echo json_encode(array(
          'error' => gettext('CSR file is invalid'),
          'error_detail' => gettext('Could not parse CSR file.')
        ));
        exit;
      }

      echo json_encode($parsed_result);
      exit;
    } elseif ($act == "p12") {
        // export cert+key in p12 format
        if (isset($id)) {
            $exp_name = urlencode("{$a_cert[$id]['descr']}.p12");
            $args = array();
            $args['friendly_name'] = $a_cert[$id]['descr'];

            $ca = lookup_ca($a_cert[$id]['caref']);
            if ($ca) {
                $args['extracerts'] = openssl_x509_read(base64_decode($ca['crt']));
            }
            set_error_handler (
                function () {
                    return;
                }
            );

            $exp_data = '';
            $res_crt = openssl_x509_read(base64_decode($a_cert[$id]['crt']));
            $res_key = openssl_pkey_get_private(array(0 => base64_decode($a_cert[$id]['prv']) , 1 => ''));
            $res_pw = !empty($pconfig['password']) ? $pconfig['password'] : null;
            openssl_pkcs12_export($res_crt, $exp_data, $res_key, $res_pw, $args);
            restore_error_handler();

            $output = json_encode(array(
              'filename' => $exp_name,
              'content' => base64_encode($exp_data)
            ));
            header("Content-Type: application/json;charset=UTF-8");
            // header("Content-Length: ". strlen($output));
            echo $output;
        }
        exit;
    } elseif ($act == "csr") {
        $input_errors = array();
        $pconfig = $_POST;
        if (!isset($id)) {
            header(url_safe('Location: /system_certmanager.php'));
            exit;
        }

        /* input validation */
        $reqdfields = explode(" ", "descr cert");
        $reqdfieldsn = array(
            gettext("Descriptive name"),
            gettext("Final Certificate data"));

        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
        $mod_csr  =  csr_get_modulus($pconfig['csr'], false);
        $mod_cert = cert_get_modulus($pconfig['cert'], false);

        if (strcmp($mod_csr, $mod_cert)) {
            // simply: if the moduli don't match, then the private key and public key won't match
            $input_errors[] = gettext("The certificate modulus does not match the signing request modulus.");
            $subject_mismatch = true;
        }

        /* save modifications */
        if (count($input_errors) == 0) {
            $cert = $a_cert[$id];
            csr_complete($cert, $pconfig['cert']);

            $a_cert[$id] = $cert;

            write_config();

            header(url_safe('Location: /system_certmanager.php'));
            exit;
        }
    } elseif (!empty($_POST['save'])) {
        $input_errors = array();
        $act = isset($_GET['act']) ? $_GET['act'] : null;

        /* input validation */
        if ($pconfig['certmethod'] == "import") {
            $reqdfields = explode(" ", "descr cert key");
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate data"),
                    gettext("Key data"));
            if (!empty($pconfig['cert']) && (!strstr($pconfig['cert'], "BEGIN CERTIFICATE") || !strstr($pconfig['cert'], "END CERTIFICATE"))) {
                $input_errors[] = gettext("This certificate does not appear to be valid.");
            }
        } elseif ($pconfig['certmethod'] == "internal") {
            $reqdfields = explode(" ", "descr caref keytype keylen curve digest_alg lifetime dn_country dn_state dn_city ".
                "dn_organization dn_email dn_commonname"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate authority"),
                    gettext("Key type"),
                    gettext("Key length"),
                    gettext("Curve"),
                    gettext("Digest algorithm"),
                    gettext("Lifetime"),
                    gettext("Distinguished name Country Code"),
                    gettext("Distinguished name State or Province"),
                    gettext("Distinguished name City"),
                    gettext("Distinguished name Organization"),
                    gettext("Distinguished name Email Address"),
                    gettext("Distinguished name Common Name"));
        } elseif ($pconfig['certmethod'] == "external") {
            $reqdfields = explode(" ", "descr csr_keytype csr_keylen csr_curve csr_digest_alg csr_dn_country csr_dn_state csr_dn_city ".
                "csr_dn_organization csr_dn_email csr_dn_commonname"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Key type"),
                    gettext("Key length"),
                    gettext("Curve"),
                    gettext("Digest algorithm"),
                    gettext("Distinguished name Country Code"),
                    gettext("Distinguished name State or Province"),
                    gettext("Distinguished name City"),
                    gettext("Distinguished name Organization"),
                    gettext("Distinguished name Email Address"),
                    gettext("Distinguished name Common Name"));
        } elseif ($pconfig['certmethod'] == "existing") {
            $reqdfields = array("certref");
            $reqdfieldsn = array(gettext("Existing Certificate Choice"));
        } elseif ($pconfig['certmethod'] == 'sign_cert_csr') {
            $reqdfields = array('caref_sign_csr', 'csr', 'lifetime_sign_csr', 'digest_alg_sign_csr');
            $reqdfieldsn = array(gettext("Certificate authority"), gettext("CSR file"), gettext("Lifetime"), gettext("Digest Algorithm"));
        }

        $altnames = array();
        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
        if (isset($pconfig['altname_value']) && $pconfig['certmethod'] != "import" && $pconfig['certmethod'] != "existing" && $pconfig['certmethod'] != 'sign_cert_csr') {
            /* subjectAltNames */
            foreach ($pconfig['altname_type'] as $altname_seq => $altname_type) {
                if (!empty($pconfig['altname_value'][$altname_seq])) {
                    $altnames[] = array("type" => $altname_type, "value" => $pconfig['altname_value'][$altname_seq]);
                }
            }

            /* Input validation for subjectAltNames */
            foreach ($altnames as $altname) {
                if (! is_valid_alt_value($altname, $input_errors)) {
                    break;
                }
            }

            /* Make sure we do not have invalid characters in the fields for the certificate */
            for ($i = 0; $i < count($reqdfields); $i++) {
                if (preg_match('/email/', $reqdfields[$i])) {
                    /* dn_email or csr_dn_name */
                    if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig[$reqdfields[$i]])) {
                        $input_errors[] = gettext("The field 'Distinguished name Email Address' contains invalid characters.");
                    }
                } elseif (preg_match('/commonname/', $reqdfields[$i])) {
                    /* dn_commonname or csr_dn_commonname */
                    if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig[$reqdfields[$i]])) {
                        $input_errors[] = gettext("The field 'Distinguished name Common Name' contains invalid characters.");
                    }
                } elseif ($reqdfields[$i] == "csr_dn_organization") {
                    if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\"\']/", $pconfig["csr_dn_organization"])) {
                        $input_errors[] = gettext("The field 'Distinguished name Organization' contains invalid characters.");
                    }
                } elseif (($reqdfields[$i] != "descr" && $reqdfields[$i] != "csr" && $reqdfields[$i] != "csr_dn_organization") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig[$reqdfields[$i]])) {
                    $input_errors[] = sprintf(gettext("The field '%s' contains invalid characters."), $reqdfieldsn[$i]);
                }
            }

            if ($pconfig['certmethod'] != "external" && !in_array($pconfig["keytype"], array("RSA", "Elliptic Curve"))) {
                $input_errors[] = gettext("Please select a valid Key Type.");
            }
            if ($pconfig['certmethod'] == "internal" && !in_array($pconfig["cert_type"], $cert_types)) {
                $input_errors[] = gettext("Please select a valid Type.");
            }
            if ($pconfig['certmethod'] != "external" && isset($pconfig["keylen"]) && $pconfig["keytype"] == "RSA" && !in_array($pconfig["keylen"], $cert_keylens)) {
                $input_errors[] = gettext("Please select a valid Key Length.");
            }
            if ($pconfig['certmethod'] != "external" && isset($pconfig["curve"]) && $pconfig["keytype"] == "Elliptic Curve" && !in_array($pconfig["curve"], $cert_curves)) {
                $input_errors[] = gettext("Please select a valid Curve.");
            }
            if ($pconfig['certmethod'] != "external" && !in_array($pconfig["digest_alg"], $openssl_digest_algs)) {
                $input_errors[] = gettext("Please select a valid Digest Algorithm.");
            }
            if ($pconfig['certmethod'] == "external" && !in_array($pconfig["keytype"], array("RSA", "Elliptic Curve"))) {
                $input_errors[] = gettext("Please select a valid Key Type.");
            }
            if ($pconfig['certmethod'] == "external" && isset($pconfig["csr_keylen"]) && $pconfig["keytype"] == "RSA" && !in_array($pconfig["csr_keylen"], $cert_keylens)) {
                $input_errors[] = gettext("Please select a valid Key Length.");
            }
            if ($pconfig['certmethod'] == "external" && isset($pconfig["csr_curve"]) && $pconfig["keytype"] == "Elliptic Curve" && !in_array($pconfig["csr_curve"], $cert_curves)) {
                $input_errors[] = gettext("Please select a valid Curve.");
            }
            if ($pconfig['certmethod'] == "external" && !in_array($pconfig["csr_digest_alg"], $openssl_digest_algs)) {
                $input_errors[] = gettext("Please select a valid Digest Algorithm.");
            }
            if ($pconfig['certmethod'] == "sign_cert_csr" && !in_array($pconfig["digest_alg_sign_csr"], $openssl_digest_algs)) {
                $input_errors[] = gettext("Please select a valid Digest Algorithm.");
            }
        }

        // validation and at the same time create $dn for sign_cert_csr
        if ($pconfig['certmethod'] === 'sign_cert_csr') {
            // XXX: we should separate validation and data gathering
            $extns = array();
            if (isset($pconfig['key_usage_sign_csr'])) {
                $san_str = '';
                if (!empty($pconfig['altname_type_sign_csr'])) {
                    for ($i = 0; $i < count($pconfig['altname_type_sign_csr']); ++$i) {
                        if ($pconfig['altname_value_sign_csr'][$i] === '') {
                            continue;
                        }
                        if (! is_valid_alt_value(array(
                            'type' => $pconfig['altname_type_sign_csr'][$i],
                            'value' => $pconfig['altname_value_sign_csr'][$i]), $input_errors
                        )) {
                            break;
                        }
                        if ($san_str !== '') {
                            $san_str .= ', ';
                        }
                        $san_str .= $pconfig['altname_type_sign_csr'][$i] . ':' . $pconfig['altname_value_sign_csr'][$i];
                    }
                }
                if ($san_str !== '') {
                    $extns['subjectAltName'] = $san_str;
                }
                if (is_array($pconfig['key_usage_sign_csr']) && count($pconfig['key_usage_sign_csr']) > 0) {
                    $resstr = '';
                    foreach ($pconfig['key_usage_sign_csr'] as $item) {
                        if (array_key_exists($item, $key_usages)) {
                            if ($resstr !== '') {
                                $resstr .= ', ';
                            }
                            $resstr .= $item;
                        } else {
                            $input_errors[] = gettext("Please select a valid keyUsage.");
                            break;
                        }
                    }
                    $extns['keyUsage'] = $resstr;
                }
                if (is_array($pconfig['extended_key_usage_sign_csr']) && count($pconfig['extended_key_usage_sign_csr']) > 0) {
                    $resstr = '';
                    foreach ($pconfig['extended_key_usage_sign_csr'] as $item) {
                        if (array_key_exists($item, $extended_key_usages)) {
                            if ($resstr !== '') {
                                $resstr .= ', ';
                            }
                            $resstr .= $item;
                        } else {
                            $input_errors[] = gettext("Please select a valid extendedKeyUsage.");
                            break;
                        }
                    }
                    $extns['extendedKeyUsage'] = $resstr;
                }
                if ($pconfig['basic_constraints_is_ca_sign_csr'] === 'true') {
                    $extns['basicConstraints'] = 'CA:' . ((isset($pconfig['basic_constraints_is_ca_sign_csr']) && $pconfig['basic_constraints_is_ca_sign_csr'] === 'true') ? 'TRUE' : 'false');
                    if (isset($pconfig['basic_constraints_path_len_sign_csr']) && $pconfig['basic_constraints_path_len_sign_csr'] != '') {
                        $extns['basicConstraints'] .= ', pathlen:' . ((int) $pconfig['basic_constraints_path_len_sign_csr']);
                    }
                }
            }
        }

        /* save modifications */
        if (count($input_errors) == 0) {
            if ($pconfig['certmethod'] == "existing") {
                $cert = lookup_cert($pconfig['certref']);
                if ($cert && !empty($userid)) {
                    $a_user[$userid]['cert'][] = $cert['refid'];
                }
            } else {
                $cert = array();
                $cert['refid'] = uniqid();
                if (isset($id) && $a_cert[$id]) {
                    $cert = $a_cert[$id];
                }

                $cert['descr'] = $pconfig['descr'];

                $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
                if ($pconfig['keytype'] == "Elliptic Curve") {
                    $pconfig['keylen_curve'] = $pconfig['curve'];
                } else {
                    $pconfig['keylen_curve'] = $pconfig['keylen'];
                }
                if ($pconfig['csr_keytype'] == "Elliptic Curve") {
                    $pconfig['csr_keylen_curve'] = $pconfig['csr_curve'];
                } else {
                    $pconfig['csr_keylen_curve'] = $pconfig['csr_keylen'];
                }
                if ($pconfig['certmethod'] == "import") {
                    cert_import($cert, $pconfig['cert'], $pconfig['key']);
                } elseif ($pconfig['certmethod'] == "internal") {
                    $dn = array(
                        'countryName' => $pconfig['dn_country'],
                        'stateOrProvinceName' => $pconfig['dn_state'],
                        'localityName' => $pconfig['dn_city'],
                        'organizationName' => $pconfig['dn_organization'],
                        'emailAddress' => $pconfig['dn_email'],
                        'commonName' => $pconfig['dn_commonname']);
                    $extns = array();
                    if (count($altnames)) {
                        $altnames_tmp = array();
                        foreach ($altnames as $altname) {
                            $altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
                        }
                        $extns['subjectAltName'] = implode(",", $altnames_tmp);
                    }

                    if (!cert_create(
                        $cert,
                        $pconfig['caref'],
                        $pconfig['keylen_curve'],
                        $pconfig['lifetime'],
                        $dn,
                        $pconfig['digest_alg'],
                        $pconfig['cert_type'],
                        $extns
                    )) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            $input_errors[] = gettext("openssl library returns:") . " " . $ssl_err;
                        }
                    }
                    if ($pconfig['private_key_location'] === 'local') {
                        // unset private key before safe
                        $act = 'download_private_key';
                        $pconfig['private_key'] = base64_decode($cert['prv']);
                        unset($cert['prv']);
                    }
                } elseif ($pconfig['certmethod'] === 'sign_cert_csr') {
                    if (!sign_cert_csr($cert, $pconfig['caref_sign_csr'], $pconfig['csr'], (int) $pconfig['lifetime_sign_csr'],
                                       $pconfig['digest_alg_sign_csr'], $extns)) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            $input_errors[] = gettext("openssl library returns:") . " " . $ssl_err;
                        }
                    }
                } elseif ($pconfig['certmethod'] == "external") {
                    $dn = array(
                        'countryName' => $pconfig['csr_dn_country'],
                        'stateOrProvinceName' => $pconfig['csr_dn_state'],
                        'localityName' => $pconfig['csr_dn_city'],
                        'organizationName' => $pconfig['csr_dn_organization'],
                        'emailAddress' => $pconfig['csr_dn_email'],
                        'commonName' => $pconfig['csr_dn_commonname']);
                    $extns = array();
                    if (!empty($pconfig['csr_dn_organizationalunit'])) {
                        $dn['organizationalUnitName'] = $pconfig['csr_dn_organizationalunit'];
                    }
                    if (count($altnames)) {
                        $altnames_tmp = array();
                        foreach ($altnames as $altname) {
                            $altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
                        }
                        $extns['subjectAltName'] = implode(",", $altnames_tmp);
                    }
                    if (!csr_generate($cert, $pconfig['csr_keylen_curve'], $dn, $pconfig['csr_digest_alg'], $extns)) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            $input_errors[] = gettext("openssl library returns:") . " " . $ssl_err;
                        }
                    }
                }
                error_reporting($old_err_level);

                if (isset($id)) {
                    $a_cert[$id] = $cert;
                } else {
                    $a_cert[] = $cert;
                }
                if (isset($a_user) && isset($userid)) {
                    $a_user[$userid]['cert'][] = $cert['refid'];
                }
            }
            if (count($input_errors) == 0) {
                write_config();
                if ($act !== 'download_private_key') {
                    if (isset($userid)) {
                        header(url_safe('Location: /system_usermanager.php?act=edit&userid=%d', array($userid)));
                    } else {
                        header(url_safe('Location: /system_certmanager.php'));
                    }
                    exit;
                }
            }

        }
    }
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_ca);
legacy_html_escape_form_data($a_cert);

include("head.inc");

if (empty($act)) {
    $main_buttons = array(
        array('label' => gettext('Add'), 'href' => 'system_certmanager.php?act=new'),
    );
}

?>

<body>
  <style>
    .monospace-dialog {
      font-family: monospace;
      white-space: pre;
    }

    .monospace-dialog > .modal-dialog {
      width:70% !important;
    }

    .modal-body {
      max-height: calc(100vh - 210px);
      overflow-y: auto;
    }
  </style>
  <script>
  $( document ).ready(function() {
    // delete entry
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data('id');
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Certificates");?>",
        message: "<?=gettext("Do you really want to delete this Certificate?");?>",
        buttons: [{
                  label: "<?=gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?=gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#action").val("del");
                    $("#iform").submit()
                }
              }]
      });
    });

    $('.p12btn').on('click', function(event) {
        event.preventDefault();
        var id = $(this).data('id');

        let password_input = $('<input type="password" class="form-control password_field" placeholder="<?=html_safe(gettext("Password"));?>">');
        let confirm_input = $('<input type="password" class="form-control password_field" placeholder="<?=html_safe(gettext("Confirm"));?>">');
        let dialog_items = $('<div class = "form-group">');
        dialog_items.append(
          $("<span>").text("<?=html_safe(gettext('Optionally use a password to protect your export'));?>"),
          $('<table class="table table-condensed"/>').append(
            $("<tbody/>").append(
              $("<tr/>").append($("<td/>").append(password_input)),
              $("<tr/>").append($("<td/>").append(confirm_input))
            )
          )
        );

        // highlight password/confirm when not equal
        let keyup_pass = function() {
            if (confirm_input.val() !== password_input.val()) {
                $(".password_field").addClass("has-warning");
                $(".password_field").closest('div').addClass('has-warning');
            } else {
                $(".password_field").removeClass("has-warning");
                $(".password_field").closest('div').removeClass('has-warning');
            }
        };
        confirm_input.on('keyup', keyup_pass);
        password_input.on('keyup', keyup_pass);


        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_INFO,
            title: "<?= gettext("Certificates");?>",
            message: dialog_items,
            buttons: [
                {
                    label: "<?=html_safe(gettext("Close"));?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }
                }, {
                    label: '<i class="fa fa-download fa-fw"></i> <?=html_safe(gettext("Download"));?>',
                    action: function(dialogRef) {
                        if (confirm_input.val() === password_input.val()) {
                            $.post('system_certmanager.php', {'id': id, 'act': 'p12', 'password': password_input.val()}, function (data) {
                                var link = $('<a></a>')
                                    .attr('href','data:application/octet-stream;base64,' + data.content)
                                    .attr('download', data.filename)
                                    .appendTo('body');
                                link.ready(function() {
                                    link.get(0).click();
                                    link.empty();
                                });
                            });
                            dialogRef.close();
                        }
                    }
                }
            ]
        });
    });

    $(".act_info").click(function(event){
        event.preventDefault();
        var id = $(this).data('id');
        $.ajax({
                url:"system_certmanager.php",
                type: 'get',
                data: {'act' : 'info', 'id' :id},
                success: function(data){
                  BootstrapDialog.show({
                              title: '<?=gettext("Certificate");?>',
                              type:BootstrapDialog.TYPE_INFO,
                              message: $("<div/>").text(data).html(),
                              cssClass: 'monospace-dialog',
                          });
                }
        });

    });

    $(".csr_info_for_sign_csr").click(function(event){
        event.preventDefault();
        var csr_payload = $('#csr').val();
        $.ajax({
                url:"system_certmanager.php",
                type: 'post',
                data: {'act' : 'csr_info', 'csr' : csr_payload},
                success: function(data){
                  BootstrapDialog.show({
                      title: '<?=gettext("Certificate Request");?>',
                      type:BootstrapDialog.TYPE_INFO,
                      message: $("<div/>").text(data).html(),
                      cssClass: 'monospace-dialog',
                  });
                }
        });
    });

    $(".x509_extension_step_sign_csr").click(function(event){
        event.preventDefault();
        var csr_payload = $('#csr').val();
        $.ajax({
                url:"system_certmanager.php",
                type: 'post',
                data: {'act' : 'csr_info_json', 'csr' : csr_payload},
                success: function(data){
                        subject_text = '';
                        Object.keys(data.subject).forEach(function(item) {
                                if (subject_text != '') {
                                        subject_text += ', ';
                                }
                                subject_text += item + '=' + data.subject[item];
                        });

                        $('#next_button_for_x509_extension_step_sign_csr').addClass('hidden');
                        $('#csr').prop('readonly', true);
                        $('#x509_extension_step_sign_cert_csr').removeClass('hidden');
                        $('#subject_sign_csr').text(subject_text);
                        $('#subject_alt_name_sign_csr_table > tbody > tr:gt(0)').remove();
                        if ('subjectAltName' in data) {
                              data.subjectAltName.forEach(function(item) {
                                      addRowAltSignCSR(item.type, item.value);
                              });
                        }
                        if ('basicConstraints' in data) {
                                $('#basic_constraints_is_ca_sign_csr').prop('checked', data.basicConstraints.CA);
                                $('#basic_constraints_path_len_sign_csr').val(
                                      ('pathlen' in data.basicConstraints) ? data.basicConstraints.pathlen : ''
                                );
                        } else {
                                $('#basic_constraints_is_ca_sign_csr').prop('checked', false);
                                $('#basic_constraints_path_len_sign_csr').val('');
                        }
                        $("#basic_constraints_is_ca_sign_csr").change();

                        $('#key_usage_sign_csr option').prop('selected', false);
                        if ('keyUsage' in data) {
                                data.keyUsage.forEach(function(item) {
                                        $('#key_usage_sign_csr_' + item).prop('selected', true);
                                });

                        }
                        $("#key_usage_sign_csr").selectpicker('refresh');

                        $('#extended_key_usage_sign_csr option').prop('selected', false);
                        if ('extendedKeyUsage' in data) {
                                data.extendedKeyUsage.forEach(function(item) {
                                        $('#extended_key_usage_sign_csr_' + (item.replace(/\./g, '_'))).prop('selected', true);
                                });

                        }
                        $("#extended_key_usage_sign_csr").selectpicker('refresh');

                        $('#submit').removeClass('hidden');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                        if (jqXHR.status == 400) {
                                BootstrapDialog.show({
                                        type:BootstrapDialog.TYPE_DANGER,
                                        title: jqXHR.responseJSON.error,
                                        message: jqXHR.responseJSON.error_detail,
                                        buttons: [
                                            {
                                                label: "<?=gettext("OK");?>",
                                                action: function(dialogRef) { dialogRef.close(); }
                                            }
                                       ]
                                });
                                return;
                        }
                        BootstrapDialog.show({
                                type:BootstrapDialog.TYPE_DANGER,
                                title: "<?= gettext("Unknown Error");?>",
                                message: "<?= gettext("Unknown error occured. Try again.");?>",
                                buttons: [
                                    {
                                        label: "<?=gettext("OK");?>",
                                        action: function(dialogRef) { dialogRef.close(); }
                                    }
                               ]
                        });
                },
        });
    });

    // parameter 'type' must not include non-alphabet characters
    function addRowAltSignCSR(type, value) {
        let $tr = $("#subject_alt_name_sign_csr_table > tbody > tr:eq(0)").clone();
        $tr.find("select").prop("name", "altname_type_sign_csr[]").val(type);
        $tr.find("input").prop("name", "altname_value_sign_csr[]").val(value);
        $tr.removeClass("hidden");
        $("#subject_alt_name_sign_csr_table > tbody").append($tr);
        $(".act-removerow-altnm-sign-csr").unbind('click').on('click', function(){
            $(this).parent().parent().remove();
        });
    }

    $("#addNewAltNmSignCSR").click(function(){
        addRowAltSignCSR('', '');
    });

    $("#basic_constraints_is_ca_sign_csr").change(function(){
        $('#basic_constraints_path_len_sign_csr').prop(
          'disabled', !$('#basic_constraints_is_ca_sign_csr').prop('checked')
        )
    });


    /**
     * remove row from altNametable
     */
    function removeRowAltNm() {
        if ( $('#altNametable > tbody > tr').length == 1 ) {
            $('#altNametable > tbody > tr:last > td > input').each(function(){
              $(this).val('');
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // javascript only for edit forms
    if ($('#certmethod').length) {
        // no ca's found, display message
        if ($("#caref option").length == 0) {
            $("#no_caref").removeClass("hidden");
            $("#caref").addClass("hidden");
        }
        // add new detail record
        $("#addNewAltNm").click(function(){
            // copy last row and reset values
            $('#altNametable > tbody').append('<tr>'+$('#altNametable > tbody > tr:last').html()+'</tr>');
            $('#altNametable > tbody > tr:last > td > input').each(function(){
              $(this).val('');
            });
            $(".act-removerow-altnm").click(removeRowAltNm);
        });
        $(".act-removerow-altnm").click(removeRowAltNm);


        $("#certmethod").change(function(){
            $("#submit").addClass("hidden");
            $("#import").addClass("hidden");
            $("#internal").addClass("hidden");
            $("#external").addClass("hidden");
            $("#existing").addClass("hidden");
            $("#sign_cert_csr").addClass("hidden");
            $("#x509_extension_sign_cert_csr").addClass("hidden");
            $('#x509_extension_step_sign_cert_csr').addClass('hidden');
            if ($(this).val() == "import") {
                $("#import").removeClass("hidden");
                $("#submit").removeClass("hidden");
            } else if ($(this).val() == "internal") {
                $("#internal").removeClass("hidden");
                $("#altNameTr").detach().appendTo("#internal > tbody:first");
                $("#submit").removeClass("hidden");
            } else if ($(this).val() == "external") {
                $("#external").removeClass("hidden");
                $("#altNameTr").detach().appendTo("#external > tbody:first");
                $("#submit").removeClass("hidden");
            } else if ($(this).val() == "sign_cert_csr") {
                $("#sign_cert_csr").removeClass("hidden");
                $('#next_button_for_x509_extension_step_sign_csr').removeClass('hidden');
                $('#csr').prop('readonly', false);
            } else {
                $("#existing").removeClass("hidden");
                $("#submit").removeClass("hidden");
            }
        });

        $("#certmethod").change();
    }


    if (document.createElement('a').download !== undefined) {
        $(".text_download_btn").click(function(event){
            event.preventDefault();
            if ($(this).attr('for')) {
                let target = $("#"+$(this).attr('for'));
                var link = $('<a></a>')
                    .attr('href', URL.createObjectURL(new Blob([target.val()])))
                    .attr('download', target.data('filename'))
                    .appendTo('body');

                link.ready(function() {
                    link.get(0).click();
                    link.empty();
                });

            }
        });
    } else {
        $(".text_download_btn").remove();
    }

$("#keytype").change(function(){
        $("#EC").addClass("hidden");
        $("#RSA").addClass("hidden");
        $("#blank").addClass("hidden");
        if ($(this).val() == "Elliptic Curve") {
            $("#EC").removeClass("hidden");
        } else {
            $("#RSA").removeClass("hidden");
        }
});

$("#keytype").change();

$("#csr_keytype").change(function(){
        $("#csr_EC").addClass("hidden");
        $("#csr_RSA").addClass("hidden");
        $("#csr_blank").addClass("hidden");
        if ($(this).val() == "Elliptic Curve") {
            $("#csr_EC").removeClass("hidden");
        } else {
            $("#csr_RSA").removeClass("hidden");
        }
});

$("#csr_keytype").change();

  });

  </script>

<?php include("fbegin.inc"); ?>
<script>
$( document ).ready(function() {
//<![CDATA[
  function internalca_change() {

    let index = document.iform.caref.selectedIndex;
    let caref = document.iform.caref[index].value;

    switch (caref) {
  <?php
  foreach ($a_ca as $ca) :
      if (!$ca['prv']) {
          continue;
      }
      $subject = cert_get_subject_array($ca['crt']);
      legacy_html_escape_form_data($subject);
      $subject_items = array('C'=>'', 'ST' => '', 'L' => '', 'O' => '', 'emailAddress' => '', 'CN' => '');
      foreach ($subject as $subject_item) {
          $subject_items[$subject_item['a']] = $subject_item['v'];
      }
  ?>
      case "<?=$ca['refid'];?>":
          $("#dn_state").val("<?=$subject_items['ST'];?>");
          $("#dn_city").val("<?=$subject_items['L'];?>");
          $("#dn_organization").val("<?=$subject_items['O'];?>");
          $("#dn_email").val("<?=$subject_items['emailAddress'];?>");
          $('#dn_country option').prop('selected', false);
          $('#dn_country option').filter('[value="<?=$subject_items['C'];?>"]').prop('selected', true);
          $("#dn_country").selectpicker('refresh');
          break;
  <?php
  endforeach; ?>
    }
  }

  // only trigger change event when in edit mode.
  if ($('#certmethod').length) {
    $("#caref").change(internalca_change);
    $("#caref").change();
  }
});

//]]>
</script>

<!-- row -->
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
      <section class="col-xs-12">
        <div class="content-box tab-content table-responsive">

        <!--- New --->
<?php
        if ($act == "new") :?>
          <form method="post" name="iform" id="iform" >
            <input type="hidden" name="act" value="<?=$act;?>"/>
<?php
            if (isset($userid)) :?>
            <input name="userid" type="hidden" value="<?=htmlspecialchars($userid);?>" />
<?php
            endif;?>
<?php
            if (isset($id)) :?>
            <input name="id" type="hidden" value="<?=$id;?>" />
<?php
            endif;?>
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"></td>
                <td  style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Method");?></td>
                <td>
                  <select name="certmethod" id="certmethod">
<?php
                  foreach ($cert_methods as $method => $desc) :?>
                    <option value="<?=$method;?>" <?=$pconfig['certmethod'] == $method ? 'selected="selected"' : ''; ?>>
                      <?=$desc;?>
                    </option>
<?php
                  endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Descriptive name");?></td>
                <td>
                  <input name="descr" type="text" id="descr" size="20" value="<?=$pconfig['descr'];?>"/>
                </td>
              </tr>
            </table>
            <!-- existing cert -->
            <table id="import" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <th colspan="2"><?=gettext("Import Certificate");?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="width:22%"><a id="help_for_cert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Certificate data");?></td>
                  <td style="width:78%">
                    <textarea name="cert" id="cert" cols="65" rows="7"><?=$pconfig['cert'];?></textarea>
                    <div class="hidden" data-for="help_for_cert">
                      <?=gettext("Paste a certificate in X.509 PEM format here.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_key" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Private key data");?></td>
                  <td>
                    <textarea name="key" id="key" cols="65" rows="7" class="formfld_cert"><?=$pconfig['key'];?></textarea>
                    <div class="hidden" data-for="help_for_key">
                      <?=gettext("Paste a private key in X.509 PEM format here.");?>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <!-- sign_cert_csr -->
            <table id="sign_cert_csr" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <th colspan="2"><?=gettext("Sign CSR");?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="width:22%"><?=gettext("Certificate authority");?></td>
                  <td style="width:78%">
                    <select name='caref_sign_csr' id='caref_sign_csr'>
  <?php
                    foreach ($a_ca as $ca) :
                        if (empty($ca['prv'])) {
                            continue;
                        }?>
                      <option value="<?=$ca['refid'];?>" <?=isset($pconfig['caref_sign_csr']) && isset($ca['refid']) && $pconfig['caref_sign_csr'] == $ca['refid'] ? 'selected="selected"' : '';?>><?=html_safe($ca['descr']);?></option>
  <?php
                    endforeach; ?>
                    </select>
                    <div class="hidden" id="no_caref_sign_csr">
                      <?=sprintf(gettext("No internal Certificate Authorities have been defined. You must %sadd%s an internal CA before creating an internal certificate."),'<a href="system_camanager.php?act=new&amp;method=internal">','</a>');?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_digest_alg_sign_csr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Digest Algorithm");?></td>
                  <td>
                    <select name='digest_alg_sign_csr' id='digest_alg_sign_csr'>
  <?php
                    foreach ($openssl_digest_algs as $digest_alg) :?>
                      <option value="<?=$digest_alg;?>" <?=$pconfig['digest_alg_sign_csr'] == $digest_alg ? 'selected="selected"' : '';?>>
                        <?=strtoupper($digest_alg);?>
                      </option>
  <?php
                    endforeach; ?>
                    </select>
                    <div class="hidden" data-for="help_for_digest_alg_sign_csr">
                      <?= gettext("NOTE: It is recommended to use an algorithm stronger than SHA1 when possible.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Lifetime");?> (<?=gettext("days");?>)</td>
                  <td>
                    <input name="lifetime_sign_csr" type="text" id="lifetime_sign_csr" size="5" value="<?=$pconfig['lifetime_sign_csr'];?>"/>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_csr_sign_csr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("CSR file");?></td>
                  <td>
                    <textarea name="csr" id="csr" cols="65" rows="7"><?=$pconfig['csr'];?></textarea><br/>
                    <a href="#" class="csr_info_for_sign_csr btn btn-secondary"><?=gettext("Show Detail");?></a><br/>
                    <div class="hidden" data-for="help_for_csr_sign_csr">
                      <?=gettext("Paste the CSR file here.");?>
                    </div>
                  </td>
                </tr>
                <tr id="next_button_for_x509_extension_step_sign_csr">
                  <td>&nbsp;</td>
                  <td>
                    <a href="#" class="x509_extension_step_sign_csr btn btn-primary"><?=gettext("Next");?></a>
                  </td>
                </tr>
              </tbody>
            </table>
            <div id="x509_extension_step_sign_cert_csr" class="hidden">
              <table class="table table-striped opnsense_standard_table_form">
                <thead>
                  <tr>
                    <th colspan="2"><?=gettext("Subject of the certificate");?></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('Subject');?></td>
                    <td id="subject_sign_csr" style="width:78%"></td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('subjectAltName');?></td>
                    <td>
                      <table class="table table-condensed" id="subject_alt_name_sign_csr_table">
                        <thead>
                            <tr>
                              <th><?=gettext("Type");?></th>
                              <th><?=gettext("Value");?></th>
                              <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hidden">
                                <td>
                                  <select>
                                      <option value="DNS"><?=gettext("DNS");?></option>
                                      <option value="IP"><?=gettext("IP");?></option>
                                      <option value="email"><?=gettext("email");?></option>
                                      <option value="URI"><?=gettext("URI");?></option>
                                  </select>
                                </td>
                                <td>
                                  <input type="text" size="20" value="">
                                </td>
                                <td>
                                  <div style="cursor:pointer;" class="act-removerow-altnm-sign-csr btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="2"></td>
                            <td>
                              <div id="addNewAltNmSignCSR" style="cursor:pointer;" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></div>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_basic_extensions_sign_csr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('basicConstraints');?></td>
                    <td>
                      <input type="checkbox" name="basic_constraints_is_ca_sign_csr" id="basic_constraints_is_ca_sign_csr" value="true" /> <?= gettext('is CA'); ?><br />
                      <?= gettext('Maximum Path Length'); ?>: <input type="text" name="basic_constraints_path_len_sign_csr" id="basic_constraints_path_len_sign_csr" size="5" value="<?=$pconfig['basic_constraints_sign_csr'];?>"/>
                      <div class="hidden" data-for="help_for_basic_extensions_sign_csr">
                        <strong><?= gettext('Maximum Path Length'); ?></strong>: <?= gettext('Define the maximum number of non-self-issued intermediate certificates that may follow this certificate in a valid certification path.'); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_key_usage_sign_csr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('keyUsage');?></td>
                    <td>
                      <select name="key_usage_sign_csr[]" title="<?html_safe(gettext("Select keyUsage"));?>" multiple="multiple" id="key_usage_sign_csr" class="selectpicker" data-live-search="true" data-size="5" tabindex="2" <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>>
<?php
                      foreach ($key_usages as $key => $human_readable): ?>
                        <option value="<?=$key;?>" id="key_usage_sign_csr_<?=$key;?>">
                          <?= $human_readable; ?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_key_usage_sign_csr">
                        <?=gettext("Practical examples:");?>
                        <ul>
                          <li><strong><?= gettext('Client Certificate'); ?></strong>: <?= $key_usages['nonRepudiation']; ?>, <?= $key_usages['digitalSignature']; ?>, <?= $key_usages['keyEncipherment']; ?></li>
                          <li><strong><?= gettext('Server Certificate'); ?></strong>: <?= $key_usages['digitalSignature']; ?>, <?= $key_usages['keyEncipherment']; ?></li>
                          <li><strong><?= gettext('Combined Client/Server Certificate'); ?></strong>: <?= $key_usages['nonRepudiation']; ?>, <?= $key_usages['digitalSignature']; ?>, <?= $key_usages['keyEncipherment'];?></li>
                          <li><strong><?= gettext('Certificate Authority'); ?></strong>: <i><?= gettext('None. Just add CA option in basicConstraits.'); ?></i></li>
                        </ul>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_extended_key_usage_sign_csr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('extendedKeyUsage');?></td>
                    <td>
                      <select name="extended_key_usage_sign_csr[]" title="<?html_safe(gettext("Select extendedKeyUsage"));?>" multiple="multiple" id="extended_key_usage_sign_csr" class="selectpicker" data-live-search="true" data-size="5" tabindex="2" <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>>
<?php
                      foreach ($extended_key_usages as $key => $human_readable): ?>
                        <option value="<?=$key;?>" id="extended_key_usage_sign_csr_<?= str_replace('.', '_', $key);?>">
                          <?= $human_readable; ?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                    <div class="hidden" data-for="help_for_extended_key_usage_sign_csr">
                      <?=gettext("Practical examples:");?>
                      <ul>
                        <li><strong><?= gettext('Client Certificate'); ?></strong>: <?= $extended_key_usages['1.3.6.1.5.5.7.3.2']; ?></li>
                        <li><strong><?= gettext('Server Certificate'); ?></strong>: <?= $extended_key_usages['1.3.6.1.5.5.7.3.1'] ?>, <?= $extended_key_usages['1.3.6.1.5.5.8.2.2']; ?></li>
                        <li><strong><?= gettext('Combined Client/Server Certificate'); ?></strong>: <?= $extended_key_usages['1.3.6.1.5.5.7.3.2']; ?>, <?= $extended_key_usages['1.3.6.1.5.5.7.3.1'] ?>, <?= $extended_key_usages['1.3.6.1.5.5.8.2.2']; ?></li>
                        <li><strong><?= gettext('Certificate Authority'); ?></strong>: <i><?= gettext('None. Just add CA option in basicConstraits.'); ?></i></li>
                      </ul>
                    </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <!-- internal cert -->
            <table id="internal" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <th colspan="2"><?=gettext("Internal Certificate");?></th>
                </tr>
              </thead>
              <tbody>
              <tr>
                <td style="width:22%"><?=gettext("Certificate authority");?></td>
                <td style="width:78%">
                  <select name='caref' id='caref'>
<?php
                  foreach ($a_ca as $ca) :
                      if (!$ca['prv']) {
                          continue;
                      }?>
                    <option value="<?=$ca['refid'];?>" <?=isset($pconfig['caref']) && isset($ca['refid']) && $pconfig['caref'] == $ca['refid'] ? 'selected="selected"' : '';?>><?=$ca['descr'];?></option>
<?php
                  endforeach; ?>
                  </select>
                  <div class="hidden" id="no_caref">
                    <?=sprintf(gettext("No internal Certificate Authorities have been defined. You must %sadd%s an internal CA before creating an internal certificate."),'<a href="system_camanager.php?act=new&amp;method=internal">','</a>');?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_cert_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type");?> </td>
                <td>
                    <select name="cert_type">
                        <option value="usr_cert" <?=$pconfig['cert_type'] == 'usr_cert' ? 'selected="selected"' : '';?>> <?=gettext("Client Certificate");?> </option>
                        <option value="server_cert" <?=$pconfig['cert_type'] == 'server_cert' ? 'selected="selected"' : '';?>> <?=gettext("Server Certificate");?> </option>
                        <option value="combined_server_client" <?=$pconfig['cert_type'] == 'combined_server_client' ? 'selected="selected"' : '';?>> <?=gettext("Combined Client/Server Certificate");?> </option>
                        <option value="v3_ca" <?=$pconfig['cert_type'] == 'v3_ca' ? 'selected="selected"' : '';?>> <?=gettext("Certificate Authority");?> </option>
                    </select>
                    <div class="hidden" data-for="help_for_digest_cert_type">
                      <?=gettext("Choose the type of certificate to generate here, the type defines it's constraints");?>
                    </div>
                </td>
              </tr>
              <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key Type");?></td>
                  <td style="width:78%">
                    <select name='keytype' id='keytype' class="selectpicker">
                  <option value="RSA" <?=$pconfig['keytype'] == "RSA" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("RSA");?>
                  </option>
                  <option value="Elliptic Curve" <?=$pconfig['keytype'] == "Elliptic Curve" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("Elliptic Curve");?>
                  </option>
                    </select>
                  </td>
                </tr>
              <tr id='RSA'>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key length");?> (<?=gettext("bits");?>)</td>
                <td>
                  <select name='keylen'>
<?php
                  foreach ($cert_keylens as $len) :?>
                    <option value="<?=$len;?>" <?=isset($pconfig['keylen']) && $pconfig['keylen'] == $len ? "selected=\"selected\"" : "";?>><?=$len;?></option>
<?php
                    endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr id='blank'><td></td></tr>
              <tr id='EC'>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Curve");?></td>
                  <td style="width:78%">
                    <select name='curve' id='curve' class="selectpicker">
<?php
                    foreach ($cert_curves as $curve) :?>
                      <option value="<?=$curve;?>" <?=isset($pconfig['curve']) && $pconfig['curve'] == $curve ? "selected=\"selected\"" : "";?>><?=$curve;?></option>
<?php
                    endforeach; ?>
                    </select>
                  </td>
                </tr>
              <tr>
                <td><a id="help_for_digest_alg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Digest Algorithm");?></td>
                <td>
                  <select name='digest_alg' id='digest_alg'>
<?php
                  foreach ($openssl_digest_algs as $digest_alg) :?>
                    <option value="<?=$digest_alg;?>" <?=$pconfig['digest_alg'] == $digest_alg ? 'selected="selected"' : '';?>>
                      <?=strtoupper($digest_alg);?>
                    </option>
<?php
                  endforeach; ?>
                  </select>
                  <div class="hidden" data-for="help_for_digest_alg">
                    <?= gettext("NOTE: It is recommended to use an algorithm stronger than SHA1 when possible.") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Lifetime");?> (<?=gettext("days");?>)</td>
                <td>
                  <input name="lifetime" type="text" id="lifetime" size="5" value="<?=$pconfig['lifetime'];?>"/>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_private_key_location" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Private key location");?></td>
                <td>
                  <select name="private_key_location" id="private_key_location">
                    <option value="firewall" <?= $pconfig['private_key_location'] === 'firewall' ? 'selected="selected"' : ''; ?>><?= gettext('Save on this firewall'); ?></option>
                    <option value="local"    <?= $pconfig['private_key_location'] === 'local'    ? 'selected="selected"' : ''; ?>><?= gettext('Download and do not save'); ?></option>
                  </select>
                  <div class="hidden" data-for="help_for_private_key_location">
                    <strong><?= gettext('Save on this firewall'); ?></strong>: <?= gettext("Normally choose this."); ?><br/>
                    <strong><?= gettext('Download and do not save'); ?></strong>: <?= gettext("If the certificate is for use by a device other than this firewall, and you can download private key soon after Saving, choose this. By this option you can download the private key, which is not saved onto this firewall."); ?><br/>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2"><?=gettext("Distinguished name");?> </th>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Country Code");?> : &nbsp;</td>
                <td>
                  <select name="dn_country" id="dn_country" class="selectpicker">
<?php
                  foreach (get_country_codes() as $cc => $cn):?>
                    <option value="<?=$cc;?>" <?=$pconfig['dn_country'] == $cc ? 'selected="selected"' : '';?>>
                      <?=$cc;?> (<?=$cn;?>)
                    </option>
<?php
                  endforeach;?>
                  </select>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_dn_state" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State or Province");?> : &nbsp;</td>
                <td>
                  <input name="dn_state" id="dn_state" type="text" size="40" value="<?=$pconfig['dn_state'];?>"/>
                  <div class="hidden" data-for="help_for_digest_dn_state">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("Sachsen");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_dn_city" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("City");?> : &nbsp;</td>
                <td>
                  <input name="dn_city" id="dn_city" type="text" size="40" value="<?=$pconfig['dn_city'];?>"/>
                  <div class="hidden" data-for="help_for_digest_dn_city">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("Leipzig");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_dn_organization" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Organization");?> : &nbsp;</td>
                <td>
                  <input name="dn_organization" id="dn_organization" type="text" size="40" value="<?=$pconfig['dn_organization'];?>"/>
                  <div class="hidden" data-for="help_for_digest_dn_organization">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("My Company Inc");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_dn_email" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email Address");?> : &nbsp;</td>
                <td>
                  <input name="dn_email" id="dn_email" type="text" size="25" value="<?=$pconfig['dn_email'];?>"/>
                  <div class="hidden" data-for="help_for_digest_dn_email">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("admin@mycompany.com");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_dn_commonname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Common Name");?> : &nbsp;</td>
                <td>
                  <input name="dn_commonname" id="dn_commonname" type="text" size="25" value="<?=$pconfig['dn_commonname'];?>"/>
                  <div class="hidden" data-for="help_for_digest_dn_commonname">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("internal-ca");?>
                  </div>
                </td>
              </tr>
              <tr id="altNameTr">
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Alternative Names");?></td>
                <td>
                  <table class="table table-condensed" id="altNametable">
                    <thead>
                        <tr>
                          <th><?=gettext("Type");?></th>
                          <th><?=gettext("Value");?></th>
                          <th></th>
                        </tr>
                    </thead>
                    <tbody>
<?php
                      if (!isset($pconfig['altname_value']) || count($pconfig['altname_value']) ==0) :?>
                      <tr>
                        <td>
                          <select name="altname_type[]" id="altname_type">
                            <option value="DNS"><?=gettext("DNS");?></option>
                            <option value="IP"><?=gettext("IP");?></option>
                            <option value="email"><?=gettext("email");?></option>
                            <option value="URI"><?=gettext("URI");?></option>
                          </select>
                        </td>
                        <td>
                          <input name="altname_value[]" type="text" size="20" value="" />
                        </td>
                        <td>
                          <div style="cursor:pointer;" class="act-removerow-altnm btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                        </td>
                      </tr>
<?php
                      else:
                        foreach ($pconfig['altname_value'] as $itemid => $item) :
                          $altname_type = isset($pconfig['altname_type'][$itemid]) ? $pconfig['altname_type'][$itemid] : null; ?>
                        <tr>
                          <td>
                            <select name="altname_type[]" id="altname_type">
                              <option value="DNS" <?=$altname_type == 'DNS' ? 'selected="selected"' : '';?>><?=gettext('DNS');?></option>
                              <option value="IP" <?=$altname_type == 'IP' ? 'selected="selected"' : '';?>><?=gettext('IP');?></option>
                              <option value="email" <?=$altname_type == 'email' ? 'selected="selected"' : '';?>><?=gettext('email');?></option>
                              <option value="URI" <?=$altname_type == 'URI' ? 'selected="selected"' : '';?>><?=gettext('URI');?></option>
                            </select>
                          </td>
                          <td>
                            <input name="altname_value[]" type="text" size="20" value="<?=$item;?>" />
                          </td>
                          <td>
                            <div style="cursor:pointer;" class="act-removerow-altnm btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                          </td>
                        </tr>

<?php
                        endforeach;
                      endif;?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colspan="2"></td>
                        <td>
                          <div id="addNewAltNm" style="cursor:pointer;" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></div>
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </td>
              </tr>
              </tbody>
            </table>
            <!-- external cert -->
            <table id="external" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <td colspan="2"><?=gettext("External Signing Request");?></td>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key Type");?></td>
                  <td style="width:78%">
                    <select name='csr_keytype' id='csr_keytype' class="selectpicker">
                  <option value="RSA" <?=$pconfig['csr_keytype'] == "RSA" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("RSA");?>
                  </option>
                  <option value="Elliptic Curve" <?=$pconfig['csr_keytype'] == "Elliptic Curve" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("Elliptic Curve");?>
                  </option>
                    </select>
                  </td>
                </tr>
                <tr id='csr_RSA'>
                  <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key length");?> (<?=gettext("bits");?>)</td>
                  <td style="width:78%">
                    <select name='csr_keylen' class="selectpicker">
<?php
                    foreach ($cert_keylens as $len) :?>
                      <option value="<?=$len;?>" <?=isset($pconfig['csr_keylen']) && $pconfig['csr_keylen'] == $len ? "selected=\"selected\"" : "";?>><?=$len;?></option>
<?php
                    endforeach; ?>
                    </select>
                </td>
              </tr>
              <tr id='csr_blank'><td></td></tr>
              <tr id='csr_EC'>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Curve");?></td>
                  <td style="width:78%">
                    <select name='csr_curve' id='csr_curve' class="selectpicker">
<?php
                    foreach ($cert_curves as $curve) :?>
                      <option value="<?=$curve;?>" <?=isset($pconfig['csr_curve']) && $pconfig['csr_curve'] == $curve ? "selected=\"selected\"" : "";?>><?=$curve;?></option>
<?php
                    endforeach; ?>
                    </select>
                  </td>
                </tr>
              <tr>
                <td><a id="help_for_csr_digest_alg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Digest Algorithm");?></td>
                <td>
                  <select name='csr_digest_alg'>
<?php
                  foreach ($openssl_digest_algs as $csr_digest_alg) :?>
                    <option value="<?=$csr_digest_alg;?>" <?=$pconfig['csr_digest_alg'] == $csr_digest_alg ? "selected=\"selected\"" : "";?>>
                      <?=strtoupper($csr_digest_alg);?>
                    </option>
<?php
                  endforeach; ?>
                  </select>
                  <div class="hidden" data-for="help_for_csr_digest_alg">
                    <?= gettext("NOTE: It is recommended to use an algorithm stronger than SHA1 when possible.") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2"><?=gettext("Distinguished name");?> </th>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Country Code");?> : &nbsp;</td>
                <td>
                  <select name="csr_dn_country" id="csr_dn_country" class="selectpicker">
<?php
                  foreach (get_country_codes() as $cc => $cn):?>
                    <option value="<?=$cc;?>" <?=$pconfig['csr_dn_country'] == $cc ? "selected=\"selected\"" : "";?>>
                      <?=$cc;?> (<?=$cn;?>)
                    </option>
<?php
                  endforeach;?>
                  </select>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_csr_dn_state" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State or Province");?> : &nbsp;</td>
                <td>
                  <input name="csr_dn_state" type="text" size="40" value="<?=$pconfig['csr_dn_state'];?>"/>
                  <div class="hidden" data-for="help_for_digest_csr_dn_state">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("Sachsen");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_csr_dn_city" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("City");?> : &nbsp;</td>
                <td>
                  <input name="csr_dn_city" type="text" size="40" value="<?=$pconfig['csr_dn_city'];?>"/>
                  <div class="hidden" data-for="help_for_digest_csr_dn_city">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("Leipzig");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_csr_dn_organization" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Organization");?> : &nbsp;</td>
                <td>
                  <input name="csr_dn_organization" type="text" size="40" value="<?=$pconfig['csr_dn_organization'];?>"/>
                  <div class="hidden" data-for="help_for_digest_csr_dn_organization">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("My Company Inc");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_csr_dn_organizationalunit" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Organizational Unit");?> : &nbsp;</td>
                <td>
                  <input name="csr_dn_organizationalunit" type="text" size="40" value="<?=$pconfig['csr_dn_organizationalunit'];?>"/>
                  <div class="hidden" data-for="help_for_digest_csr_dn_organizationalunit">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("IT department");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_csr_dn_email" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email Address");?> : &nbsp;</td>
                <td>
                  <input name="csr_dn_email" type="text" size="25" value="<?=$pconfig['csr_dn_email'];?>"/>
                  <div class="hidden" data-for="help_for_digest_csr_dn_email">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("admin@mycompany.com");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_digest_csr_dn_commonname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Common Name");?> : &nbsp;</td>
                <td>
                  <input name="csr_dn_commonname" type="text" size="25" value="<?=$pconfig['csr_dn_commonname'];?>"/>
                  <div class="hidden" data-for="help_for_digest_csr_dn_commonname">
                    <em><?=gettext("ex:");?></em>
                    &nbsp;
                    <?=gettext("internal-ca");?>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <!-- choose existing cert -->
          <table id="existing" class="table table-striped">
            <thead>
              <tr>
                <th colspan="2"><?=gettext("Choose an Existing Certificate");?></th>
              </tr>
            </thead>
              <tbody>
              <tr>
                <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Existing Certificates");?></td>
                <td style="width:78%">
                  <select name='certref'>
<?php
                  foreach ($config['cert'] as $cert) :
                      $caname = "";
                      $usercert = isset($config['system']['user'][$userid]['cert']) ? $config['system']['user'][$userid]['cert'] : array();
                      if (isset($userid) && in_array($cert['refid'], $usercert)) {
                          continue;
                      }
                      if (isset($cert['caref'])) {
                          $ca = lookup_ca($cert['caref']);
                          if ($ca) {
                              $caname = " (CA: {$ca['descr']})";
                          }
                      }?>
                    <option value="<?=$cert['refid'];?>" <?=isset($pconfig['certref']) && isset($cert['refid']) && $pconfig['certref'] == $cert['refid'] ? "selected=\"selected\"" : "";?>>
                      <?=$cert['descr'];?> <?=$caname;?>
                      <?=isset($cert['refid']) && cert_in_use($cert['refid']) ? gettext("*In Use") : "";?>
                      <?=is_cert_revoked($cert) ? gettext("*Revoked") :"";?>
                    </option>
<?php
                  endforeach; ?>
                  </select>
                </td>
              </tr>
              </tbody>
            </table>
            <!-- submit -->
            <table class="table">
              <tr>
                <td style="width:22%">&nbsp;</td>
                <td style="width:78%">
                  <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                </td>
              </tr>
            </table>
          </form>
          <!--- CSR --->
<?php
          elseif ($act == "csr") :
?>

          <form method="post" name="iform" id="iform">
            <input name="act" type="hidden" value="csr" />
<?php
            if (isset($id)) :?>
            <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
            endif;?>
            <table class="table table-striped">
              <tr>
                <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Descriptive name");?></td>
                <td style="width:78%">
                  <input name="descr" type="text" id="descr" readonly="readonly" value="<?=$pconfig['descr'];?>"/>
                </td>
              </tr>
              <tr>
                <td colspan="2" class="list" height="12"></td>
              </tr>
              <tr>
                <td colspan="2"><?=gettext("Complete Signing Request");?></td>
              </tr>
              <tr>
                <td><?=gettext("Signing request data");?></td>
                <td>
                  <textarea name="csr" id="csr" cols="65" rows="7" class="formfld_cert" readonly="readonly"><?=$pconfig['csr'];?></textarea>
                  <br />
                  <?=gettext("Copy the certificate signing data from here and forward it to your certificate authority for signing.");?>
                </td>
              </tr>
              <tr>
                <td><?=gettext("Final certificate data");?></td>
                <td>
                  <textarea name="cert" id="cert" cols="65" rows="7" class="formfld_cert"><?=$pconfig['cert'];?></textarea>
                  <br />
                  <?=gettext("Paste the certificate received from your certificate authority here.");?>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input id="submit" name="update" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Update')) ?>" />
                </td>
              </tr>
            </table>
          </form>
          <!--- Download Private key -->
<?php
          elseif ($act == "download_private_key"):?>

          <table class="table table-striped opnsense_standard_table_form">
            <thead>
              <tr>
                <th colspan="2"><?= gettext('Certificate has been issued.'); ?></th>
              </tr>
            </thead>
            <tbody>
                <tr>
                  <td><?= gettext('Private key'); ?></td>
                  <td>
                    <textarea id="secret_key_for_cert" cols="65" rows="7" data-filename="privatekey.pem"><?= $pconfig['private_key']; ?></textarea>
                    <small><?= gettext('The private key is not saved and no longer downloadable after closing this window.'); ?><br/></small>
                    <a href="#" for="secret_key_for_cert" id="download_private_key_link" class="btn btn-primary text_download_btn">
                      <?= gettext('Download Private Key'); ?>
                    </a>
                  </td>
                </tr>
                <tr>
                  <td><?= gettext('Certificate'); ?></td>
                  <td>
                    <textarea id="cert_just_created" cols="65" rows="7" data-filename="certificate.pem"><?= html_safe(base64_decode($cert['crt'])); ?></textarea>
                    <small><?= gettext('You can download, revise, or add to CRL later.'); ?><br/></small>
                    <a href="#" for="cert_just_created" id="download_cert_link" class="btn btn-primary text_download_btn"><?= gettext('Download Certificate'); ?></a>
                  </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                      <a href="/system_certmanager.php" class="btn btn-primary"><?= gettext('Go back'); ?></a>
                    </td>
                </tr>
            </tbody>
          </table>

<?php
          else :?>
          <form method="post" name="iform" id="iform">
            <input type="hidden" name="id" id="id" value="<?=isset($id) ? $id :"";?>"/>
            <input type="hidden" name="act" id="action" value="<?=$act;?>"/>
          </form>
          <table class="table table-striped">
            <thead>
              <tr>
                <th><?=gettext("Name");?></th>
                <th><?=gettext("Issuer");?></th>
                <th><?=gettext("Distinguished Name");?></th>
                <th><?=gettext("In Use");?></th>
              </tr>
            </thead>
            <tbody>
<?php
            $i = 0;
            foreach ($a_cert as $cert) :
                $name = $cert['descr'];
                $purpose = null;

                if (!empty($cert['crt'])) {
                    $subj = cert_get_subject($cert['crt']);
                    $issuer = cert_get_issuer($cert['crt']);
                    $purpose = cert_get_purpose($cert['crt']);
                    list($startdate, $enddate) = cert_get_dates($cert['crt']);
                    if ($subj==$issuer) {
                        $caname = "<em>" . gettext("self-signed") . "</em>";
                    } else {
                        $caname = "<em>" . gettext("external"). "</em>";
                    }
                    $subj = htmlspecialchars($subj);
                }
                if (isset($cert['csr'])) {
                    $subj = htmlspecialchars(csr_get_subject($cert['csr']));
                    $caname = "<em>" . gettext("external - signature pending") . "</em>";
                }
                if (isset($cert['caref'])) {
                    $ca = lookup_ca($cert['caref']);
                    if ($ca) {
                        $caname = $ca['descr'];
                    }
                }?>
              <tr>
                <td>
                  <i class="fa fa-certificate"></i>
                  <?=$name;?>
<?php
                  if (is_array($purpose)) :?>
                  <br/><br/>
                  <?=gettext('CA:') ?> <?=$purpose['ca']; ?>,
                  <?=gettext('Server:') ?> <?=$purpose['server']; ?>
<?php
                  endif; ?>
                </td>
                <td><?=$caname;?>&nbsp;</td>
                <td><?=$subj;?>&nbsp;<br />
                  <table>
                      <tr>
                          <td style="width:10%">&nbsp;</td>
                          <td style="width:20%"><?=gettext("Valid From")?>:</td>
                          <td style="width:70%"><?= $startdate ?></td>
                      </tr>
                      <tr>
                          <td>&nbsp;</td>
                          <td><?=gettext("Valid Until")?>:</td>
                          <td><?= $enddate ?></td>
                      </tr>
                  </table>
                </td>
                <td class="text-nowrap">
<?php
                if (is_cert_revoked($cert)) :?>
                  <b><?=gettext('Revoked') ?></b><br />
<?php
                endif;
                if (!isset($cert['prv'])) :?>
                  <b><?=gettext('No private key here') ?></b><br />
<?php
                endif;
                if (is_webgui_cert($cert['refid'])) :?>
                  <?=gettext('Web GUI') ?><br />
<?php
                endif;
                if (is_user_cert($cert['refid'])) :?>
                  <?=gettext('User Cert') ?><br />
<?php
                endif;
                if (is_openvpn_server_cert($cert['refid'])) :?>
                  <?=gettext('OpenVPN Server') ?><br />
<?php
                endif;
                if (is_openvpn_client_cert($cert['refid'])) :?>
                  <?=gettext('OpenVPN Client') ?><br />
<?php
                endif;
                if (is_ipsec_cert($cert['refid'])) :?>
                  <?=gettext('IPsec Tunnel') ?><br />
<?php
                endif; ?>

<?php if (isset($cert['crt'])): ?>
                  <a href="#" class="btn btn-default btn-xs act_info" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=gettext("show certificate info");?>">
                    <i class="fa fa-info-circle fa-fw"></i>
                  </a>
<?php endif ?>
                  <a href="system_certmanager.php?act=exp&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export user cert");?>">
                      <i class="fa fa-download fa-fw"></i>
                  </a>
<?php if (isset($cert['prv'])): ?>
                  <a href="system_certmanager.php?act=key&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export user key");?>">
                    <i class="fa fa-download fa-fw"></i>
                  </a>
                  <a data-id="<?=$i;?>"  class="btn btn-default btn-xs p12btn" data-toggle="tooltip" title="<?=gettext("export ca+user cert+user key in .p12 format");?>">
                      <i class="fa fa-download fa-fw"></i>
                  </a>
<?php endif ?>
<?php if (!cert_in_use($cert['refid'])): ?>
                  <a id="del_<?=$i;?>" data-id="<?=$i;?>" title="<?=gettext("delete cert"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                    <i class="fa fa-trash fa-fw"></i>
                  </a>
<?php endif ?>
<?php if (isset($cert['csr'])): ?>
                  <a href="system_certmanager.php?act=csr&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("update csr");?>">
                    <i class="fa fa-pencil fa-fw"></i>
                  </a>
<?php endif ?>
                </td>
              </tr>
<?php
              $i++;
              endforeach; ?>

            </tbody>
          </table>
<?php
          endif; ?>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
