<?php
/*
	vpn_openvpn_export.php

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
require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("filter.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");
require_once("openvpn-client-export.inc");

global $current_openvpn_version, $current_openvpn_version_rev;

$pgtitle = array("OpenVPN", "Client Export Utility");

$ras_server = array();
if (isset($config['openvpn']['openvpn-server'])) {
    // collect info
    foreach ($config['openvpn']['openvpn-server'] as $sindex => $server) {
        if (isset($server['disable'])) {
            continue;
        }
        $ras_user = array();
        $ras_certs = array();
        if (stripos($server['mode'], "server") === false) {
            continue;
        }
        if (($server['mode'] == "server_tls_user") && ($server['authmode'] == "Local Database")) {
            if (isset($config['system']['user'])) {
                foreach ($config['system']['user'] as $uindex => $user) {
                    if (!isset($user['cert'])) {
                        continue;
                    }
                    foreach ($user['cert'] as $cindex => $cert) {
                        // If $cert is not an array, it's a certref not a cert.
                        if (!is_array($cert)) {
                            $cert = lookup_cert($cert);
                        }

                        if ($cert['caref'] != $server['caref']) {
                            continue;
                        }
                        $ras_userent = array();
                        $ras_userent['uindex'] = $uindex;
                        $ras_userent['cindex'] = $cindex;
                        $ras_userent['name'] = $user['name'];
                        $ras_userent['certname'] = $cert['descr'];
                        $ras_user[] = $ras_userent;
                    }
                }
            }
        } elseif (($server['mode'] == "server_tls") || (($server['mode'] == "server_tls_user") && ($server['authmode'] != "Local Database"))) {
            if (isset($config['cert'])) {
                foreach ($config['cert'] as $cindex => $cert) {
                    if (($cert['caref'] != $server['caref']) || ($cert['refid'] == $server['certref'])) {
                        continue;
                    }
                    $ras_cert_entry['cindex'] = $cindex;
                    $ras_cert_entry['certname'] = $cert['descr'];
                    $ras_cert_entry['certref'] = $cert['refid'];
                    $ras_certs[] = $ras_cert_entry;
                }
            }
        }

        $ras_serverent = array();
        $prot = $server['protocol'];
        $port = $server['local_port'];
        if ($server['description']) {
            $name = "{$server['description']} {$prot}:{$port}";
        } else {
            $name = "Server {$prot}:{$port}";
        }
        $ras_serverent['index'] = $sindex;
        $ras_serverent['name'] = $name;
        $ras_serverent['users'] = $ras_user;
        $ras_serverent['certs'] = $ras_certs;
        $ras_serverent['mode'] = $server['mode'];
        $ras_server[] = $ras_serverent;
    }

    // handle request export..
    if (!empty($_GET['act'])) {
        $input_errors = array();
        $exp_path = false;
        $act = $_GET['act'];
        $srvid = isset($_GET['srvid']) ? $_GET['srvid'] : false;
        $usrid = isset($_GET['usrid']) ? $_GET['usrid'] : false;
        $crtid = isset($_GET['crtid']) ? $_GET['crtid'] : false;
        if ($srvid === false) {
            redirectHeader("vpn_openvpn_export.php");
            exit;
        } elseif (($config['openvpn']['openvpn-server'][$srvid]['mode'] != "server_user") &&
                 (($usrid === false) || ($crtid === false))) {
            redirectHeader("vpn_openvpn_export.php");
            exit;
        }

        if ($config['openvpn']['openvpn-server'][$srvid]['mode'] == "server_user") {
            $nokeys = true;
        } else {
            $nokeys = false;
        }

        $useaddr = '';
        if (isset($_GET['useaddr']) && !empty($_GET['useaddr'])) {
            $useaddr = trim($_GET['useaddr']);
        }

        if (!(is_ipaddr($useaddr) || is_hostname($useaddr) ||
            in_array($useaddr, array("serveraddr", "servermagic", "servermagichost", "serverhostname")))) {
            $input_errors[] = gettext("You need to specify an IP or hostname.");
        }

        $advancedoptions = isset($_GET['advancedoptions']) ? $_GET['advancedoptions'] : null;
        $openvpnmanager = isset($_GET['openvpnmanager']) ? $_GET['openvpnmanager'] : null;

        $verifyservercn = isset($_GET['verifyservercn']) ? $_GET['verifyservercn'] : null;
        $randomlocalport = isset($_GET['randomlocalport']) ? $_GET['randomlocalport'] : null;
        $usetoken = $_GET['usetoken'];
        if ($usetoken && (substr($act, 0, 10) == "confinline")) {
            $input_errors[] = gettext("You cannot use Microsoft Certificate Storage with an Inline configuration.");
        }
        if ($usetoken && (($act == "conf_yealink_t28") || ($act == "conf_yealink_t38g") || ($act == "conf_yealink_t38g2") || ($act == "conf_snom"))) {
            $input_errors[] = gettext("You cannot use Microsoft Certificate Storage with a Yealink or SNOM configuration.");
        }
        $password = "";
        if (!empty($_GET['password'])) {
            $password = $_GET['password'];
        }

        $proxy = "";
        if (!empty($_GET['proxy_addr']) || !empty($_GET['proxy_port'])) {
            $proxy = array();
            if (empty($_GET['proxy_addr'])) {
                $input_errors[] = gettext("You need to specify an address for the proxy port.");
            } else {
                $proxy['ip'] = $_GET['proxy_addr'];
            }
            if (empty($_GET['proxy_port'])) {
                $input_errors[] = gettext("You need to specify a port for the proxy ip.");
            } else {
                $proxy['port'] = $_GET['proxy_port'];
            }
            if (isset($_GET['proxy_type'])) {
                $proxy['proxy_type'] = $_GET['proxy_type'];
            }
            if (isset($_GET['proxy_authtype'])) {
                $proxy['proxy_authtype'] = $_GET['proxy_authtype'];
                if ($_GET['proxy_authtype'] != "none") {
                    if (empty($_GET['proxy_user'])) {
                        $input_errors[] = gettext("You need to specify a username with the proxy config.");
                    } else {
                        $proxy['user'] = $_GET['proxy_user'];
                    }
                    if (!empty($_GET['proxy_user']) && empty($_GET['proxy_password'])) {
                        $input_errors[] = gettext("You need to specify a password with the proxy user.");
                    } else {
                        $proxy['password'] = $_GET['proxy_password'];
                    }
                }
            }
        }

        $exp_name = openvpn_client_export_prefix($srvid, $usrid, $crtid);

        if (substr($act, 0, 4) == "conf") {
            switch ($act) {
                case "confzip":
                    $exp_name = urlencode($exp_name."-config.zip");
                    $expformat = "zip";
                    break;
                case "conf_yealink_t28":
                    $exp_name = urlencode("client.tar");
                    $expformat = "yealink_t28";
                    break;
                case "conf_yealink_t38g":
                    $exp_name = urlencode("client.tar");
                    $expformat = "yealink_t38g";
                    break;
                case "conf_yealink_t38g2":
                    $exp_name = urlencode("client.tar");
                    $expformat = "yealink_t38g2";
                    break;
                case "conf_snom":
                    $exp_name = urlencode("vpnclient.tar");
                    $expformat = "snom";
                    break;
                case "confinline":
                    $exp_name = urlencode($exp_name."-config.ovpn");
                    $expformat = "inline";
                    break;
                case "confinlinedroid":
                    $exp_name = urlencode($exp_name."-android-config.ovpn");
                    $expformat = "inlinedroid";
                    break;
                case "confinlineios":
                    $exp_name = urlencode($exp_name."-ios-config.ovpn");
                    $expformat = "inlineios";
                    break;
                default:
                    $exp_name = urlencode($exp_name."-config.ovpn");
                    $expformat = "baseconf";
            }
            $exp_path = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $nokeys, $proxy, $expformat, $password, false, false, $openvpnmanager, $advancedoptions);
        }

        if ($act == "visc") {
            $exp_name = urlencode($exp_name."-Viscosity.visc.zip");
            $exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions);
        }

        if (substr($act, 0, 4) == "inst") {
            $exp_name = urlencode($exp_name."-install.exe");
            $exp_path = openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, substr($act, 5));
        }

        if (!$exp_path) {
            $input_errors[] = gettext("Failed to export config files!");
        }

        if (count($input_errors) == 0) {
            if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
                $exp_size = strlen($exp_path);
            } else {
                $exp_size = filesize($exp_path);
            }
            header('Pragma: ');
            header('Cache-Control: ');
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$exp_name}");
            header("Content-Length: $exp_size");
            if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
                echo $exp_path;
            } else {
                readfile($exp_path);
                @unlink($exp_path);
            }
            exit;
        }
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
<?php foreach ($ras_server as $sindex => $server) :
?>
servers[<?=$sindex;?>] = new Array();
servers[<?=$sindex;
?>][0] = '<?=$server['index'];?>';
servers[<?=$sindex;?>][1] = new Array();
servers[<?=$sindex;
?>][2] = '<?=$server['mode'];?>';
servers[<?=$sindex;?>][3] = new Array();
<?php	  foreach ($server['users'] as $uindex => $user) :
?>
servers[<?=$sindex;
?>][1][<?=$uindex;?>] = new Array();
servers[<?=$sindex;
?>][1][<?=$uindex;
?>][0] = '<?=$user['uindex'];?>';
servers[<?=$sindex;
?>][1][<?=$uindex;
?>][1] = '<?=$user['cindex'];?>';
servers[<?=$sindex;
?>][1][<?=$uindex;
?>][2] = '<?=$user['name'];?>';
servers[<?=$sindex;
?>][1][<?=$uindex;
?>][3] = '<?=str_replace("'", "\\'", $user['certname']);?>';
<?
endforeach; ?>
<?php	  $c=0;
foreach ($server['certs'] as $cert) :
?>
servers[<?=$sindex;
?>][3][<?=$c;?>] = new Array();
servers[<?=$sindex;
?>][3][<?=$c;
?>][0] = '<?=$cert['cindex'];?>';
servers[<?=$sindex;
?>][3][<?=$c;
?>][1] = '<?=str_replace("'", "\\'", $cert['certname']);?>';
<?php $c++; endforeach; ?>
<?php endforeach; ?>

function download_begin(act, i, j) {

	var index = document.getElementById("server").selectedIndex;
	var users = servers[index][1];
	var certs = servers[index][3];
	var useaddr;

	var advancedoptions;

	if (document.getElementById("useaddr").value == "other") {
		if (document.getElementById("useaddr_hostname").value == "") {
			alert("<?=gettext('Please specify an IP address or hostname.') ?>");
			return;
		}
		useaddr = document.getElementById("useaddr_hostname").value;
	} else
		useaddr = document.getElementById("useaddr").value;

	advancedoptions = document.getElementById("advancedoptions").value;

	var verifyservercn;
	verifyservercn = document.getElementById("verifyservercn").value;

	var randomlocalport = 0;
	if (document.getElementById("randomlocalport").checked)
		randomlocalport = 1;
	var usetoken = 0;
	if (document.getElementById("usetoken").checked)
		usetoken = 1;
	var usepass = 0;
	if (document.getElementById("usepass").checked)
		usepass = 1;
	var openvpnmanager = 0;
	if (document.getElementById("openvpnmanager").checked)
		openvpnmanager = 1;

	var pass = document.getElementById("pass").value;
	var conf = document.getElementById("conf").value;
	if (usepass && (act.substring(0,4) == "inst")) {
		if (!pass || !conf) {
			alert("<?=gettext('The password or confirm field is empty') ?>");
			return;
		}
		if (pass != conf) {
			alert("<?=gettext('The password and confirm fields must match') ?>");
			return;
		}
	}

	var useproxy = 0;
	var useproxypass = 0;
	if (document.getElementById("useproxy").checked)
		useproxy = 1;

	var proxyaddr = document.getElementById("proxyaddr").value;
	var proxyport = document.getElementById("proxyport").value;
	if (useproxy) {
		if (!proxyaddr || !proxyport) {
			alert("<?=gettext('The proxy ip and port cannot be empty') ?>");
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
				alert("<?=gettext('Please fill the proxy username and password.') ?>");
				return;
			}
			if (!proxypass || !proxyconf) {
				alert("<?=gettext('The proxy password or confirm field is empty') ?>");
				return;
			}
			if (proxypass != proxyconf) {
				alert("<?=gettext('The proxy password and confirm fields must match') ?>");
				return;
			}
		}
	}

	var dlurl;
	dlurl  = "/vpn_openvpn_export.php?act=" + act;
	dlurl += "&srvid=" + escape(servers[index][0]);
	if (users[i]) {
		dlurl += "&usrid=" + escape(users[i][0]);
		dlurl += "&crtid=" + escape(users[i][1]);
	}
	if (certs[j]) {
		dlurl += "&usrid=";
		dlurl += "&crtid=" + escape(certs[j][0]);
	}
	dlurl += "&useaddr=" + escape(useaddr);
	dlurl += "&verifyservercn=" + escape(verifyservercn);
	dlurl += "&randomlocalport=" + escape(randomlocalport);
	dlurl += "&openvpnmanager=" + escape(openvpnmanager);
	dlurl += "&usetoken=" + escape(usetoken);
	if (usepass)
		dlurl += "&password=" + escape(pass);
	if (useproxy) {
		dlurl += "&proxy_type=" + escape(proxytype);
		dlurl += "&proxy_addr=" + escape(proxyaddr);
		dlurl += "&proxy_port=" + escape(proxyport);
		dlurl += "&proxy_authtype=" + escape(proxyauth);
		if (useproxypass) {
			dlurl += "&proxy_user=" + escape(proxyuser);
			dlurl += "&proxy_password=" + escape(proxypass);
		}
	}

	dlurl += "&advancedoptions=" + escape(advancedoptions);

	window.open(dlurl,"_self");
}

function server_changed() {

	var table = document.getElementById("users");
	while (table.rows.length > 1 )
		table.deleteRow(1);

	var index = document.getElementById("server").selectedIndex;
	var users = servers[index][1];
	var certs = servers[index][3];
	for (i=0; i < users.length; i++) {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.innerHTML = users[i][2];
		cell1.innerHTML = users[i][3];
		cell2.innerHTML = "- Standard Configurations:<br\/>";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confzip\"," + i + ", -1)'>Archive</button>";
    cell2.innerHTML += "&nbsp;&nbsp;";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf\"," + i + ", -1)'>Config Only</button>";
		cell2.innerHTML += "<br\/>- Inline Configurations:<br\/>";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinlinedroid\"," + i + ", -1)'>Android</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinlineios\"," + i + ", -1)'>OpenVPN Connect (iOS/Android)</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinline\"," + i + ", -1)'>Others</button>";
		cell2.innerHTML += "<br\/>- Windows Installers (<?php echo $current_openvpn_version . '-Ix' . $current_openvpn_version_rev;?>):<br\/>";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x86-xp\"," + i + ", -1)'>x86-xp</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x64-xp\"," + i + ", -1)'>x64-xp</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x86-win6\"," + i + ", -1)'>x86-win6</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x64-win6\"," + i + ", -1)'>x64-win6</button>";
		cell2.innerHTML += "<br\/>- Mac OSX:<br\/>";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"visc\"," + i + ", -1)'>Viscosity Bundle</button>";
	}
	for (j=0; j < certs.length; j++) {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		if (servers[index][2] == "server_tls") {
			cell0.innerHTML = "Certificate (SSL/TLS, no Auth)";
		} else {
			cell0.innerHTML = "Certificate with External Auth";
		}
		cell1.innerHTML = certs[j][1];
		cell2.innerHTML = "- Standard Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confzip\",-1," + j + ")'>Archive</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf\",-1," + j + ")'>File Only</button>";
		cell2.innerHTML += "<br\/>- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinlinedroid\",-1," + j + ")'>Android</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinlineios\",-1," + j + ")'>OpenVPN Connect (iOS/Android)</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinline\",-1," + j + ")'>Others</button>";
		cell2.innerHTML += "<br\/>- Windows Installers (<?php echo $current_openvpn_version . '-Ix' . $current_openvpn_version_rev;?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x86-xp\",-1," + j + ")'>x86-xp</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x64-xp\",-1," + j + ")'>x64-xp</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x86-win6\",-1," + j + ")'>x86-win6</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x64-win6\",-1," + j + ")'>x64-win6</button>";
		cell2.innerHTML += "<br\/>- Mac OSX:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"visc\",-1," + j + ")'>Viscosity Bundle</button>";
		if (servers[index][2] == "server_tls") {
			cell2.innerHTML += "<br\/>- Yealink SIP Handsets: <br\/>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
      cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf_yealink_t28\",-1," + j + ")'>T28</button>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
      cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf_yealink_t38g\",-1," + j + ")'>T38G (1)</button>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
      cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf_yealink_t38g\",-1," + j + ")'>T38G (1)</button>";
			cell2.innerHTML += "<br\/>";
      cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf_snom\",-1," + j + ")'>SNOM SIP Handset</button>";
		}
	}
	if (servers[index][2] == 'server_user') {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.innerHTML = "Authentication Only (No Cert)";
		cell1.innerHTML = "none";
		cell2.innerHTML = "- Standard Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confzip\"," + i + ")'>Archive</button>";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\"," + i + ")'>Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"conf\"," + i + ")'>File Only</button>";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\"," + i + ")'>File Only<\/a>";
		cell2.innerHTML += "<br\/>- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinlinedroid\"," + i + ")'>Android</button>";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\"," + i + ")'>Android<\a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinlineios\"," + i + ")'>OpenVPN Connect (iOS/Android)</button>";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineios\"," + i + ")'>OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"confinline\"," + i + ")'>Others</button>";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\"," + i + ")'>Others<\/a>";
		cell2.innerHTML += "<br\/>- Windows Installers (<?php echo $current_openvpn_version . '-Ix' . $current_openvpn_version_rev;?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x86-xp\"," + i + ")'>x86-xp</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x64-xp\"," + i + ")'>x64-xp</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x86-win6\"," + i + ")'>x86-win6</button>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"inst-x64-win6\"," + i + ")'>x64-win6</button>";
		cell2.innerHTML += "<br\/>- Mac OSX:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
    cell2.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"visc\"," + i + ")'>Viscosity Bundle</button>";
	}
}

function useaddr_changed(obj) {

	if (obj.value == "other")
		$('#HostName').show();
	else
		$('#HostName').hide();

}

function usepass_changed() {

	if (document.getElementById("usepass").checked)
		document.getElementById("usepass_opts").style.display = "";
	else
		document.getElementById("usepass_opts").style.display = "none";
}

function useproxy_changed(obj) {

  if ($('#useproxy').prop( "checked" ) ){
      $('#useproxy_opts').show();
  } else {
      $('#useproxy_opts').hide();
  }

  if ($( "#useproxypass option:selected" ).text() != 'none') {
      $('#useproxypass_opts').show();
  } else {
      $('#useproxypass_opts').hide();
  }
}
//]]>
</script>
<?php
if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
}
if (isset($savemsg)) {
    print_info_box($savemsg);
}
?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <?php
                  $tab_array = array();
                  $tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
                  $tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
                  $tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
                  $tab_array[] = array(gettext("Client Export"), true, "vpn_openvpn_export.php");
                  $tab_array[] = array(gettext("Shared Key Export"), false, "vpn_openvpn_export_shared.php");
                  display_top_tabs($tab_array);
                ?>
        <div class="tab-content content-box col-xs-12">
          <div class="table-responsive">
            <table width="100%" border="0" class="table table-striped" cellpadding="0" cellspacing="0">
              <tr>
                <td width="22%"></td>
                <td width="78%" align="right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                </td>
              </tr>
					<tr>
						<td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Remote Access Server");?></td>
						<td>
							<select name="server" id="server" class="formselect" onchange="server_changed()">
								<?php foreach ($ras_server as & $server) :
    ?>
								<option value="<?=$server['index'];
?>"><?=htmlspecialchars($server['name']);?></option>
								<?php
endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Host Name Resolution");?></td>
						<td >
                  <select name="useaddr" id="useaddr" class="formselect" onchange="useaddr_changed(this)">
                    <option value="serveraddr" ><?=gettext("Interface IP Address");?></option>
                    <option value="servermagic" ><?=gettext("Automagic Multi-WAN IPs (port forward targets)");?></option>
                    <option value="servermagichost" ><?=gettext("Automagic Multi-WAN dynamic DNS Hostnames (port forward targets)");?></option>
                    <option value="serverhostname" ><?=gettext("Installation hostname");?></option>
                    <?php if (isset($config['dyndnses']['dyndns'])) :
?>
                        <?php foreach ($config['dyndnses']['dyndns'] as $ddns) :
?>
                        <option value="<?php echo $ddns["host"] ?>"><?=gettext("Dynamic DNS");
?>: <?= htmlspecialchars($ddns["host"]); ?></option>
                        <?php
endforeach; ?>
                    <?php
endif; ?>
                    <?php if (isset($config['dnsupdates']['dnsupdate'])) :
?>
                        <?php foreach ($config['dnsupdates']['dnsupdate'] as $ddns) :
?>
                        <option value="<?php echo $ddns["host"] ?>"><?=gettext("Dynamic DNS");
?>: <?= htmlspecialchars($ddns["host"]); ?></option>
                        <?php
endforeach; ?>
                    <?php
endif; ?>
                    <option value="other"><?=gettext("Other");?></option>
                  </select>
                  <div id="HostName" style="display:none;" >
                    <div>
                        <?=gettext("Enter the hostname or IP address the client will use to connect to this server.");?>
                    </div>
                    <input name="useaddr_hostname" type="text" id="useaddr_hostname" size="40" />
                  </div>
						</td>
					</tr>
					<tr>
						<td valign="top"><a id="help_for_verify_server_cn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Verify Server CN");?></td>
						<td >
                  <select name="verifyservercn" id="verifyservercn" class="formselect">
                    <option value="auto"><?=gettext("Automatic - Use verify-x509-name (OpenVPN 2.3+) where possible");?></option>
                    <option value="tls-remote"><?=gettext("Use tls-remote (Deprecated, use only on old clients &lt;= OpenVPN 2.2.x");?>)</option>
                    <option value="tls-remote-quote"><?=gettext("Use tls-remote and quote the server CN");?></option>
                    <option value="none"><?=gettext("Do not verify the server CN");?></option>
                  </select>
                  <div class="hidden" for="help_for_verify_server_cn">
                    <?=gettext("Optionally verify the server certificate Common Name (CN) when the client connects. Current clients, including the most recent versions of Windows, Viscosity, Tunnelblick, OpenVPN on iOS and Android and so on should all work at the default automatic setting.");?><br/><br/>
                    <?=gettext("Only use tls-remote if you must use an older client that you cannot control. The option has been deprecated by OpenVPN and will be removed in the next major version.");?><br/><br/>
                    <?=gettext("With tls-remote the server CN may optionally be enclosed in quotes. This can help if the server CN contains spaces and certain clients cannot parse the server CN. Some clients have problems parsing the CN with quotes. Use only as needed.");?>
                  </div>
						</td>
					</tr>
					<tr>
						<td valign="top"><a id="help_for_random_local_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Random Local Port");?></td>
						<td >
                  <input name="randomlocalport" id="randomlocalport" type="checkbox" value="yes" checked="CHECKED" />
                  <div class="hidden" for="help_for_random_local_port">
                    <?=gettext("Use a random local source port (lport) for traffic from the client. Without this set, two clients may not run concurrently.");?>
                    <br/>
                    <?=gettext("NOTE: Not supported on older clients. Automatically disabled for Yealink and Snom configurations."); ?>
                  </div>
					</tr>
					<tr>
						<td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate Export Options");?></td>
						<td >
                  <div>
                    <input name="usetoken" id="usetoken" type="checkbox" value="yes" />
                    <?=gettext("Use Microsoft Certificate Storage instead of local files.");?>
                  </div>
                  <div>
                    <input name="usepass" id="usepass" type="checkbox" value="yes" onclick="usepass_changed()" />
                    <?=gettext("Use a password to protect the pkcs12 file contents or key in Viscosity bundle.");?>
                  </div>
                  <div id="usepass_opts" style="display:none">
                    <?=gettext("Password");?> :
                    <input name="pass" id="pass" type="password" class="formfld pwd" size="20" value="" />
                    <?=gettext("Confirm");?> :
                    <input name="conf" id="conf" type="password" class="formfld pwd" size="20" value="" />
                  </div>
						</td>
					</tr>
					<tr>
						<td valign="top"><a id="help_for_http_proxy" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Proxy");?></td>
						<td >
                  <input name="useproxy" id="useproxy" type="checkbox" value="yes" onclick="useproxy_changed(this)" />
                  <div class="hidden" for="help_for_http_proxy">
                    <?=gettext("Use proxy to communicate with the server.");?>
                  </div>
                  <div id="useproxy_opts" style="display:none" >
                    <?=gettext("Type");?>
                    <select name="useproxytype" id="useproxytype" class="formselect">
                      <option value="http"><?=gettext("HTTP");?></option>
                      <option value="socks"><?=gettext("SOCKS");?></option>
                    </select>
                    <?=gettext("IP Address");?>
                    <input name="proxyaddr" id="proxyaddr" type="text" class="formfld unknown" size="30" value="" />
                    <?=gettext("Port");?> :
                    <input name="proxyport" id="proxyport" type="text" class="formfld unknown" size="5" value="" />
                    <div>
                        <?=gettext("Choose proxy authentication if any.");?>
                      <select name="useproxypass" id="useproxypass" class="formselect" onchange="useproxy_changed(this)">
                        <option value="none"><?=gettext("none");?></option>
                        <option value="basic"><?=gettext("basic");?></option>
                        <option value="ntlm"><?=gettext("ntlm");?></option>
                      </select>
                      <div id="useproxypass_opts" style="display:none">
                        <?=gettext("Username");?> :
                        <input name="proxyuser" id="proxyuser" type="text" class="formfld unknown" size="20" value="" />
                            <?=gettext("Password");?> :
                        <input name="proxypass" id="proxypass" type="password" class="formfld pwd" size="20" value="" />
                            <?=gettext("Confirm");?> :
                        <input name="proxyconf" id="proxyconf" type="password" class="formfld pwd" size="20" value="" />
                      </div>
                    </div>
                  </div>
						</td>
					</tr>
					<tr>
						<td valign="top"><a id="help_for_openvpnmanager" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Management Interface OpenVPNManager");?></td>
						<td >
                  <input name="openvpnmanager" id="openvpnmanager" type="checkbox" value="yes" />
                  <div class="hidden" for="help_for_openvpnmanager">
                    <?=gettext('This will change the generated .ovpn configuration to allow for usage of the management interface.'.
                    'And include the OpenVPNManager program in the "Windows Installers". With this OpenVPN can be used also by non-administrator users.'.
                    'This is also useful for Windows Vista/7/8 systems where elevated permissions are needed to add routes to the system.');?>
                    <br/>
                    <?=gettext("NOTE: This is not currently compatible with the 64-bit OpenVPN installer. It will work with the 32-bit installer on a 64-bit system.");?>
                  </div>
						</td>
					</tr>
					<tr>
						<td colspan="2" class="list" height="12">&nbsp;</td>
					</tr>
					<tr>
						<td valign="top"><a id="help_for_advancedoptions" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Additional configuration options");?></td>
						<td >
                  <textarea rows="6" cols="68" name="advancedoptions" id="advancedoptions"></textarea><br/>
                  <div class="hidden" for="help_for_advancedoptions">
                    <?=gettext("Enter any additional options you would like to add to the OpenVPN client export configuration here, separated by a line break or semicolon"); ?><br/>
							<?=gettext("EXAMPLE: remote-random"); ?>;
                  </div>
						</td>
					</tr>
					<tr>
						<td valign="top"><a id="help_for_clientpkg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Client Install Packages");?></td>
                <td>
                  <table width="100%" id="users" border="0" cellpadding="0" cellspacing="0" class="table table-striped table-bordered ">
						<tr>
							<td width="25%" ><b><?=gettext("User");?></b></td>
							<td width="35%" ><b><?=gettext("Certificate Name");?></b></td>
							<td width="40%" ><b><?=gettext("Export");?></b></td>
						</tr>
					</table>
                  <div class="hidden" for="help_for_clientpkg">
                    <?= gettext("NOTES:") ?> <br/>
                    <?= gettext("The &quot;XP&quot; Windows installers work on Windows XP and later versions. The &quot;win6&quot; Windows installers include a new tap-windows6 driver that works only on Windows Vista and later.") ?><br/>
                    <br/><br/>
                    <strong><?= gettext("Links to OpenVPN clients for various platforms:") ?></strong><br/>
                    <a href="http://openvpn.net/index.php/open-source/downloads.html"><?= gettext("OpenVPN Community Client") ?></a> - <?=gettext("Binaries for Windows, Source for other platforms. Packaged above in the Windows Installers")?><br/>
                    <a href="https://play.google.com/store/apps/details?id=de.blinkt.openvpn"><?= gettext("OpenVPN For Android") ?></a> - <?=gettext("Recommended client for Android")?><br/>
                    <a href="http://www.featvpn.com/"><?= gettext("FEAT VPN For Android") ?></a> - <?=gettext("For older versions of Android")?><br/>
                    <?= gettext("OpenVPN Connect") ?>: <a href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn"><?=gettext("Android (Google Play)")?></a> or <a href="https://itunes.apple.com/us/app/openvpn-connect/id590379981"><?=gettext("iOS (App Store)")?></a> - <?= gettext("Recommended client for iOS") ?>
                    <br/><a href="http://www.sparklabs.com/viscosity/"><?= gettext("Viscosity") ?></a> - <?= gettext("Recommended client for Mac OSX") ?>
                    <br/><a href="http://code.google.com/p/tunnelblick/"><?= gettext("Tunnelblick") ?></a> - <?= gettext("Free client for OSX") ?>
                    <br/><br/>
                    <?= gettext("NOTES:") ?><br/>
                    <?= gettext("If you expect to see a certain client in the list but it is not there, it is usually due to a CA mismatch between the OpenVPN server instance and the client certificates found in the User Manager.") ?><br/>
                  </div>
                </td>
					</tr>
				</table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<script type="text/javascript">
//<![CDATA[
server_changed();
//]]>
</script>

<?php include("foot.inc"); ?>
