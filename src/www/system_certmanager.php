<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once('guiconfig.inc');
require_once("system.inc");

function csr_generate(&$cert, $keylen, $dn, $digest_alg = 'sha256')
{
    $args = array(
        'config' => '/usr/local/etc/ssl/opnsense.cnf',
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => (int)$keylen,
        'x509_extensions' => 'v3_req',
        'digest_alg' => $digest_alg,
        'encrypt_key' => false
    );

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

// types
$cert_methods = array(
    "import" => gettext("Import an existing Certificate"),
    "internal" => gettext("Create an internal Certificate"),
    "external" => gettext("Create a Certificate Signing Request"),
);
$cert_keylens = array( "512", "1024", "2048", "3072", "4096", "8192");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");


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

    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    } else {
        $act = null;
    }

    $pconfig = array();
    if ($act == "new") {
        if (isset($_GET['method'])) {
            $pconfig['certmethod'] = $_GET['method'];
        } else {
            $pconfig['certmethod'] = null;
        }
        $pconfig['keylen'] = "2048";
        $pconfig['digest_alg'] = "sha256";
        $pconfig['csr_keylen'] = "2048";
        $pconfig['csr_digest_alg'] = "sha256";
        $pconfig['lifetime'] = "365";
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

            $exp_data = "";
            $res_crt = openssl_x509_read(base64_decode($a_cert[$id]['crt']));
            $res_key = openssl_pkey_get_private(array(0 => base64_decode($a_cert[$id]['prv']) , 1 => ""));

            openssl_pkcs12_export($res_crt, $exp_data, $res_key, null, $args);
            $exp_size = strlen($exp_data);
            restore_error_handler();

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
    if (isset($a_cert[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (isset($a_user[$_POST['userid']])) {
        $userid = $_POST['userid'];
    }
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }

    if ($act == "del") {
        if (isset($id)) {
            unset($a_cert[$id]);
            write_config();
        }
        header(url_safe('Location: /system_certmanager.php'));
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
        $pconfig = $_POST;

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
            $reqdfields = explode(" ", "descr caref keylen lifetime dn_country dn_state dn_city ".
                "dn_organization dn_email dn_commonname"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate authority"),
                    gettext("Key length"),
                    gettext("Lifetime"),
                    gettext("Distinguished name Country Code"),
                    gettext("Distinguished name State or Province"),
                    gettext("Distinguished name City"),
                    gettext("Distinguished name Organization"),
                    gettext("Distinguished name Email Address"),
                    gettext("Distinguished name Common Name"));
        } elseif ($pconfig['certmethod'] == "external") {
            $reqdfields = explode(" ", "descr csr_keylen csr_dn_country csr_dn_state csr_dn_city ".
                "csr_dn_organization csr_dn_email csr_dn_commonname"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Key length"),
                    gettext("Distinguished name Country Code"),
                    gettext("Distinguished name State or Province"),
                    gettext("Distinguished name City"),
                    gettext("Distinguished name Organization"),
                    gettext("Distinguished name Email Address"),
                    gettext("Distinguished name Common Name"));
        } elseif ($pconfig['certmethod'] == "existing") {
            $reqdfields = array("certref");
            $reqdfieldsn = array(gettext("Existing Certificate Choice"));
        }

        $altnames = array();
        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
        if (isset($pconfig['altname_value']) && $pconfig['certmethod'] != "import" && $pconfig['certmethod'] != "existing") {
            /* subjectAltNames */
            foreach ($pconfig['altname_type'] as $altname_seq => $altname_type) {
                if (!empty($pconfig['altname_value'][$altname_seq])) {
                    $altnames[] = array("type" => $altname_type, "value" => $pconfig['altname_value'][$altname_seq]);
                }
            }

            /* Input validation for subjectAltNames */
            foreach ($altnames as $altname) {
                switch ($altname['type']) {
                    case "DNS":
                        $dns_regex = '/^(?:(?:[a-z0-9_\*]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])\.)*(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i';
                        if (!preg_match($dns_regex, $altname['value'])) {
                            $input_errors[] = gettext("DNS subjectAltName values must be valid hostnames or FQDNs");
                        }
                        break;
                    case "IP":
                        if (!is_ipaddr($altname['value'])) {
                            $input_errors[] = gettext("IP subjectAltName values must be valid IP Addresses");
                        }
                        break;
                    case "email":
                        if (empty($altname['value'])) {
                            $input_errors[] = gettext("You must provide an email address for this type of subjectAltName");
                        }
                        if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $altname['value'])) {
                            $input_errors[] = gettext("The email provided in a subjectAltName contains invalid characters.");
                        }
                        break;
                    case "URI":
                        if (!is_URL($altname['value'])) {
                            $input_errors[] = gettext("URI subjectAltName types must be a valid URI");
                        }
                        break;
                    default:
                        $input_errors[] = gettext("Unrecognized subjectAltName type.");
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
                } elseif (($reqdfields[$i] != "descr") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $pconfig[$reqdfields[$i]])) {
                    $input_errors[] = sprintf(gettext("The field '%s' contains invalid characters."), $reqdfieldsn[$i]);
                }
            }

            if ($pconfig['certmethod'] != "external" && isset($pconfig["keylen"]) && !in_array($pconfig["keylen"], $cert_keylens)) {
                $input_errors[] = gettext("Please select a valid Key Length.");
            }
            if ($pconfig['certmethod'] != "external" && !in_array($pconfig["digest_alg"], $openssl_digest_algs)) {
                $input_errors[] = gettext("Please select a valid Digest Algorithm.");
            }

            if ($pconfig['certmethod'] == "external" && isset($pconfig["csr_keylen"]) && !in_array($pconfig["csr_keylen"], $cert_keylens)) {
                $input_errors[] = gettext("Please select a valid Key Length.");
            }
            if ($pconfig['certmethod'] == "external" && !in_array($pconfig["csr_digest_alg"], $openssl_digest_algs)) {
                $input_errors[] = gettext("Please select a valid Digest Algorithm.");
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

                if ($pconfig['certmethod'] == "import") {
                    cert_import($cert, $pconfig['cert'], $pconfig['key']);
                }

                if ($pconfig['certmethod'] == "internal") {
                    $dn = array(
                        'countryName' => $pconfig['dn_country'],
                        'stateOrProvinceName' => $pconfig['dn_state'],
                        'localityName' => $pconfig['dn_city'],
                        'organizationName' => $pconfig['dn_organization'],
                        'emailAddress' => $pconfig['dn_email'],
                        'commonName' => $pconfig['dn_commonname']);
                    if (count($altnames)) {
                        $altnames_tmp = array();
                        foreach ($altnames as $altname) {
                            $altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
                        }
                        $dn['subjectAltName'] = implode(",", $altnames_tmp);
                    }

                    if (!cert_create(
                        $cert,
                        $pconfig['caref'],
                        $pconfig['keylen'],
                        $pconfig['lifetime'],
                        $dn,
                        $pconfig['digest_alg'],
                        $pconfig['cert_type']
                    )) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            $input_errors[] = gettext("openssl library returns:") . " " . $ssl_err;
                        }
                    }
                }

                if ($pconfig['certmethod'] == "external") {
                    $dn = array(
                        'countryName' => $pconfig['csr_dn_country'],
                        'stateOrProvinceName' => $pconfig['csr_dn_state'],
                        'localityName' => $pconfig['csr_dn_city'],
                        'organizationName' => $pconfig['csr_dn_organization'],
                        'emailAddress' => $pconfig['csr_dn_email'],
                        'commonName' => $pconfig['csr_dn_commonname']);
                    if (!empty($pconfig['csr_dn_organizationalunit'])) {
                        $dn['organizationalUnitName'] = $pconfig['csr_dn_organizationalunit'];
                    }
                    if (count($altnames)) {
                        $altnames_tmp = array();
                        foreach ($altnames as $altname) {
                            $altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
                        }
                        $dn['subjectAltName'] = implode(",", $altnames_tmp);
                    }
                    if (!csr_generate($cert, $pconfig['csr_keylen'], $dn, $pconfig['csr_digest_alg'])) {
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

    /**
     * remove row from altNametable
     */
    function removeRowAltNm() {
        if ( $('#altNametable > tbody > tr').length == 1 ) {
            $('#altNametable > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // javascript only for edit forms
    if ($('#certmethod').length) {
        // no ca's found, display message
        if ($("#caref  option").size() == 0) {
            $("#no_caref").removeClass("hidden");
            $("#caref").addClass("hidden");
        }
        // add new detail record
        $("#addNewAltNm").click(function(){
            // copy last row and reset values
            $('#altNametable > tbody').append('<tr>'+$('#altNametable > tbody > tr:last').html()+'</tr>');
            $('#altNametable > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
            $(".act-removerow-altnm").click(removeRowAltNm);
        });

        $(".act-removerow-altnm").click(removeRowAltNm);


        $("#certmethod").change(function(){
            $("#import").addClass("hidden");
            $("#internal").addClass("hidden");
            $("#external").addClass("hidden");
            $("#existing").addClass("hidden");
            if ($(this).val() == "import") {
                $("#import").removeClass("hidden");
            } else if ($(this).val() == "internal") {
                $("#internal").removeClass("hidden");
                $("#altNameTr").detach().appendTo("#internal > tbody:first");
            } else if ($(this).val() == "external") {
                $("#external").removeClass("hidden");
                $("#altNameTr").detach().appendTo("#external > tbody:first");
            } else {
                $("#existing").removeClass("hidden");
            }
        });

        $("#certmethod").change();
    }
  });
  </script>

<?php include("fbegin.inc"); ?>
<script>
$( document ).ready(function() {
//<![CDATA[
  function internalca_change() {

    index = document.iform.caref.selectedIndex;
    caref = document.iform.caref[index].value;

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
          $('#dn_country option').removeAttr('selected');
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
                    <option value="<?=$method;?>" <?=$pconfig['certmethod'] == $method ? "selected=\"selected\"":"";?>>
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
                    <option value="<?=$ca['refid'];?>" <?=isset($pconfig['caref']) && isset($ca['refid']) && $pconfig['caref'] == $ca['refid'] ? "selected=\"selected\"" : "";?>><?=$ca['descr'];?></option>
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
                        <option value="usr_cert" <?=$pconfig['cert_type'] == 'usr_cert' ? "selected=\"selected\"" : "";?>> <?=gettext("Client Certificate");?> </option>
                        <option value="server_cert" <?=$pconfig['cert_type'] == 'server_cert' ? "selected=\"selected\"" : "";?>> <?=gettext("Server Certificate");?> </option>
                        <option value="v3_ca" <?=$pconfig['cert_type'] == 'v3_ca' ? "selected=\"selected\"" : "";?>> <?=gettext("Certificate Authority");?> </option>
                    </select>
                    <div class="hidden" data-for="help_for_digest_cert_type">
                      <?=gettext("Choose the type of certificate to generate here, the type defines it's constraints");?>
                    </div>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key length");?> (<?=gettext("bits");?>)</td>
                <td>
                  <select name='keylen'>
<?php
                  foreach ($cert_keylens as $len) :?>
                    <option value="<?=$len;?>" <?=$pconfig['keylen'] == $len ? "selected=\"selected\"" : "";?>><?=$len;?></option>
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
                    <option value="<?=$digest_alg;?>" <?=$pconfig['digest_alg'] == $digest_alg ? "selected=\"selected\"" : "";?>>
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
                <th colspan="2"><?=gettext("Distinguished name");?> </th>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Country Code");?> : &nbsp;</td>
                <td>
                  <select name="dn_country" id="dn_country" class="selectpicker">
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
                          <div style="cursor:pointer;" class="act-removerow-altnm btn btn-default btn-xs" alt="remove"><i class="fa fa-minus fa-fw"></i></div>
                        </td>
                      </tr>
<?php
                      else:
                        foreach ($pconfig['altname_value'] as $itemid => $item) :
                          $altname_type = isset($pconfig['altname_type'][$itemid]) ? $pconfig['altname_type'][$itemid] : null; ?>
                        <tr>
                          <td>
                            <select name="altname_type[]" id="altname_type">
                              <option value="DNS" <?=$altname_type == "DNS" ? "selected=\"selected\"" : "";?>><?=gettext("DNS");?></option>
                              <option value="IP" <?=$altname_type == "IP" ? "selected=\"selected\"" : "";?>><?=gettext("IP");?></option>
                              <option value="email" <?=$altname_type == "email" ? "selected=\"selected\"" : "";?>><?=gettext("email");?></option>
                              <option value="URI" <?=$altname_type == "URI" ? "selected=\"selected\"" : "";?>><?=gettext("URI");?></option>
                            </select>
                          </td>
                          <td>
                            <input name="altname_value[]" type="text" size="20" value="<?=$item;?>" />
                          </td>
                          <td>
                            <div style="cursor:pointer;" class="act-removerow-altnm btn btn-default btn-xs" alt="remove"><i class="fa fa-minus fa-fw"></i></div>
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
                          <div id="addNewAltNm" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><i class="fa fa-plus fa-fw"></i></div>
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
                  <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key length");?> (<?=gettext("bits");?>)</td>
                  <td style="width:78%">
                    <select name='csr_keylen' class="selectpicker">
<?php
                    foreach ($cert_keylens as $len) :?>
                      <option value="<?=$len;?>" <?=$pconfig['csr_keylen'] == $len ? "selected=\"selected\"" : "";?>><?=$len;?></option>
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
                  <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                </td>
              </tr>
            </table>
          </form>
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
                  <?=gettext("Copy the certificate signing data from here and forward it to your certificate authority for signing.");?></td>
                </td>
              </tr>
              <tr>
                <td><?=gettext("Final certificate data");?></td>
                <td>
                  <textarea name="cert" id="cert" cols="65" rows="7" class="formfld_cert"><?=$pconfig['cert'];?></textarea>
                  <br />
                  <?=gettext("Paste the certificate received from your certificate authority here.");?></td>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input id="submit" name="update" type="submit" class="btn btn-primary" value="<?=gettext("Update");?>" />
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
                $name = htmlspecialchars($cert['descr']);
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

                  <a href="#" class="btn btn-default btn-xs act_info" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=gettext("show certificate info");?>">
                    <i class="fa fa-info-circle fa-fw"></i>
                  </a>

                  <a href="system_certmanager.php?act=exp&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export user cert");?>">
                      <i class="fa fa-download fa-fw"></i>
                  </a>

                  <a href="system_certmanager.php?act=key&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export user key");?>">
                    <i class="fa fa-download fa-fw"></i>
                  </a>

                  <a href="system_certmanager.php?act=p12&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export ca+user cert+user key in .p12 format");?>">
                      <i class="fa fa-download fa-fw"></i>
                  </a>
<?php
                  if (!cert_in_use($cert['refid'])) :?>

                  <a id="del_<?=$i;?>" data-id="<?=$i;?>" title="<?=gettext("delete cert"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                    <i class="fa fa-trash fa-fw"></i>
                  </a>
<?php
                  endif;
                  if (isset($cert['csr'])) :?>
                  <a href="system_certmanager.php?act=csr&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("update csr");?>">
                    <i class="fa fa-pencil fa-fw"></i>
                  </a>
<?php
                  endif; ?>
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
