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

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("services.inc");
require_once("interfaces.inc");

error_reporting(E_ALL);




$pgtitle = array(gettext("OpenVPN"), gettext("Client"));
$shortcut_section = "openvpn";

if (!isset($config['openvpn']['openvpn-client'])) {
    $config['openvpn']['openvpn-client'] = array();
}

$a_client = &$config['openvpn']['openvpn-client'];

if (!is_array($config['ca'])) {
    $config['ca'] = array();
}

$a_ca =& $config['ca'];

if (!is_array($config['cert'])) {
    $config['cert'] = array();
}

$a_cert =& $config['cert'];

if (!isset($config['crl']) || !is_array($config['crl'])) {
    $config['crl'] = array();
}

$a_crl =& $config['crl'];





$vpnid = 0;
$act = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		if (isset($_GET['id']) && is_numericint($_GET['id'])) {
		    $id = $_GET['id'];
		}
		if (isset($_GET['act'])) {
		    $act = $_GET['act'];
		}

		$pconfig = array();
		// set defaults
		$pconfig['autokey_enable'] = "yes"; // just in case the modes switch
		$pconfig['autotls_enable'] = "yes"; // just in case the modes switch
		$pconfig['tlsauth_enable'] = "yes";
		$pconfig['digest'] = "SHA1";
		$pconfig['verbosity_level'] = 1; // Default verbosity is 1

		// edit existing.
		if ($act=="edit" && isset($id) && $a_client[$id] ) {
				// 1 on 1 copy of config attributes
				$copy_fields = "auth_user,auth_pass,disable,mode,protocol,interface
				,local_port,server_addr,server_port,resolve_retry
				,proxy_addr,proxy_port,proxy_user,proxy_passwd,proxy_authtype,description
				,custom_options,ns_cert_type,dev_mode,caref,certref,crypto,digest,engine
				,tunnel_network,tunnel_networkv6,remote_network,remote_networkv6,use_shaper
				,compression,passtos,no_tun_ipv6,route_no_pull,route_no_exec,verbosity_level";

				foreach (explode(",",$copy_fields) as $fieldname) {
					$fieldname = trim($fieldname);
					if(isset($a_client[$id][$fieldname])) {
						$pconfig[$fieldname] = $a_client[$id][$fieldname];
					} elseif (!isset($pconfig[$fieldname])) {
						// initialize element
						$pconfig[$fieldname] = null;
					}
				}

				// load / convert
				if (!empty($a_client[$id]['ipaddr'])) {
            $pconfig['interface'] = $pconfig['interface'] . '|' . $a_client[$id]['ipaddr'];
        }

        if (isset($a_client[$id]['tls'])) {
            $pconfig['tls'] = base64_decode($a_client[$id]['tls']);
        } else  {
						$pconfig['tls'] = null;
				}

				if (isset($a_client[$id]['shared_key'])) {
						$pconfig['shared_key'] = base64_decode($a_client[$id]['shared_key']);
				} else {
						$pconfig['shared_key'] = null ;
				}

				if (isset($id) && $a_client[$id]) {
				    $vpnid = $a_client[$id]['vpnid'];
				}
		} elseif ($act=="new") {
				// create new
		    $pconfig['interface'] = "wan";
		    $pconfig['server_port'] = 1194;
				$init_fields = "auth_user,auth_pass,disable,mode,protocol,interface
				,local_port,server_addr,server_port,resolve_retry
				,proxy_addr,proxy_port,proxy_user,proxy_passwd,proxy_authtype,description
				,custom_options,ns_cert_type,dev_mode,caref,certref,crypto,digest,engine
				,tunnel_network,tunnel_networkv6,remote_network,remote_networkv6,use_shaper
				,compression,passtos,no_tun_ipv6,route_no_pull,route_no_exec,verbosity_level";

				foreach (explode(",",$init_fields) as $fieldname) {
					$fieldname = trim($fieldname);
					if (!isset($pconfig[$fieldname])) {
						$pconfig[$fieldname] = null;
					}
				}
		}
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['id']) && is_numericint($_POST['id'])) {
			$id = $_POST['id'];
	}
	if (isset($_POST['act'])) {
			$act = $_POST['act'];
	}

	if ($act == "del") {
			if (!isset($id) || !isset($a_client[$id])) {
					redirectHeader("vpn_openvpn_client.php");
					exit;
			}
			if (!empty($a_client[$id])) {
					openvpn_delete('client', $a_client[$id]);
			}
			unset($a_client[$id]);
			write_config();
			$savemsg = gettext("Client successfully deleted")."<br />";
	} else {
			$pconfig = $_POST;
			$input_errors = array();

	    if (isset($id) && $a_client[$id]) {
	        $vpnid = $a_client[$id]['vpnid'];
	    }
			if (isset($pconfig['mode']) && $pconfig['mode'] != "p2p_shared_key") {
	        $tls_mode = true;
	    } else {
	        $tls_mode = false;
	    }

			// generate new key
			if (!empty($pconfig['autokey_enable'])) {
	        $pconfig['shared_key'] = openvpn_create_key();
	    }

	    /* input validation */
			if (strpos($pconfig['interface'],'|') !== false) {
					list($iv_iface, $iv_ip) = explode("|", $pconfig['interface']);
			} else {
					$iv_iface = $pconfig['interface'];
					$iv_ip = null;
			}

	    if (is_ipaddrv4($iv_ip) && (stristr($pconfig['protocol'], "6") !== false)) {
	        $input_errors[] = gettext("Protocol and IP address families do not match. You cannot select an IPv6 protocol and an IPv4 IP address.");
	    } elseif (is_ipaddrv6($iv_ip) && (stristr($pconfig['protocol'], "6") === false)) {
	        $input_errors[] = gettext("Protocol and IP address families do not match. You cannot select an IPv4 protocol and an IPv6 IP address.");
	    } elseif ((stristr($pconfig['protocol'], "6") === false) && !get_interface_ip($iv_iface) && ($pconfig['interface'] != "any")) {
	        $input_errors[] = gettext("An IPv4 protocol was selected, but the selected interface has no IPv4 address.");
	    } elseif ((stristr($pconfig['protocol'], "6") !== false) && !get_interface_ipv6($iv_iface) && ($pconfig['interface'] != "any")) {
	        $input_errors[] = gettext("An IPv6 protocol was selected, but the selected interface has no IPv6 address.");
	    }
	    if (!empty($pconfig['local_port'])) {
					if (empty($pconfig['local_port']) || !is_numeric($pconfig['local_port']) || $pconfig['local_port'] < 0 || ($pconfig['local_port'] > 65535)) {
							$input_errors[] = "The field Local port must contain a valid port, ranging from 0 to 65535.";
					}
	        $portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
	        if (($portused != $vpnid) && ($portused != 0)) {
	            $input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
	        }
	    }
			if (empty($pconfig['server_addr']) || (!is_domain($pconfig['server_addr']) && !is_ipaddr($pconfig['server_addr']))) {
					$input_errors[] = gettext("The field Server host or address must contain a valid IP address or domain name.") ;
			}

			if (empty($pconfig['server_port']) || !is_numeric($pconfig['server_port']) || $pconfig['server_port'] < 0 || ($pconfig['server_port'] > 65535)) {
					$input_errors[] = "The field Server port must contain a valid port, ranging from 0 to 65535.";
			}

	    if (!empty($pconfig['proxy_addr'])) {
					if (empty($pconfig['proxy_addr']) || (!is_domain($pconfig['proxy_addr']) && !is_ipaddr($pconfig['proxy_addr']))) {
							$input_errors[] = gettext("The field Proxy host or address must contain a valid IP address or domain name.") ;
					}
					if (empty($pconfig['proxy_port']) || !is_numeric($pconfig['proxy_port']) || $pconfig['proxy_port'] < 0 || ($pconfig['proxy_port'] > 65535)) {
							$input_errors[] = "The field Proxy port must contain a valid port, ranging from 0 to 65535.";
					}
	        if (isset($pconfig['proxy_authtype']) && $pconfig['proxy_authtype'] != "none") {
	            if (empty($pconfig['proxy_user']) || empty($pconfig['proxy_passwd'])) {
	                $input_errors[] = gettext("User name and password are required for proxy with authentication.");
	            }
	        }
	    }
	    if (!empty($pconfig['tunnel_network'])) {
	        if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4")) {
	            $input_errors[] = $result;
	        }
	    }
	    if (!empty($pconfig['tunnel_networkv6'])) {
	        if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6")) {
	            $input_errors[] = $result;
	        }
	    }
	    if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
	        $input_errors[] = $result;
	    }
	    if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
	        $input_errors[] = $result;
	    }
	    if (!empty($pconfig['use_shaper']) && (!is_numeric($pconfig['use_shaper']) || ($pconfig['use_shaper'] <= 0))) {
	        $input_errors[] = gettext("The bandwidth limit must be a positive numeric value.");
	    }
	    if (!$tls_mode && empty($pconfig['autokey_enable'])) {
	        if (!strstr($pconfig['shared_key'], "-----BEGIN OpenVPN Static key V1-----") ||
	            !strstr($pconfig['shared_key'], "-----END OpenVPN Static key V1-----")) {
	            $input_errors[] = gettext("The field 'Shared Key' does not appear to be valid");
	        }
	    }
	    if ($tls_mode && !empty($pconfig['tlsauth_enable']) && empty($pconfig['autotls_enable'])) {
	        if (!strstr($pconfig['tls'], "-----BEGIN OpenVPN Static key V1-----") ||
	            !strstr($pconfig['tls'], "-----END OpenVPN Static key V1-----")) {
	            $input_errors[] = gettext("The field 'TLS Authentication Key' does not appear to be valid");
	        }
	    }

	    /* If we are not in shared key mode, then we need the CA/Cert. */
	    if (isset($pconfig['mode']) && $pconfig['mode'] != "p2p_shared_key") {
	        $reqdfields = explode(" ", "caref");
	        $reqdfieldsn = array(gettext("Certificate Authority"));
	    } elseif (empty($pconfig['autokey_enable'])) {
	        /* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
	        $reqdfields = array('shared_key');
	        $reqdfieldsn = array(gettext('Shared key'));
	    }

			do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

	    if (($pconfig['mode'] != "p2p_shared_key") && empty($pconfig['certref']) && empty($pconfig['auth_user']) && empty($pconfig['auth_pass'])) {
	        $input_errors[] = gettext("If no Client Certificate is selected, a username and password must be entered.");
	    }

	    if (count($input_errors) == 0) {
					// save data
	        $client = array();
					// 1 on 1 copy of config attributes
					$copy_fields = "auth_user,auth_pass,protocol,dev_mode,local_port
					,server_addr,server_port,resolve_retry,proxy_addr,proxy_port
					,proxy_authtype,proxy_user,proxy_passwd,description,mode,crypto,digest
					,engine,tunnel_network,tunnel_networkv6,remote_network,remote_networkv6
					,use_shaper,compression,passtos,no_tun_ipv6,route_no_pull,route_no_exec
					,verbosity_level,interface";

					foreach (explode(",",$copy_fields) as $fieldname) {
						$fieldname = trim($fieldname);
						if(isset($pconfig[$fieldname])) {
							$client[$fieldname] = $pconfig[$fieldname];
						}
					}

					// attributes containing some kind of logic
	        if ($vpnid) {
	            $client['vpnid'] = $vpnid;
	        } else {
	            $client['vpnid'] = openvpn_vpnid_next();
	        }
	        if ($_POST['disable'] == "yes") {
	            $client['disable'] = true;
	        }

					if (strpos($pconfig['interface'], "|") !== false) {
							list($client['interface'], $client['ipaddr']) = explode("|", $pconfig['interface']);
					}
	        $client['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);

	        if ($tls_mode) {
	            $client['caref'] = $pconfig['caref'];
	            $client['certref'] = $pconfig['certref'];
	            if ($pconfig['tlsauth_enable']) {
	                if ($pconfig['autotls_enable']) {
	                    $pconfig['tls'] = openvpn_create_key();
	                }
	                $client['tls'] = base64_encode($pconfig['tls']);
	            }
	        } else {
	            $client['shared_key'] = base64_encode($pconfig['shared_key']);
	        }

	        if (isset($id) && isset($a_client[$id])) {
	            $a_client[$id] = $client;
	        } else {
	            $a_client[] = $client;
	        }

	        openvpn_resync('client', $client);
	        write_config();

	        header("Location: vpn_openvpn_client.php");
	        exit;
	    }
		}
}

// escape form output before processing
legacy_html_escape_form_data($pconfig);

include("head.inc");

$main_buttons = array(
    array('href'=>'vpn_openvpn_client.php?act=new', 'label'=>gettext("add client")),
);

?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
	if (document.iform != undefined) {
		mode_change();
		autokey_change();
		tlsauth_change();
		useproxy_changed();
	}

	// link delete buttons
	$(".act_delete").click(function(){
		var id = $(this).attr("id").split('_').pop(-1);
		BootstrapDialog.show({
				type:BootstrapDialog.TYPE_INFO,
				title: "<?= gettext("OpenVPN");?>",
				message: "<?= gettext("Do you really want to delete this client?"); ?>",
				buttons: [{
								label: "<?= gettext("No");?>",
								action: function(dialogRef) {
										dialogRef.close();
								}}, {
									label: "<?= gettext("Yes");?>",
									action: function(dialogRef) {
										$.post(window.location, {act: 'del', id:id}, function(data) {
													location.reload();
										});
										dialogRef.close();
								}
						}]
		});
	});
});


function mode_change() {
	index = document.iform.mode.selectedIndex;
	value = document.iform.mode.options[index].value;
	switch(value) {
		case "p2p_tls":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("psk").style.display="none";
			break;
		case "p2p_shared_key":
			document.getElementById("tls").style.display="none";
			document.getElementById("tls_ca").style.display="none";
			document.getElementById("tls_cert").style.display="none";
			document.getElementById("psk").style.display="";
			break;
	}
}

function dev_mode_change() {
	index = document.iform.dev_mode.selectedIndex;
	value = document.iform.dev_mode.options[index].value;
	switch(value) {
		case "tun":
			document.getElementById("chkboxNoTunIPv6").style.display="";
			break;
		case "tap":
			document.getElementById("chkboxNoTunIPv6").style.display="none";
			break;
	}
}

function autokey_change() {
	if (document.iform.autokey_enable.checked)
		document.getElementById("autokey_opts").style.display="none";
	else
		document.getElementById("autokey_opts").style.display="";
}

function useproxy_changed() {

	if (jQuery('#proxy_authtype').val() != 'none') {
		jQuery('#proxy_authtype_opts').show();
	} else {
		jQuery('#proxy_authtype_opts').hide();
	}
}

function tlsauth_change() {

<?php if (empty($pconfig['tls'])) :
?>
	if (document.iform.tlsauth_enable.checked)
		document.getElementById("tlsauth_opts").style.display="";
	else
		document.getElementById("tlsauth_opts").style.display="none";
<?php
endif; ?>

	autotls_change();
}

function autotls_change() {

<?php if (empty($pconfig['tls'])) :
?>
	autocheck = document.iform.autotls_enable.checked;
<?php
else :
?>
	autocheck = false;
<?php
endif; ?>

	if (document.iform.tlsauth_enable.checked && !autocheck)
		document.getElementById("autotls_opts").style.display="";
	else
		document.getElementById("autotls_opts").style.display="none";
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
                if (isset($savemsg)) {
                    print_info_box($savemsg);
                }
?>


			    <section class="col-xs-12">
<?php
                        $tab_array = array();
                        $tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
                        $tab_array[] = array(gettext("Client"), true, "vpn_openvpn_client.php");
                        $tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
                        $tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
                                                $tab_array[] = array(gettext("Client Export"), false, "vpn_openvpn_export.php");
                                                $tab_array[] = array(gettext("Shared Key Export"), false, "vpn_openvpn_export_shared.php");
                        display_top_tabs($tab_array);
?>

					<div class="tab-content content-box col-xs-12">
							<?php if ($act=="new" || $act=="edit") :
?>
							<form action="vpn_openvpn_client.php" method="post" name="iform" id="iform">
							<div class="table-responsive">
							<table class="table table-striped table-sort">
								<tr>
									<td colspan="2" valign="top" class="listtopic"><?=gettext("General information"); ?></td>
								</tr>
								<tr>
									<td width="22%" valign="top" class="vncellreq"><a id="help_for_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a><?=gettext("Disabled"); ?></td>
									<td width="78%" class="vtable">
										<input name="disable" type="checkbox" value="yes" <?= !empty($pconfig['disable']) ? "checked=\"checked\"" : "";?> />
										<div class="hidden" for="help_for_disable">
											<small><?=gettext("Set this option to disable this client without removing it from the list"); ?>.</small>
										</div>
									</td>
								</tr>
								<tr>
									<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Mode");?></td>
									<td width="78%" class="vtable">
										<select name="mode" id="mode" class="form-control" onchange="mode_change()">
<?php
														$openvpn_client_modes = array(
															'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
															'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )") );
                            foreach ($openvpn_client_modes as $name => $desc) :
                                $selected = "";
                                if ($pconfig['mode'] == $name) {
                                    $selected = "selected=\"selected\"";
                                }
?>
                            <option value="<?=$name;?>" <?=$selected;?>><?=$desc;?></option>
<?php
                            endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Protocol");?></td>
									<td width="78%" class="vtable">
										<select name='protocol' class="form-control">
<?php
                            foreach (array("UDP", "UDP6", "TCP", "TCP6") as $prot) :
                                $selected = "";
                                if ($pconfig['protocol'] == $prot) {
                                    $selected = "selected=\"selected\"";
                                }
?>
                            <option value="<?=$prot;?>" <?=$selected;?>><?=$prot;?></option>
							<?php
                            endforeach; ?>
							</select>
							</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Device mode");?></td>
							<td width="78%" class="vtable">
							<select name='dev_mode' class="form-control" onchange="dev_mode_change()">
							<?php
                            foreach ($openvpn_dev_mode as $mode) :
                                $selected = "";
                                if ($pconfig['dev_mode'] == $mode) {
                                    $selected = "selected=\"selected\"";
                                }
                            ?>
                            <option value="<?=$mode;
?>" <?=$selected;
?>><?=$mode;?></option>
							<?php
                            endforeach; ?>
							</select>
							</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface"); ?></td>
						<td width="78%" class="vtable">
							<select name="interface" class="form-control">
								<?php
                                    $interfaces = get_configured_interface_with_descr();
                                    $carplist = get_configured_carp_interface_list();
                                foreach ($carplist as $cif => $carpip) {
                                    $interfaces[$cif.'|'.$carpip] = $carpip." (".get_vip_descr($carpip).")";
                                }
                                    $aliaslist = get_configured_ip_aliases_list();
                                foreach ($aliaslist as $aliasip => $aliasif) {
                                    $interfaces[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                                }
                                    $grouplist = return_gateway_groups_array();
                                foreach ($grouplist as $name => $group) {
                                    if ($group['ipprotocol'] != inet) {
                                        continue;
                                    }
                                    if ($group[0]['vip'] <> "") {
                                        $vipif = $group[0]['vip'];
                                    } else {
                                        $vipif = $group[0]['int'];
                                    }
                                    $interfaces[$name] = "GW Group {$name}";
                                }
                                    $interfaces['lo0'] = "Localhost";
                                    $interfaces['any'] = "any";
                                foreach ($interfaces as $iface => $ifacename) :
                                    $selected = "";
                                    if ($iface == $pconfig['interface']) {
                                        $selected = "selected=\"selected\"";
                                    }
                                ?>
                                <option value="<?=$iface;
?>" <?=$selected;?>>
                                    <?=htmlspecialchars($ifacename);?>
                                </option>
								<?php
                                endforeach; ?>
							</select> <br />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Local port");?></td>
						<td width="78%" class="vtable">
							<input name="local_port" type="text" class="form-control unknown" size="5" value="<?=htmlspecialchars($pconfig['local_port']);?>" />
							<p class="text-muted"><em><small><?=gettext("Set this option if you would like to bind to a specific port. Leave this blank or enter 0 for a random dynamic port."); ?></small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server host or address");?></td>
						<td width="78%" class="vtable">
							<input name="server_addr" type="text" class="form-control unknown" size="30" value="<?=htmlspecialchars($pconfig['server_addr']);?>" />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server port");?></td>
						<td width="78%" class="vtable">
							<input name="server_port" type="text" class="form-control unknown" size="5" value="<?=htmlspecialchars($pconfig['server_port']);?>" />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Proxy host or address");?></td>
						<td width="78%" class="vtable">
							<input name="proxy_addr" type="text" class="form-control unknown" size="30" value="<?=htmlspecialchars($pconfig['proxy_addr']);?>" />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Proxy port");?></td>
						<td width="78%" class="vtable">
							<input name="proxy_port" type="text" class="form-control unknown" size="5" value="<?=htmlspecialchars($pconfig['proxy_port']);?>" />
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Proxy authentication extra options");?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="proxy authentication">
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;<?=gettext("Authentication method"); ?> :&nbsp;
										</span>
									</td>
									<td>
										<select name="proxy_authtype" id="proxy_authtype" class="form-control select" onchange="useproxy_changed()">
											<option value="none" <?php if ($pconfig['proxy_authtype'] == "none") {
                                                echo "selected=\"selected\"";

} ?>><?=gettext("none"); ?></option>
											<option value="basic" <?php if ($pconfig['proxy_authtype'] == "basic") {
                                                echo "selected=\"selected\"";

} ?>><?=gettext("basic"); ?></option>
											<option value="ntlm" <?php if ($pconfig['proxy_authtype'] == "ntlm") {
                                                echo "selected=\"selected\"";

} ?>><?=gettext("ntlm"); ?></option>
										</select>
									</td>
								</tr>
							</table>
							<br />
							 <table border="0" cellpadding="2" cellspacing="0" id="proxy_authtype_opts" style="display:none" summary="proxy authentication options">
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;<?=gettext("Username"); ?> :&nbsp;
										</span>
									</td>
									<td>
										<input name="proxy_user" id="proxy_user" class="form-control unknown" size="20" value="<?=htmlspecialchars($pconfig['proxy_user']);?>" />
									</td>
								</tr>
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;<?=gettext("Password"); ?> :&nbsp;
										</span>
									</td>
									<td>
										<input name="proxy_passwd" id="proxy_passwd" type="password" class="form-control pwd" size="20" value="<?=htmlspecialchars($pconfig['proxy_passwd']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server host name resolution"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="server host name resolution">
								<tr>
									<td>
										<?php set_checked($pconfig['resolve_retry'], $chk); ?>
										<input name="resolve_retry" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Infinitely resolve server"); ?>
										</span>
									</td>
								</tr>
							</table>
							<p class="text-muted"><em><small><?=gettext("Continuously attempt to resolve the server host " .
                            "name. Useful when communicating with a server " .
                            "that is not permanently connected to the Internet"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Description"); ?></td>
						<td width="78%" class="vtable">
							<input name="description" type="text" class="form-control unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>" />
							<p class="text-muted"><em><small><?=gettext("You may enter a description here for your reference (not parsed)"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("User Authentication Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("User name/pass"); ?></td>
						<td width="78%" class="vtable">
							<?=gettext("Leave empty when no user name and password are needed."); ?>
							<br/>
							<table border="0" cellpadding="2" cellspacing="0" summary="user name password">
								<tr>
									<td align="right" width="25%">
									<span class="vexpl">
									&nbsp;<?=gettext("Username"); ?> :&nbsp;
									</span>
									</td>
									<td>
									<input name="auth_user" id="auth_user" class="form-control unknown" size="20" value="<?=htmlspecialchars($pconfig['auth_user']);?>" />
									</td>
								</tr>
								<tr>
									<td align="right" width="25%">
									<span class="vexpl">
									&nbsp;<?=gettext("Password"); ?> :&nbsp;
									</span>
									</td>
									<td>
									<input name="auth_pass" id="auth_pass" type="password" class="form-control pwd" size="20" value="<?=htmlspecialchars($pconfig['auth_pass']);?>" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Cryptographic Settings"); ?></td>
					</tr>
					<tr id="tls">
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("TLS Authentication"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="tls authentication">
								<tr>
									<td>
										<?php set_checked($pconfig['tlsauth_enable'], $chk); ?>
										<input name="tlsauth_enable" id="tlsauth_enable" type="checkbox" value="yes" <?=$chk;?> onclick="tlsauth_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Enable authentication of TLS packets"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php if (!$pconfig['tls']) :
?>
							<table border="0" cellpadding="2" cellspacing="0" id="tlsauth_opts" summary="tls authentication options">
								<tr>
									<td>
										<?php set_checked($pconfig['autotls_enable'], $chk); ?>
										<input name="autotls_enable" id="autotls_enable" type="checkbox" value="yes" <?=$chk;?> onclick="autotls_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Automatically generate a shared TLS authentication key"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php
endif; ?>
							<table border="0" cellpadding="2" cellspacing="0" id="autotls_opts" summary="tls authentication options">
								<tr>
									<td>
										<textarea name="tls" cols="65" rows="7" class="formpre"><?=htmlspecialchars($pconfig['tls']);?></textarea>
										<p class="text-muted"><em><small><?=gettext("Paste your shared key here"); ?>.</small></em></p>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr id="tls_ca">
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer Certificate Authority"); ?></td>
							<td width="78%" class="vtable">
							<?php if (count($a_ca)) :
?>
							<select name='caref' class="form-control">
							<?php
                            foreach ($a_ca as $ca) :
                                $selected = "";
                                if ($pconfig['caref'] == $ca['refid']) {
                                    $selected = "selected=\"selected\"";
                                }
                            ?>
                            <option value="<?=$ca['refid'];
?>" <?=$selected;
?>><?=$ca['descr'];?></option>
							<?php
                            endforeach; ?>
							</select>
							<?php
else :
?>
								<b>No Certificate Authorities defined.</b> <br />Create one under <a href="system_camanager.php">System: Certificates</a>.
							<?php
endif; ?>
							</td>
					</tr>
					<tr id="tls_cert">
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Client Certificate"); ?></td>
							<td width="78%" class="vtable">
							<select name='certref' class="form-control">
							<?php
                            foreach ($a_cert as $cert) :
                                $selected = "";
                                $caname = "";
                                $inuse = "";
                                $revoked = "";
                                $ca = lookup_ca($cert['caref']);
                                if ($ca) {
                                    $caname = " (CA: {$ca['descr']})";
                                }
                                if ($pconfig['certref'] == $cert['refid']) {
                                    $selected = "selected=\"selected\"";
                                }
                                if (cert_in_use($cert['refid'])) {
                                    $inuse = " *In Use";
                                }
                                if (is_cert_revoked($cert)) {
                                    $revoked = " *Revoked";
                                }
                            ?>
								<option value="<?=$cert['refid'];
?>" <?=$selected;
?>><?=$cert['descr'] . $caname . $inuse . $revoked;?></option>
							<?php
                            endforeach; ?>
								<option value="" <?PHP if (empty($pconfig['certref'])) {
                                    echo "selected=\"selected\"";
} ?>>None (Username and Password required)</option>
							</select>
							<?php if (!count($a_cert)) :
?>
								<b>No Certificates defined.</b> <br />Create one under <a href="system_certmanager.php">System: Certificates</a> if one is required for this connection.
							<?php
endif; ?>
						</td>
					</tr>
					<tr id="psk">
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Shared Key"); ?></td>
						<td width="78%" class="vtable">
							<?php if (!$pconfig['shared_key']) :
?>
							<table border="0" cellpadding="2" cellspacing="0" summary="shared key">
								<tr>
									<td>
										<?php set_checked($pconfig['autokey_enable'], $chk); ?>
										<input name="autokey_enable" type="checkbox" value="yes" <?=$chk;?> onclick="autokey_change()" />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Automatically generate a shared key"); ?>.
										</span>
									</td>
								</tr>
							</table>
							<?php
endif; ?>
							<table border="0" cellpadding="2" cellspacing="0" id="autokey_opts" summary="shared key options">
								<tr>
									<td>
										<textarea name="shared_key" cols="65" rows="7" class="formpre"><?=htmlspecialchars($pconfig['shared_key']);?></textarea>
										<p class="text-muted"><em><small><?=gettext("Paste your shared key here"); ?>.</small></em></p>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Encryption algorithm"); ?></td>
						<td width="78%" class="vtable">
							<select name="crypto" class="form-control">
								<?php
                                    $cipherlist = openvpn_get_cipherlist();
                                foreach ($cipherlist as $name => $desc) :
                                    $selected = "";
                                    if ($name == $pconfig['crypto']) {
                                        $selected = " selected=\"selected\"";
                                    }
                                ?>
								<option value="<?=$name;?>"<?=$selected?>>
                                <?=htmlspecialchars($desc);?>
								</option>
								<?php
                                endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Auth Digest Algorithm"); ?></td>
						<td width="78%" class="vtable">
							<select name="digest" class="form-control">
								<?php
                                    $digestlist = openvpn_get_digestlist();
                                foreach ($digestlist as $name => $desc) :
                                    $selected = "";
                                    if ($name == $pconfig['digest']) {
                                        $selected = " selected=\"selected\"";
                                    }
                                ?>
								<option value="<?=$name;?>"<?=$selected?>>
                                <?=htmlspecialchars($desc);?>
								</option>
								<?php
                                endforeach; ?>
							</select>
							<p class="text-muted"><em><small><?PHP echo gettext("NOTE: Leave this set to SHA1 unless the server is set to match. SHA1 is the default for OpenVPN."); ?></small></em></p>
						</td>
					</tr>
					<tr id="engine">
						<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hardware Crypto"); ?></td>
						<td width="78%" class="vtable">
							<select name="engine" class="form-control">
								<?php
                                    $engines = openvpn_get_engines();
                                foreach ($engines as $name => $desc) :
                                    $selected = "";
                                    if ($name == $pconfig['engine']) {
                                        $selected = " selected=\"selected\"";
                                    }
                                ?>
								<option value="<?=$name;?>"<?=$selected?>>
                                <?=htmlspecialchars($desc);?>
								</option>
								<?php
                                endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Tunnel Settings"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv4 Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_network" type="text" class="form-control unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_network']);?>" />
							<p class="text-muted"><em><small><?=gettext("This is the virtual network used for private " .
                            "communications between this client and the " .
                            "server expressed using CIDR (eg. 10.0.8.0/24). " .
                            "The first network address is assumed to be the " .
                            "server address and the second network address " .
                            "will be assigned to the client virtual " .
                            "interface"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv6 Tunnel Network"); ?></td>
						<td width="78%" class="vtable">
							<input name="tunnel_networkv6" type="text" class="form-control unknown" size="20" value="<?=htmlspecialchars($pconfig['tunnel_networkv6']);?>" />
							<p class="text-muted"><em><small><?=gettext("This is the IPv6 virtual network used for private " .
                            "communications between this client and the " .
                            "server expressed using CIDR (eg. fe80::/64). " .
                            "The first network address is assumed to be the " .
                            "server address and the second network address " .
                            "will be assigned to the client virtual " .
                            "interface"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv4 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_network" type="text" class="form-control unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_network']);?>" />
							<p class="text-muted"><em><small><?=gettext("These are the IPv4 networks that will be routed through " .
                            "the tunnel, so that a site-to-site VPN can be " .
                            "established without manually changing the routing tables. " .
                            "Expressed as a comma-separated list of one or more CIDR ranges. " .
                            "If this is a site-to-site VPN, enter the " .
                            "remote LAN/s here. You may leave this blank to " .
                            "only communicate with other clients"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv6 Remote Network/s"); ?></td>
						<td width="78%" class="vtable">
							<input name="remote_networkv6" type="text" class="form-control unknown" size="40" value="<?=htmlspecialchars($pconfig['remote_networkv6']);?>" />
							<p class="text-muted"><em><small><?=gettext("These are the IPv6 networks that will be routed through " .
                            "the tunnel, so that a site-to-site VPN can be " .
                            "established without manually changing the routing tables. " .
                            "Expressed as a comma-separated list of one or more IP/PREFIX. " .
                            "If this is a site-to-site VPN, enter the " .
                            "remote LAN/s here. You may leave this blank to " .
                            "only communicate with other clients"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Limit outgoing bandwidth");?></td>
						<td width="78%" class="vtable">
							<input name="use_shaper" type="text" class="form-control unknown" size="5" value="<?=htmlspecialchars($pconfig['use_shaper']);?>" />
							<p class="text-muted"><em><small><?=gettext("Maximum outgoing bandwidth for this tunnel. " .
                            "Leave empty for no limit. The input value has " .
                            "to be something between 100 bytes/sec and 100 " .
                            "Mbytes/sec (entered as bytes per second)"); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Compression"); ?></td>
						<td width="78%" class="vtable">
							<select name="compression" class="form-control">
								<?php
                                foreach ($openvpn_compression_modes as $cmode => $cmodedesc) :
                                    $selected = "";
                                    if ($cmode == $pconfig['compression']) {
                                        $selected = " selected=\"selected\"";
                                    }
                                ?>
								<option value="<?= $cmode ?>" <?= $selected ?>><?= $cmodedesc ?></option>
								<?php
                                endforeach; ?>
							</select>
							<p class="text-muted"><em><small><?=gettext("Compress tunnel packets using the LZO algorithm. Adaptive compression will dynamically disable compression for a period of time if OpenVPN detects that the data in the packets is not being compressed efficiently."); ?>.</small></em></p>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type-of-Service"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="type-of-service">
								<tr>
									<td>
										<?php set_checked($pconfig['passtos'], $chk); ?>
										<input name="passtos" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Set the TOS IP header value of tunnel packets to match the encapsulated packet value"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="chkboxNoTunIPv6">
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Disable IPv6"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="disable-ipv6">
								<tr>
									<td>
										<?php set_checked($pconfig['no_tun_ipv6'], $chk); ?>
										<input name="no_tun_ipv6" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Don't forward IPv6 traffic"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="chkboxRouteNoPull">
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Don't pull routes"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="dont-pull-routes">
								<tr>
									<td>
										<?php set_checked($pconfig['route_no_pull'], $chk); ?>
										<input name="route_no_pull" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("Don't add or remove routes automatically. Instead pass routes to ");
?> <strong>--route-up</strong> <?=gettext("script using environmental variables"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="chkboxRouteNoExec">
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Don't add/remove routes"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="dont-exec-routes">
								<tr>
									<td>
										<?php set_checked($pconfig['route_no_exec'], $chk); ?>
										<input name="route_no_exec" type="checkbox" value="yes" <?=$chk;?> />
									</td>
									<td>
										<span class="vexpl">
											<?=gettext("This option effectively bars the server from adding routes to the client's routing table, however note that this option still allows the server to set the TCP/IP properties of the client's TUN/TAP interface"); ?>.
										</span>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>

				<table width="100%" border="0" cellpadding="6" cellspacing="0" id="client_opts" summary="advance configuration">
					<tr>
						<td colspan="2" class="list" height="12"></td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced configuration"); ?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced"); ?></td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="advance configuration">
								<tr>
									<td>
										<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=htmlspecialchars($pconfig['custom_options']);?></textarea><br />
										<p class="text-muted"><em><small><?=gettext("Enter any additional options you would like to add to the OpenVPN client configuration here, separated by a semicolon"); ?><br />
										<?=gettext("EXAMPLE:"); ?> <strong>remote server.mysite.com 1194;</strong> or <strong>remote 1.2.3.4 1194;</strong></small></em></p>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<tr id="comboboxVerbosityLevel">
							<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Verbosity level");?></td>
							<td width="78%" class="vtable">
							<select name="verbosity_level" class="form-control">
							<?php
                            foreach ($openvpn_verbosity_level as $verb_value => $verb_desc) :
                                $selected = "";
                                if ($pconfig['verbosity_level'] == $verb_value) {
                                    $selected = "selected=\"selected\"";
                                }
                            ?>
                            <option value="<?=$verb_value;
?>" <?=$selected;
?>><?=$verb_desc;?></option>
							<?php
                            endforeach; ?>
							</select>
							<p class="text-muted"><em><small><?=gettext("Each level shows all info from the previous levels. Level 3 is recommended if you want a good summary of what's happening without being swamped by output"); ?>.<br /> <br />
							<strong>none</strong> -- <?=gettext("No output except fatal errors"); ?>. <br />
							<strong>default</strong>-<strong>4</strong> -- <?=gettext("Normal usage range"); ?>. <br />
							<strong>5</strong> -- <?=gettext("Output R and W characters to the console for each packet read and write, uppercase is used for TCP/UDP packets and lowercase is used for TUN/TAP packets"); ?>. <br />
							<strong>6</strong>-<strong>11</strong> -- <?=gettext("Debug info range"); ?>.</small></em></p>
							</td>
					</tr>

				</table>

				<br />
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="icons">
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							<input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
							<input name="act" type="hidden" value="<?=$act;?>" />
							<?php if (isset($id) && $a_client[$id]) :
?>
							<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
							<?php
endif; ?>
						</td>
					</tr>
				</table>
							 </div>
							</form>

			<?php
else :
?>

			<table class="table table-striped">
				<thead>
				<tr>
					<td width="10%" class="listhdrr"><?=gettext("Disabled"); ?></td>
					<td width="10%" class="listhdrr"><?=gettext("Protocol"); ?></td>
					<td width="30%" class="listhdrr"><?=gettext("Server"); ?></td>
					<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
					<td width="10%" class="list"></td>
				</tr>
				</thead>

				<tbody>
				<?php
                    $i = 0;
                foreach ($a_client as $client) :
                    $disabled = "NO";
                    if (isset($client['disable'])) {
                        $disabled = "YES";
                    }
                    $server = "{$client['server_addr']}:{$client['server_port']}";
                ?>
				<tr ondblclick="document.location='vpn_openvpn_client.php?act=edit&amp;id=<?=$i;?>'">
                <td class="listlr">
                    <?=$disabled;?>
                </td>
                <td class="listr">
                    <?=htmlspecialchars($client['protocol']);?>
                </td>
                <td class="listr">
                    <?=htmlspecialchars($server);?>
                </td>
                <td class="listbg">
                    <?=htmlspecialchars($client['description']);?>
                </td>
                <td valign="middle" class="list nowrap">
                    <a href="vpn_openvpn_client.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
										<a id="del_<?=$i;?>" title="<?=gettext("delete client"); ?>" class="act_delete btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>

                </td>
				</tr>
				<?php
                $i++;
                endforeach;
                ?>
				</tbody>
			</table>

			<?php
endif; ?>

					</div>
			    </section>
			</div>
		</div>
	</section>






<?php include("foot.inc"); ?>


<?php

/* local utility functions */

function set_checked($var, & $chk)
{
    if ($var) {
        $chk = "checked=\"checked\"";
    } else {
        $chk = "";
    }
}
