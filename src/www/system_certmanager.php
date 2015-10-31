<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2008 Shrew Soft Inc.
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

$cert_methods = array(
    "import" => gettext("Import an existing Certificate"),
    "internal" => gettext("Create an internal Certificate"),
    "external" => gettext("Create a Certificate Signing Request"),
);

$cert_keylens = array( "512", "1024", "2048", "4096");

$altname_types = array("DNS", "IP", "email", "URI");
$openssl_digest_algs = array("sha1", "sha224", "sha256", "sha384", "sha512");

$pgtitle = array(gettext("System"), gettext("Certificate Manager"));

if (isset($_GET['userid']) && is_numericint($_GET['userid'])) {
    $userid = $_GET['userid'];
}
if (isset($_POST['userid']) && is_numericint($_POST['userid'])) {
    $userid = $_POST['userid'];
}

if (isset($userid)) {
    $cert_methods["existing"] = gettext("Choose an existing certificate");
    if (!is_array($config['system']['user'])) {
        $config['system']['user'] = array();
    }
    $a_user =& $config['system']['user'];
}

if (isset($_GET['id']) && is_numericint($_GET['id'])) {
    $id = $_GET['id'];
}
if (isset($_POST['id']) && is_numericint($_POST['id'])) {
    $id = $_POST['id'];
}

if (!isset($config['ca']) || !is_array($config['ca'])) {
    $config['ca'] = array();
}

$a_ca =& $config['ca'];

if (!is_array($config['cert'])) {
    $config['cert'] = array();
}

$a_cert =& $config['cert'];

$internal_ca_count = 0;
foreach ($a_ca as $ca) {
    if ($ca['prv']) {
        $internal_ca_count++;
    }
}

$act = null;
if (isset($_GET['act'])) {
	$act = $_GET['act'];
} elseif (isset($_POST['act'])) {
    $act = $_POST['act'];
}

if ($act == "del") {
    if (!isset($a_cert[$id])) {
        redirectHeader("system_certmanager.php");
        exit;
    }

    $name = $a_cert[$id]['descr'];
    unset($a_cert[$id]);
    write_config();
    $savemsg = sprintf(gettext("Certificate %s successfully deleted"), $name) . "<br />";
    redirectHeader("system_certmanager.php");
    exit;
}

if ($act == "new") {
    if (isset($_GET['method'])) {
	$pconfig['method'] = $_GET['method'];
    } else {
	$pconfig['method'] = null;
    }
    $pconfig['keylen'] = "2048";
    $pconfig['digest_alg'] = "sha256";
    $pconfig['csr_keylen'] = "2048";
    $pconfig['csr_digest_alg'] = "sha256";
    $pconfig['lifetime'] = "365";
}

if ($act == "exp") {
    if (!$a_cert[$id]) {
        redirectHeader("system_certmanager.php");
        exit;
    }

    $exp_name = urlencode("{$a_cert[$id]['descr']}.crt");
    $exp_data = base64_decode($a_cert[$id]['crt']);
    $exp_size = strlen($exp_data);

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename={$exp_name}");
    header("Content-Length: $exp_size");
    echo $exp_data;
    exit;
}

if ($act == "key") {
    if (!$a_cert[$id]) {
        redirectHeader("system_certmanager.php");
        exit;
    }

    $exp_name = urlencode("{$a_cert[$id]['descr']}.key");
    $exp_data = base64_decode($a_cert[$id]['prv']);
    $exp_size = strlen($exp_data);

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename={$exp_name}");
    header("Content-Length: $exp_size");
    echo $exp_data;
    exit;
}

if ($act == "p12") {
    if (!$a_cert[$id]) {
        redirectHeader("system_certmanager.php");
        exit;
    }

    $exp_name = urlencode("{$a_cert[$id]['descr']}.p12");
    $args = array();
    $args['friendly_name'] = $a_cert[$id]['descr'];

    $ca = lookup_ca($a_cert[$id]['caref']);
    if ($ca) {
        $args['extracerts'] = openssl_x509_read(base64_decode($ca['crt']));
    }

    $res_crt = openssl_x509_read(base64_decode($a_cert[$id]['crt']));
    $res_key = openssl_pkey_get_private(array(0 => base64_decode($a_cert[$id]['prv']) , 1 => ""));

    $exp_data = "";
    openssl_pkcs12_export($res_crt, $exp_data, $res_key, null, $args);
    $exp_size = strlen($exp_data);

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename={$exp_name}");
    header("Content-Length: $exp_size");
    echo $exp_data;
    exit;
}

if ($act == "csr") {
    if (!$a_cert[$id]) {
        redirectHeader("system_certmanager.php");
        exit;
    }

    $pconfig['descr'] = $a_cert[$id]['descr'];
    $pconfig['csr'] = base64_decode($a_cert[$id]['csr']);
}

if ($_POST) {
    if ($_POST['save'] == gettext("Save")) {
        $input_errors = array();
        $pconfig = $_POST;

        /* input validation */
        if ($pconfig['method'] == "import") {
            $reqdfields = explode(
                " ",
                "descr cert key"
            );
            $reqdfieldsn = array(
                    gettext("Descriptive name"),
                    gettext("Certificate data"),
                    gettext("Key data"));
            if ($_POST['cert'] && (!strstr($_POST['cert'], "BEGIN CERTIFICATE") || !strstr($_POST['cert'], "END CERTIFICATE"))) {
                $input_errors[] = gettext("This certificate does not appear to be valid.");
            }
        }

        if ($pconfig['method'] == "internal") {
            $reqdfields = explode(
                " ",
                "descr caref keylen lifetime dn_country dn_state dn_city ".
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
        }

        if ($pconfig['method'] == "external") {
            $reqdfields = explode(
                " ",
                "descr csr_keylen csr_dn_country csr_dn_state csr_dn_city ".
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
        }

        if ($pconfig['method'] == "existing") {
            $reqdfields = array("certref");
            $reqdfieldsn = array(gettext("Existing Certificate Choice"));
        }

        $altnames = array();
        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
        if ($pconfig['method'] != "import" && $pconfig['method'] != "existing") {
            /* subjectAltNames */
            foreach ($_POST as $key => $value) {
                $entry = '';
                if (!substr_compare('altname_type', $key, 0, 12)) {
                    $entry = substr($key, 12);
                    $field = 'type';
                } elseif (!substr_compare('altname_value', $key, 0, 13)) {
                    $entry = substr($key, 13);
                    $field = 'value';
                }
                if (ctype_digit($entry)) {
                    $altnames[$entry][$field] = $value;
                }
            }
            $pconfig['altnames']['item'] = $altnames;

            /* Input validation for subjectAltNames */
            foreach ($altnames as $idx => $altname) {
                switch ($altname['type']) {
                    case "DNS":
                        if (!is_hostname($altname['value'])) {
                            array_push($input_errors, "DNS subjectAltName values must be valid hostnames or FQDNs");
                        }
                        break;
                    case "IP":
                        if (!is_ipaddr($altname['value'])) {
                            array_push($input_errors, "IP subjectAltName values must be valid IP Addresses");
                        }
                        break;
                    case "email":
                        if (empty($altname['value'])) {
                            array_push($input_errors, "You must provide an e-mail address for this type of subjectAltName");
                        }
                        if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $altname['value'])) {
                            array_push($input_errors, "The e-mail provided in a subjectAltName contains invalid characters.");
                        }
                        break;
                    case "URI":
                        /* Close enough? */
                        if (!is_URL($altname['value'])) {
                            $input_errors[] = "URI subjectAltName types must be a valid URI";
                        }
                        break;
                    default:
                        $input_errors[] = "Unrecognized subjectAltName type.";
                }
            }

            /* Make sure we do not have invalid characters in the fields for the certificate */
            for ($i = 0; $i < count($reqdfields); $i++) {
                if (preg_match('/email/', $reqdfields[$i])) {
/* dn_email or csr_dn_name */
                    if (preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST[$reqdfields[$i]])) {
                        array_push($input_errors, "The field 'Distinguished name Email Address' contains invalid characters.");
                    }
                } elseif (preg_match('/commonname/', $reqdfields[$i])) {
/* dn_commonname or csr_dn_commonname */
                    if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $_POST[$reqdfields[$i]])) {
                        array_push($input_errors, "The field 'Distinguished name Common Name' contains invalid characters.");
                    }
                } elseif (($reqdfields[$i] != "descr") && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\.\"\']/", $_POST[$reqdfields[$i]])) {
                    array_push($input_errors, "The field '" . $reqdfieldsn[$i] . "' contains invalid characters.");
                }
            }

            if (($pconfig['method'] != "external") && isset($_POST["keylen"]) && !in_array($_POST["keylen"], $cert_keylens)) {
                array_push($input_errors, gettext("Please select a valid Key Length."));
            }
            if (($pconfig['method'] != "external") && !in_array($_POST["digest_alg"], $openssl_digest_algs)) {
                array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
            }

            if (($pconfig['method'] == "external") && isset($_POST["csr_keylen"]) && !in_array($_POST["csr_keylen"], $cert_keylens)) {
                array_push($input_errors, gettext("Please select a valid Key Length."));
            }
            if (($pconfig['method'] == "external") && !in_array($_POST["csr_digest_alg"], $openssl_digest_algs)) {
                array_push($input_errors, gettext("Please select a valid Digest Algorithm."));
            }
        }

        /* if this is an AJAX caller then handle via JSON */
        if (isAjax() && is_array($input_errors)) {
            input_errors2Ajax($input_errors);
            exit;
        }

        /* save modifications */
        if (!$input_errors) {
            if ($pconfig['method'] == "existing") {
                $cert = lookup_cert($pconfig['certref']);
                if ($cert && $a_user) {
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

                if ($pconfig['method'] == "import") {
                    cert_import($cert, $pconfig['cert'], $pconfig['key']);
                }

                if ($pconfig['method'] == "internal") {
                    $dn = array(
                        'countryName' => $pconfig['dn_country'],
                        'stateOrProvinceName' => $pconfig['dn_state'],
                        'localityName' => $pconfig['dn_city'],
                        'organizationName' => $pconfig['dn_organization'],
                        'emailAddress' => $pconfig['dn_email'],
                        'commonName' => $pconfig['dn_commonname']);
                    if (count($altnames)) {
                        $altnames_tmp = "";
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
                        $pconfig['digest_alg']
                    )) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            array_push($input_errors, "openssl library returns: " . $ssl_err);
                        }
                    }
                }

                if ($pconfig['method'] == "external") {
                    $dn = array(
                        'countryName' => $pconfig['csr_dn_country'],
                        'stateOrProvinceName' => $pconfig['csr_dn_state'],
                        'localityName' => $pconfig['csr_dn_city'],
                        'organizationName' => $pconfig['csr_dn_organization'],
                        'emailAddress' => $pconfig['csr_dn_email'],
                        'commonName' => $pconfig['csr_dn_commonname']);
                    if (count($altnames)) {
                        $altnames_tmp = "";
                        foreach ($altnames as $altname) {
                            $altnames_tmp[] = "{$altname['type']}:{$altname['value']}";
                        }
                        $dn['subjectAltName'] = implode(",", $altnames_tmp);
                    }
                    if (!csr_generate($cert, $pconfig['csr_keylen'], $dn, $pconfig['csr_digest_alg'])) {
                        $input_errors = array();
                        while ($ssl_err = openssl_error_string()) {
                            array_push($input_errors, "openssl library returns: " . $ssl_err);
                        }
                    }
                }
                error_reporting($old_err_level);

                if (isset($id) && $a_cert[$id]) {
                    $a_cert[$id] = $cert;
                } else {
                    $a_cert[] = $cert;
                }
                if (isset($a_user) && isset($userid)) {
                    $a_user[$userid]['cert'][] = $cert['refid'];
                }
            }

            if (!$input_errors) {
                write_config();
            }

            if (isset($userid)) {
                post_redirect("system_usermanager.php", array('act' => 'edit', 'userid' => $userid));
                exit;
            }
        }
    }

    if ($_POST['save'] == gettext("Update")) {
        unset($input_errors);
        $pconfig = $_POST;

        /* input validation */
        $reqdfields = explode(" ", "descr cert");
        $reqdfieldsn = array(
            gettext("Descriptive name"),
            gettext("Final Certificate data"));

        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

//		old way
        /* make sure this csr and certificate subjects match */
//		$subj_csr = csr_get_subject($pconfig['csr'], false);
//		$subj_cert = cert_get_subject($pconfig['cert'], false);
//
//		if ( !isset($_POST['ignoresubjectmismatch']) && !($_POST['ignoresubjectmismatch'] == "yes") ) {
//			if (strcmp($subj_csr,$subj_cert)) {
//				$input_errors[] = sprintf(gettext("The certificate subject '%s' does not match the signing request subject."),$subj_cert);
//				$subject_mismatch = true;
//			}
//		}
        $mod_csr  =  csr_get_modulus($pconfig['csr'], false);
        $mod_cert = cert_get_modulus($pconfig['cert'], false);

        if (strcmp($mod_csr, $mod_cert)) {
            // simply: if the moduli don't match, then the private key and public key won't match
            $input_errors[] = sprintf(gettext("The certificate modulus does not match the signing request modulus."), $subj_cert);
            $subject_mismatch = true;
        }

        /* if this is an AJAX caller then handle via JSON */
        if (isAjax() && is_array($input_errors)) {
            input_errors2Ajax($input_errors);
            exit;
        }

        /* save modifications */
        if (!$input_errors) {
            $cert = $a_cert[$id];

            $cert['descr'] = $pconfig['descr'];

            csr_complete($cert, $pconfig['cert']);

            $a_cert[$id] = $cert;

            write_config();

            redirectHeader("system_certmanager.php");
        }
    }
}

include("head.inc");

$main_buttons = array(
    array('label'=>gettext("add or import certificate"), 'href'=>'system_certmanager.php?act=new'),
);



?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

function method_change() {

<?php
if ($internal_ca_count) {
    $submit_style = "";
} else {
    $submit_style = "none";
}
?>

	method = document.iform.method.selectedIndex;

	switch (method) {
		case 0:
			document.getElementById("import").style.display="";
			document.getElementById("internal").style.display="none";
			document.getElementById("external").style.display="none";
			document.getElementById("existing").style.display="none";
			document.getElementById("descriptivename").style.display="";
			document.getElementById("submit").style.display="";
			break;
		case 1:
			document.getElementById("import").style.display="none";
			document.getElementById("internal").style.display="";
			document.getElementById("external").style.display="none";
			document.getElementById("existing").style.display="none";
			document.getElementById("descriptivename").style.display="";
			document.getElementById("submit").style.display="<?=$submit_style;?>";
			break;
		case 2:
			document.getElementById("import").style.display="none";
			document.getElementById("internal").style.display="none";
			document.getElementById("external").style.display="";
			document.getElementById("existing").style.display="none";
			document.getElementById("descriptivename").style.display="";
			document.getElementById("submit").style.display="";
			break;
		case 3:
			document.getElementById("import").style.display="none";
			document.getElementById("internal").style.display="none";
			document.getElementById("external").style.display="none";
			document.getElementById("existing").style.display="";
			document.getElementById("descriptivename").style.display="none";
			document.getElementById("submit").style.display="";
			break;
	}
}

<?php if ($internal_ca_count) :
?>
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
?>
case "<?=$ca['refid'];?>":
    document.iform.dn_country.value = "<?=$subject[0]['v'];?>";
    document.iform.dn_state.value = "<?=$subject[1]['v'];?>";
    document.iform.dn_city.value = "<?=$subject[2]['v'];?>";
    document.iform.dn_organization.value = "<?=$subject[3]['v'];?>";
    document.iform.dn_email.value = "<?=$subject[4]['v'];?>";
    break;
<?php
endforeach; ?>
	}
}
<?php
endif; ?>

//]]>
</script>
<script type="text/javascript" src="/javascript/row_helper_dynamic.js"></script>
<input type='hidden' name='altname_value_type' value='select' />
<input type='hidden' name='altname_type_type' value='textbox' />
<script type="text/javascript">
//<![CDATA[
	rowname[0] = "altname_type";
	rowtype[0] = "textbox";
	rowsize[0] = "10";
	rowname[1] = "altname_value";
	rowtype[1] = "textbox";
	rowsize[1] = "30";
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

					<?php if ($act == "new" || ((isset($_POST['save']) && $_POST['save'] == gettext("Save")) && $input_errors)) :
?>

					<form action="system_certmanager.php" method="post" name="iform" id="iform" >
						<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area" class="table table-striped">
							<?php if (!isset($id)) :
?>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Method");?></td>
								<td width="78%" class="vtable">
									<select name='method' id='method' class="formselect" onchange='method_change()'>
									<?php
                                    foreach ($cert_methods as $method => $desc) :
                                        $selected = "";
                                        if ($pconfig['method'] == $method) {
                                            $selected = " selected=\"selected\"";
                                        }
                                    ?>
                                    <option value="<?=$method;
?>"<?=$selected;
?>><?=$desc;?></option>
									<?php
                                    endforeach; ?>
									</select>
								</td>
							</tr>
							<?php
endif; ?>
							<tr id="descriptivename">
								<?php
                                if (isset($a_user) && empty($pconfig['descr'])) {
                                    $pconfig['descr'] = $a_user[$userid]['name'];
                                }
                                ?>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
								<td width="78%" class="vtable">
									<input name="descr" type="text" class="formfld unknown" id="descr" size="20" value="<?php if(isset($pconfig['descr'])) echo htmlspecialchars($pconfig['descr']);?>"/>
								</td>
							</tr>
						</table>

						<table width="100%" border="0" cellpadding="6" cellspacing="0" id="import" summary="import" class="table table-striped">
						<thead>
							<tr>
								<th colspan="2" valign="top" class="listtopic"><?=gettext("Import Certificate");?></th>
							</tr>
						</thead>

	                        <tbody>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate data");?></td>
								<td width="78%" class="vtable">
									<textarea name="cert" id="cert" cols="65" rows="7" class="formfld_cert"><?php if(isset($pconfig['cert'])) echo htmlspecialchars($pconfig['cert']);?></textarea>
									<br />
									<?=gettext("Paste a certificate in X.509 PEM format here.");?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Private key data");?></td>
								<td width="78%" class="vtable">
									<textarea name="key" id="key" cols="65" rows="7" class="formfld_cert"><?php  if(isset($pconfig['key'])) echo htmlspecialchars($pconfig['key']);?></textarea>
									<br />
									<?=gettext("Paste a private key in X.509 PEM format here.");?>
								</td>
							</tr>
	                        </tbody>
						</table>

						<table width="100%" border="0" cellpadding="6" cellspacing="0" id="internal" summary="internal" class="table table-striped">
							<thead>
							<tr>
								<th colspan="2" valign="top" class="listtopic"><?=gettext("Internal Certificate");?></th>
							</tr>
							</thead>

	                        <tbody>
							<?php if (!$internal_ca_count) :
?>

							<tr>
								<td colspan="2" align="center" class="vtable">
									<?=gettext("No internal Certificate Authorities have been defined. You must");?>
									<a href="system_camanager.php?act=new&amp;method=internal"><?=gettext("create");?></a>
									<?=gettext("an internal CA before creating an internal certificate.");?>
								</td>
							</tr>

							<?php
else :
?>

							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Certificate authority");?></td>
								<td width="78%" class="vtable">
									<select name='caref' id='caref' class="formselect" onchange='internalca_change()'>
									<?php
                                    foreach ($a_ca as $ca) :
                                        if (!$ca['prv']) {
                                            continue;
                                        }
                                        $selected = "";
                                        if (isset($pconfig['caref']) && isset($ca['refid']) && $pconfig['caref'] == $ca['refid']) {
                                            $selected = " selected=\"selected\"";
                                        }
                                    ?>
                                    <option value="<?=$ca['refid'];
?>"<?=$selected;
?>><?=$ca['descr'];?></option>
									<?php
                                    endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Key length");?></td>
								<td width="78%" class="vtable">
									<select name='keylen' class="formselect">
									<?php
                                    foreach ($cert_keylens as $len) :
                                        $selected = "";
                                        if ($pconfig['keylen'] == $len) {
                                            $selected = " selected=\"selected\"";
                                        }
                                    ?>
                                    <option value="<?=$len;
?>"<?=$selected;
?>><?=$len;?></option>
									<?php
                                    endforeach; ?>
									</select>
									<?=gettext("bits");?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Digest Algorithm");?></td>
								<td width="78%" class="vtable">
									<select name='digest_alg' id='digest_alg' class="formselect">
									<?php
                                    foreach ($openssl_digest_algs as $digest_alg) :
                                        $selected = "";
                                        if ($pconfig['digest_alg'] == $digest_alg) {
                                            $selected = " selected=\"selected\"";
                                        }
                                    ?>
                                    <option value="<?=$digest_alg;
?>"<?=$selected;
?>><?=strtoupper($digest_alg);?></option>
									<?php
                                    endforeach; ?>
									</select>
									<br /><?= gettext("NOTE: It is recommended to use an algorithm stronger than SHA1 when possible.") ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Lifetime");?></td>
								<td width="78%" class="vtable">
									<input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="5" value="<?=htmlspecialchars($pconfig['lifetime']);?>"/>
									<?=gettext("days");?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Distinguished name");?></td>
								<td width="78%" class="vtable">
									<table border="0" cellspacing="0" cellpadding="2" summary="name">
										<tr>
											<td align="right"><?=gettext("Country Code");?> : &nbsp;</td>
											<td align="left">
												<input name="dn_country" type="text" class="formfld unknown" maxlength="2" size="2" value="<?php if (isset($pconfig['dn_country'])) echo htmlspecialchars($pconfig['dn_country']);?>"/>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("State or Province");?> : &nbsp;</td>
											<td align="left">
												<input name="dn_state" type="text" class="formfld unknown" size="40" value="<?php if (isset($pconfig['dn_state'])) echo htmlspecialchars($pconfig['dn_state']);?>"/>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("City");?> : &nbsp;</td>
											<td align="left">
												<input name="dn_city" type="text" class="formfld unknown" size="40" value="<?php if (isset($pconfig['dn_city'])) echo htmlspecialchars($pconfig['dn_city']);?>"/>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Organization");?> : &nbsp;</td>
											<td align="left">
												<input name="dn_organization" type="text" class="formfld unknown" size="40" value="<?php if (isset($pconfig['dn_organization'])) echo htmlspecialchars($pconfig['dn_organization']);?>"/>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Email Address");?> : &nbsp;</td>
											<td align="left">
												<input name="dn_email" type="text" class="formfld unknown" size="25" value="<?php if (isset($pconfig['dn_email'])) echo htmlspecialchars($pconfig['dn_email']);?>"/>
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("webadmin@mycompany.com");?>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Common Name");?> : &nbsp;</td>
											<td align="left">
												<?php
                                                if (isset($a_user) && empty($pconfig['dn_commonname'])) {
                                                    $pconfig['dn_commonname'] = $a_user[$userid]['name'];
                                                }
                                                ?>
												<input name="dn_commonname" type="text" class="formfld unknown" size="25" value="<?php if (isset($pconfig['dn_commonname'])) htmlspecialchars($pconfig['dn_commonname']);?>"/>
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("www.example.com");?>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Alternative Names");?> : &nbsp;</td>
											<td align="left">
												<table id="altNametable">
												<thead>
												<tr>
													<th><div id="onecolumn"><?=gettext("Type");?></div></th>
													<th><div id="twocolumn"><?=gettext("Value");?></div></th>
												</tr>
												</thead>
												<tbody>
												<?php
                                                    $counter = 0;
                                                if (isset($pconfig['altnames']['item'])) :
                                                    foreach ($pconfig['altnames']['item'] as $item) :
                                                        $type = $item['type'];
                                                        $value = $item['value'];
                                                ?>
												<tr>
													<td>
													<input autocomplete="off" name="altname_type<?php echo $counter; ?>" type="text" class="formfld unknown" id="altname_type<?php echo $counter;
?>" size="20" value="<?=htmlspecialchars($type);?>" />
													</td>
													<td>
													<input autocomplete="off" name="altname_value<?php echo $counter; ?>" type="text" class="formfld unknown" id="altname_value<?php echo $counter;
?>" size="20" value="<?=htmlspecialchars($value);?>" />
													</td>
													<td>
													<a onclick="removeRow(this); return false;" href="#" title="<?=gettext("remove this entry"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
													</td>
												</tr>
												<?php
                                                        $counter++;
                                                    endforeach;
                                                endif;
                                                ?>
												<tr><td>&nbsp;</td></tr>
												</tbody>
												</table>
												<a onclick="javascript:addRowTo('altNametable', 'formfldalias'); return false;" href="#" title="<?=gettext("add another entry");?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
												<script type="text/javascript">
												//<![CDATA[
													field_counter_js = 3;
													rows = 1;
													totalrows = <?php echo $counter; ?>;
													loaded = <?php echo $counter; ?>;
												//]]>
												</script>
												<br />NOTE: Type must be one of DNS (FQDN or Hostname), IP (IP address), URI, or email.
											</td>
										</tr>
									</table>
								</td>
							</tr>
	                    </tbody>

						<?php
endif; ?>

						</table>

						<table width="100%" border="0" cellpadding="6" cellspacing="0" id="external" summary="external" class="table table-striped">
							<thead>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("External Signing Request");?></td>
							</tr>
							</thead>
							<tbody>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Key length");?></td>
								<td width="78%" class="vtable">
									<select name='csr_keylen' class="formselect">
									<?php
                                    if (!isset($pconfig['csr_keylen']) && isset($pconfig['csr_keylen'])) {
                                        $pconfig['csr_keylen'] = $pconfig['csr_keylen'];
                                    }
                                    foreach ($cert_keylens as $len) :
                                        $selected = "";
                                        if ($pconfig['csr_keylen'] == $len) {
                                            $selected = " selected=\"selected\"";
                                        }
                                    ?>
                                    <option value="<?=$len;
?>"<?=$selected;
?>><?=$len;?></option>
									<?php
                                    endforeach; ?>
									</select>
									bits
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Digest Algorithm");?></td>
								<td width="78%" class="vtable">
									<select name='csr_digest_alg' id='csr_digest_alg' class="formselect">
									<?php
                                    foreach ($openssl_digest_algs as $csr_digest_alg) :
                                        $selected = "";
                                        if ($pconfig['csr_digest_alg'] == $csr_digest_alg) {
                                            $selected = " selected=\"selected\"";
                                        }
                                    ?>
                                    <option value="<?=$csr_digest_alg;
?>"<?=$selected;
?>><?=strtoupper($csr_digest_alg);?></option>
									<?php
                                    endforeach; ?>
									</select>
									<br /><?= gettext("NOTE: It is recommended to use an algorithm stronger than SHA1 when possible.") ?>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Distinguished name");?></td>
								<td width="78%" class="vtable">
									<table border="0" cellspacing="0" cellpadding="2" summary="name">
										<tr>
											<td align="right"><?=gettext("Country Code");?> : &nbsp;</td>
											<td align="left">
												<select name='csr_dn_country' class="formselect">
												<?php
                                                $dn_cc = get_country_codes();
                                                foreach ($dn_cc as $cc => $cn) {
                                                    $selected = '';
                                                    if (isset($pconfig['csr_dn_country']) && $pconfig['csr_dn_country'] == $cc) {
                                                        $selected = ' selected="selected"';
                                                    }
                                                    print "<option value=\"$cc\"$selected>$cc ($cn)</option>";
                                                }
                                                ?>
												</select>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("State or Province");?> : &nbsp;</td>
											<td align="left">
												<input name="csr_dn_state" type="text" class="formfld unknown" size="40" value="<?php if (isset($pconfig['csr_dn_state'])) echo htmlspecialchars($pconfig['csr_dn_state']);?>" />
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("Sachsen");?>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("City");?> : &nbsp;</td>
											<td align="left">
												<input name="csr_dn_city" type="text" class="formfld unknown" size="40" value="<?php if (isset($pconfig['csr_dn_city'])) echo htmlspecialchars($pconfig['csr_dn_city']);?>" />
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("Leipzig");?>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Organization");?> : &nbsp;</td>
											<td align="left">
												<input name="csr_dn_organization" type="text" class="formfld unknown" size="40" value="<?php if (isset($pconfig['csr_dn_organization'])) echo htmlspecialchars($pconfig['csr_dn_organization']);?>" />
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("My Company Inc.");?>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Email Address");?> : &nbsp;</td>
											<td align="left">
												<input name="csr_dn_email" type="text" class="formfld unknown" size="25" value="<?php if (isset($pconfig['csr_dn_email'])) echo htmlspecialchars($pconfig['csr_dn_email']);?>"/>
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("webadmin@mycompany.com");?>
											</td>
										</tr>
										<tr>
											<td align="right"><?=gettext("Common Name");?> : &nbsp;</td>
											<td align="left">
												<input name="csr_dn_commonname" type="text" class="formfld unknown" size="25" value="<?php if(isset($pconfig['csr_dn_commonname'])) echo htmlspecialchars($pconfig['csr_dn_commonname']);?>"/>
												&nbsp;
												<em>ex:</em>
												&nbsp;
												<?=gettext("www.example.com");?>
											</td>
										</tr>
									</table>
								</td>
							</tr>
							</tbody>
						</table>

						<table width="100%" border="0" cellpadding="6" cellspacing="0" id="existing" summary="existing" class="table table-striped">
							<thead>
							<tr>
								<th colspan="2" valign="top" class="listtopic"><?=gettext("Choose an Existing Certificate");?></th>
							</tr>
							</thead>
							<tbody>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Existing Certificates");?></td>
								<td width="78%" class="vtable">
									<?php if (isset($userid) && $a_user) :
?>
									<input name="userid" type="hidden" value="<?=htmlspecialchars($userid);?>" />
									<?php
endif;?>
									<select name='certref' class="formselect">
									<?php
                                    foreach ($config['cert'] as $cert) :
                                        $selected = "";
                                        $caname = "";
                                        $inuse = "";
                                        $revoked = "";
                                        $usercert = isset($config['system']['user'][$userid]['cert']) ? $config['system']['user'][$userid]['cert'] : array();
                                        if (isset($userid) && in_array($cert['refid'], $usercert)) {
                                            continue;
                                        }
                                        if (isset($cert['caref'])) {
	                                        $ca = lookup_ca($cert['caref']);
		                                if ($ca) {
			                            $caname = " (CA: {$ca['descr']})";
				                }
					} else {
						$ca = null;
					}
                                        if (isset($pconfig['certref']) && isset($cert['refid']) && $pconfig['certref'] == $cert['refid']) {
                                            $selected = " selected=\"selected\"";
                                        }
                                        if (isset($cert['refid']) && cert_in_use($cert['refid'])) {
                                            $inuse = " *In Use";
                                        }
                                        if (is_cert_revoked($cert)) {
                                            $revoked = " *Revoked";
                                        }
                                    ?>
                                    <option value="<?=$cert['refid'];
?>"<?=$selected;
?>><?=$cert['descr'] . $caname . $inuse . $revoked;?></option>
									<?php
                                    endforeach; ?>
									</select>
								</td>
							</tr>
							</tbody>
						</table>

						<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="save" class="table">
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%">
									<input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
									<?php if (isset($id) && $a_cert[$id]) :
?>
									<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
									<?php
endif;?>
								</td>
							</tr>
						</table>
					</form>

					<?php
elseif ($act == "csr" || ((isset($_POST['save']) && $_POST['save'] == gettext("Update")) && $input_errors)) :
?>

					<form action="system_certmanager.php" method="post" name="iform" id="iform">
						<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="name"  class="table table-striped">
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Descriptive name");?></td>
								<td width="78%" class="vtable">
									<input name="descr" type="text" class="formfld unknown" id="descr" size="20" value="<?=htmlspecialchars($pconfig['descr']);?>"/>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="list" height="12"></td>
							</tr>
							<tr>
								<td colspan="2" valign="top" class="listtopic"><?=gettext("Complete Signing Request");?></td>
							</tr>

							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Signing request data");?></td>
								<td width="78%" class="vtable">
									<textarea name="csr" id="csr" cols="65" rows="7" class="formfld_cert" readonly="readonly"><?=htmlspecialchars($pconfig['csr']);?></textarea>
									<br />
									<?=gettext("Copy the certificate signing data from here and forward it to your certificate authority for signing.");?></td>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top" class="vncellreq"><?=gettext("Final certificate data");?></td>
								<td width="78%" class="vtable">
									<textarea name="cert" id="cert" cols="65" rows="7" class="formfld_cert"><?=htmlspecialchars($pconfig['cert']);?></textarea>
									<br />
									<?=gettext("Paste the certificate received from your certificate authority here.");?></td>
								</td>
							</tr>
							<tr>
								<td width="22%" valign="top">&nbsp;</td>
								<td width="78%">
									<?php /* if ( isset($subject_mismatch) && $subject_mismatch === true): ?>
									<input id="ignoresubjectmismatch" name="ignoresubjectmismatch" type="checkbox" class="formbtn" value="yes" />
									<label for="ignoresubjectmismatch"><strong><?=gettext("Ignore certificate subject mismatch"); ?></strong></label><br />
									<?php echo gettext("Warning: Using this option may create an " .
									"invalid certificate.  Check this box to disable the request -> " .
									"response subject verification. ");
									?><br />
									<?php endif; */ ?>
									<input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Update");?>" />
									<?php if (isset($id) && $a_cert[$id]) :
?>
									<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
									<input name="act" type="hidden" value="csr" />
									<?php
endif;?>
								</td>
							</tr>
						</table>
					</form>

					<?php
else :
?>

					<table summary="details"  class="table table-striped">
						<thead>
						<tr>
							<td width="15%" class="listhdrr"><?=gettext("Name");?></td>
							<td width="15%" class="listhdrr"><?=gettext("Issuer");?></td>
							<td width="40%" class="listhdrr"><?=gettext("Distinguished Name");?></td>
							<td width="10%" class="listhdrr"><?=gettext("In Use");?></td>
						</tr>
						</thead>
						<tbody>
						<?php
                            $i = 0;
                        foreach ($a_cert as $cert) :
                            $name = htmlspecialchars($cert['descr']);

                            if ($cert['crt']) {
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
			}

                            $certimg = '<span class="glyphicon glyphicon-certificate __iconspacer"></span>';
                        ?>
						<tr>
                        <td class="listlr">
                            <table summary="icon">
                                <tr>
                                    <td align="left" valign="middle">
                                        <?=$certimg;?>
                                    </td>
                                    <td align="left" valign="middle">
                                        <?=$name;?>
                                    </td>
                                </tr>
                                <tr><td>&nbsp;</td></tr>
                                <?php if (is_array($purpose)) :
?>
									<tr><td colspan="2">
										CA: <?php echo $purpose['ca']; ?>,
										Server: <?php echo $purpose['server']; ?>
									</td></tr>
									<?php
endif; ?>
                            </table>
						</td>
						<td class="listr"><?=$caname;?>&nbsp;</td>
						<td class="listr"><?=$subj;?>&nbsp;<br />
                        <table summary="valid">
                            <tr>
                                <td width="10%">&nbsp;</td>
                                <td width="20%"><?=gettext("Valid From")?>:</td>
                                <td width="70%"><?= $startdate ?></td>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                                <td><?=gettext("Valid Until")?>:</td>
                                <td><?= $enddate ?></td>
                            </tr>
                        </table>
						</td>
						<td class="listr">
                        <?php if (is_cert_revoked($cert)) :
?>
							<b>Revoked</b><br />
							<?php
endif; ?>
                        <?php if (is_webgui_cert($cert['refid'])) :
?>
							webConfigurator<br />
							<?php
endif; ?>
                        <?php if (is_user_cert($cert['refid'])) :
?>
							User Cert<br />
							<?php
endif; ?>
                        <?php if (is_openvpn_server_cert($cert['refid'])) :
?>
							OpenVPN Server<br />
							<?php
endif; ?>
                        <?php if (is_openvpn_client_cert($cert['refid'])) :
?>
							OpenVPN Client<br />
							<?php
endif; ?>
                        <?php if (is_ipsec_cert($cert['refid'])) :
?>
							IPsec Tunnel<br />
							<?php
endif; ?>


                        <a href="system_certmanager.php?act=exp&amp;id=<?=$i;
?>" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("export ca");?>">
                        <span class="glyphicon glyphicon-download"></span>
                        </a>

                        <a href="system_certmanager.php?act=key&amp;id=<?=$i;
?>" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("export key");?>">
                        <span class="glyphicon glyphicon-download"></span>
                        </a>

                        <a href="system_certmanager.php?act=p12&amp;id=<?=$i;
?>" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("export ca cert+user cert+user cert key in .p12 format");?>">
                            <span class="glyphicon glyphicon-download"></span>
                        </a>
						<?php if (!cert_in_use($cert['refid'])) :
?>

							<a href="system_certmanager.php?act=del&amp;id=<?=$i;
?>" class="btn btn-default btn-xs" onclick="return confirm('<?=gettext("Do you really want to delete this Certificate?");
?>')" data-toggle="tooltip" data-placement="left" title="<?=gettext("delete cert");?>">
								<span class="glyphicon glyphicon-remove"></span>
							</a>

						<?php
endif; ?>
						<?php if (isset($cert['csr'])) :
?>

							<a href="system_certmanager.php?act=csr&amp;id=<?=$i;
?>" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("update csr");?>">
							<span class="glyphicon glyphicon-edit"></span>
							</a>
						<?php
endif; ?>
						</td>
					</tr>
						<?php $i++;

                        endforeach; ?>

						<tr>
							<td>&nbsp;</td>
							<td colspan="3"><?=gettext("Note: You can only delete a certificate if it is not currently in use.");?></td>
						</tr>
						</tbody>
					</table>
					<?php
endif; ?>
				</div>
			</section>
		</div>
	</div>
</section>

<script type="text/javascript">
//<![CDATA[

method_change();
internalca_change();

//]]>
</script>

<?php include("foot.inc");
