<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2014 Ermal Luçi
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
require_once("guiconfig.inc");
require_once("vpn.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");


/*
 * ikeid management functions
 */

function ipsec_ikeid_used($ikeid) {
	global $config;

	foreach ($config['ipsec']['phase1'] as $ph1ent)
		if( $ikeid == $ph1ent['ikeid'] )
			return true;

	return false;
}

function ipsec_ikeid_next() {

	$ikeid = 1;
	while(ipsec_ikeid_used($ikeid))
		$ikeid++;

	return $ikeid;
}


if (!is_array($config['ipsec'])) {
		$config['ipsec'] = array();
}

if (!is_array($config['ipsec']['phase1'])) {
    $config['ipsec']['phase1'] = array();
}

if (!is_array($config['ipsec']['phase2'])) {
    $config['ipsec']['phase2'] = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	// fetch data
	if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
	    $p1index = $_GET['dup'];
	} elseif (isset($_GET['p1index']) && is_numericint($_GET['p1index'])) {
	    $p1index = $_GET['p1index'];
	}
	$pconfig = array();

	// generice defaults
	$pconfig['interface'] = "wan";
	$pconfig['iketype'] = "ikev1";
	$phase1_fields = "mode,protocol,myid_type,myid_data,peerid_type,peerid_data
	,encryption-algorithm,halgo,dhgroup,lifetime,authentication_method,descr,nat_traversal
	,interface,iketype,dpd_delay,dpd_maxfail,remote-gateway,pre-shared-key,certref
	,caref,reauth_enable,rekey_enable";
	if (isset($p1index) && isset($config['ipsec']['phase1'][$p1index])) {
			// 1-on-1 copy
			foreach (explode(",", $phase1_fields) as $fieldname) {
				$fieldname = trim($fieldname);
				if(isset($config['ipsec']['phase1'][$p1index][$fieldname])) {
					$pconfig[$fieldname] = $config['ipsec']['phase1'][$p1index][$fieldname];
				} elseif (!isset($pconfig[$fieldname])) {
					// initialize element
					$pconfig[$fieldname] = null;
				}
			}

			// attributes with some kind of logic behind them...
	    if (!isset($_GET['dup']) || !is_numericint($_GET['dup'])) {
					// don't copy the ikeid on dup
	        $pconfig['ikeid'] = $config['ipsec']['phase1'][$p1index]['ikeid'];
	    }
	    $pconfig['disabled'] = isset($config['ipsec']['phase1'][$p1index]['disabled']);

			$pconfig['remotebits'] = null;
			$pconfig['remotenet'] = null ;
			if (isset($a_phase1[$p1index]['remote-subnet']) && strpos($config['ipsec']['phase1'][$p1index]['remote-subnet'],'/') !== false) {
		list($pconfig['remotenet'],$pconfig['remotebits']) = explode("/", $config['ipsec']['phase1'][$p1index]['remote-subnet']);
			} elseif (isset($config['ipsec']['phase1'][$p1index]['remote-subnet'])) {
				$pconfig['remotenet'] = $config['ipsec']['phase1'][$p1index]['remote-subnet'];
			}

	    if (isset($config['ipsec']['phase1'][$p1index]['mobile'])) {
	        $pconfig['mobile'] = true;
	    }
	} else {
	    /* defaults new */
	    if (isset($config['interfaces']['lan'])) {
	        $pconfig['localnet'] = "lan";
	    }
	    $pconfig['mode'] = "aggressive";
	    $pconfig['protocol'] = "inet";
	    $pconfig['myid_type'] = "myaddress";
	    $pconfig['peerid_type'] = "peeraddress";
	    $pconfig['authentication_method'] = "pre_shared_key";
	    $pconfig['encryption-algorithm'] = array("name" => "3des") ;
	    $pconfig['halgo'] = "sha1";
	    $pconfig['dhgroup'] = "2";
	    $pconfig['lifetime'] = "28800";
	    $pconfig['nat_traversal'] = "on";
	    $pconfig['iketype'] = "ikev1";

	    /* mobile client */
	    if (isset($_GET['mobile'])) {
	        $pconfig['mobile']=true;
	    }
			// init empty
			foreach (explode(",", $phase1_fields) as $fieldname) {
				$fieldname = trim($fieldname);
				if (!isset($pconfig[$fieldname])) {
					$pconfig[$fieldname] = null;
				}
			}

	}

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$a_phase1 = &$config['ipsec']['phase1'];
	if (isset($_POST['p1index']) && is_numericint($_POST['p1index'])) {
	    $p1index = $_POST['p1index'];
	}
	$input_errors = array();
	$pconfig = $_POST;
	$old_ph1ent = $a_phase1[$p1index];

	// Preperations to kill some settings which aren't left empty by the field.
	// Unset ca and cert if not required to avoid storing in config
	if ($pconfig['authentication_method'] == "pre_shared_key" || $pconfig['authentication_method'] == "xauth_psk_server") {
			unset($pconfig['caref']);
			unset($pconfig['certref']);
	}
	// unset dpd on post
	if (!isset($pconfig['dpd_enable'])) {
		unset($pconfig['dpd_delay']);
		unset($pconfig['dpd_maxfail']);
	}

	/* My identity */
	if ($pconfig['myid_type'] == "myaddress") {
			$pconfig['myid_data'] = "";
	}
	/* Peer identity */
	if ($pconfig['myid_type'] == "peeraddress") {
			$pconfig['peerid_data'] = "";
	}

	/* input validation */
	$method = $pconfig['authentication_method'];

	// Only require PSK here for normal PSK tunnels (not mobile) or xauth.
	// For RSA methods, require the CA/Cert.
	switch ($method) {
			case "eap-tls":
					if ($pconfig['iketype'] != 'ikev2') {
							$input_errors[] = gettext("EAP-TLS can only be used with IKEv2 type VPNs.");
					}
					break;
			case "pre_shared_key":
					// If this is a mobile PSK tunnel the user PSKs go on
					//    the PSK tab, not here, so skip the check.
					if ($pconfig['mobile']) {
							break;
					}
			case "xauth_psk_server":
					$reqdfields = explode(" ", "pre-shared-key");
					$reqdfieldsn = array(gettext("Pre-Shared Key"));
					break;
			case "hybrid_rsa_server":
			case "xauth_rsa_server":
			case "rsasig":
					$reqdfields = explode(" ", "caref certref");
					$reqdfieldsn = array(gettext("Certificate Authority"),gettext("Certificate"));
					break;
	}
	if (empty($pconfig['mobile'])) {
			$reqdfields[] = "remote-gateway";
			$reqdfieldsn[] = gettext("Remote gateway");
	}

	do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

	if ((!empty($pconfig['lifetime']) && !is_numeric($pconfig['lifetime']))) {
			$input_errors[] = gettext("The P1 lifetime must be an integer.");
	}

	if (!empty($pconfig['remote-gateway'])) {
			if (!is_ipaddr($pconfig['remote-gateway']) && !is_domain($pconfig['remote-gateway'])) {
					$input_errors[] = gettext("A valid remote gateway address or host name must be specified.");
			} elseif (is_ipaddrv4($pconfig['remote-gateway']) && ($pconfig['protocol'] != "inet"))
					$input_errors[] = gettext("A valid remote gateway IPv4 address must be specified or you need to change protocol to IPv6");
			elseif (is_ipaddrv6($pconfig['remote-gateway']) && ($pconfig['protocol'] != "inet6"))
					$input_errors[] = gettext("A valid remote gateway IPv6 address must be specified or you need to change protocol to IPv4");
	}

	if ((!empty($pconfig['remote-gateway']) && is_ipaddr($pconfig['remote-gateway']) && !isset($pconfig['disabled']) )) {
			$t = 0;
			foreach ($a_phase1 as $ph1tmp) {
					if ($p1index <> $t) {
							if (isset($ph1tmp['remote-gateway']) && $ph1tmp['remote-gateway'] == $pconfig['remote-gateway'] && !isset($ph1tmp['disabled'])) {
									$input_errors[] = sprintf(gettext('The remote gateway "%1$s" is already used by phase1 "%2$s".'), $pconfig['remote-gateway'], $ph1tmp['descr']);
							}
					}
					$t++;
			}
	}

	if (count($config['ipsec']['phase2'])) {
			foreach ($config['ipsec']['phase2'] as $phase2) {
					if ($phase2['ikeid'] == $pconfig['ikeid']) {
							if (($pconfig['protocol'] == "inet") && ($phase2['mode'] == "tunnel6")) {
									$input_errors[] = gettext("There is a Phase 2 using IPv6, you cannot use IPv4.");
									break;
							}
							if (($pconfig['protocol'] == "inet6") && ($phase2['mode'] == "tunnel")) {
									$input_errors[] = gettext("There is a Phase 2 using IPv4, you cannot use IPv6.");
									break;
							}
					}
			}
	}

	if ($pconfig['myid_type'] == "address" and $pconfig['myid_data'] == "") {
			$input_errors[] = gettext("Please enter an address for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "keyid tag" and $pconfig['myid_data'] == "") {
			$input_errors[] = gettext("Please enter a keyid tag for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "fqdn" and $pconfig['myid_data'] == "") {
			$input_errors[] = gettext("Please enter a fully qualified domain name for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "user_fqdn" and $pconfig['myid_data'] == "") {
			$input_errors[] = gettext("Please enter a user and fully qualified domain name for 'My Identifier'");
	}

	if ($pconfig['myid_type'] == "dyn_dns" and $pconfig['myid_data'] == "") {
			$input_errors[] = gettext("Please enter a dynamic domain name for 'My Identifier'");
	}

	if ((($pconfig['myid_type'] == "address") && !is_ipaddr($pconfig['myid_data']))) {
			$input_errors[] = gettext("A valid IP address for 'My identifier' must be specified.");
	}

	if ((($pconfig['myid_type'] == "fqdn") && !is_domain($pconfig['myid_data']))) {
			$input_errors[] = gettext("A valid domain name for 'My identifier' must be specified.");
	}

	if ($pconfig['myid_type'] == "fqdn") {
			if (is_domain($pconfig['myid_data']) == false) {
					$input_errors[] = gettext("A valid FQDN for 'My identifier' must be specified.");
			}
	}

	if ($pconfig['myid_type'] == "user_fqdn") {
			$user_fqdn = explode("@", $pconfig['myid_data']);
			if (is_domain($user_fqdn[1]) == false) {
					$input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'My identifier' must be specified.");
			}
	}

	if ($pconfig['myid_type'] == "dyn_dns") {
			if (is_domain($pconfig['myid_data']) == false) {
					$input_errors[] = gettext("A valid Dynamic DNS address for 'My identifier' must be specified.");
			}
	}

	// Only enforce peer ID if we are not dealing with a pure-psk mobile config.
	if (!(($pconfig['authentication_method'] == "pre_shared_key") && !empty($pconfig['mobile']))) {
			if ($pconfig['peerid_type'] == "address" and $pconfig['peerid_data'] == "") {
					$input_errors[] = gettext("Please enter an address for 'Peer Identifier'");
			}
			if ($pconfig['peerid_type'] == "keyid tag" and $pconfig['peerid_data'] == "") {
					$input_errors[] = gettext("Please enter a keyid tag for 'Peer Identifier'");
			}
			if ($pconfig['peerid_type'] == "fqdn" and $pconfig['peerid_data'] == "") {
					$input_errors[] = gettext("Please enter a fully qualified domain name for 'Peer Identifier'");
			}
			if ($pconfig['peerid_type'] == "user_fqdn" and $pconfig['peerid_data'] == "") {
					$input_errors[] = gettext("Please enter a user and fully qualified domain name for 'Peer Identifier'");
			}
			if ((($pconfig['peerid_type'] == "address") && !is_ipaddr($pconfig['peerid_data']))) {
					$input_errors[] = gettext("A valid IP address for 'Peer identifier' must be specified.");
			}
			if ((($pconfig['peerid_type'] == "fqdn") && !is_domain($pconfig['peerid_data']))) {
					$input_errors[] = gettext("A valid domain name for 'Peer identifier' must be specified.");
			}
			if ($pconfig['peerid_type'] == "fqdn") {
					if (is_domain($pconfig['peerid_data']) == false) {
							$input_errors[] = gettext("A valid FQDN for 'Peer identifier' must be specified.");
					}
			}
			if ($pconfig['peerid_type'] == "user_fqdn") {
					$user_fqdn = explode("@", $pconfig['peerid_data']);
					if (is_domain($user_fqdn[1]) == false) {
							$input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'Peer identifier' must be specified.");
					}
			}
	}

	if (!empty($pconfig['dpd_enable'])) {
			if (!is_numeric($pconfig['dpd_delay'])) {
					$input_errors[] = gettext("A numeric value must be specified for DPD delay.");
			}
			if (!is_numeric($pconfig['dpd_maxfail'])) {
					$input_errors[] = gettext("A numeric value must be specified for DPD retries.");
			}
	}

	if (!empty($pconfig['iketype']) && $pconfig['iketype'] != "ikev1" && $pconfig['iketype'] != "ikev2") {
			$input_errors[] = gettext("Valid arguments for IKE type is v1 or v2");
	}

	/* build our encryption algorithms array */
	if (!isset($pconfig['encryption-algorithm']) || !is_array($pconfig['encryption-algorithm'])) {
		$pconfig['encryption-algorithm'] = array();
	}
	$pconfig['encryption-algorithm']['name'] = $_POST['encryption-algorithm'];
	if ($pconfig['ealgo_keylen']) {
			$pconfig['ealgo']['keylen'] = $_POST['ealgo_keylen'];
	}

	if (count($input_errors) == 0) {
			$copy_fields = "ikeid,iketype,interface,mode,protocol,myid_type,myid_data
			,peerid_type,peerid_data,encryption-algorithm,hash-algorithm,dhgroup
			,lifetime,pre-shared-key,certref,caref,authentication_method,descr
			,nat_traversal";

			foreach (explode(",",$copy_fields) as $fieldname) {
				$fieldname = trim($fieldname);
				if(!empty($pconfig[$fieldname])) {
					$ph1ent[$fieldname] = $pconfig[$fieldname];
				}
			}

			$ph1ent['disabled'] = !empty($pconfig['disabled']) ? true : false;
			$ph1ent['private-key'] =isset($pconfig['privatekey']) ? base64_encode($pconfig['privatekey']) : null;
			if (!empty($pconfig['mobile'])) {
					$ph1ent['mobile'] = true;
			} else {
					$ph1ent['remote-gateway'] = $pconfig['remote-gateway'];
			}
			if (isset($pconfig['reauth_enable'])) {
					$ph1ent['reauth_enable'] = true;
			}
			if (isset($pconfig['rekey_enable'])) {
					$ph1ent['rekey_enable'] = true;
			}

			if (isset($pconfig['dpd_enable'])) {
					$ph1ent['dpd_delay'] = $pconfig['dpd_delay'];
					$ph1ent['dpd_maxfail'] = $pconfig['dpd_maxfail'];
			}

			/* generate unique phase1 ikeid */
			if ($ph1ent['ikeid'] == 0) {
					$ph1ent['ikeid'] = ipsec_ikeid_next();
			}

			if (isset($p1index) && isset($a_phase1[$p1index])) {
					$a_phase1[$p1index] = $ph1ent;
			} else {
					$a_phase1[] = $ph1ent;
			}

			/* if the remote gateway changed and the interface is not WAN then remove route */
			/* the vpn_ipsec_configure() handles adding the route */
			if ($pconfig['interface'] <> "wan") {
					if ($old_ph1ent['remote-gateway'] <> $pconfig['remote-gateway']) {
							mwexec("/sbin/route delete -host {$old_ph1ent['remote-gateway']}");
					}
			}

			write_config();
			mark_subsystem_dirty('ipsec');

			header("Location: vpn_ipsec.php");
			exit;
	}
}

if (!empty($pconfig['mobile'])) {
    $pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 1"), gettext("Mobile Client"));
} else {
    $pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 1"));
}
$shortcut_section = "ipsec";

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[

<?php
    /* determine if we should init the key length */
    $keyset = '';
		if (isset($pconfig['ealgo']['keylen'])) {
		    if (is_numeric($pconfig['ealgo']['keylen'])) {
		        $keyset = $pconfig['ealgo']['keylen'];
		    }
		}
?>
$( document ).ready(function() {
	// old js code..
	myidsel_change();
	peeridsel_change();
	methodsel_change();
	ealgosel_change(<?=$keyset;?>);
	dpdchkbox_change();
});


function myidsel_change() {
	if ($("#myid_type").val() == 'myaddress') {
		$("#myid_data").removeClass('show');
		$("#myid_data").addClass('hidden');
	} else {
		$("#myid_data").removeClass('hidden');
		$("#myid_data").addClass('show');
	}
}

function peeridsel_change() {
	if ($("#peerid_type").val() == 'peeraddress') {
		$("#peerid_data").removeClass('show');
		$("#peerid_data").addClass('hidden');
	} else {
		$("#peerid_data").removeClass('hidden');
		$("#peerid_data").addClass('show');
	}
}

function methodsel_change() {
	index = document.iform.authentication_method.selectedIndex;
	value = document.iform.authentication_method.options[index].value;

	switch (value) {
	case 'eap-tls':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = '';
		document.getElementById('opt_ca').style.display = '';
		document.getElementById('opt_cert').disabled = false;
		document.getElementById('opt_ca').disabled = false;
		break;
	case 'hybrid_rsa_server':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = '';
		document.getElementById('opt_ca').style.display = '';
		document.getElementById('opt_cert').disabled = false;
		document.getElementById('opt_ca').disabled = false;
		break;
	case 'xauth_rsa_server':
	case 'rsasig':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = '';
		document.getElementById('opt_ca').style.display = '';
		document.getElementById('opt_cert').disabled = false;
		document.getElementById('opt_ca').disabled = false;
		break;
<?php if (!empty($pconfig['mobile'])) {
?>
	case 'pre_shared_key':
		document.getElementById('opt_psk').style.display = 'none';
		document.getElementById('opt_peerid').style.display = 'none';
		document.getElementById('opt_cert').style.display = 'none';
		document.getElementById('opt_ca').style.display = 'none';
		document.getElementById('opt_cert').disabled = true;
		document.getElementById('opt_ca').disabled = true;
		break;
<?php
} ?>
	default: /* psk modes*/
		document.getElementById('opt_psk').style.display = '';
		document.getElementById('opt_peerid').style.display = '';
		document.getElementById('opt_cert').style.display = 'none';
		document.getElementById('opt_ca').style.display = 'none';
		document.getElementById('opt_cert').disabled = true;
		document.getElementById('opt_ca').disabled = true;
		break;
	}
}

/* PHP generated java script for variable length keys */
function ealgosel_change(bits) {
	switch (document.iform.ealgo.selectedIndex) {
<?php
$i = 0;
foreach ($p1_ealgos as $algo => $algodata) {
    if (isset($algodata['keysel']) && is_array($algodata['keysel'])) {
        echo "		case {$i}:\n";
        echo "			document.iform.ealgo_keylen.style.visibility = 'visible';\n";
        echo "			document.iform.ealgo_keylen.options.length = 0;\n";
    //      echo "			document.iform.ealgo_keylen.options[document.iform.ealgo_keylen.options.length] = new Option( 'auto', 'auto' );\n";

        $key_hi = $algodata['keysel']['hi'];
        $key_lo = $algodata['keysel']['lo'];
        $key_step = $algodata['keysel']['step'];

        for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
            echo "			document.iform.ealgo_keylen.options[document.iform.ealgo_keylen.options.length] = new Option( '{$keylen} bits', '{$keylen}' );\n";
        }
        echo "			break;\n";
    } else {
        echo "		case {$i}:\n";
        echo "			document.iform.ealgo_keylen.style.visibility = 'hidden';\n";
        echo "			document.iform.ealgo_keylen.options.length = 0;\n";
        echo "			break;\n";
    }
    $i++;
}
?>
	}

	if( bits )
		document.iform.ealgo_keylen.value = bits;
}

function dpdchkbox_change() {
	if( document.iform.dpd_enable.checked )
		document.getElementById('opt_dpd').style.display = '';
	else
		document.getElementById('opt_dpd').style.display = 'none';

	if (!document.iform.dpd_delay.value)
		document.iform.dpd_delay.value = "10";

	if (!document.iform.dpd_maxfail.value)
		document.iform.dpd_maxfail.value = "5";
}

//]]>
</script>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
?>

			<section class="col-xs-12">
<?php
                        $tab_array = array();
                        $tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
                        $tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
                        $tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
                        $tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
                        display_top_tabs($tab_array);
?>

				<div class="tab-content content-box col-xs-12">
					<form action="vpn_ipsec_phase1.php" method="post" name="iform" id="iform">
						<div class="table-responsive">
							<table class="table table-striped">
									<tr>
										<td width="22%"><b><?=gettext("General information"); ?></b></td>
										<td width="78%" align="right">
											<small><?=gettext("full help"); ?> </small>
											<i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top"><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
										<td>
											<input name="disabled" type="checkbox" id="disabled" value="yes" <?=!empty($pconfig['disabled'])?"checked=\"checked\"":"";?> />
											<div class="hidden" for="help_for_disabled">
												<strong><?=gettext("Disable this phase1 entry"); ?></strong><br />
												<?=gettext("Set this option to disable this phase1 without " .
                                                "removing it from the list"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_iketype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key Exchange version"); ?></td>
										<td>

											<select name="iketype" class="formselect">
<?php
                      $keyexchange = array("ikev1" => "V1", "ikev2" => "V2");
                      foreach ($keyexchange as $kidx => $name) :
                        ?>
                        <option value="<?=$kidx;?>" <?= $kidx == $pconfig['iketype'] ? "selected=\"selected\"" : "";?> >
                            <?=$name;?>
                        </option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_iketype">
												<?=gettext("Select the KeyExchange Protocol version to be used. Usually known as IKEv1 or IKEv2."); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_protocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Internet Protocol"); ?></td>
										<td>
											<select name="protocol" class="formselect">
											<?php
                      $protocols = array("inet" => "IPv4", "inet6" => "IPv6");
                      foreach ($protocols as $protocol => $name) :
                      ?>
												<option value="<?=$protocol;?>"  <?=$protocol == $pconfig['protocol'] ? "selected=\"selected\"" : "";?> >
														<?=$name?>
                        </option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_protocol">
												<?=gettext("Select the Internet Protocol family from this dropdown"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td ><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
										<td>
											<select name="interface" class="formselect">
<?php
											$interfaces = get_configured_interface_with_descr();
			                $carplist = get_configured_carp_interface_list();
                      foreach ($carplist as $cif => $carpip) {
                          $interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
                      }

                      $aliaslist = get_configured_ip_aliases_list();
                      foreach ($aliaslist as $aliasip => $aliasif) {
                          $interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                      }

                      $grouplist = return_gateway_groups_array();
                      foreach ($grouplist as $name => $group) {
                          if ($group[0]['vip'] <> "") {
                              $vipif = $group[0]['vip'];
                          } else {
                              $vipif = $group[0]['int'];
                          }
                          $interfaces[$name] = "GW Group {$name}";
                      }


                      foreach ($interfaces as $iface => $ifacename) :
?>
												<option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : "" ?> >
														<?=htmlspecialchars($ifacename);?>
                        </option>
<?php									endforeach;
?>
											</select>
											<div class="hidden" for="help_for_interface">
												<?=gettext("Select the interface for the local endpoint of this phase1 entry"); ?>.
											</div>
										</td>
									</tr>
									<?php if (empty($pconfig['mobile'])) :
?>

									<tr>
										<td ><a id="help_for_remotegw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Remote gateway"); ?></td>
										<td>
											<input name="remote-gateway" type="text" class="formfld unknown" id="remotegw" size="28" value="<?=$pconfig['remote-gateway'];?>" />
											<div class="hidden" for="help_for_remotegw">
												<?=gettext("Enter the public IP address or host name of the remote gateway"); ?>
											</div>
										</td>
									</tr>
<?php						endif;
?>
									<tr>
										<td><a id="help_for_remotegw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
										<td>
											<input name="descr" type="text" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
											<div class="hidden" for="help_for_remotegw">
												<?=gettext("You may enter a description here " .
                                                "for your reference (not parsed)"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2">&nbsp;</td>
									</tr>
									<tr>
										<td colspan="2"><b><?=gettext("Phase 1 proposal (Authentication)"); ?></b></td>
									</tr>
									<tr>
										<td><a id="help_for_authmethod" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication method"); ?></td>
										<td>
											<select name="authentication_method" class="formselect" onchange="methodsel_change()">
<?php
											$p1_authentication_methods = array(
												'hybrid_rsa_server' => array( 'name' => 'Hybrid RSA + Xauth', 'mobile' => true ),
												'xauth_rsa_server' => array( 'name' => 'Mutual RSA + Xauth', 'mobile' => true ),
												'xauth_psk_server' => array( 'name' => 'Mutual PSK + Xauth', 'mobile' => true ),
												'eap-tls' => array( 'name' => 'EAP-TLS', 'mobile' => true),
												'rsasig' => array( 'name' => 'Mutual RSA', 'mobile' => false ),
												'pre_shared_key' => array( 'name' => 'Mutual PSK', 'mobile' => false ) );
                      foreach ($p1_authentication_methods as $method_type => $method_params) :
                          if (empty($pconfig['mobile']) && $method_params['mobile']) {
                              continue;
                          }
	                      ?>
													<option value="<?=$method_type;?>" <?= $method_type == $pconfig['authentication_method'] ? "selected=\"selected\"" : "";?> >
				<?=$method_params['name'];?>
                          </option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_authmethod">
												<?=gettext("Must match the setting chosen on the remote side"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_mode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Negotiation mode"); ?></td>
										<td>
											<select name="mode" class="formselect">
											<?php
                      $modes = array("main" => "Main", "aggressive" => "Aggressive");
                      foreach ($modes as $mode => $mdescr) :
?>
			<option value="<?=$mode;?>" <?= $mode == $pconfig['mode'] ? "selected=\"selected\"" : "" ;?> >
                            <?=$mdescr;?>
												</option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_mode">
												<?=gettext("Aggressive is more flexible, but less secure"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td ><i class="fa fa-info-circle text-muted"></i> <?=gettext("My identifier"); ?></td>
										<td>
											<select name="myid_type" id="myid_type" class="formselect" onchange="myidsel_change()">
<?php
											$my_identifier_list = array(
												'myaddress' => array( 'desc' => gettext('My IP address'), 'mobile' => true ),
												'address' => array( 'desc' => gettext('IP address'), 'mobile' => true ),
												'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
												'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
												'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
												'keyid tag' => array( 'desc' => gettext('KeyID tag'), 'mobile' => true ),
												'dyn_dns' => array( 'desc' => gettext('Dynamic DNS'), 'mobile' => true ));
											foreach ($my_identifier_list as $id_type => $id_params) :
?>
												<option value="<?=$id_type;?>" <?php if ($id_type == $pconfig['myid_type']) {
                                                    echo "selected=\"selected\"";
} ?>>
													<?=$id_params['desc'];?>
												</option>
											<?php
endforeach; ?>
											</select>
											<div id="myid_data">
												<input name="myid_data" type="text" size="30" value="<?=$pconfig['myid_data'];?>" />
											</div>
										</td>
									</tr>
									<tr id="opt_peerid">
										<td ><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer identifier"); ?></td>
										<td>
											<select name="peerid_type" id="peerid_type" class="formselect" onchange="peeridsel_change()">
<?php
											$peer_identifier_list = array(
												'peeraddress' => array( 'desc' => gettext('Peer IP address'), 'mobile' => false ),
												'address' => array( 'desc' => gettext('IP address'), 'mobile' => false ),
												'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
												'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
												'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
												'keyid tag' => array( 'desc' =>gettext('KeyID tag'), 'mobile' => true ));
											foreach ($peer_identifier_list as $id_type => $id_params) :
												if (!empty($pconfig['mobile']) && !$id_params['mobile']) {
													continue;
												}
?>
												<option value="<?=$id_type;?>" <?= $id_type == $pconfig['peerid_type'] ? "selected=\"selected\"" : "";?> >
				<?=$id_params['desc'];?>
												</option>
<?php								endforeach;
?>
											</select>
											<input name="peerid_data" type="text" id="peerid_data" size="30" value="<?=$pconfig['peerid_data'];?>" />
<?php if (!empty($pconfig['mobile'])) {
?>
											<small><?=gettext("NOTE: This is known as the \"group\" setting on some VPN client implementations"); ?>.</small>
										<?php
} ?>
										</td>
									</tr>
									<tr id="opt_psk">
										<td ><a id="help_for_psk" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Pre-Shared Key"); ?></td>
										<td>
											<input name="pre-shared-key" type="text" class="formfld unknown" id="pskey" size="40"
														 value="<?= $pconfig['authentication_method'] == "pre_shared_key" || $pconfig['authentication_method'] == "xauth_psk_server" ? $pconfig['pre-shared-key'] : "";?>" />
											<div class="hidden" for="help_for_psk">
												<?=gettext("Input your Pre-Shared Key string"); ?>.
											</div>
										</td>
									</tr>
									<tr id="opt_cert">
										<td ><a id="help_for_certref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("My Certificate"); ?></td>
										<td>
											<select name="certref" class="formselect">
<?php
                      if (isset($config['cert'])) :
                        foreach ($config['cert'] as $cert) :
?>
												<option value="<?=$cert['refid'];?>" <?= isset($pconfig['certref']) && $pconfig['certref'] == $cert['refid'] ? "selected=\"selected\"" : ""?>>
													<?=$cert['descr'];?>
												</option>
<?php								endforeach;
                      endif;
?>
											</select>
											<div class="hidden" for="help_for_certref">
												<?=gettext("Select a certificate previously configured in the Certificate Manager"); ?>.
											</div>
										</td>
									</tr>
									<tr id="opt_ca">
										<td><a id="help_for_caref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("My Certificate Authority"); ?></td>
										<td>
											<select name="caref" class="formselect">
											<?php
										$config__ca = isset($config['ca']) ? $config['ca'] : array();
                        foreach ($config__ca as $ca) :
                            $selected = "";
                            if ($pconfig['caref'] == $ca['refid']) {
                                $selected = "selected=\"selected\"";
                            }
                        ?>
													<option value="<?=$ca['refid'];?>" <?= isset($pconfig['caref']) && $pconfig['caref'] == $ca['refid'] ? "selected=\"selected\"":"";?>>
														<?=htmlspecialchars($ca['descr']);?>
													</option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_caref">
												<?=gettext("Select a certificate authority previously configured in the Certificate Manager"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2"><b><?=gettext("Phase 1 proposal (Algorithms)"); ?></b></td>
                  </tr>
									<tr>
										<td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Encryption algorithm"); ?></td>
										<td>
											<select name="encryption-algorithm" id="ealgo" class="formselect" onchange="ealgosel_change()">
<?php
                      foreach ($p1_ealgos as $algo => $algodata) :
                      ?>
	                      <option value="<?=$algo;?>" <?= $algo == $pconfig['encryption-algorithm']['name'] ? "selected=\"selected\"" : "" ;?>>
	                          <?=$algodata['name'];?>
	                      </option>
<?php
                      endforeach;
?>
											</select>
											<select name="ealgo_keylen" width="30" class="formselect">
											</select>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_halgo" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hash algorithm"); ?></td>
										<td>
											<select name="halgo" class="formselect">
											<?php
											$p1_halgos = array(
												'md5' => 'MD5',
												'sha1' => 'SHA1',
												'sha256' => 'SHA256',
												'sha384' => 'SHA384',
												'sha512' => 'SHA512',
												'aesxcbc' => 'AES-XCBC'
											);
											foreach ($p1_halgos as $algo => $algoname) :
?>
												<option value="<?=$algo;?>" <?= $algo == $pconfig['halgo'] ? "selected=\"selected\"" : "";?>>
													<?=$algoname;?>
												</option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_halgo">
												<?=gettext("Must match the setting chosen on the remote side"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_dhgroup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DH key group"); ?></td>
										<td>
											<select name="dhgroup" class="formselect">
<?php
											$p1_dhgroups = array(
												1  => '1 (768 bit)',
												2  => '2 (1024 bit)',
												5  => '5 (1536 bit)',
												14 => '14 (2048 bit)',
												15 => '15 (3072 bit)',
												16 => '16 (4096 bit)',
												17 => '17 (6144 bit)',
												18 => '18 (8192 bit)',
												22 => '22 (1024(sub 160) bit)',
												23 => '23 (2048(sub 224) bit)',
												24 => '24 (2048(sub 256) bit)'
											);
											foreach ($p1_dhgroups as $keygroup => $keygroupname) :
?>
												<option value="<?=$keygroup;?>" <?= $keygroup == $pconfig['dhgroup'] ? "selected=\"selected\"" : "";?>>
													<?=$keygroupname;?>
												</option>
<?php								endforeach;
?>
											</select>
											<div class="hidden" for="help_for_dhgroup">
												<?=gettext("Must match the setting chosen on the remote side"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_lifetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Lifetime"); ?></td>
										<td>
											<input name="lifetime" type="text" id="lifetime" size="20" value="<?=$pconfig['lifetime'];?>" />
											<div class="hidden" for="help_for_lifetime">
												<?=gettext("seconds"); ?>
											</div>
										</td>
									</tr>
                  <tr>
										<td colspan="2"><b><?=gettext("Advanced Options"); ?></b></td>
                  </tr>
									<tr>
										<td><a id="help_for_rekey_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Enable Rekey");?></td>
										<td>
											<input name="rekey_enable" type="checkbox" id="rekey_enable" value="yes" <?=isset($pconfig['rekey_enable']) ? "checked=\"checked\"" : ""; ?> />
											<div class="hidden" for="help_for_rekey_enable">
												<?=gettext("Whether a connection should be renegotiated when it is about to expire."); ?>
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_reauth_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Enable Reauth");?></td>
										<td>
											<input name="reauth_enable" type="checkbox" id="reauth_enable" value="yes" <?= isset($pconfig['reauth_enable']) ? "checked=\"checked\"" : "";?> />
											<div class="hidden" for="help_for_reauth_enable">
												<?=gettext("Whether rekeying of an IKE_SA should also reauthenticate the peer. In IKEv1, reauthentication is always done."); ?>
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_nat_traversal" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("NAT Traversal"); ?></td>
										<td>
											<select name="nat_traversal" class="formselect">
												<option value="off" <?= isset($pconfig['nat_traversal']) && $pconfig['nat_traversal'] == "off" ? "selected=\"selected\"" :"" ;?> >
													<?=gettext("Disable"); ?>
												</option>
												<option value="on" <?= isset($pconfig['nat_traversal']) && $pconfig['nat_traversal'] == "on" ? "selected=\"selected\"" :"" ;?> >
													<?=gettext("Enable"); ?>
												</option>
												<option value="force" <?= isset($pconfig['nat_traversal']) && $pconfig['nat_traversal'] == "force" ? "selected=\"selected\"" :"" ;?> >
													<?=gettext("Force"); ?>
												</option>
											</select>
											<div class="hidden" for="help_for_nat_traversal">
													<?=gettext("Set this option to enable the use of NAT-T (i.e. the encapsulation of ESP in UDP packets) if needed, " .
	                                                "which can help with clients that are behind restrictive firewalls"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_dpd_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Dead Peer Detection"); ?></td>
										<td>
											<input name="dpd_enable" type="checkbox" id="dpd_enable" value="yes" <?=!empty($pconfig['dpd_delay']) && !empty($pconfig['dpd_maxfail'])?"checked=\"checked\"":"";?> onclick="dpdchkbox_change()" />
											<div class="hidden" for="help_for_dpd_enable">
												<?=gettext("Enable DPD"); ?>
											</div>
											<div id="opt_dpd">
												<br />
												<input name="dpd_delay" type="text" class="formfld unknown" id="dpd_delay" size="5" value="<?=$pconfig['dpd_delay'];?>" />
												<?=gettext("seconds"); ?>
												<div class="hidden" for="help_for_dpd_enable">
													<?=gettext("Delay between requesting peer acknowledgement"); ?>.
												</div>
												<br />
												<input name="dpd_maxfail" type="text" class="formfld unknown" id="dpd_maxfail" size="5" value="<?=$pconfig['dpd_maxfail'];?>" />
												<?=gettext("retries"); ?>
												<div class="hidden" for="help_for_dpd_enable">
													<?=gettext("Number of consecutive failures allowed before disconnect"); ?>.
												</div>
											</div>
										</td>
									</tr>
									<tr>
										<td>&nbsp;</td>
										<td>
											<?php if (isset($p1index) && isset($config['ipsec']['phase1'][$p1index]) && !isset($_GET['dup'])) :
?>
											<input name="p1index" type="hidden" value="<?=$p1index;?>" />
											<?php
endif; ?>
											<?php if (!empty($pconfig['mobile'])) :
?>
											<input name="mobile" type="hidden" value="true" />
											<?php
endif; ?>
											<input name="ikeid" type="hidden" value="<?=$pconfig['ikeid'];?>" />
											<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
										</td>
									</tr>
                </tbody>
							</table>
						</div>
					</form>
				</div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc");
