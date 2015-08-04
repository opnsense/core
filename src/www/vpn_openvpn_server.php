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

if (!isset($config['openvpn']['openvpn-server'])) {
    $config['openvpn']['openvpn-server'] = array();
}
$a_server = &$config['openvpn']['openvpn-server'];

$act = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	// fetch id if provided
	if (isset($_GET['id']) && is_numericint($_GET['id'])) {
	    $id = $_GET['id'];
	}
	if (isset($_GET['act'])) {
			$act = $_GET['act'];
	}
	$pconfig = array();
	// defaults
	$vpnid = 0;
	$pconfig['verbosity_level'] = 1;
	$pconfig['digest'] = "SHA1"; // OpenVPN Defaults to SHA1 if unset
	$pconfig['autokey_enable'] = "yes";
	$pconfig['autotls_enable'] = "yes";
	$pconfig['tlsauth_enable'] = "yes";
	if ($act == "edit" && isset($id) && isset($a_server[$id])) {
			if ($a_server[$id]['mode'] != "p2p_shared_key") {
					$pconfig['cert_depth'] = 1;
			}

			// 1 on 1 copy of config attributes
			$copy_fields = "mode,protocol,authmode,dev_mode,interface,local_port
			,description,custom_options,crypto,engine,tunnel_network
			,tunnel_networkv6,remote_network,remote_networkv6,gwredir,local_network
			,local_networkv6,maxclients,compression,passtos,client2client
			,dynamic_ip,pool_enable,topology_subnet,serverbridge_dhcp
			,serverbridge_interface,serverbridge_dhcp_start,serverbridge_dhcp_end
			,dns_server1,dns_server2,dns_server3,dns_server4,ntp_server1
			,ntp_server2,netbios_enable,netbios_ntype,netbios_scope,wins_server1
			,wins_server2,no_tun_ipv6,push_register_dns,dns_domain,nbdd_server1
			,client_mgmt_port,verbosity_level,caref,crlref,certref,dh_length
			,cert_depth,strictusercn,digest,disable,duplicate_cn,vpnid";

			foreach (explode(",",$copy_fields) as $fieldname) {
				$fieldname = trim($fieldname);
				if(isset($a_server[$id][$fieldname])) {
					$pconfig[$fieldname] = $a_server[$id][$fieldname];
				} elseif (!isset($pconfig[$fieldname])) {
					// initialize element
					$pconfig[$fieldname] = null;
				}
			}

			// load / convert
			if (!empty($a_server[$id]['ipaddr'])) {
          $pconfig['interface'] = $pconfig['interface'] . '|' . $a_server[$id]['ipaddr'];
      }
			if (!empty($a_server[$id]['shared_key'])) {
				$pconfig['shared_key'] = base64_decode($a_server[$id]['shared_key']);
			} else {
				$pconfig['shared_key'] = null;
			}
			if (!empty($a_server[$id]['tls'])) {
					$pconfig['tlsauth_enable'] = "yes";
					$pconfig['tls'] = base64_decode($a_server[$id]['tls']);
			} else {
				$pconfig['tls'] = null;
			}
	} elseif ($act == "new") {
	    $pconfig['dh_length'] = 1024;
	    $pconfig['dev_mode'] = "tun";
	    $pconfig['interface'] = "wan";
	    $pconfig['local_port'] = openvpn_port_next('UDP');
	    $pconfig['pool_enable'] = "yes";
	    $pconfig['cert_depth'] = 1;
			// init all fields used in the form
			$init_fields = "mode,protocol,authmode,dev_mode,interface,local_port
			,description,custom_options,crypto,engine,tunnel_network
			,tunnel_networkv6,remote_network,remote_networkv6,gwredir,local_network
			,local_networkv6,maxclients,compression,passtos,client2client
			,dynamic_ip,pool_enable,topology_subnet,serverbridge_dhcp
			,serverbridge_interface,serverbridge_dhcp_start,serverbridge_dhcp_end
			,dns_server1,dns_server2,dns_server3,dns_server4,ntp_server1
			,ntp_server2,netbios_enable,netbios_ntype,netbios_scope,wins_server1
			,wins_server2,no_tun_ipv6,push_register_dns,dns_domain,nbdd_server1
			,client_mgmt_port,verbosity_level,caref,crlref,certref,dh_length
			,cert_depth,strictusercn,digest,disable,duplicate_cn,vpnid,shared_key,tls";
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
				// action delete
		    if (!isset($a_server[$id])) {
		        redirectHeader("vpn_openvpn_server.php");
		        exit;
		    }
		    if (!empty($a_server[$id])) {
		        openvpn_delete('server', $a_server[$id]);
		    }
		    unset($a_server[$id]);
		    write_config();
		    $savemsg = gettext("Server successfully deleted")."<br />";
		} else {
				// action add/update
				$input_errors = array();
		    $pconfig = $_POST;

		    if (isset($id) && $a_server[$id]) {
		        $vpnid = $a_server[$id]['vpnid'];
		    } else {
		        $vpnid = 0;
		    }
				if ($pconfig['mode'] != "p2p_shared_key") {
		        $tls_mode = true;
		    } else {
		        $tls_mode = false;
		    }
				if (!empty($pconfig['autokey_enable'])) {
		        $pconfig['shared_key'] = openvpn_create_key();
		    }

				// all input validators
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

		    if (empty($pconfig['authmode']) && (($pconfig['mode'] == "server_user") || ($pconfig['mode'] == "server_tls_user"))) {
		        $input_errors[] = gettext("You must select a Backend for Authentication if the server mode requires User Auth.");
		    }

		    if ($result = openvpn_validate_port($pconfig['local_port'], 'Local port')) {
		        $input_errors[] = $result;
		    }

		    if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], 'IPv4 Tunnel Network', false, "ipv4")) {
		        $input_errors[] = $result;
		    }

		    if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], 'IPv6 Tunnel Network', false, "ipv6")) {
		        $input_errors[] = $result;
		    }

		    if ($result = openvpn_validate_cidr($pconfig['remote_network'], 'IPv4 Remote Network', true, "ipv4")) {
		        $input_errors[] = $result;
		    }

		    if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], 'IPv6 Remote Network', true, "ipv6")) {
		        $input_errors[] = $result;
		    }

		    if ($result = openvpn_validate_cidr($pconfig['local_network'], 'IPv4 Local Network', true, "ipv4")) {
		        $input_errors[] = $result;
		    }

		    if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], 'IPv6 Local Network', true, "ipv6")) {
		        $input_errors[] = $result;
		    }

		    $portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
		    if (($portused != $vpnid) && ($portused != 0)) {
		        $input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
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

		    if (!empty($pconfig['wins_server_enable'])) {
		        if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1']))) {
		            $input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
		        }
		        if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2']))) {
		            $input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
		        }
		    }
		    if (!empty($pconfig['nbdd_server_enable'])) {
		        if (!empty($pconfig['nbdd_server1']) && !is_ipaddr(trim($pconfig['nbdd_server1']))) {
		            $input_errors[] = gettext("The field 'NetBIOS Data Distribution Server #1' must contain a valid IP address");
		        }
		    }

		    if (!empty($pconfig['client_mgmt_port_enable'])) {
		        if ($result = openvpn_validate_port($pconfig['client_mgmt_port'], 'Client management port')) {
		            $input_errors[] = $result;
		        }
		    }

		    if (!empty($pconfig['maxclients']) && !is_numeric($pconfig['maxclients'])) {
		        $input_errors[] = gettext("The field 'Concurrent connections' must be numeric.");
		    }

		    /* If we are not in shared key mode, then we need the CA/Cert. */
		    if (isset($pconfig['mode']) && $pconfig['mode'] != "p2p_shared_key") {
		        $reqdfields = explode(" ", "caref certref");
		        $reqdfieldsn = array(gettext("Certificate Authority"),gettext("Certificate"));
		    } elseif (empty($pconfig['autokey_enable'])) {
		        /* We only need the shared key filled in if we are in shared key mode and autokey is not selected. */
		        $reqdfields = array('shared_key');
		        $reqdfieldsn = array(gettext('Shared key'));
		    }

		    if ($pconfig['dev_mode'] != "tap") {
		        $reqdfields[] = 'tunnel_network';
		        $reqdfieldsn[] = gettext('Tunnel network');
		    } else {
		        if ($pconfig['serverbridge_dhcp'] && $pconfig['tunnel_network']) {
		            $input_errors[] = gettext("Using a tunnel network and server bridge settings together is not allowed.");
		        }
		        if (($pconfig['serverbridge_dhcp_start'] && !$pconfig['serverbridge_dhcp_end'])
		        || (!$pconfig['serverbridge_dhcp_start'] && $pconfig['serverbridge_dhcp_end'])) {
		            $input_errors[] = gettext("Server Bridge DHCP Start and End must both be empty, or defined.");
		        }
		        if (($pconfig['serverbridge_dhcp_start'] && !is_ipaddrv4($pconfig['serverbridge_dhcp_start']))) {
		            $input_errors[] = gettext("Server Bridge DHCP Start must be an IPv4 address.");
		        }
		        if (($pconfig['serverbridge_dhcp_end'] && !is_ipaddrv4($pconfig['serverbridge_dhcp_end']))) {
		            $input_errors[] = gettext("Server Bridge DHCP End must be an IPv4 address.");
		        }
		        if (ip2ulong($pconfig['serverbridge_dhcp_start']) > ip2ulong($pconfig['serverbridge_dhcp_end'])) {
		            $input_errors[] = gettext("The Server Bridge DHCP range is invalid (start higher than end).");
		        }
		    }
		    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

		    if (count($input_errors) == 0) {
						// validation correct, save data
						$server = array();

						// delete(rename) old interface so a new TUN or TAP interface can be created.
						if (isset($id) && $pconfig['dev_mode'] <> $a_server[$id]['dev_mode']) {
								openvpn_delete('server', $a_server[$id]);
						}
						// 1 on 1 copy of config attributes
						$copy_fields = "mode,protocol,dev_mode,local_port,description,crypto,digest,engine
						,tunnel_network,tunnel_networkv6,remote_network,remote_networkv6
						,gwredir,local_network,local_networkv6,maxclients,compression
						,passtos,client2client,dynamic_ip,pool_enable,topology_subnet
						,serverbridge_dhcp,serverbridge_interface,serverbridge_dhcp_start
						,serverbridge_dhcp_end,dns_domain,dns_server1,dns_server2,dns_server3
						,dns_server4,push_register_dns,ntp_server1,ntp_server2,netbios_enable
						,netbios_ntype,netbios_scope,no_tun_ipv6,verbosity_level,wins_server1
						,wins_server2,nbdd_server1,client_mgmt_port";

						foreach (explode(",",$copy_fields) as $fieldname) {
							$fieldname = trim($fieldname);
							if(isset($pconfig[$fieldname])) {
								$server[$fieldname] = $pconfig[$fieldname];
							}
						}

						// attributes containing some kind of logic
		        if ($vpnid != 0) {
		            $server['vpnid'] = $vpnid;
		        } else {
		            $server['vpnid'] = openvpn_vpnid_next();
		        }

		        if ($pconfig['disable'] == "yes") {
		            $server['disable'] = true;
		        }
		        if (!empty($pconfig['authmode'])) {
		            $server['authmode'] = implode(",", $pconfig['authmode']);
		        }
						if (strpos($pconfig['interface'], "|") !== false) {
							list($server['interface'], $server['ipaddr']) = explode("|", $pconfig['interface']);
						}

						$server['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);

		        if ($tls_mode) {
		            if ($pconfig['tlsauth_enable']) {
		                if (!empty($pconfig['autotls_enable'])) {
		                    $pconfig['tls'] = openvpn_create_key();
		                }
		                $server['tls'] = base64_encode($pconfig['tls']);
		            }
								foreach (array("caref","crlref",
											"certref","dh_length","cert_depth") as $cpKey) {
												if (isset($pconfig[$cpKey])) {
													$server[$cpKey] = $pconfig[$cpKey];
												}
								}
		            if (isset($pconfig['mode']) && $pconfig['mode'] == "server_tls_user" && isset($server['strictusercn'])) {
		                $server['strictusercn'] = $pconfig['strictusercn'];
		            }
		        } else {
		            $server['shared_key'] = base64_encode($pconfig['shared_key']);
		        }

		        if (isset($_POST['duplicate_cn']) && $_POST['duplicate_cn'] == "yes") {
		            $server['duplicate_cn'] = true;
		        }

						// update or add to config
		        if (isset($id) && $a_server[$id]) {
		            $a_server[$id] = $server;
		        } else {
		            $a_server[] = $server;
		        }

		        openvpn_resync('server', $server);
		        write_config();

		        header("Location: vpn_openvpn_server.php");
		        exit;
		    } elseif (!empty($pconfig['authmode'])) {
		        $pconfig['authmode'] = implode(",", $pconfig['authmode']);
		    }
		}
}
$pgtitle = array(gettext("OpenVPN"), gettext("Server"));
$shortcut_section = "openvpn";

include("head.inc");

$main_buttons = array(
    array('href'=>'vpn_openvpn_server.php?act=new', 'label'=>gettext("add server")),
);

legacy_html_escape_form_data($pconfig);
?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
	// link delete buttons
	$(".act_delete").click(function(){
		var id = $(this).attr("id").split('_').pop(-1);
		BootstrapDialog.show({
				type:BootstrapDialog.TYPE_INFO,
				title: "<?= gettext("OpenVPN");?>",
				message: "<?= gettext("Do you really want to delete this server?"); ?>",
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
		mode_change();
		autokey_change();
		tlsauth_change();
		gwredir_change();
		dns_domain_change();
		dns_server_change();
		wins_server_change();
		client_mgmt_port_change();
		ntp_server_change();
		netbios_change();
		tuntap_change();
	}

});

function mode_change() {
	index = document.iform.mode.selectedIndex;
	value = document.iform.mode.options[index].value;
	switch(value) {
		case "p2p_tls":
		case "server_tls":
		case "server_user":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_crl").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("tls_dh").style.display="";
			document.getElementById("cert_depth").style.display="";
			document.getElementById("strictusercn").style.display="none";
			document.getElementById("psk").style.display="none";
			break;
		case "server_tls_user":
			document.getElementById("tls").style.display="";
			document.getElementById("tls_ca").style.display="";
			document.getElementById("tls_crl").style.display="";
			document.getElementById("tls_cert").style.display="";
			document.getElementById("tls_dh").style.display="";
			document.getElementById("cert_depth").style.display="";
			document.getElementById("strictusercn").style.display="";
			document.getElementById("psk").style.display="none";
			break;
		case "p2p_shared_key":
			document.getElementById("tls").style.display="none";
			document.getElementById("tls_ca").style.display="none";
			document.getElementById("tls_crl").style.display="none";
			document.getElementById("tls_cert").style.display="none";
			document.getElementById("tls_dh").style.display="none";
			document.getElementById("cert_depth").style.display="none";
			document.getElementById("strictusercn").style.display="none";
			document.getElementById("psk").style.display="";
			break;
	}
	switch(value) {
		case "p2p_shared_key":
			document.getElementById("client_opts").style.display="none";
			document.getElementById("remote_optsv4").style.display="";
			document.getElementById("remote_optsv6").style.display="";
			document.getElementById("gwredir_opts").style.display="none";
			document.getElementById("local_optsv4").style.display="none";
			document.getElementById("local_optsv6").style.display="none";
			document.getElementById("authmodetr").style.display="none";
			document.getElementById("inter_client_communication").style.display="none";
			break;
		case "p2p_tls":
			document.getElementById("client_opts").style.display="none";
			document.getElementById("remote_optsv4").style.display="";
			document.getElementById("remote_optsv6").style.display="";
			document.getElementById("gwredir_opts").style.display="";
			document.getElementById("local_optsv4").style.display="";
			document.getElementById("local_optsv6").style.display="";
			document.getElementById("authmodetr").style.display="none";
			document.getElementById("inter_client_communication").style.display="none";
			break;
		case "server_user":
                case "server_tls_user":
			document.getElementById("authmodetr").style.display="";
			document.getElementById("client_opts").style.display="";
			document.getElementById("remote_optsv4").style.display="none";
			document.getElementById("remote_optsv6").style.display="none";
			document.getElementById("gwredir_opts").style.display="";
			document.getElementById("local_optsv4").style.display="";
			document.getElementById("local_optsv6").style.display="";
			document.getElementById("inter_client_communication").style.display="";
			break;
		case "server_tls":
			document.getElementById("authmodetr").style.display="none";
		default:
			document.getElementById("client_opts").style.display="";
			document.getElementById("remote_optsv4").style.display="none";
			document.getElementById("remote_optsv6").style.display="none";
			document.getElementById("gwredir_opts").style.display="";
			document.getElementById("local_optsv4").style.display="";
			document.getElementById("local_optsv6").style.display="";
			document.getElementById("inter_client_communication").style.display="";
			break;
	}
	gwredir_change();
}

function autokey_change() {

	if ((document.iform.autokey_enable != null) && (document.iform.autokey_enable.checked))
		document.getElementById("autokey_opts").style.display="none";
	else
		document.getElementById("autokey_opts").style.display="";
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

function gwredir_change() {

	if (document.iform.gwredir.checked) {
		document.getElementById("local_optsv4").style.display="none";
		document.getElementById("local_optsv6").style.display="none";
	} else {
		document.getElementById("local_optsv4").style.display="";
		document.getElementById("local_optsv6").style.display="";
	}
}

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

function client_mgmt_port_change() {

	if (document.iform.client_mgmt_port_enable.checked)
		document.getElementById("client_mgmt_port_data").style.display="";
	else
		document.getElementById("client_mgmt_port_data").style.display="none";
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

function tuntap_change() {

	mindex = document.iform.mode.selectedIndex;
	mvalue = document.iform.mode.options[mindex].value;

	switch(mvalue) {
		case "p2p_tls":
		case "p2p_shared_key":
			p2p = true;
			break;
		default:
			p2p = false;
			break;
	}

	index = document.iform.dev_mode.selectedIndex;
	value = document.iform.dev_mode.options[index].value;
	switch(value) {
		case "tun":
			document.getElementById("chkboxNoTunIPv6").style.display="";
			document.getElementById("ipv4_tunnel_network").className="vncellreq";
			document.getElementById("serverbridge_dhcp").style.display="none";
			document.getElementById("serverbridge_interface").style.display="none";
			document.getElementById("serverbridge_dhcp_start").style.display="none";
			document.getElementById("serverbridge_dhcp_end").style.display="none";
			document.getElementById("topology_subnet_opt").style.display="";
			break;
		case "tap":
			document.getElementById("chkboxNoTunIPv6").style.display="none";
			document.getElementById("ipv4_tunnel_network").className="vncell";
			if (!p2p) {
				document.getElementById("serverbridge_dhcp").style.display="";
				document.getElementById("serverbridge_interface").style.display="";
				document.getElementById("serverbridge_dhcp_start").style.display="";
				document.getElementById("serverbridge_dhcp_end").style.display="";
				document.getElementById("topology_subnet_opt").style.display="none";
				document.iform.serverbridge_dhcp.disabled = false;
				if (document.iform.serverbridge_dhcp.checked) {
					document.iform.serverbridge_interface.disabled = false;
					document.iform.serverbridge_dhcp_start.disabled = false;
					document.iform.serverbridge_dhcp_end.disabled = false;
				} else {
					document.iform.serverbridge_interface.disabled = true;
					document.iform.serverbridge_dhcp_start.disabled = true;
					document.iform.serverbridge_dhcp_end.disabled = true;
				}
			} else {
				document.getElementById("topology_subnet_opt").style.display="none";
				document.iform.serverbridge_dhcp.disabled = true;
				document.iform.serverbridge_interface.disabled = true;
				document.iform.serverbridge_dhcp_start.disabled = true;
				document.iform.serverbridge_dhcp_end.disabled = true;
			}
			break;
	}
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
                    print_info_box_np($savemsg);
                }
                ?>

			    <section class="col-xs-12">

				<?php
                        $tab_array = array();
                        $tab_array[] = array(gettext("Server"), true, "vpn_openvpn_server.php");
                        $tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
                        $tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
                        $tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
                        $tab_array[] = array(gettext("Client Export"), false, "vpn_openvpn_export.php");
                                        $tab_array[] = array(gettext("Shared Key Export"), false, "vpn_openvpn_export_shared.php");
                        display_top_tabs($tab_array);
                    ?>

					<div class="tab-content content-box col-xs-12">

					    <?php if ($act=="new" || $act=="edit") :
?>
							<form action="vpn_openvpn_server.php" method="post" name="iform" id="iform" onsubmit="presubmit()">

								<div class="table-responsive">
									<table class="table table-striped">
										<tr>
											<td width="22%" valign="top" class="listtopic"><?=gettext("General information"); ?></td>
											<td width="78%" valign="top" align="right">
												<small><?=gettext("full help"); ?> </small>
												<i class="fa fa-toggle-off text-danger" id="show_all_help_opnvpn_server" type="button"></i></a>
											</td>
										</tr>
										<tr>
											<td valign="top" class="vncellreq">
												<a id="help_for_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
												<b><?=gettext("Disabled"); ?></b>
											</td>
											<td class="vtable">
												<div>
													<input name="disable" type="checkbox" value="yes" <?= !empty($pconfig['disable']) ? "checked=\"checked\"" : "";?> />
												</div>
												<div class="hidden" for="help_for_disable">
												<?=gettext("Set this option to disable this server without removing it from the list"); ?>.
												</div >
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Mode");?></td>
												<td width="78%" class="vtable">
												<select name='mode' id='mode' class="form-control" onchange='mode_change(); tuntap_change()'>
												<?php
																						$openvpn_server_modes = array(
																							'p2p_tls' => gettext("Peer to Peer ( SSL/TLS )"),
																							'p2p_shared_key' => gettext("Peer to Peer ( Shared Key )"),
																							'server_tls' => gettext("Remote Access ( SSL/TLS )"),
																							'server_user' => gettext("Remote Access ( User Auth )"),
																							'server_tls_user' => gettext("Remote Access ( SSL/TLS + User Auth )"));
                                                foreach ($openvpn_server_modes as $name => $desc) :
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
										<tr id="authmodetr" style="display:none">
                          <td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Backend for authentication");?></td>
                          <td width="78%" class="vtable">
                          	<select name='authmode[]' id='authmode' class="form-control" multiple="multiple" size="5">
                                                        <?php
																												if (isset($pconfig['authmode'])) {
																													$authmodes = explode(",", $pconfig['authmode']);
																												} else {
																													$authmodes = array();
																												}
                                                        $auth_servers = auth_get_authserver_list();
                                                        foreach ($auth_servers as $auth_server) :
                                                                $selected = "";
                                                            if (in_array($auth_server['name'], $authmodes)) {
                                                                    $selected = "selected=\"selected\"";
                                                            }
                                                        ?>
                                                        <option value="<?=htmlspecialchars($auth_server['name']);?>" <?=$selected;?>><?=htmlspecialchars($auth_server['name']);?></option>
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
                                                foreach ($openvpn_prots as $prot) :
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
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Device Mode"); ?></td>
											<td width="78%" class="vtable">
												<select name="dev_mode" class="form-control" onchange='tuntap_change()'>
                                                        <?php
                                                        foreach ($openvpn_dev_mode as $device) :
                                                               $selected = "";
                                                            if (! empty($pconfig['dev_mode'])) {
                                                                if ($pconfig['dev_mode'] == $device) {
                                                                        $selected = "selected=\"selected\"";
                                                                }
                                                            } else {
                                                                if ($device == "tun") {
                                                                        $selected = "selected=\"selected\"";
                                                                }
                                                            }
                                                        ?>
                                                        <option value="<?=$device;?>" <?=$selected;?>><?=$device;?></option>
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
                                                    <option value="<?=$iface;?>" <?=$selected;?>>
                                                        <?=htmlspecialchars($ifacename);?>
                                                    </option>
													<?php
                                                    endforeach; ?>
												</select> <br />
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Local port");?></td>
											<td width="78%" class="vtable">
												<input name="local_port" type="text" class="form-control unknown" size="5" value="<?=$pconfig['local_port'];?>" />
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
											<td width="78%" class="vtable">
												<input name="description" type="text" class="form-control unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>" />
												<div class="hidden" for="help_for_description">
													<?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
												</div>
											</td>
										</tr>
										<tr>
											<td colspan="2" class="list" height="12"></td>
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
															<input name="tlsauth_enable" id="tlsauth_enable" type="checkbox" value="yes" <?=!empty($pconfig['tlsauth_enable']) ? "checked=\"checked\"" : "" ;?> onclick="tlsauth_change()" />
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
															<input name="autotls_enable" id="autotls_enable" type="checkbox" value="yes" <?=!empty($pconfig['autotls_enable']) ? "checked=\"checked\"" : "" ;?> onclick="autotls_change()" />
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
												<table border="0" cellpadding="2" cellspacing="0" id="autotls_opts" summary="tls authentication key">
													<tr>
														<td>
															<textarea name="tls" cols="65" rows="7" class="formpre"><?=$pconfig['tls'];?></textarea>
															<?=gettext("Paste your shared key here"); ?>.
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<tr id="tls_ca">
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer Certificate Authority"); ?></td>
												<td width="78%" class="vtable">
												<?php if (isset($config['ca'])) :
?>
												<select name='caref' class="form-control">
												<?php
                                                foreach ($config['ca'] as $ca) :
                                                    $selected = "";
                                                    if ($pconfig['caref'] == $ca['refid']) {
                                                        $selected = "selected=\"selected\"";
                                                    }
                                                ?>
                                                <option value="<?=htmlspecialchars($ca['refid']);?>" <?=$selected;?>><?=htmlspecialchars($ca['descr']);?></option>
												<?php
                                                endforeach; ?>
												</select>
												<?php
else :
?>
													<b><?=gettext("No Certificate Authorities defined.");?></b> <br /><?=gettext("Create one under")?> <a href="system_camanager.php"> <?=gettext("System: Certificates");?></a>.
												<?php
endif; ?>
												</td>
										</tr>
										<tr id="tls_crl">
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer Certificate Revocation List"); ?></td>
												<td width="78%" class="vtable">
												<?php if (isset($config['crl'])) :
?>
												<select name='crlref' class="form-control">
													<option value="">None</option>
												<?php
                                                foreach ($config['crl'] as $crl) :
																										if (isset($acrl['refid'])) {
																											$selected = "";
	                                                    $caname = "";
	                                                    $ca = lookup_ca($crl['caref']);
	                                                    if ($ca) {
	                                                        $caname = " (CA: {$ca['descr']})";
	                                                        if ($pconfig['crlref'] == $crl['refid']) {
	                                                            $selected = "selected=\"selected\"";
	                                                        }
	                                                    }
																										}
                                                ?>
                                                <option value="<?=htmlspecialchars($crl['refid']);?>" <?=$selected;?>><?=htmlspecialchars($crl['descr'] . $caname);?></option>
												<?php
                                                endforeach; ?>
												</select>
												<?php
else :
?>
													<b><?=gettext("No Certificate Revocation Lists (CRLs) defined.");?></b> <br /><?=gettext("Create one under");?> <a href="system_crlmanager.php"><?=gettext("System: Certificates");?></a>.
												<?php
endif; ?>
												</td>
										</tr>
										<tr id="tls_cert">
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Certificate"); ?></td>
												<td width="78%" class="vtable">
												<?php if (isset($config['cert'])) :
?>
												<select name='certref' class="form-control">
												<?php
                                                foreach ($config['cert'] as $cert) :
                                                    $selected = "";
                                                    $caname = "";
                                                    $inuse = "";
                                                    $revoked = "";
																										if (isset($cert['caref'])) {
																											$ca = lookup_ca($cert['caref']);
	                                                    if (!empty($ca)) {
	                                                        $caname = " (CA: {$ca['descr']})";
	                                                    }
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
													<option value="<?=htmlspecialchars($cert['refid']);?>" <?=$selected;?>><?=htmlspecialchars($cert['descr'] . $caname . $inuse . $revoked);?></option>
												<?php
                                                endforeach; ?>
												</select>
												<?php
else :
?>
													<b><?=gettext("No Certificates defined.");?></b> <br /><?=gettext("Create one under");?> <a href="system_certmanager.php"><?=gettext("System: Certificates");?></a>.
												<?php
endif; ?>
											</td>
										</tr>
										<tr id="tls_dh">
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("DH Parameters Length"); ?></td>
											<td width="78%" class="vtable">
												<select name="dh_length" class="form-control">
													<?php
                                                    foreach (array(1024, 2048, 4096) as $length) :
                                                        $selected = "";
                                                        if ($length == $pconfig['dh_length']) {
                                                            $selected = " selected=\"selected\"";
                                                        }
                                                    ?>
													<option<?=$selected?>><?=$length;?></option>
													<?php
                                                    endforeach; ?>
												</select>
												<span class="vexpl">
													<?=gettext("bits"); ?>
												</span>
											</td>
										</tr>
										<tr id="psk">
											<td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Shared Key"); ?></td>
											<td width="78%" class="vtable">
												<?php if (empty($pconfig['shared_key'])) :
?>
												<table border="0" cellpadding="2" cellspacing="0" summary="shared key">
													<tr>
														<td>
															<input name="autokey_enable" type="checkbox" value="yes"  <?=!empty($pconfig['autokey_enable']) ? "checked=\"checked\"" : "" ;?>  onclick="autokey_change()" />
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
												<table border="0" cellpadding="2" cellspacing="0" id="autokey_opts" summary="shared key">
													<tr>
														<td>
															<textarea name="shared_key" cols="65" rows="7" class="formpre"><?=$pconfig['shared_key'];?></textarea>
															<?=gettext("Paste your shared key here"); ?>.
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
											<td width="22%" valign="top" class="vncellreq"><a id="help_for_digest" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Auth Digest Algorithm"); ?></td>
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
												<div class="hidden" for="help_for_digest">
													<?PHP echo gettext("NOTE: Leave this set to SHA1 unless all clients are set to match. SHA1 is the default for OpenVPN."); ?>
												</div>
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
										<tr id="cert_depth">
											<td width="22%" valign="top" class="vncell"><a id="help_for_cert_depth" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Certificate Depth"); ?></td>
											<td width="78%" class="vtable">
												<table border="0" cellpadding="2" cellspacing="0" summary="certificate depth">
												<tr><td>
												<select name="cert_depth" class="form-control">
													<option value="">Do Not Check</option>
													<?php
																										$openvpn_cert_depths = array(
																											1 => "One (Client+Server)",
																											2 => "Two (Client+Intermediate+Server)",
																											3 => "Three (Client+2xIntermediate+Server)",
																											4 => "Four (Client+3xIntermediate+Server)",
																											5 => "Five (Client+4xIntermediate+Server)"
																										);
                                                    foreach ($openvpn_cert_depths as $depth => $depthdesc) :
                                                        $selected = "";
                                                        if ($depth == $pconfig['cert_depth']) {
                                                            $selected = " selected=\"selected\"";
                                                        }
                                                    ?>
													<option value="<?= $depth ?>" <?= $selected ?>><?= $depthdesc ?></option>
													<?php
                                                    endforeach; ?>
												</select>
												</td></tr>
												<tr><td>
												<div class="hidden" for="help_for_cert_depth">
													<span class="vexpl">
														<?=gettext("When a certificate-based client logs in, do not accept certificates below this depth. Useful for denying certificates made with intermediate CAs generated from the same CA as the server."); ?>
													</span>
												</div>
												</td></tr>
												</table>
											</td>
										</tr>
										<tr id="strictusercn">
											<td width="22%" valign="top" class="vncell"><a id="help_for_strictusercn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Strict User/CN Matching"); ?></td>
											<td width="78%" class="vtable">
															<input name="strictusercn" type="checkbox" value="yes" <?=!empty($pconfig['strictusercn']) ? "checked=\"checked\"" : "" ;?> />
															<div class="hidden" for="help_for_strictusercn">
																<span class="vexpl">
																	<?=gettext("When authenticating users, enforce a match between the common name of the client certificate and the username given at login."); ?>
																</span>
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
											<td width="22%" valign="top" class="vncellreq" id="ipv4_tunnel_network"><a id="help_for_tunnel_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Tunnel Network"); ?></td>
											<td width="78%" class="vtable">
												<input name="tunnel_network" type="text" class="form-control unknown" size="20" value="<?=$pconfig['tunnel_network'];?>" />
												<div class="hidden" for="help_for_tunnel_network">
													<?=gettext("This is the IPv4 virtual network used for private " .
	                                                "communications between this server and client " .
	                                                "hosts expressed using CIDR (eg. 10.0.8.0/24). " .
	                                                "The first network address will be assigned to " .
	                                                "the	server virtual interface. The remaining " .
	                                                "network addresses can optionally be assigned " .
	                                                "to connecting clients. (see Address Pool)"); ?>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_tunnel_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Tunnel Network"); ?></td>
											<td width="78%" class="vtable">
												<input name="tunnel_networkv6" type="text" class="form-control unknown" size="20" value="<?=$pconfig['tunnel_networkv6'];?>" />
												<div class="hidden" for="help_for_tunnel_networkv6">
													<?=gettext("This is the IPv6 virtual network used for private " .
	                                                "communications between this server and client " .
	                                                "hosts expressed using CIDR (eg. fe80::/64). " .
	                                                "The first network address will be assigned to " .
	                                                "the server virtual interface. The remaining " .
	                                                "network addresses can optionally be assigned " .
	                                                "to connecting clients. (see Address Pool)"); ?>
												</div>
											</td>
										</tr>
										<tr id="serverbridge_dhcp">
											<td width="22%" valign="top" class="vncell"><a id="help_for_serverbridge_dhcp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bridge DHCP"); ?></td>
											<td width="78%" class="vtable">
															<input name="serverbridge_dhcp" type="checkbox" value="yes" <?=!empty($pconfig['serverbridge_dhcp']) ? "checked=\"checked\"" : "" ;?> onchange="tuntap_change()" />
															<div class="hidden" for="help_for_serverbridge_dhcp">
																<span class="vexpl">
																	<?=gettext("Allow clients on the bridge to obtain DHCP."); ?><br />
																</span>
															</div>
											</td>
										</tr>
										<tr id="serverbridge_interface">
											<td width="22%" valign="top" class="vncell"><a id="help_for_serverbridge_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bridge Interface"); ?></td>
											<td width="78%" class="vtable">
												<select name="serverbridge_interface" class="form-control">
													<?php
                                                    $serverbridge_interface['none'] = "none";
                                                    $serverbridge_interface = array_merge($serverbridge_interface, get_configured_interface_with_descr());
                                                    $carplist = get_configured_carp_interface_list();
                                                    foreach ($carplist as $cif => $carpip) {
                                                        $serverbridge_interface[$cif.'|'.$carpip] = $carpip." (".get_vip_descr($carpip).")";
                                                    }
                                                        $aliaslist = get_configured_ip_aliases_list();
                                                    foreach ($aliaslist as $aliasip => $aliasif) {
                                                        $serverbridge_interface[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                                                    }
                                                    foreach ($serverbridge_interface as $iface => $ifacename) :
                                                        $selected = "";
                                                        if ($iface == $pconfig['serverbridge_interface']) {
                                                            $selected = "selected=\"selected\"";
                                                        }
                                                    ?>
                                                    <option value="<?=$iface;
?>" <?=$selected;?>>
                                                        <?=htmlspecialchars($ifacename);?>
                                                    </option>
													<?php
                                                    endforeach; ?>
												</select>
												<div class="hidden" for="help_for_serverbridge_interface">
													<?=gettext("The interface to which this tap instance will be " .
	                                                "bridged. This is not done automatically. You must assign this " .
	                                                "interface and create the bridge separately. " .
	                                                "This setting controls which existing IP address and subnet " .
	                                                "mask are used by OpenVPN for the bridge. Setting this to " .
	                                                "'none' will cause the Server Bridge DHCP settings below to be ignored."); ?>
												</div>
											</td>
										</tr>
										<tr id="serverbridge_dhcp_start">
											<td width="22%" valign="top" class="vncell"><a id="help_for_serverbridge_dhcp_start" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Server Bridge DHCP Start"); ?></td>
											<td width="78%" class="vtable">
												<input name="serverbridge_dhcp_start" type="text" class="form-control unknown" size="20" value="<?=$pconfig['serverbridge_dhcp_start'];?>" />
												<div class="hidden" for="help_for_serverbridge_dhcp_start">
													<?=gettext("When using tap mode as a multi-point server, " .
	                                                "you may optionally supply a DHCP range to use on the " .
	                                                "interface to which this tap instance is bridged. " .
	                                                "If these settings are left blank, DHCP will be passed " .
	                                                "through to the LAN, and the interface setting above " .
	                                                "will be ignored."); ?>
												</div>
											</td>
										</tr>
										<tr id="serverbridge_dhcp_end">
											<td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Bridge DHCP End"); ?></td>
											<td width="78%" class="vtable">
												<input name="serverbridge_dhcp_end" type="text" class="form-control unknown" size="20" value="<?=$pconfig['serverbridge_dhcp_end'];?>" />
												<br />
											</td>
										</tr>
										<tr id="gwredir_opts">
											<td width="22%" valign="top" class="vncell"><a id="help_for_gwredir" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect Gateway"); ?></td>
											<td width="78%" class="vtable">
												<input name="gwredir" type="checkbox" value="yes" <?=!empty($pconfig['gwredir']) ? "checked=\"checked\"" : "" ;?> onclick="gwredir_change()" />
												<div class="hidden" for="help_for_gwredir">
														<span class="vexpl">
															<?=gettext("Force all client generated traffic through the tunnel"); ?>.
														</span>
												</div>
											</td>
										</tr>
										<tr id="local_optsv4">
											<td width="22%" valign="top" class="vncell"><a id="help_local_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Local Network/s"); ?></td>
											<td width="78%" class="vtable">
												<input name="local_network" type="text" class="form-control unknown" size="40" value="<?=$pconfig['local_network'];?>" />
												<div class="hidden" for="help_local_network">
													<?=gettext("These are the IPv4 networks that will be accessible " .
	                                                "from the remote endpoint. Expressed as a comma-separated list of one or more CIDR ranges. " .
	                                                "You may leave this blank if you don't " .
	                                                "want to add a route to the local network " .
	                                                "through this tunnel on the remote machine. " .
	                                                "This is generally set to your LAN network"); ?>.
												</div>
											</td>
										</tr>
										<tr id="local_optsv6">
											<td width="22%" valign="top" class="vncell"><a id="help_for_local_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a><?=gettext("IPv6 Local Network/s"); ?></td>
											<td width="78%" class="vtable">
												<input name="local_networkv6" type="text" class="form-control unknown" size="40" value="<?=$pconfig['local_networkv6'];?>" />
												<div class="hidden" for="help_for_local_networkv6">
													<?=gettext("These are the IPv6 networks that will be accessible " .
	                                                "from the remote endpoint. Expressed as a comma-separated list of one or more IP/PREFIX. " .
	                                                "You may leave this blank if you don't " .
	                                                "want to add a route to the local network " .
	                                                "through this tunnel on the remote machine. " .
	                                                "This is generally set to your LAN network"); ?>.
												</div>
											</td>
										</tr>
										<tr id="remote_optsv4">
											<td width="22%" valign="top" class="vncell"><a id="help_for_remote_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Remote Network/s"); ?></td>
											<td width="78%" class="vtable">
												<input name="remote_network" type="text" class="form-control unknown" size="40" value="<?=$pconfig['remote_network'];?>" />
												<div class="hidden" for="help_for_remote_network">
													<?=gettext("These are the IPv4 networks that will be routed through " .
	                                                "the tunnel, so that a site-to-site VPN can be " .
	                                                "established without manually changing the routing tables. " .
	                                                "Expressed as a comma-separated list of one or more CIDR ranges. " .
	                                                "If this is a site-to-site VPN, enter the " .
	                                                "remote LAN/s here. You may leave this blank if " .
	                                                "you don't want a site-to-site VPN"); ?>.
												</div>
											</td>
										</tr>
										<tr id="remote_optsv6">
											<td width="22%" valign="top" class="vncell"><a id="help_for_remote_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Remote Network/s"); ?></td>
											<td width="78%" class="vtable">
												<input name="remote_networkv6" type="text" class="form-control unknown" size="40" value="<?=$pconfig['remote_networkv6'];?>" />
												<div class="hidden" for="help_for_remote_networkv6">
													<?=gettext("These are the IPv6 networks that will be routed through " .
	                                                "the tunnel, so that a site-to-site VPN can be " .
	                                                "established without manually changing the routing tables. " .
	                                                "Expressed as a comma-separated list of one or more IP/PREFIX. " .
	                                                "If this is a site-to-site VPN, enter the " .
	                                                "remote LAN/s here. You may leave this blank if " .
	                                                "you don't want a site-to-site VPN"); ?>.
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_maxclients" href="#" class="showhelp"><a id="help_for_maxclients" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Concurrent connections");?></td>
											<td width="78%" class="vtable">
												<input name="maxclients" type="text" class="form-control unknown" size="5" value="<?=$pconfig['maxclients'];?>" />
												<div class="hidden" for="help_for_maxclients">
													<?=gettext("Specify the maximum number of clients allowed to concurrently connect to this server"); ?>.
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_compression" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Compression"); ?></td>
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
												<div class="hidden" for="help_for_compression">
													<?=gettext("Compress tunnel packets using the LZO algorithm. Adaptive compression will dynamically disable compression for a period of time if OpenVPN detects that the data in the packets is not being compressed efficiently"); ?>.
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_passtos" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type-of-Service"); ?></td>
											<td width="78%" class="vtable">
												<input name="passtos" type="checkbox" value="yes" <?=!empty($pconfig['passtos']) ? "checked=\"checked\"" : "" ;?> />
												<div class="hidden" for="help_for_passtos">
													<span class="vexpl">
														<?=gettext("Set the TOS IP header value of tunnel packets to match the encapsulated packet value"); ?>.
													</span>
												</div>
											</td>
										</tr>
										<tr id="inter_client_communication">
											<td width="22%" valign="top" class="vncell"><a id="help_for_client2client" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Inter-client communication"); ?></td>
											<td width="78%" class="vtable">
													<input name="client2client" type="checkbox" value="yes"  <?=!empty($pconfig['client2client']) ? "checked=\"checked\"" : "" ;?> />
													<div class="hidden" for="help_for_client2client">
														<span class="vexpl">
															<?=gettext("Allow communication between clients connected to this server"); ?>
														</span>
													</div>
											</td>
										</tr>
										<tr id="duplicate_cn">
											<td width="22%" valign="top" class="vncell"><a id="help_for_duplicate_cn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Duplicate Connections"); ?></td>
											<td width="78%" class="vtable">
														<input name="duplicate_cn" type="checkbox" value="yes" <?=!empty($pconfig['duplicate_cn']) ? "checked=\"checked\"" : "" ;?> />
														<div class="hidden" for="help_for_duplicate_cn">
															<span class="vexpl">
																<?=gettext("Allow multiple concurrent connections from clients using the same Common Name.<br />NOTE: This is not generally recommended, but may be needed for some scenarios."); ?>
															</span>
														</div>
											</td>
										</tr>
										<tr id="chkboxNoTunIPv6">
											<td width="22%" valign="top" class="vncell"><a id="help_for_no_tun_ipv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable IPv6"); ?></td>
											<td width="78%" class="vtable">
												<input name="no_tun_ipv6" type="checkbox" value="yes" <?=!empty($pconfig['no_tun_ipv6']) ? "checked=\"checked\"" : "" ;?> />
												<div class="hidden" for="help_for_no_tun_ipv6">
													<span class="vexpl">
														<?=gettext("Don't forward IPv6 traffic"); ?>.
													</span>
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
											<td width="22%" valign="top" class="vncell"><a id="help_for_dynamic_ip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Dynamic IP"); ?></td>
											<td width="78%" class="vtable">
												<input name="dynamic_ip" type="checkbox" id="dynamic_ip" value="yes" <?=!empty($pconfig['dynamic_ip']) ? "checked=\"checked\"" : "" ;?> />
												<div class="hidden" for="help_for_dynamic_ip">
													<span class="vexpl">
														<?=gettext("Allow connected clients to retain their connections if their IP address changes"); ?>.<br />
													</span>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_pool_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Address Pool"); ?></td>
											<td width="78%" class="vtable">
												<input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?=!empty($pconfig['pool_enable']) ? "checked=\"checked\"" : "" ;?> />
												<div class="hidden" for="help_for_pool_enable">
													<span class="vexpl">
														<?=gettext("Provide a virtual adapter IP address to clients (see Tunnel Network)"); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr id="topology_subnet_opt">
											<td width="22%" valign="top" class="vncell"><a id="help_for_topology_subnet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Topology"); ?></td>
											<td width="78%" class="vtable">
												<input name="topology_subnet" type="checkbox" id="topology_subnet" value="yes"  <?=!empty($pconfig['topology_subnet']) ? "checked=\"checked\"" : "" ;?> />
												<div class="hidden" for="help_for_topology_subnet">
													<span class="vexpl">
														<?=gettext("Allocate only one IP per client (topology subnet), rather than an isolated subnet per client (topology net30)."); ?><br />
														<?=gettext("Relevant when supplying a virtual adapter IP address to clients when using tun mode on IPv4."); ?><br />
														<?=gettext("Some clients may require this even for IPv6, such as OpenVPN Connect (iOS/Android). Others may break if it is present, such as older versions of OpenVPN or clients such as Yealink phones."); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_dns_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Default Domain"); ?></td>
											<td width="78%" class="vtable">
												<input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?=!empty($pconfig['dns_domain']) ? "checked=\"checked\"" : "" ;?>  onclick="dns_domain_change()" />
												<div id="dns_domain_data" summary="dns domain data">
															<input name="dns_domain" type="text" class="form-control unknown" id="dns_domain" size="30" value="<?=htmlspecialchars($pconfig['dns_domain']);?>" />
												</div>
												<div class="hidden" for="help_for_dns_domain">
													<span class="vexpl">
																								<?=gettext("Provide a default domain name to clients"); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_dns_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Servers"); ?></td>
											<td width="78%" class="vtable">
												<input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=!empty($pconfig['dns_server1']) || !empty($pconfig['dns_server2']) || !empty($pconfig['dns_server3']) || !empty($pconfig['dns_server4']) ? "checked=\"checked\"" : "" ;?> onclick="dns_server_change()" />
												<div id="dns_server_data" summary="dns servers">
															<span class="vexpl">
																<?=gettext("Server"); ?> #1:&nbsp;
															</span>
															<input name="dns_server1" type="text" class="form-control unknown" id="dns_server1" size="20" value="<?=$pconfig['dns_server1'];?>" />
															<span class="vexpl">
																<?=gettext("Server"); ?> #2:&nbsp;
															</span>
															<input name="dns_server2" type="text" class="form-control unknown" id="dns_server2" size="20" value="<?=$pconfig['dns_server2'];?>" />
															<span class="vexpl">
																<?=gettext("Server"); ?> #3:&nbsp;
															</span>
															<input name="dns_server3" type="text" class="form-control unknown" id="dns_server3" size="20" value="<?=$pconfig['dns_server3'];?>" />
															<span class="vexpl">
																<?=gettext("Server"); ?> #4:&nbsp;
															</span>
															<input name="dns_server4" type="text" class="form-control unknown" id="dns_server4" size="20" value="<?=$pconfig['dns_server4'];?>" />
												</div>
												<div class="hidden" for="help_for_dns_server">
													<span class="vexpl">
														<?=gettext("Provide a DNS server list to clients"); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr id="chkboxPushRegisterDNS">
											<td width="22%" valign="top" class="vncell"><a id="help_for_push_register_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Force DNS cache update"); ?></td>
											<td width="78%" class="vtable">
												<input name="push_register_dns" type="checkbox" value="yes" <?=!empty($pconfig['push_register_dns']) ? "checked=\"checked\"" : "" ;?> />
												<div class="hidden" for="help_for_push_register_dns">
													<span class="vexpl">
														<?=gettext("Run ''net stop dnscache'', ''net start dnscache'', ''ipconfig /flushdns'' and ''ipconfig /registerdns'' on connection initiation. This is known to kick Windows into recognizing pushed DNS servers."); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_ntp_server_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NTP Servers"); ?></td>
											<td width="78%" class="vtable">
												<input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=!empty($pconfig['ntp_server1']) || !empty($pconfig['ntp_server2']) ? "checked=\"checked\"" : "" ;?>  onclick="ntp_server_change()" />
												<div id="ntp_server_data" summary="ntp servers">
													<span class="vexpl">
														<?=gettext("Server"); ?> #1:&nbsp;
													</span>
													<input name="ntp_server1" type="text" class="form-control unknown" id="ntp_server1" size="20" value="<?=$pconfig['ntp_server1'];?>" />
													<span class="vexpl">
														<?=gettext("Server"); ?> #2:&nbsp;
													</span>
													<input name="ntp_server2" type="text" class="form-control unknown" id="ntp_server2" size="20" value="<?=$pconfig['ntp_server2'];?>" />
												</div>
												<div class="hidden" for="help_for_ntp_server_enable">
													<span class="vexpl">
														<?=gettext("Provide a NTP server list to clients"); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_netbios_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NetBIOS Options"); ?></td>
											<td width="78%" class="vtable">
												<input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=!empty($pconfig['netbios_enable']) ? "checked=\"checked\"" : "" ;?> onclick="netbios_change()" />
												<div class="hidden" for="help_for_netbios_enable">
													<span class="vexpl">
													<?=gettext("Enable NetBIOS over TCP/IP"); ?><br />
													<?=gettext("If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled"); ?>.
													</span>
												</div>
												<div id="netbios_data" summary="netboios options">
													<span class="vexpl">
														<?=gettext("Node Type"); ?>:&nbsp;
													</span>
													<select name='netbios_ntype' class="form-control">
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
													<span class="vexpl">
														<?=gettext("Scope ID"); ?>:&nbsp;
													</span>
													<input name="netbios_scope" type="text" class="form-control unknown" id="netbios_scope" size="30" value="<?=$pconfig['netbios_scope'];?>" />
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
												<input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?=!empty($pconfig['wins_server1']) || !empty($pconfig['wins_server2']) ? "checked=\"checked\"" : "" ;?>  onclick="wins_server_change()" />
												<div class="hidden" for="help_for_wins_server">
													<span class="vexpl">
														<?=gettext("Provide a WINS server list to clients"); ?><br />
													</span>
												</div>
												<div id="wins_server_data" summary="wins servers">
													<span class="vexpl">
														<?=gettext("Server"); ?> #1:&nbsp;
													</span>
													<input name="wins_server1" type="text" class="form-control unknown" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>" />
													<span class="vexpl">
														<?=gettext("Server"); ?> #2:&nbsp;
													</span>
													<input name="wins_server2" type="text" class="form-control unknown" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>" />
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><a id="help_for_client_mgmt_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Client Management Port"); ?></td>
											<td width="78%" class="vtable">
												<input name="client_mgmt_port_enable" type="checkbox" id="client_mgmt_port_enable" value="yes" <?=!empty($pconfig['client_mgmt_port']) ? "checked=\"checked\"" : "" ;?> onclick="client_mgmt_port_change()" />
												<div id="client_mgmt_port_data" summary="client management port">
															<input name="client_mgmt_port" type="text" class="form-control unknown" id="client_mgmt_port" size="30" value="<?=htmlspecialchars($pconfig['client_mgmt_port']);?>" />
												</div>
												<div class="hidden" for="help_for_client_mgmt_port">
													<span class="vexpl">
				                  	<?=gettext("Use a different management port on clients. The default port is 166. Specify a different port if the client machines need to select from multiple OpenVPN links."); ?><br />
													</span>
												</div>
											</td>
										</tr>
										<tr>
											<td colspan="2" class="list" height="12"></td>
										</tr>
										<tr>
											<td colspan="2" valign="top" class="listtopic"><?=gettext("Advanced configuration"); ?></td>
										</tr>
										<tr id="client_opts">
											<td width="22%" valign="top" class="vncell"><a id="help_for_custom_options" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Advanced"); ?></td>
											<td width="78%" class="vtable">
												<textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea><br />
												<div class="hidden" for="help_for_custom_options">
													<?=gettext("Enter any additional options you would like to add to the OpenVPN server configuration here, separated by a semicolon"); ?><br />
													<?=gettext("EXAMPLE: push \"route 10.0.0.0 255.255.255.0\""); ?>;
												</div>
											</td>
										</tr>
										<tr id="comboboxVerbosityLevel">
											<td width="22%" valign="top" class="vncell"><a id="help_for_verbosity_level" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Verbosity level");?></td>
											<td width="78%" class="vtable">
												<select name="verbosity_level" class="form-control">
												<?php
                                                foreach ($openvpn_verbosity_level as $verb_value => $verb_desc) :
                                                    $selected = "";
                                                    if ($pconfig['verbosity_level'] == $verb_value) {
                                                        $selected = "selected=\"selected\"";
                                                    }
                                                ?>
                                                <option value="<?=$verb_value;?>" <?=$selected;?>><?=$verb_desc;?></option>
												<?php
                                                endforeach; ?>
												</select>
												<div class="hidden" for="help_for_verbosity_level">
													<?=gettext("Each level shows all info from the previous levels. Level 3 is recommended if you want a good summary of what's happening without being swamped by output"); ?>.<br /> <br />
													<strong>none</strong> -- <?=gettext("No output except fatal errors"); ?>. <br />
													<strong>default</strong>-<strong>4</strong> -- <?=gettext("Normal usage range"); ?>. <br />
													<strong>5</strong> -- <?=gettext("Output R and W characters to the console for each packet read and write, uppercase is used for TCP/UDP packets and lowercase is used for TUN/TAP packets"); ?>. <br />
													<strong>6</strong>-<strong>11</strong> -- <?=gettext("Debug info range"); ?>.
												</div>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top">&nbsp;</td>
											<td width="78%">
												<input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
												<input name="act" type="hidden" value="<?=$act;?>" />
												<?php if (isset($id) && $a_server[$id]) :
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
								<table class="table table-striped table-sort sortable">
									<thead>
									<tr>
										<td width="10%" class="listhdrr"><?=gettext("Disabled"); ?></td>
										<td width="10%" class="listhdrr"><?=gettext("Protocol / Port"); ?></td>
										<td width="30%" class="listhdrr"><?=gettext("Tunnel Network"); ?></td>
										<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
										<td width="10%" class="list"></td>
									</tr>
									</thead>

									<tbody>
									<?php
                                        $i = 0;
                                    foreach ($a_server as $server) :
                                        $disabled = "NO";
                                        if (!empty($server['disable'])) {
                                            $disabled = "YES";
                                        }
                                    ?>
									<tr>
                                    <td class="listlr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
                                        <?=$disabled;?>
                                    </td>
                                    <td class="listr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
                                        <?=htmlspecialchars($server['protocol']);
?> / <?=htmlspecialchars($server['local_port']);?>
                                    </td>
                                    <td class="listr" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
                                        <?=htmlspecialchars($server['tunnel_network']);?><br />
                                        <?=htmlspecialchars($server['tunnel_networkv6']);?><br />
                                    </td>
                                    <td class="listbg" ondblclick="document.location='vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>'">
                                        <?=htmlspecialchars($server['description']);?>
                                    </td>
                                    <td valign="middle" class="list nowrap">
                                        <a href="vpn_openvpn_server.php?act=edit&amp;id=<?=$i;
?>" title="<?=gettext("edit server"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                                        &nbsp;
                                        <a id="del_<?=$i;?>" title="<?=gettext("delete server"); ?>" class="act_delete btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
                                    </td>
									</tr>
									<?php
                                    $i++;
                                    endforeach;
                                    ?>
									<tr style="display:none;"><td></td></tr>
									</tbody>
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
