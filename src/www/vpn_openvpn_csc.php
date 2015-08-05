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


$pgtitle = array(gettext("OpenVPN"), gettext("Client Specific Override"));
$shortcut_section = "openvpn";

// define all fields used in this form
$all_form_fields = "custom_options,disable,common_name,block,description
,tunnel_network,local_network,local_networkv6,remote_network
,remote_networkv6,gwredir,push_reset,dns_domain,dns_server1
,dns_server2,dns_server3,dns_server4,ntp_server1,ntp_server2
,netbios_enable,netbios_ntype,netbios_scope,wins_server1
,wins_server2";

// read config.
if (!isset($config['openvpn']['openvpn-csc'])) {
    $config['openvpn']['openvpn-csc'] = array();
}
$a_csc = &$config['openvpn']['openvpn-csc'];

$vpnid = 0;
$act=null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		$pconfig = array();
		if (isset($_GET['act'])) {
				$act = $_GET['act'];
		}
		if (isset($_GET['id']) && is_numericint($_GET['id'])) {
		    $id = $_GET['id'];
		}

		if ($act=="edit" && isset($id) && isset($a_csc[$id])) {
				// 1 on 1 copy of config attributes
				foreach (explode(",",$all_form_fields) as $fieldname) {
					$fieldname = trim($fieldname);
					if(isset($a_csc[$id][$fieldname])) {
						$pconfig[$fieldname] = $a_csc[$id][$fieldname];
					} elseif (!isset($pconfig[$fieldname])) {
						// initialize element
						$pconfig[$fieldname] = null;
					}
				}
		} else {
				// init all form attributes
				foreach (explode(",",$all_form_fields) as $fieldname) {
						$fieldname = trim($fieldname);
						if (!isset($pconfig[$fieldname])) {
								$pconfig[$fieldname] = null;
						}
				}
		}
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$input_errors = array();
		$pconfig = $_POST;
		if (isset($_POST['act'])) {
		    $act = $_POST['act'];
		}
		if (isset($_POST['id']) && is_numericint($_POST['id'])) {
		    $id = $_POST['id'];
		}

		if ($act == "del") {
		    if (!isset($a_csc[$id])) {
		        redirectHeader("vpn_openvpn_csc.php");
		        exit;
		    }

				@unlink("/var/etc/openvpn-csc/{$a_csc[$id]['common_name']}");
		    unset($a_csc[$id]);
		    write_config();
		} else {
				/* perform validations */
		    if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'Tunnel network')) {
		        $input_errors[] = $result;
		    }
		    if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4")) {
		        $input_errors[] = $result;
		    }
		    if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6")) {
		        $input_errors[] = $result;
		    }
		    if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
		        $input_errors[] = $result;
		    }
		    if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
		        $input_errors[] = $result;
		    }

		    if (!empty($pconfig['dns_server_enable'])) {
		        if (!empty($pconfig['dns_server1']) && !is_ipaddr(trim($pconfig['dns_server1']))) {
		            $input_errors[] = gettext("The field 'DNS Server #1' must contain a valid IP address");
		        }
		        if (!empty($pconfig['dns_server2']) && !is_ipaddr(trim($pconfig['dns_server2']))) {
		            $input_errors[] = gettext("The field 'DNS Server #2' must contain a valid IP address");
		        }
		        if (!empty($pconfig['dns_server3']) && !is_ipaddr(trim($pconfig['dns_server3']))) {
		            $input_errors[] = gettext("The field 'DNS Server #3' must contain a valid IP address");
		        }
		        if (!empty($pconfig['dns_server4']) && !is_ipaddr(trim($pconfig['dns_server4']))) {
		            $input_errors[] = gettext("The field 'DNS Server #4' must contain a valid IP address");
		        }
		    }

		    if (!empty($pconfig['ntp_server_enable'])) {
		        if (!empty($pconfig['ntp_server1']) && !is_ipaddr(trim($pconfig['ntp_server1']))) {
		            $input_errors[] = gettext("The field 'NTP Server #1' must contain a valid IP address");
		        }
		        if (!empty($pconfig['ntp_server2']) && !is_ipaddr(trim($pconfig['ntp_server2']))) {
		            $input_errors[] = gettext("The field 'NTP Server #2' must contain a valid IP address");
		        }
		        if (!empty($pconfig['ntp_server3']) && !is_ipaddr(trim($pconfig['ntp_server3']))) {
		            $input_errors[] = gettext("The field 'NTP Server #3' must contain a valid IP address");
		        }
		        if (!empty($pconfig['ntp_server4']) && !is_ipaddr(trim($pconfig['ntp_server4']))) {
		            $input_errors[] = gettext("The field 'NTP Server #4' must contain a valid IP address");
		        }
		    }

		    if (!empty($pconfig['netbios_enable'])) {
		        if ($pconfig['wins_server_enable']) {
		            if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1']))) {
		                $input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
		            }
		            if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2']))) {
		                $input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
		            }
		        }
		    }

		    $reqdfields[] = 'common_name';
		    $reqdfieldsn[] = 'Common name';

		    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

		    if (count($input_errors) == 0) {
		        $csc = array();
						// 1 on 1 copy of config attributes
						foreach (explode(",",$all_form_fields) as $fieldname) {
								$fieldname = trim($fieldname);
								if(isset($pconfig[$fieldname])) {
										$csc[$fieldname] = $pconfig[$fieldname];
								}
						}

						// handle fields with some kind of logic
						if (!empty($pconfig['disable']) && $pconfig['disable'] == "yes") {
		            $csc['disable'] = true;
		        }

		        if (isset($id) && $a_csc[$id]) {
		            $old_csc_cn = $a_csc[$id]['common_name'];
		            $a_csc[$id] = $csc;
		        } else {
		            $a_csc[] = $csc;
		        }

		        if (!empty($old_csc_cn)) {
								@unlink('/var/etc/openvpn-csc/' . basename($old_csc_cn));
		        }
		        openvpn_resync_csc($csc);
		        write_config();

		        header("Location: vpn_openvpn_csc.php");
		        exit;
		    }
		}
}

// escape form output before processing
legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>

<script type="text/javascript">
//<![CDATA[

$( document ).ready(function() {
	// link delete buttons
	$(".act_delete").click(function(){
		var id = $(this).attr("id").split('_').pop(-1);
		BootstrapDialog.show({
				type:BootstrapDialog.TYPE_INFO,
				title: "<?= gettext("OpenVPN");?>",
				message: "<?= gettext("Do you really want to delete this csc?"); ?>",
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
	// init form (old stuff)
	if (document.iform != undefined) {
		dns_domain_change();
		dns_server_change();
		wins_server_change();
		ntp_server_change();
		netbios_change();
	}

});


function dns_domain_change() {

	if (document.iform.dns_domain_enable.checked)
		document.getElementById("dns_domain_data").style.display="";
	else
		document.getElementById("dns_domain_data").style.display="none";
}

function dns_server_change() {

	if (document.iform.dns_server_enable.checked)
		document.getElementById("dns_server_data").style.display="";
	else
		document.getElementById("dns_server_data").style.display="none";
}

function wins_server_change() {

	if (document.iform.wins_server_enable.checked)
		document.getElementById("wins_server_data").style.display="";
	else
		document.getElementById("wins_server_data").style.display="none";
}

function ntp_server_change() {

	if (document.iform.ntp_server_enable.checked)
		document.getElementById("ntp_server_data").style.display="";
	else
		document.getElementById("ntp_server_data").style.display="none";
}

function netbios_change() {

	if (document.iform.netbios_enable.checked) {
		document.getElementById("netbios_data").style.display="";
		document.getElementById("wins_opts").style.display="";
	} else {
		document.getElementById("netbios_data").style.display="none";
		document.getElementById("wins_opts").style.display="none";
	}
}

//]]>
</script>

<?

if ($act!="new" && $act!="edit") {
    $main_buttons = array(
        array('href'=>'vpn_openvpn_csc.php?act=new', 'label'=>gettext("add csc")),
    );
}

?>

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

				<?php
                        $tab_array = array();
                        $tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
                        $tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
                        $tab_array[] = array(gettext("Client Specific Overrides"), true, "vpn_openvpn_csc.php");
                        $tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
                                                $tab_array[] = array(gettext("Client Export"), false, "vpn_openvpn_export.php");
                                                $tab_array[] = array(gettext("Shared Key Export"), false, "vpn_openvpn_export_shared.php");
                        display_top_tabs($tab_array);
                    ?>

					<div class="tab-content content-box col-xs-12">

							<?php if ($act=="new" || $act=="edit") :
?>
							<form action="vpn_openvpn_csc.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
							 <div class="table-responsive">
								<table class="table table-striped table-sort">
									<tr>
										<td width="22%"><?=gettext("General information"); ?></td>
										<td width="78%" align="right">
											<small><?=gettext("full help"); ?> </small>
											<i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_opnvpn_server" type="button"></i></a>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><a id="help_for_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
										<td width="78%" class="vtable">
											<input name="disable" type="checkbox" value="yes" <?= !empty($pconfig['disable']) ? "checked=\"checked\"" : "";?> />
											<div class="hidden" for="help_for_disable">
												<?=gettext("Set this option to disable this client-specific override without removing it from the list"); ?>
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><a id="help_for_common_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Common name"); ?></td>
										<td width="78%" class="vtable">
											<input name="common_name" type="text" class="formfld unknown" size="30" value="<?=$pconfig['common_name'];?>" />
											<div class="hidden" for="help_for_common_name">
												<?=gettext("Enter the client's X.509 common name here"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
										<td width="78%" class="vtable">
											<input name="description" type="text" class="formfld unknown" size="30" value="<?=$pconfig['description'];?>" />
											<div class="hidden" for="help_for_description">
											 	<?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_block" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Connection blocking"); ?></td>
										<td width="78%" class="vtable">
											<input name="block" type="checkbox" value="yes" <?= !empty($pconfig['block']) ? "checked=\"checked\"" : "";?> />
											<div class="hidden" for="help_for_block">
													<?=gettext("Block this client connection based on its common name"); ?>.<br/>
													<?=gettext("Don't use this option to permanently disable a " .
	                                                 "client due to a compromised key or password. " .
	                                                 "Use a CRL (certificate revocation list) instead"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2" class="list" height="12"></td>
									</tr>
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Tunnel Settings"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_tunnel_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Tunnel Network"); ?></td>
										<td width="78%" class="vtable">
											<input name="tunnel_network" type="text" class="formfld unknown" size="20" value="<?=$pconfig['tunnel_network'];?>" />
											<div class="hidden" for="help_for_tunnel_network">
												<?=gettext("This is the virtual network used for private " .
												"communications between this client and the " .
												"server expressed using CIDR (eg. 10.0.8.0/24). " .
												"The first network address is assumed to be the " .
												"server address and the second network address " .
												"will be assigned to the client virtual " .
												"interface"); ?>.
											</div>
										</td>
									</tr>
									<tr id="local_optsv4">
										<td width="22%" valign="top" class="vncell"><a id="help_for_local_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Local Network/s"); ?></td>
										<td width="78%" class="vtable">
											<input name="local_network" type="text" class="formfld unknown" size="40" value="<?=$pconfig['local_network'];?>" />
											<div class="hidden" for="help_for_local_network">
												<?=gettext("These are the IPv4 networks that will be accessible " .
												"from this particular client. Expressed as a comma-separated list of one or more CIDR ranges."); ?>
 											<br /><?=gettext("NOTE: You do not need to specify networks here if they have " .
											"already been defined on the main server configuration.");?>
											</div>
										</td>
									</tr>
									<tr id="local_optsv6">
										<td width="22%" valign="top" class="vncell"><a id="help_for_local_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Local Network/s"); ?></td>
										<td width="78%" class="vtable">
											<input name="local_networkv6" type="text" class="formfld unknown" size="40" value="<?=$pconfig['local_networkv6'];?>" />
											<div class="hidden" for="help_for_local_networkv6">
												 <?=gettext("These are the IPv6 networks that will be accessible " .
												 "from this particular client. Expressed as a comma-separated list of one or more IP/PREFIX networks."); ?><br />
												 <?=gettext("NOTE: You do not need to specify networks here if they have " .
												 "already been defined on the main server configuration.");?>
											</div>
										</td>
									</tr>
									<tr id="remote_optsv4">
										<td width="22%" valign="top" class="vncell"><a id="help_for_remote_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Remote Network/s"); ?></td>
										<td width="78%" class="vtable">
											<input name="remote_network" type="text" class="formfld unknown" size="40" value="<?=$pconfig['remote_network'];?>" />
											<div class="hidden" for="help_for_remote_network">
												<?=gettext("These are the IPv4 networks that will be routed " .
												"to this client specifically using iroute, so that a site-to-site " .
												"VPN can be established. " .
												"Expressed as a comma-separated list of one or more CIDR ranges. " .
												"You may leave this blank if there are no client-side networks to " .
												"be routed"); ?>.<br />
												<?=gettext("NOTE: Remember to add these subnets to the " .
												"IPv4 Remote Networks list on the corresponding OpenVPN server settings.");?>
											</div>
										</td>
									</tr>
									<tr id="remote_optsv6">
										<td width="22%" valign="top" class="vncell"><a id="help_for_remote_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Remote Network/s"); ?></td>
										<td width="78%" class="vtable">
											<input name="remote_networkv6" type="text" class="formfld unknown" size="40" value="<?=$pconfig['remote_networkv6'];?>" />
											<div class="hidden" for="help_for_remote_networkv6">
												<?=gettext("These are the IPv6 networks that will be routed " .
												"to this client specifically using iroute, so that a site-to-site " .
												"VPN can be established. " .
												"Expressed as a comma-separated list of one or more IP/PREFIX networks. " .
												"You may leave this blank if there are no client-side networks to " .
												"be routed"); ?>.<br />
												<?=gettext("NOTE: Remember to add these subnets to the " .
												"IPv6 Remote Networks list on the corresponding OpenVPN server settings.");?>
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_gwredir" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect Gateway"); ?></td>
										<td width="78%" class="vtable">
											<input name="gwredir" type="checkbox" value="yes" <?= !empty($pconfig['gwredir']) ? "checked=\"checked\"" : "";?> />
											<div class="hidden" for="help_for_gwredir">
												<?=gettext("Force all client generated traffic through the tunnel"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2" class="list" height="12"></td>
									</tr>
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Client Settings"); ?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_push_reset" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Server Definitions"); ?></td>
										<td width="78%" class="vtable">
											<input name="push_reset" type="checkbox" value="yes" <?= !empty($pconfig['push_reset']) ? "checked=\"checked\"" : "";?> />
											<div class="hidden" for="help_for_push_reset">
													<?=gettext("Prevent this client from receiving any server-defined client settings"); ?>.
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_dns_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Default Domain"); ?></td>
										<td width="78%" class="vtable">
											<input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?= !empty($pconfig['dns_domain']) ? "checked=\"checked\"" : "";?> onclick="dns_domain_change()" />
											<div id="dns_domain_data">
												<input name="dns_domain" type="text" class="formfld unknown" id="dns_domain" size="30" value="<?=$pconfig['dns_domain'];?>" />
											</div>
											<div class="hidden" for="help_for_dns_domain">
												<?=gettext("Provide a default domain name to clients"); ?><br />
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_dns_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Servers"); ?></td>
										<td width="78%" class="vtable">
											<input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=!empty($pconfig['dns_server1']) || !empty($pconfig['dns_server2']) || !empty($pconfig['dns_server3']) || !empty($pconfig['dns_server4']) ? "checked=\"checked\"" : "" ;?> onclick="dns_server_change()" />
											<div id="dns_server_data">
												<?=gettext("Server"); ?> #1:&nbsp;
												<input name="dns_server1" type="text" class="formfld unknown" id="dns_server1" size="20" value="<?=htmlspecialchars($pconfig['dns_server1']);?>" />
												<?=gettext("Server"); ?> #2:&nbsp;
												<input name="dns_server2" type="text" class="formfld unknown" id="dns_server2" size="20" value="<?=htmlspecialchars($pconfig['dns_server2']);?>" />
												<?=gettext("Server"); ?> #3:&nbsp;
												<input name="dns_server3" type="text" class="formfld unknown" id="dns_server3" size="20" value="<?=htmlspecialchars($pconfig['dns_server3']);?>" />
												<?=gettext("Server"); ?> #4:&nbsp;
												<input name="dns_server4" type="text" class="formfld unknown" id="dns_server4" size="20" value="<?=htmlspecialchars($pconfig['dns_server4']);?>" />
											</div>
											<div class="hidden" for="help_for_dns_server">
												<?=gettext("Provide a DNS server list to clients"); ?>
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_ntp_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NTP Servers"); ?></td>
										<td width="78%" class="vtable">
											<input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=!empty($pconfig['ntp_server1']) || !empty($pconfig['ntp_server2']) ? "checked=\"checked\"" : "" ;?> onclick="ntp_server_change()" />
											<div id="ntp_server_data">
												<?=gettext("Server"); ?> #1:&nbsp;
												<input name="ntp_server1" type="text" class="formfld unknown" id="ntp_server1" size="20" value="<?=$pconfig['ntp_server1'];?>" />
												<?=gettext("Server"); ?> #2:&nbsp;
												<input name="ntp_server2" type="text" class="formfld unknown" id="ntp_server2" size="20" value="<?=$pconfig['ntp_server2'];?>" />
											</div>
											<div class="hidden" for="help_for_ntp_server">
												<?=gettext("Provide a NTP server list to clients"); ?>
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_netbios_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NetBIOS Options"); ?></td>
										<td width="78%" class="vtable">
											<input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=!empty($pconfig['netbios_enable']) ? "checked=\"checked\"" : "" ;?> onclick="netbios_change()" />
											<?=gettext("Enable NetBIOS over TCP/IP");?>
											<div class="hidden" for="help_for_netbios_enable">
												<?=gettext("If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled"); ?>.
											</div>

											<div id="netbios_data">
												<?=gettext("Node Type"); ?>:&nbsp;
												<select name='netbios_ntype' class="formselect">
												<?php
																										foreach ($netbios_nodetypes as $type => $name) :
																												$selected = "";
																												if ($pconfig['netbios_ntype'] == $type) {
																														$selected = "selected=\"selected\"";
																												}
																										?>
																										<option value="<?=$type;
?>" <?=$selected;
?>><?=$name;?></option>
												<?php
																										endforeach; ?>
												</select>

												<div class="hidden" for="help_for_netbios_enable">
													<?=gettext("Possible options: b-node (broadcasts), p-node " .
													"(point-to-point name queries to a WINS server), " .
													"m-node (broadcast then query name server), and " .
													"h-node (query name server, then broadcast)"); ?>.
												</div>
												Scope ID:&nbsp;
												<input name="netbios_scope" type="text" class="formfld unknown" id="netbios_scope" size="30" value="<?=$pconfig['netbios_scope'];?>" />
												<div class="hidden" for="help_for_netbios_enable">
													<?=gettext("A NetBIOS Scope	ID provides an extended naming " .
													"service for	NetBIOS over TCP/IP. The NetBIOS " .
													"scope ID isolates NetBIOS traffic on a single " .
													"network to only those nodes with the same " .
													"NetBIOS scope ID"); ?>.
												</div>
											</div>
										</td>
									</tr>
									<tr id="wins_opts">
										<td width="22%" valign="top" class="vncell"><a id="help_for_wins_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WINS Servers"); ?></td>
										<td width="78%" class="vtable">
											<input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes"  <?=!empty($pconfig['wins_server1']) || !empty($pconfig['wins_server2']) ? "checked=\"checked\"" : "" ;?> onclick="wins_server_change()" />
											<div id="wins_server_data">
												<?=gettext("Server"); ?> #1:
												<input name="wins_server1" type="text" class="formfld unknown" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>" />
												<?=gettext("Server"); ?> #2:
												<input name="wins_server2" type="text" class="formfld unknown" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>" />
											</div>
											<div class="hidden" for="help_for_wins_server">
												<?=gettext("Provide a WINS server list to clients"); ?>
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><a id="help_for_custom_options" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Advanced"); ?></td>
										<td width="78%" class="vtable">
											<textarea rows="6" cols="70" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea>
											<div class="hidden" for="help_for_custom_options">
												<?=gettext("Enter any additional options you would like to add for this client specific override, separated by a semicolon"); ?><br />
												<?=gettext("EXAMPLE: push \"route 10.0.0.0 255.255.255.0\""); ?>;
											</div>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top">&nbsp;</td>
										<td width="78%">
											<input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
											<input name="act" type="hidden" value="<?=$act;?>" />
											<?php if (isset($id) && $a_csc[$id]) :
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

							<div class="table-responsive">
								<table class="table table-striped">

									<tr>
										<td width="10%" class="listhdrr"><?=gettext("Disabled"); ?></td>
										<td width="40%" class="listhdrr"><?=gettext("Common Name"); ?></td>
										<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
										<td width="10%" class="list"></td>
									</tr>
									<?php
                                        $i = 0;
                                    foreach ($a_csc as $csc) :
                                        $disabled = "NO";
                                        if (isset($csc['disable'])) {
                                            $disabled = "YES";
                                        }
                                    ?>
									<tr ondblclick="document.location='vpn_openvpn_csc.php?act=edit&amp;id=<?=$i;?>'">
                                    <td class="listlr">
                                        <?=$disabled;?>
                                    </td>
                                    <td class="listr">
                                        <?=htmlspecialchars($csc['common_name']);?>
                                    </td>
                                    <td class="listbg">
                                        <?=htmlspecialchars($csc['description']);?>
                                    </td>
                                    <td valign="middle" class="list nowrap">
																			<a href="vpn_openvpn_csc.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
																			<a id="del_<?=$i;?>" title="<?=gettext("delete csc"); ?>" class="act_delete btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
                                    </td>
									</tr>
									<?php
                                    $i++;
                                    endforeach;
                                    ?>

									<tr>
										<td colspan="4">
											<p>
												<?=gettext("Additional OpenVPN client specific overrides can be added here.");?>
											</p>
										</td>
									</tr>
								</table>
							</div>
				    <?php
endif; ?>

					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
