<?php 
/*
	vpn_openvpn_export_shared.php

	Copyright (C) 2008 Shrew Soft Inc.
	Copyright (C) 2010 Ermal Luçi
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

	DISABLE_PHP_LINT_CHECKING
*/

require("globals.inc");
require("guiconfig.inc");
require("openvpn-client-export.inc");

$pgtitle = array("OpenVPN", "Client Export Utility");

if (!is_array($config['openvpn']['openvpn-server']))
	$config['openvpn']['openvpn-server'] = array();

$a_server = $config['openvpn']['openvpn-server'];

$ras_server = array();
foreach($a_server as $sindex => $server) {
	if (isset($server['disable']))
		continue;
	$ras_user = array();
	if ($server['mode'] != "p2p_shared_key")
		continue;

	$ras_serverent = array();
	$prot = $server['protocol'];
	$port = $server['local_port'];
	if ($server['description'])
		$name = "{$server['description']} {$prot}:{$port}";
	else
		$name = "Shared Key Server {$prot}:{$port}";
	$ras_serverent['index'] = $sindex;
	$ras_serverent['name'] = $name;
	$ras_serverent['mode'] = $server['mode'];
	$ras_server[] = $ras_serverent;
}

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

$error = false;

if(($act == "skconf") || ($act == "skzipconf")) {
	$srvid = $_GET['srvid'];
	if (($srvid === false) || ($config['openvpn']['openvpn-server'][$srvid]['mode'] != "p2p_shared_key")) {
		pfSenseHeader("vpn_openvpn_export.php");
		exit;
	}

	if (empty($_GET['useaddr'])) {
		$error = true;
		$input_errors[] = "You need to specify an IP or hostname.";
	} else
		$useaddr = $_GET['useaddr'];

	$proxy = "";
	if (!empty($_GET['proxy_addr']) || !empty($_GET['proxy_port'])) {
		$proxy = array();
		if (empty($_GET['proxy_addr'])) {
			$error = true;
			$input_errors[] = "You need to specify an address for the proxy port.";
		} else
			$proxy['ip'] = $_GET['proxy_addr'];
		if (empty($_GET['proxy_port'])) {
			$error = true;
			$input_errors[] = "You need to specify a port for the proxy ip.";
		} else
			$proxy['port'] = $_GET['proxy_port'];
		$proxy['proxy_type'] = $_GET['proxy_type'];
		$proxy['proxy_authtype'] = $_GET['proxy_authtype'];
		if ($_GET['proxy_authtype'] != "none") {
			if (empty($_GET['proxy_user'])) {
				$error = true;
				$input_errors[] = "You need to specify a username with the proxy config.";
			} else
				$proxy['user'] = $_GET['proxy_user'];
			if (!empty($_GET['proxy_user']) && empty($_GET['proxy_password'])) {
				$error = true;
				$input_errors[] = "You need to specify a password with the proxy user.";
			} else
				$proxy['password'] = $_GET['proxy_password'];
		}
	}

	$exp_name = openvpn_client_export_prefix($srvid);
	if ($act == "skzipconf")
		$zipconf = true;
	$exp_data = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, $zipconf);
	if (!$exp_data) {
		$input_errors[] = "Failed to export config files!";
		$error = true;
	}
	if (!$error) {
		if ($zipconf) {
			$exp_name = urlencode($exp_data);
			$exp_size = filesize("{$g['tmp_path']}/{$exp_data}");
		} else {
			$exp_name = urlencode($exp_name."-config.ovpn");
			$exp_size = strlen($exp_data);
		}

		header('Pragma: ');
		header('Cache-Control: ');
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename={$exp_name}");
		header("Content-Length: $exp_size");
		if ($zipconf)
			readfile("{$g['tmp_path']}/{$exp_data}");
		else
			echo $exp_data;

		@unlink("{$g['tmp_path']}/{$exp_data}");
		exit;
	}
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
var viscosityAvailable = false;

var servers = new Array();
<?php	foreach ($ras_server as $sindex => $server): ?>
servers[<?=$sindex;?>] = new Array();
servers[<?=$sindex;?>][0] = '<?=$server['index'];?>';
servers[<?=$sindex;?>][1] = new Array();
servers[<?=$sindex;?>][2] = '<?=$server['mode'];?>';
<?	endforeach; ?>

function download_begin(act) {

	var index = document.getElementById("server").selectedIndex;
	var useaddr;

	if (document.getElementById("useaddr").value == "other") {
		if (document.getElementById("useaddr_hostname").value == "") {
			alert("Please specify an IP address or hostname.");
			return;
		}
		useaddr = document.getElementById("useaddr_hostname").value;
	} else
		useaddr = document.getElementById("useaddr").value;

	var useproxy = 0;
	var useproxypass = 0;
	if (document.getElementById("useproxy").checked)
		useproxy = 1;

	var proxyaddr = document.getElementById("proxyaddr").value;
	var proxyport = document.getElementById("proxyport").value;
	if (useproxy) {
		if (!proxyaddr || !proxyport) {
			alert("The proxy ip and port cannot be empty");
			return;
		}

		if (document.getElementById("useproxypass").value != 'none')
			useproxypass = 1;

		var proxytype = document.getElementById("useproxytype").value;

		var proxyauth = document.getElementById("useproxypass").value;
		var proxyuser = document.getElementById("proxyuser").value;
		var proxypass = document.getElementById("proxypass").value;
		var proxyconf = document.getElementById("proxyconf").value;
		if (useproxypass) {
			if (!proxyuser) {
				alert("Please fill the proxy username and password.");
				return;
			}
			if (!proxypass || !proxyconf) {
				alert("The proxy password or confirm field is empty");
				return;
			}
			if (proxypass != proxyconf) {
				alert("The proxy password and confirm fields must match");
				return;
			}
		}
	}

	var dlurl;
	dlurl  = "/vpn_openvpn_export_shared.php?act=" + act;
	dlurl += "&srvid=" + servers[index][0];
	dlurl += "&useaddr=" + useaddr;
	if (useproxy) {
		dlurl += "&proxy_type=" + escape(proxytype);
		dlurl += "&proxy_addr=" + proxyaddr;
		dlurl += "&proxy_port=" + proxyport;
		dlurl += "&proxy_authtype=" + proxyauth;
		if (useproxypass) {
			dlurl += "&proxy_user=" + proxyuser;
			dlurl += "&proxy_password=" + proxypass;
		}
	}

	window.open(dlurl,"_self");
}

function server_changed() {

	var table = document.getElementById("clients");
	while (table.rows.length > 1 )
		table.deleteRow(1);

	var index = document.getElementById("server").selectedIndex;

	if (servers[index][2] == 'p2p_shared_key') {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		cell0.className = "listlr";
		cell0.innerHTML = "Other Shared Key OS Client";
		cell1.className = "listr";
		cell1.innerHTML = "<a href='javascript:download_begin(\"skconf\")'>Configuration<\/a>";
		cell1.innerHTML += "<br\/>";
		cell1.innerHTML += "<a href='javascript:download_begin(\"skzipconf\")'>Configuration archive<\/a>";
	}
}

function useaddr_changed(obj) {

	if (obj.value == "other")
		$('HostName').show();
	else
		$('HostName').hide();
	
}

function useproxy_changed(obj) {

	if ((obj.id == "useproxy" && obj.checked) ||
		(obj.id == "useproxypass" && (obj.value != 'none'))) {
		$(obj.id + '_opts').show();
	} else {
		$(obj.id + '_opts').hide();
	}
}
//]]>
</script>
<?php
	if ($input_errors)
		print_input_errors($input_errors);
	if ($savemsg)
		print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="openvpn export shared">
 	<tr>
		<td>
			<?php 
				$tab_array = array();
				$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
				$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
				$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
				$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
				$tab_array[] = array(gettext("Client Export"), false, "vpn_openvpn_export.php");
				$tab_array[] = array(gettext("Shared Key Export"), true, "vpn_openvpn_export_shared.php");
				display_top_tabs($tab_array);
			?>
		</td>
	</tr>
	<tr>
		<td id="mainarea">
			<div class="tabcont">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
					<tr>
						<td width="22%" valign="top" class="vncellreq">Shared Key Server</td>
						<td width="78%" class="vtable">
							<select name="server" id="server" class="formselect" onchange="server_changed()">
								<?php foreach($ras_server as & $server): ?>
								<option value="<?=$server['sindex'];?>"><?=$server['name'];?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Host Name Resolution</td>
						<td width="78%" class="vtable">
							<table border="0" cellpadding="2" cellspacing="0" summary="host name resolution">
								<tr>
									<td>
										<select name="useaddr" id="useaddr" class="formselect" onchange="useaddr_changed(this)">
											<option value="serveraddr" >Interface IP Address</option>
											<option value="serverhostname" >Installation hostname</option>
											<?php if (is_array($config['dyndnses']['dyndns'])): ?>
												<?php foreach ($config['dyndnses']['dyndns'] as $ddns): ?>
													<option value="<?php echo $ddns["host"] ?>">DynDNS: <?php echo $ddns["host"] ?></option>
												<?php endforeach; ?>
											<?php endif; ?>
											<option value="other">Other</option>
										</select>
										<br />
										<div style="display:none;" id="HostName">
											<input name="useaddr_hostname" id="useaddr_hostname" size="40" />
											<span class="vexpl">
												Enter the hostname or IP address the client will use to connect to this server.
											</span>
										</div>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncell">Use Proxy</td>
						<td width="78%" class="vtable">
							 <table border="0" cellpadding="2" cellspacing="0" summary="http proxy">
								<tr>
									<td>
										<input name="useproxy" id="useproxy" type="checkbox" value="yes" onclick="useproxy_changed(this)" />

									</td>
									<td>
										<span class="vexpl">
											Use proxy to communicate with the server.
										</span>
									</td>
								</tr>
							</table>
							<table border="0" cellpadding="2" cellspacing="0" id="useproxy_opts" style="display:none" summary="user options">
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;     Type :&nbsp;
										</span>
									</td>
									<td>
										<select name="useproxytype" id="useproxytype" class="formselect">
											<option value="http">HTTP</option>
											<option value="socks">Socks</option>
										</select>
									</td>
								</tr>
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;     IP Address :&nbsp;
										</span>
									</td>
									<td>
										<input name="proxyaddr" id="proxyaddr" class="formfld unknown" size="30" value="" />
									</td>
								</tr>
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;      Port :&nbsp;
										</span>
									</td>
														<td>
										<input name="proxyport" id="proxyport" class="formfld unknown" size="5" value="" />
									</td>
								</tr>
								<tr>
									<td width="25%">
							<br />
									</td>
									<td>
										<select name="useproxypass" id="useproxypass" class="formselect" onchange="useproxy_changed(this)">
											<option value="none">none</option>
											<option value="basic">basic</option>
											<option value="ntlm">ntlm</option>
										</select>
										<span class="vexpl">
											Choose proxy authentication if any.
										</span>
							<br />
							<table border="0" cellpadding="2" cellspacing="0" id="useproxypass_opts" style="display:none" summary="name and password">
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;Username :&nbsp;
										</span>
									</td>
									<td>
										<input name="proxyuser" id="proxyuser" class="formfld unknown" size="20" value="" />
									</td>
								</tr>
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;Password :&nbsp;
										</span>
									</td>
									<td>
										<input name="proxypass" id="proxypass" type="password" class="formfld pwd" size="20" value="" />
									</td>
								</tr>
								<tr>
									<td align="right" width="25%">
										<span class="vexpl">
											 &nbsp;Confirm :&nbsp;
										</span>
									</td>
														<td>
										<input name="proxyconf" id="proxyconf" type="password" class="formfld pwd" size="20" value="" />
									</td>
								</tr>
							</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2" valign="top" class="listtopic">Client Configuration Packages</td>
					</tr>
				</table>
				<table width="100%" id="clients" border="0" cellpadding="0" cellspacing="0" summary="heading">
					<tr>
						<td width="25%" class="listhdrr"><?=gettext("Client Type");?></td>
						<td width="50%" class="listhdrr"><?=gettext("Export");?></td>
					</tr>
				</table>
				<table width="100%" border="0" cellpadding="5" cellspacing="10" summary="note">
					<tr>
						<td align="right" valign="top" width="5%"><?= gettext("NOTE:") ?></td>
						<td><?= gettext("These are shared key configurations for use in site-to-site tunnels with other routers. Shared key tunnels are not normally used for remote access connections to end users.") ?></td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>
<script type="text/javascript">
//<![CDATA[
server_changed();
//]]>
</script>
<?php include("fend.inc"); ?>
</body>
</html>
