<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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

require_once('guiconfig.inc');

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

function cert_unrevoke($cert, &$crl)
{
    if (!is_crl_internal($crl)) {
        return false;
    }

    foreach ($crl['cert'] as $id => $rcert) {
        if (($rcert['refid'] == $cert['refid']) || ($rcert['descr'] == $cert['descr'])) {
            unset($crl['cert'][$id]);
            if (count($crl['cert']) == 0) {
                // Protect against accidentally switching the type to imported, for older CRLs
                if (!isset($crl['method'])) {
                    $crl['method'] = "internal";
                }
            }
            crl_update($crl);
            return true;
        }
    }

    return false;
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

// prepare config types
$a_crl = &config_read_array('crl');
$a_cert = &config_read_array('cert');
$a_ca = &config_read_array('ca');

$thiscrl = false;
$act = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // locate cert by refid, returns false when not found
    if (isset($_GET['id'])) {
        $thiscrl =& lookup_crl($_GET['id']);
        if ($thiscrl !== false) {
            $id = $_GET['id'];
        }
    }
    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    }

    if ($act == "exp") {
        $exp_name = urlencode("{$thiscrl['descr']}.crl");
        $exp_data = base64_decode($thiscrl['text']);
        $exp_size = strlen($exp_data);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename={$exp_name}");
        header("Content-Length: $exp_size");
        echo $exp_data;
        exit;
    } elseif ($act == "new") {
        $pconfig = array();
        $pconfig['descr'] = null;
        $pconfig['crltext'] = null;
        $pconfig['crlmethod'] = !empty($_GET['method']) ? $_GET['method'] : null;
        $pconfig['caref'] = !empty($_GET['caref']) ? $_GET['caref'] : null;
        $pconfig['lifetime'] = "9999";
        $pconfig['serial'] = "0";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    // locate cert by refid, returns false when not found
    if (isset($_POST['id'])) {
        $thiscrl =& lookup_crl($_POST['id']);
        if ($thiscrl !== false) {
            $id = $_POST['id'];
        }
    }
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    }

    if ($act == "del" && isset($id)) {
        $name = $thiscrl['descr'];
        if (is_openvpn_server_crl($id)) {
            $savemsg = sprintf(gettext("Certificate Revocation List %s is in use and cannot be deleted"), $name) . "<br />";
        } else {
            foreach ($a_crl as $cid => $acrl) {
                if ($acrl['refid'] == $thiscrl['refid']) {
                    unset($a_crl[$cid]);
                }
            }
            write_config(sprintf('Deleted CRL %s', $name));
            header(url_safe('Location: /system_crlmanager.php'));
            exit;
        }
    } elseif ($act == "delcert" && isset($id)) {
        if (!isset($thiscrl['cert']) || !is_array($thiscrl['cert'])) {
            header(url_safe('Location: /system_crlmanager.php'));
            exit;
        }
        $found = false;
        foreach ($thiscrl['cert'] as $acert) {
            if ($acert['refid'] == $pconfig['certref']) {
                $found = true;
                $thiscert = $acert;
            }
        }
        if (!$found) {
            header(url_safe('Location: /system_crlmanager.php'));
            exit;
        }
        $name = $thiscert['descr'];
        if (cert_unrevoke($thiscert, $thiscrl)) {
            write_config(sprintf('Deleted certificate %s from CRL %s', $name, $thiscrl['descr']));
            plugins_configure('crl');
            header(url_safe('Location: /system_crlmanager.php'));
            exit;
        } else {
            $savemsg = sprintf(gettext("Failed to delete certificate %s from CRL %s"), $name, $thiscrl['descr']) . "<br />";
        }
        $act="edit";
    } elseif ($act == "addcert") {
        $input_errors = array();
        if (!isset($id)) {
            header(url_safe('Location: /system_crlmanager.php'));
            exit;
        }

        // certref, crlref
        $crl =& lookup_crl($id);
        $cert = lookup_cert($pconfig['certref']);

        if (empty($crl['caref']) || empty($cert['caref'])) {
            $input_errors[] = gettext("Both the Certificate and CRL must be specified.");
        }

        if ($crl['caref'] != $cert['caref']) {
            $input_errors[] = gettext("CA mismatch between the Certificate and CRL. Unable to Revoke.");
        }
        if (!is_crl_internal($crl)) {
            $input_errors[] = gettext("Cannot revoke certificates for an imported/external CRL.");
        }

        if (!count($input_errors)) {
            $reason = (empty($pconfig['crlreason'])) ? CERT_CRL_STATUS_UNSPECIFIED : $pconfig['crlreason'];
            if (cert_revoke($cert, $crl, $reason)) {
                write_config(sprintf('Revoked certificate %s in CRL %s', $cert['descr'], $crl['descr']));
                plugins_configure('crl');
                header(url_safe('Location: /system_crlmanager.php'));
                exit;
            } else {
                $savemsg = gettext("Cannot revoke certificate. See general log for details.") . "<br />";
                $act="edit";
            }
        }
    } else {
        $input_errors = array();
        $pconfig = $_POST;

        /* input validation */
        if (($pconfig['crlmethod'] == "existing") || ($act == "editimported")) {
            $reqdfields = explode(" ", "descr crltext");
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate Revocation List data"));
        } elseif ($pconfig['crlmethod'] == "internal") {
            $reqdfields = explode(
                " ",
                "descr caref"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate Authority"));
        }

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        /* save modifications */
        if (count($input_errors) == 0) {
            if (isset($id)) {
                $crl =& $thiscrl;
            } else {
                $crl = array();
                $crl['refid'] = uniqid();
            }

            foreach (array("descr", "caref", "crlmethod") as $fieldname) {
                if (isset($pconfig[$fieldname])) {
                    $crl[$fieldname] = $pconfig[$fieldname];
                }
            }

            if (($pconfig['crlmethod'] == "existing") || ($act == "editimported")) {
                $crl['text'] = base64_encode($pconfig['crltext']);
            }

            if ($pconfig['crlmethod'] == "internal") {
                 /* check if new CRL CA have private key and it match public key so this CA can sign anything */
                if (isset($pconfig['caref']) && !isset($id)) {
                    $cacert = lookup_ca($pconfig['caref']);
                    $ca_str_crt = base64_decode($cacert['crt']);
                    $ca_str_key = base64_decode($cacert['prv']);
                    if (!openssl_x509_check_private_key($ca_str_crt, $ca_str_key)) {
                        syslog(LOG_ERR, "CRL error: CA keys mismatch");
                        $savemsg = gettext("Cannot create CRL for this CA. CA keys mismatch or key missing.") . "<br />";
                        $act="edit";
                    }
                }

                $crl['serial'] = empty($pconfig['serial']) ? 9999 : $pconfig['serial'];
                $crl['lifetime'] = empty($pconfig['lifetime']) ? 9999 : $pconfig['lifetime'];
                $crl['cert'] = array();
                crl_update($crl);
            }

            if (!isset($id)) {
                $a_crl[] = $crl;
            }

            if (!isset($savemsg)) {
                write_config(sprintf('Saved CRL %s', $crl['descr']));
                plugins_configure('crl');
                header(url_safe('Location: /system_crlmanager.php'));
                exit;
            }
        }
    }

}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($thiscrl);
include("head.inc");
?>

<body>
  <script>

  $( document ).ready(function() {
    // delete cert revocation list
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data('id');
      var descr = $(this).data('descr');
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?=gettext("Certificates");?>",
        message: "<?=gettext("Do you really want to delete this Certificate Revocation List?");?> (" + descr + ")" ,
        buttons: [{
                  label: "<?=gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                  label: "<?=gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#action").val("del");
                    $("#iform").submit();
                }
              }]
      });
    });

    // Delete certificate from CRL
    $(".act_delete_cert").click(function(event){
      event.preventDefault();
      var id = $(this).data('id');
      var certref = $(this).data('certref');
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?=gettext("Certificates");?>",
        message: "<?=gettext("Delete this certificate from the CRL?");?>",
        buttons: [{
                  label: "<?=gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                  label: "<?=gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#certref").val(certref);
                    $("#action").val("delcert");
                    $("#iform").submit();
                }
              }]
      });
    });

    $("#crlmethod").change(function(){
        $("#existing").addClass("hidden");
        $("#internal").addClass("hidden");
        if ($("#crlmethod").val() == "internal") {
            $("#internal").removeClass("hidden");
        } else {
            $("#existing").removeClass("hidden");
        };
    });
    $("#crlmethod").change();
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
        <div class="content-box tab-content">
<?php
        if ($act == "new") :?>
          <form method="post" name="iform" id="iform">
            <input type="hidden" name="act" id="action" value="<?=$act;?>"/>
            <table class="table table-striped opnsense_standard_table_form">
<?php
              if (!isset($id)) :?>
              <tr>
                <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Method");?></td>
                <td style="width:78%">
                  <select name="crlmethod" id="crlmethod">
                    <option value="internal" <?=$pconfig['crlmethod'] == "internal" ? "selected=\"selected\"" : "";?>><?=gettext("Create an internal Certificate Revocation List");?></option>
                    <option value="existing" <?=$pconfig['crlmethod'] == "existing" ? "selected=\"selected\"" : "";?>><?=gettext("Import an existing Certificate Revocation List");?></option>
                  </select>
                </td>
              </tr>
<?php
              endif; ?>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Descriptive name");?></td>
                <td>
                  <input name="descr" type="text" id="descr" size="20" value="<?=$pconfig['descr'];?>"/>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate Authority");?></td>
                <td>
                  <select name='caref' id='caref' class="selectpicker">
<?php foreach ($a_ca as $ca): ?>
                    <option value="<?= html_safe($ca['refid']) ?>" <?=$pconfig['caref'] == $ca['refid'] ? 'selected="selected"' : '' ?>><?= html_safe($ca['descr']) ?></option>
<?php endforeach ?>
                  </select>
                </td>
              </tr>
            </table>
            <!-- import existing -->
            <table id="existing" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <th colspan="2"><?=gettext("Existing Certificate Revocation List");?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="width:22%"><a id="help_for_crltext" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("CRL data");?></td>
                  <td style="width:78%">
                    <textarea name="crltext" id="crltext" cols="65" rows="7"><?=$pconfig['crltext'];?></textarea>
                    <div class="hidden" data-for="help_for_crltext">
                      <?=gettext("Paste a Certificate Revocation List in X.509 CRL format here.");?>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <!-- create internal -->
            <table id="internal" class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <th colspan="2"><?=gettext("Internal Certificate Revocation List");?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="width:22%"><a id="help_for_lifetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Lifetime");?> (<?=gettext("days");?>)</td>
                  <td style="width:78%">
                    <input name="lifetime" type="text" id="lifetime" size="5" value="<?=$pconfig['lifetime'];?>"/>
                    <div class="hidden" data-for="help_for_lifetime">
                      <?=gettext("Default: 9999");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_serial" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Serial");?></td>
                  <td>
                    <input name="serial" type="text" id="serial" size="5" value="<?=$pconfig['serial'];?>"/>
                    <div class="hidden" data-for="help_for_serial">
                      <?=gettext("Default: 0");?>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%">&nbsp;</td>
                <td style="width:78%">
                  <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
<?php
                  if (isset($id)) :?>
                  <input name="id" type="hidden" value="<?=$id;?>" />
<?php
                  endif;?>
                </td>
              </tr>
            </table>
          </form>
<?php
          elseif ($act == "editimported") :?>
          <form method="post" name="iform" id="iform">
            <table id="editimported" class="table table-striped opnsense_standard_table_form">
              <tr>
                <th colspan="2"><?=gettext("Edit Imported Certificate Revocation List");?></th>
              </tr>
              <tr>
                <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Descriptive name");?></td>
                <td style="width:78%">
                  <input name="descr" type="text" id="descr" size="20" value="<?=$thiscrl['descr'];?>"/>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_crltext" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("CRL data");?></td>
                <td>
                  <textarea name="crltext" id="crltext" cols="65" rows="7"><?=base64_decode($thiscrl['text']);?></textarea>
                  <div class="hidden" data-for="help_for_crltext">
                    <?=gettext("Paste a Certificate Revocation List in X.509 CRL format here.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                  <input name="id" type="hidden" value="<?=$id;?>" />
                  <input name="act" type="hidden" value="<?=$act;?>" />
                </td>
              </tr>
            </table>
          </form>
<?php
          elseif ($act == "edit") :?>
          <form method="post" name="iform" id="iform">
            <input type="hidden" name="id" id="id" value=""/>
            <input type="hidden" name="certref" id="certref" value=""/>
            <input type="hidden" name="act" id="action" value=""/>
          </form>
          <form method="post">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th colspan="4"><?=gettext("Currently Revoked Certificates for CRL");?> : <?=$thiscrl['descr'];?></th>
                </tr>
                <tr>
                  <th><?=gettext("Certificate Name")?></th>
                  <th><?=gettext("Revocation Reason")?></th>
                  <th><?=gettext("Revoked At")?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
<?php /* List Certs on CRL */
                if (!isset($thiscrl['cert']) || !is_array($thiscrl['cert']) || (count($thiscrl['cert']) == 0)) :?>
                <tr>
                  <td colspan="4">
                      <?=gettext("No Certificates Found for this CRL."); ?>
                  </td>
                </tr>
<?php
              else :
                foreach ($thiscrl['cert'] as $cert) :?>
                <tr>
                  <td><?=$cert['descr']; ?></td>
                  <td><?=crl_status_code()[$cert["reason"]][0]; ?></td>
                  <td><?=date("D M j G:i:s T Y", $cert["revoke_time"]); ?></td>
                  <td>
                    <a id="del_cert_<?=$thiscrl['refid'];?>" data-id="<?=$thiscrl['refid'];?>" data-certref="<?=$cert['refid'];?>" title="<?=gettext("Delete this certificate from the CRL");?>" data-toggle="tooltip"  class="act_delete_cert btn btn-default btn-xs">
                      <i class="fa fa-trash fa-fw"></i>
                    </a>
                  </td>
                </tr>
<?php
                endforeach;
              endif;
              $ca_certs = array();
              foreach ($a_cert as $cert) {
                  if (isset($cert['caref']) && isset($thiscrl['caref']) && $cert['caref'] == $thiscrl['caref']) {
                      $revoked = false;
                      if (isset($thiscrl['cert'])) {
                          foreach ($thiscrl['cert'] as $revoked_cert) {
                              if ($cert['refid'] == $revoked_cert['refid']) {
                                  $revoked = true;
                                  break;
                              }
                          }
                      }
                      if (!$revoked) {
                          $ca_certs[] = $cert;
                      }
                  }
              }
              if (count($ca_certs) == 0) :?>
                <tr>
                  <td colspan="4"><?=gettext("No Certificates Found for this CA."); ?></td>
                </tr>
<?php
                else:?>
                <tr>
                  <th colspan="4"><?=gettext("Revoke a Certificate"); ?></th>
                </tr>
                <tr>
                  <td>
                    <b><?=gettext("Choose a Certificate to Revoke"); ?></b>:
                  </td>
                  <td colspan="3" style="text-align:left">
                    <select name='certref' id='certref' class="selectpicker" data-style="btn-default" data-live-search="true">
<?php
                  foreach ($ca_certs as $cert) :?>
                    <option value="<?=$cert['refid'];?>"><?=htmlspecialchars($cert['descr'])?></option>
<?php
                  endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td>
                    <b><?=gettext("Reason");?></b>:
                  </td>
                  <td colspan="3" style="text-align:left">
                    <select name='crlreason' id='crlreason' class="selectpicker" data-style="btn-default">
<?php
                  foreach (crl_status_code() as $code => $reason) :?>
                    <option value="<?= $code ?>"><?=$reason[0]?></option>
<?php
                  endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td colspan="3" style="text-align:left">
                    <input name="act" type="hidden" value="addcert" />
                    <input name="id" type="hidden" value="<?=$thiscrl['refid'];?>" />
                    <input id="submit" name="add" type="submit" class="formbtn btn btn-primary" value="<?= html_safe(gettext('Add')) ?>" />
                  </td>
                </tr>
<?php
                endif; ?>
              </tbody>
            </table>
          </form>
<?php
          else :?>
          <form method="post" id="iform" class="table table-striped">
            <input type="hidden" name="id" id="id" value=""/>
            <input type="hidden" name="act" id="action" value=""/>
            <table class="table table-striped">
              <thead>
                <tr>
                  <td><?=gettext("Name");?></td>
                  <td><?=gettext("Internal");?></td>
                  <td><?=gettext("Certificates");?></td>
                  <td><?=gettext("In Use");?></td>
                  <td class="text-nowrap"></td>
                </tr>
              </thead>
              <tbody>
<?php
                // Map CRLs to CAs
                $ca_crl_map = array();
                foreach ($a_crl as $crl) {
                    $ca_crl_map[$crl['caref']][] = $crl['refid'];
                }

                foreach ($a_ca as $ca) :?>
                <tr>
                  <td colspan="4"> <?=htmlspecialchars($ca['descr']);?></td>
                  <td class="text-nowrap">
<?php
                  if (!empty($ca['prv'])) :?>
                    <a href="system_crlmanager.php?act=new&amp;caref=<?=$ca['refid']; ?>" data-toggle="tooltip" title="<?= html_safe(sprintf(gettext('Add or Import CRL for %s'), $ca['descr'])) ?>" class="btn btn-default btn-xs">
                      <i class="fa fa-plus fa-fw"></i>
                    </a>
<?php
                  else :?>
                    <a href="system_crlmanager.php?act=new&amp;caref=<?=$ca['refid']; ?>&amp;method=existing" data-toggle="tooltip" title="<?= html_safe(sprintf(gettext('Import CRL for %s'), $ca['descr'])) ?>" class="btn btn-default btn-xs">
                      <i class="fa fa-plus fa-fw"></i>
                    </a>
<?php
                  endif;?>
                  </td>
                </tr>
<?php
                  if (isset($ca_crl_map[$ca['refid']]) && is_array($ca_crl_map[$ca['refid']])):
                    foreach ($ca_crl_map[$ca['refid']] as $crl):
                        $tmpcrl = lookup_crl($crl);
                        $internal = is_crl_internal($tmpcrl);
                        $inuse = is_openvpn_server_crl($tmpcrl['refid']);?>
                <tr>
                  <td><?=htmlspecialchars($tmpcrl['descr']); ?></td>
                  <td><?=$internal ? gettext("YES") : gettext("NO"); ?></td>
                  <td><?=$internal ? (isset($tmpcrl['cert']) ? count($tmpcrl['cert']) : 0) : gettext("Unknown (imported)"); ?></td>
                  <td><?=$inuse ? gettext("YES") : gettext("NO"); ?></td>
                  <td class="text-nowrap">
                    <a href="system_crlmanager.php?act=exp&amp;id=<?=$tmpcrl['refid'];?>" class="btn btn-default btn-xs">
                        <i class="fa fa-download fa-fw" data-toggle="tooltip" title="<?=gettext("Export CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>"></i>
                    </a>
<?php
                  if ($internal) :?>
                    <a href="system_crlmanager.php?act=edit&amp;id=<?=$tmpcrl['refid'];?>" class="btn btn-default btn-xs">
                      <i class="fa fa-pencil fa-fw" data-toggle="tooltip" title="<?=gettext("Edit CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>"></i>
                    </a>
<?php
                  else :?>
                    <a href="system_crlmanager.php?act=editimported&amp;id=<?=$tmpcrl['refid'];?>" class="btn btn-default btn-xs">
                      <i class="fa fa-pencil fa-fw" data-toggle="tooltip" title="<?=gettext("Edit CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>"></i>
                    </a>
<?php
                  endif; ?>
<?php
                  if (!$inuse) :?>
                    <a id="del_<?=$tmpcrl['refid'];?>" data-descr="<?=htmlspecialchars($tmpcrl['descr']);?>" data-id="<?=$tmpcrl['refid'];?>" title="<?=gettext("Delete CRL") . " " . htmlspecialchars($tmpcrl['descr']);?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                      <i class="fa fa-trash fa-fw"></i>
                    </a>
<?php
                  endif; ?>
                  </td>
                </tr>
<?php
                    endforeach;
                  endif; ?>
<?php
                endforeach; ?>
              </tbody>
            </table>
          </form>
<?php
        endif; ?>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
