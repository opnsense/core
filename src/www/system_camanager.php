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

require_once("guiconfig.inc");
require_once("system.inc");

function ca_import(& $ca, $str, $key="", $serial=0)
{
    global $config;

    $ca['crt'] = base64_encode($str);
    if (!empty($key)) {
        $ca['prv'] = base64_encode($key);
    }
    if (!empty($serial)) {
        $ca['serial'] = $serial;
    }
    $subject = cert_get_subject($str, false);
    $issuer = cert_get_issuer($str, false);

    // Find my issuer unless self-signed
    if ($issuer != $subject) {
        $issuer_crt =& lookup_ca_by_subject($issuer);
        if ($issuer_crt) {
            $ca['caref'] = $issuer_crt['refid'];
        }
    }

    /* Correct if child certificate was loaded first */
    if (is_array($config['ca'])) {
        foreach ($config['ca'] as & $oca) {
            $issuer = cert_get_issuer($oca['crt']);
            if ($ca['refid'] != $oca['refid'] && $issuer == $subject) {
                $oca['caref'] = $ca['refid'];
            }
        }
    }
    if (is_array($config['cert'])) {
        foreach ($config['cert'] as & $cert) {
            $issuer = cert_get_issuer($cert['crt']);
            if ($issuer == $subject) {
                $cert['caref'] = $ca['refid'];
            }
        }
    }
    return true;
}

function ca_inter_create(&$ca, $keylen_curve, $lifetime, $dn, $caref, $digest_alg = 'sha256')
{
    // Create Intermediate Certificate Authority
    $signing_ca = &lookup_ca($caref);
    if (!$signing_ca) {
        return false;
    }

    $signing_ca_res_crt = openssl_x509_read(base64_decode($signing_ca['crt']));
    $signing_ca_res_key = openssl_pkey_get_private(array(0 => base64_decode($signing_ca['prv']) , 1 => ""));
    if (!$signing_ca_res_crt || !$signing_ca_res_key) {
        return false;
    }
    $signing_ca_serial = ++$signing_ca['serial'];

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

    // Sign the certificate
    $res_crt = openssl_csr_sign($res_csr, $signing_ca_res_crt, $signing_ca_res_key, $lifetime, $args, $signing_ca_serial);
    if (!$res_crt) {
        return false;
    }

    // export our certificate data
    if (!openssl_pkey_export($res_key, $str_key) ||
        !openssl_x509_export($res_crt, $str_crt)) {
        return false;
    }

    // return our ca information
    $ca['crt'] = base64_encode($str_crt);
    $ca['caref'] = $caref;
    $ca['prv'] = base64_encode($str_key);
    $ca['serial'] = 0;

    return true;
}

$ca_keylens = array( "512", "1024", "2048", "3072", "4096", "8192");
$ca_curves = array( "prime256v1", "secp384r1", "secp521r1");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");
$a_ca = &config_read_array('ca');
$a_cert = &config_read_array('cert');
$a_crl = &config_read_array('crl');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($a_ca[$_GET['id']])) {
        $id = $_GET['id'];
    }

    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    } else {
        $act = null;
    }

    // set defaults
    $pconfig = array();
    $pconfig['camethod'] = null ;
    $pconfig['descr'] = null;
    $pconfig['serial'] = null;
    $pconfig['lifetime'] = null;
    $pconfig['dn_country'] = null;
    $pconfig['dn_state'] = null;
    $pconfig['dn_city'] = null;
    $pconfig['dn_organization'] = null;
    $pconfig['dn_email'] = null;
    $pconfig['dn_commonname'] = null;

    if ($act == "edit") {
        if (!isset($id)) {
            header(url_safe('Location: /system_camanager.php'));
            exit;
        }
        $pconfig['descr'] = $a_ca[$id]['descr'];
        $pconfig['refid'] = $a_ca[$id]['refid'];
        $pconfig['cert'] = base64_decode($a_ca[$id]['crt']);
        $pconfig['serial'] = $a_ca[$id]['serial'] + 1;
        if (!empty($a_ca[$id]['prv'])) {
            $pconfig['key'] = base64_decode($a_ca[$id]['prv']);
        }
    } elseif ($act == "new") {
        if (isset($_GET['method'])) {
            $pconfig['camethod'] = $_GET['method'];
        }
        $pconfig['refid'] = null;
        $pconfig['keytype'] = "RSA";
        $pconfig['keylen'] = "2048";
        $pconfig['digest_alg'] = "sha256";
        $pconfig['lifetime'] = "825";
        $pconfig['dn_commonname'] = "internal-ca";
    } elseif ($act == "exp") {
        if (!isset($id)) {
            header(url_safe('Location: /system_camanager.php'));
            exit;
        }

        $exp_name = urlencode("{$a_ca[$id]['descr']}.crt");
        $exp_data = base64_decode($a_ca[$id]['crt']);
        $exp_size = strlen($exp_data);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename={$exp_name}");
        header("Content-Length: $exp_size");
        echo $exp_data;
        exit;
    } elseif ($act == "expkey") {
        if (!isset($id)) {
            header(url_safe('Location: /system_camanager.php'));
            exit;
        }

        $exp_name = urlencode("{$a_ca[$id]['descr']}.key");
        $exp_data = base64_decode($a_ca[$id]['prv']);
        $exp_size = strlen($exp_data);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename={$exp_name}");
        header("Content-Length: $exp_size");
        echo $exp_data;
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($a_ca[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }

    if ($act == "del") {
        if (!isset($id)) {
            header(url_safe('Location: /system_camanager.php'));
            exit;
        }
        $index = count($a_cert) - 1;
        for (; $index >=0; $index--) {
            if (isset($a_cert[$index]['caref']) && isset($a_ca[$id]['refid']) && $a_cert[$index]['caref'] == $a_ca[$id]['refid']) {
                unset($a_cert[$index]);
            }
        }

        $index = count($a_crl) - 1;
        for (; $index >=0; $index--) {
            if ($a_crl[$index]['caref'] == $a_ca[$id]['refid']) {
                unset($a_crl[$index]);
            }
        }

        unset($a_ca[$id]);
        write_config();
        system_trust_configure();
        header(url_safe('Location: /system_camanager.php'));
        exit;
    } else {
        $input_errors = array();
        $pconfig = $_POST;

        /* input validation */
        if ($pconfig['camethod'] == "existing") {
            $reqdfields = explode(" ", "descr cert");
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate data"));
            if (!empty($pconfig['cert']) && (!strstr($pconfig['cert'], "BEGIN CERTIFICATE") || !strstr($pconfig['cert'], "END CERTIFICATE"))) {
                $input_errors[] = gettext("This certificate does not appear to be valid.");
            }
            if (!empty($pconfig['key']) && strstr($pconfig['key'], "ENCRYPTED")) {
                $input_errors[] = gettext("Encrypted private keys are not yet supported.");
            }
        } elseif ($pconfig['camethod'] == "internal") {
            $reqdfields = explode(
                " ",
                "descr keytype keylen curve digest_alg lifetime dn_country dn_state dn_city ".
                "dn_organization dn_email dn_commonname"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
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
        } elseif ($pconfig['camethod'] == "intermediate") {
            $reqdfields = explode(
                " ",
                "descr caref keytype keylen curve digest_alg lifetime dn_country dn_state dn_city ".
                "dn_organization dn_email dn_commonname"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Signing Certificate Authority"),
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
        }

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
        if ($pconfig['camethod'] != "existing") {
            /* Make sure we do not have invalid characters in the fields for the certificate */
            for ($i = 0; $i < count($reqdfields); $i++) {
                if ($reqdfields[$i] == 'dn_email') {
                    if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig["dn_email"])) {
                        $input_errors[] = gettext("The field 'Distinguished name Email Address' contains invalid characters.");
                    }
                } elseif ($reqdfields[$i] == 'dn_commonname') {
                    if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig["dn_commonname"])) {
                        $input_errors[] = gettext("The field 'Distinguished name Common Name' contains invalid characters.");
                    }
                } elseif ($reqdfields[$i] == "dn_organization") {
                    if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\"\']/", $pconfig["dn_organization"])) {
                        $input_errors[] = gettext("The field 'Distinguished name Organization' contains invalid characters.");
                    }
                } elseif (($reqdfields[$i] != "descr" && $reqdfields[$i] != "dn_organization") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig["$reqdfields[$i]"])) {
                    $input_errors[] = sprintf(gettext("The field '%s' contains invalid characters."), $reqdfieldsn[$i]);
                }
            }
            if (!in_array($pconfig["keytype"], array("RSA", "Elliptic Curve"))) {
                $input_errors[] = gettext("Please select a valid Key Type.");
            }
            if (!in_array($pconfig['keylen'], $ca_keylens) && $pconfig["keytype"] == "RSA") {
                $input_errors[] = gettext("Please select a valid Key Length.");
            }
            if (!in_array($pconfig['curve'], $ca_curves) && $pconfig["keytype"] == "Elliptic Curve") {
                $input_errors[] = gettext("Please select a valid Curve.");
            }
            if (!in_array($pconfig["digest_alg"], $openssl_digest_algs)) {
                $input_errors[] = gettext("Please select a valid Digest Algorithm.");
            }
        }

        if (isset($pconfig['serial']) && $pconfig['serial'] !== '' &&
            ((string)((int)$pconfig['serial']) != $pconfig['serial'] || $pconfig['serial'] < 1)) {
            $input_errors[] = gettext('The serial number must be a number greater than zero or left blank.');
        }

        /* save modifications */
        if (count($input_errors) == 0) {
            $ca = array();

            if (isset($id)) {
                $ca = $a_ca[$id];
            } else {
                $ca['refid'] = uniqid();
            }

            if (isset($pconfig['descr'])) {
                $ca['descr'] = $pconfig['descr'];
            } else {
                $ca['descr'] = null;
            }

            if (!empty($pconfig['serial'])) {
                $ca['serial'] = $pconfig['serial'] - 1;
            }

            if (isset($id)) {
                // edit existing
                $ca['crt'] = base64_encode($pconfig['cert']);
                if (!empty($pconfig['key'])) {
                    $ca['prv'] = base64_encode($pconfig['key']);
                }
            } else {
                $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
                if ($pconfig['keytype'] == "Elliptic Curve") {
                    $pconfig['keylen_curve'] = $pconfig['curve'];
                } else {
                    $pconfig['keylen_curve'] = $pconfig['keylen'];
                }
                if ($pconfig['camethod'] == "existing") {
                    ca_import($ca, $pconfig['cert'], $pconfig['key'], $pconfig['serial']);
                } elseif ($pconfig['camethod'] == "internal") {
                    $dn = array(
                        'countryName' => $pconfig['dn_country'],
                        'stateOrProvinceName' => $pconfig['dn_state'],
                        'localityName' => $pconfig['dn_city'],
                        'organizationName' => $pconfig['dn_organization'],
                        'emailAddress' => $pconfig['dn_email'],
                        'commonName' => $pconfig['dn_commonname']);
                    if (!ca_create($ca, $pconfig['keylen_curve'], $pconfig['lifetime'], $dn, $pconfig['digest_alg'])) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            $input_errors[] = gettext("openssl library returns:") . " " . $ssl_err;
                        }
                    }
                } elseif ($pconfig['camethod'] == "intermediate") {
                    $dn = array(
                        'countryName' => $pconfig['dn_country'],
                        'stateOrProvinceName' => $pconfig['dn_state'],
                        'localityName' => $pconfig['dn_city'],
                        'organizationName' => $pconfig['dn_organization'],
                        'emailAddress' => $pconfig['dn_email'],
                        'commonName' => $pconfig['dn_commonname']);
                    if (!ca_inter_create($ca, $pconfig['keylen_curve'], $pconfig['lifetime'], $dn, $pconfig['caref'], $pconfig['digest_alg'])) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            $input_errors[] = gettext("openssl library returns:") . " " . $ssl_err;
                        }
                    }
                }
                error_reporting($old_err_level);
            }

            if (isset($id) && $a_ca[$id]) {
                $a_ca[$id] = $ca;
            } else {
                $a_ca[] = $ca;
            }

            if (count($input_errors) == 0) {
                write_config();
                system_trust_configure();
                header(url_safe('Location: /system_camanager.php'));
                exit;
            }
        }
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");

$main_buttons = array(
    array('label' => gettext('Add'), 'href' => 'system_camanager.php?act=new'),
);

?>

<body>
  <script>
  $( document ).ready(function() {
    // delete entry
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data('id');
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Authorities");?>",
        message: "<?=gettext("Do you really want to delete this Certificate Authority and its CRLs, and unreference any associated certificates?");?>",
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

    $("#camethod").change(function(){
        $("#existing").addClass("hidden");
        $("#internal").addClass("hidden");
        $("#intermediate").addClass("hidden");
        if ($(this).val() == "existing") {
            $("#existing").removeClass("hidden");
        } else if ($(this).val() == "internal") {
            $("#internal").removeClass("hidden");
        } else {
            $("#internal").removeClass("hidden");
            $("#intermediate").removeClass("hidden");
        }
    });

    $("#camethod").change();

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
  });
  </script>

<?php include("fbegin.inc"); ?>

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

        <?php if ($act == "new" || $act == "edit") :
?>

        <form method="post" name="iform" id="iform">
          <input type="hidden" name="id" id="id" value="<?=isset($id) ? $id :"";?>"/>
          <input type="hidden" name="act" id="action" value="<?=$act;?>"/>
          <table class="table table-striped opnsense_standard_table_form">
            <tr>
              <td style="width:22%"></td>
              <td style="width:78%; text-align:right">
                <small><?=gettext("full help"); ?> </small>
                <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
              </td>
            </tr>
            <tr>
              <td><?=gettext("Descriptive name");?></td>
              <td>
                <input name="descr" type="text" id="descr" size="20" value="<?=$pconfig['descr'];?>"/>
              </td>
            </tr>
            <tr class="<?=isset($id) ? "hidden" : "";?>">
              <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Method");?></td>
              <td>
                <select name='camethod' id='camethod' class="selectpicker" data-style="btn-default">
                  <option value="existing" <?=$pconfig['camethod'] == "existing" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("Import an existing Certificate Authority");?>
                  </option>
                  <option value="internal" <?=$pconfig['camethod'] == "internal" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("Create an internal Certificate Authority");?>
                  </option>
                  <option value="intermediate" <?=$pconfig['camethod'] == "intermediate" ? "selected=\"selected\"" : "";?>>
                    <?=gettext("Create an intermediate Certificate Authority");?>
                  </option>
                </select>
              </td>
            </tr>
          </table>
          <!-- existing ca -->
          <table id="existing" class="table table-striped opnsense_standard_table_form">
            <thead>
              <tr>
                <th colspan="2"><?=gettext("Existing Certificate Authority");?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="width:22%"><a id="help_for_cert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Certificate data");?></td>
                <td style="width:78%">
                  <textarea name="cert" cols="65" rows="7" id="cert"><?=isset($pconfig['cert']) ? $pconfig['cert'] : "";?></textarea>
                  <div class="hidden" data-for="help_for_cert">
                    <?=gettext("Paste a certificate in X.509 PEM format here.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td>
                  <a id="help_for_key" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Certificate Private Key");?><br />
                  <?=gettext("(optional)");?>
                </td>
                <td style="width:78%">
                  <textarea name="key" id="key" cols="65" rows="7"><?= isset($pconfig['key']) ? $pconfig['key'] : "";?></textarea>
                  <div class="hidden" data-for="help_for_key">
                    <?=gettext("Paste the private key for the above certificate here. This is optional in most cases, but required if you need to generate a Certificate Revocation List (CRL).");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_serial" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Serial for next certificate");?></td>
                <td>
                  <input name="serial" type="text" id="serial" size="20" value="<?=$pconfig['serial'];?>"/>
                  <div class="hidden" data-for="help_for_serial">
                    <?=gettext("Enter a decimal number to be used as the serial number for the next certificate to be created using this CA.");?>
                  </div>
                </td>
              </tr>
              </tbody>
            </table>
            <!-- internal ca -->
            <table  id="internal" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <th colspan="2"><?=gettext("Internal Certificate Authority");?></th>
                </tr>
              </thead>
              <tbody>
                <tr id='intermediate'>
                  <td style="width:22%"> <i class="fa fa-info-circle text-muted"></i>  <?=gettext("Signing Certificate Authority");?></td>
                  <td style="width:78%">
                    <select name='caref' id='caref' class="selectpicker" onchange='internalca_change()'>
<?php
                    foreach ($a_ca as $ca) :
                        if (!$ca['prv']) {
                            continue;
                        }?>
                      <option value="<?=$ca['refid'];?>"<?=isset($pconfig['caref']) && isset($ca['refid']) && $pconfig['caref'] == $ca['refid'] ? " selected=\"selected\"" :"" ;?>><?=htmlspecialchars($ca['descr']);?></option>
<?php
                    endforeach; ?>
                    </select>
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
                  <td style="width:78%">
                    <select name='keylen' id='keylen' class="selectpicker">
<?php
                        foreach ($ca_keylens as $len) :?>
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
                    foreach ($ca_curves as $curve) :?>
                      <option value="<?=$curve;?>" <?=isset($pconfig['curve']) && $pconfig['curve'] == $curve ? "selected=\"selected\"" : "";?>><?=$curve;?></option>
<?php
                    endforeach; ?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_digest_alg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Digest Algorithm");?></td>
                  <td>
                    <select name='digest_alg' id='digest_alg' class="selectpicker">
<?php
                    foreach ($openssl_digest_algs as $digest_alg) :?>
                      <option value="<?=$digest_alg;?>" <?=isset($pconfig['digest_alg']) && $pconfig['digest_alg'] == $digest_alg ? "selected=\"selected\"" : "";?>><?=strtoupper($digest_alg);?></option>
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
                    <input name="lifetime" type="text" id="lifetime"  value="<?=$pconfig['lifetime'];?>"/>
                  </td>
                </tr>
                <tr>
                  <th colspan="2"><?=gettext("Distinguished name");?></th>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Country Code");?> : &nbsp;</td>
                  <td>
                      <select name="dn_country" class="selectpicker">
<?php
                      foreach (get_country_codes() as $cc => $cn):?>
                        <option value="<?=$cc;?>" <?=$pconfig['dn_country'] == $cc ? "selected=\"selected\"" : "";?>>
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
                    <input name="dn_state" type="text" size="40" value="<?=$pconfig['dn_state'];?>"/>
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
                    <input name="dn_city" type="text" size="40" value="<?=$pconfig['dn_city'];?>"/>
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
                    <input name="dn_organization" type="text" size="40" value="<?=$pconfig['dn_organization'];?>"/>
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
                    <input name="dn_email" type="text" size="25" value="<?=$pconfig['dn_email'];?>"/>
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
                    <input name="dn_commonname" type="text" size="25" value="<?=$pconfig['dn_commonname'];?>"/>
                    <div class="hidden" data-for="help_for_digest_dn_commonname">
                      <em><?=gettext("ex:");?></em>
                      &nbsp;
                      <?=gettext("internal-ca");?>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="table opnsense_standard_table_form">
            <tr>
              <td style="width:22%">&nbsp;</td>
              <td style="width:78%">
                <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
              </td>
            </tr>
          </table>
        </form>

<?php
        else :?>
        <form method="post" name="iform" id="iform">
          <input type="hidden" name="id" id="id" value="<?=isset($id) ? $id :"";?>"/>
          <input type="hidden" name="act" id="action" value="<?=$act;?>"/>
        </form>
        <table style="width:100%; border:0;" class="table table-striped">
          <thead>
            <tr>
              <th><?=gettext("Name");?></th>
              <th><?=gettext("Internal");?></th>
              <th><?=gettext("Issuer");?></th>
              <th><?=gettext("Certificates");?></th>
              <th><?=gettext("Distinguished Name");?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
<?php
          $i = 0;
          foreach ($a_ca as $ca) :
              $issuer = htmlspecialchars(cert_get_issuer($ca['crt']));
              $subj = htmlspecialchars(cert_get_subject($ca['crt']));
              list($startdate, $enddate) = cert_get_dates($ca['crt']);
              if ($subj == $issuer) {
                  $issuer_name = "<em>" . gettext("self-signed") . "</em>";
              } else {
                  $issuer_name = "<em>" . gettext("external") . "</em>";
              }

              if (isset($ca['caref'])) {
                  $issuer_ca = lookup_ca($ca['caref']);
                  if ($issuer_ca) {
                      $issuer_name = $issuer_ca['descr'];
                  }
              }

              $certcount = 0;

              foreach ($a_cert as $cert) {
                  if ($cert['caref'] == $ca['refid']) {
                      $certcount++;
                  }
              }

              foreach ($a_ca as $cert) {
                  if (isset($cert['caref'])) {
                      if ($cert['caref'] == $ca['refid']) {
                          $certcount++;
                      }
                  }
              }
?>
            <tr>
              <td><?=htmlspecialchars($ca['descr']);?></td>
              <td><?=!empty($ca['prv']) ? gettext("YES") : gettext("NO");?>&nbsp;</td>
              <td><?=$issuer_name;?>&nbsp;</td>
              <td><?=$certcount;?>&nbsp;</td>
              <td><?=$subj;?><br />
                  <table style="width:100%; font-size: 9px">
                    <tr>
                      <td>&nbsp;</td>
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
                  <a href="system_camanager.php?act=edit&amp;id=<?=$i;?>" data-toggle="tooltip" title="<?=gettext("edit CA");?>" class="btn btn-default btn-xs">
                    <i class="fa fa-pencil fa-fw"></i>
                  </a>
                  <a href="system_camanager.php?act=exp&amp;id=<?=$i;?>" data-toggle="tooltip" title="<?=gettext("export CA cert");?>" class="btn btn-default btn-xs">
                    <i class="fa fa-download fa-fw"></i>
                  </a>
<?php
                  if ($ca['prv']) :?>
                  <a href="system_camanager.php?act=expkey&amp;id=<?=$i;?>" data-toggle="tooltip" title="<?=gettext("export CA private key");?>" class="btn btn-default btn-xs">
                    <i class="fa fa-download fa-fw"></i>
                  </a>
<?php
                  endif; ?>
                  <a id="del_<?=$i;?>" data-id="<?=$i;?>" title="<?=gettext("delete ca"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                    <i class="fa fa-trash fa-fw"></i>
                  </a>
                </td>
              </tr>
<?php
              $i++;
              endforeach;?>
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
