<?php

/*
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
*/

require_once("guiconfig.inc");
require_once("openvpn.inc");
require_once("filter.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");
require_once("openvpn-client-export.inc");

$pgtitle = array("OpenVPN", "Client Export Utility");

$ras_server = array();
if (isset($config['openvpn']['openvpn-server'])) {
    foreach ($config['openvpn']['openvpn-server'] as $sindex => $server) {
        if (isset($server['disable'])) {
            continue;
        }
        $ras_user = array();
        if ($server['mode'] != "p2p_shared_key") {
            continue;
        }

        $ras_serverent = array();
        $prot = $server['protocol'];
        $port = $server['local_port'];
        if ($server['description']) {
            $name = "{$server['description']} {$prot}:{$port}";
        } else {
            $name = "Shared Key Server {$prot}:{$port}";
        }
        $ras_serverent['index'] = $sindex;
        $ras_serverent['name'] = $name;
        $ras_serverent['mode'] = $server['mode'];
        $ras_server[] = $ras_serverent;
    }
}

if (isset($_GET['act'])) {
    $input_errors = array();
    $act = $_GET['act'];
    if (($act == "skconf") || ($act == "skzipconf")) {
        $srvid = isset($_GET['srvid']) ? $_GET['srvid'] : false;
        if ($srvid === false || !isset($config['openvpn']['openvpn-server'][$srvid]['mode']) ||
              $config['openvpn']['openvpn-server'][$srvid]['mode'] != "p2p_shared_key") {
                redirectHeader("vpn_openvpn_export.php");
                exit;
        }

        if (empty($_GET['useaddr'])) {
            $input_errors[] = "You need to specify an IP or hostname.";
        } else {
            $useaddr = $_GET['useaddr'];
        }

        $proxy = "";
        if (!empty($_GET['proxy_addr']) || !empty($_GET['proxy_port'])) {
            $proxy = array();
            if (empty($_GET['proxy_addr'])) {
                $input_errors[] = "You need to specify an address for the proxy port.";
            } else {
                $proxy['ip'] = $_GET['proxy_addr'];
            }
            if (empty($_GET['proxy_port'])) {
                $input_errors[] = "You need to specify a port for the proxy ip.";
            } else {
                $proxy['port'] = $_GET['proxy_port'];
            }
            $proxy['proxy_type'] = $_GET['proxy_type'];
            $proxy['proxy_authtype'] = $_GET['proxy_authtype'];
            if ($_GET['proxy_authtype'] != "none") {
                if (empty($_GET['proxy_user'])) {
                    $input_errors[] = "You need to specify a username with the proxy config.";
                } else {
                    $proxy['user'] = $_GET['proxy_user'];
                }
                if (!empty($_GET['proxy_user']) && empty($_GET['proxy_password'])) {
                    $input_errors[] = "You need to specify a password with the proxy user.";
                } else {
                    $proxy['password'] = $_GET['proxy_password'];
                }
            }
        }

        $exp_name = openvpn_client_export_prefix($srvid);
        if ($act == "skzipconf") {
            $zipconf = true;
        }
        $exp_data = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, $zipconf);
        if (!$exp_data) {
            $input_errors[] = "Failed to export config files!";
        }
        if (count($input_errors) == 0) {
            if ($zipconf) {
                $exp_name = urlencode($exp_data);
                $exp_size = filesize("/tmp/{$exp_data}");
            } else {
                $exp_name = urlencode($exp_name."-config.ovpn");
                $exp_size = strlen($exp_data);
            }

            header('Pragma: ');
            header('Cache-Control: ');
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$exp_name}");
            header("Content-Length: $exp_size");
            if ($zipconf) {
                readfile("/tmp/{$exp_data}");
            } else {
                echo $exp_data;
            }

            @unlink("/tmp/{$exp_data}");
            exit;
        }
    }
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
  server_changed();
});

var servers = new Array();
<?php	foreach ($ras_server as $sindex => $server) :
?>
servers[<?=$sindex;?>] = new Array();
servers[<?=$sindex;
?>][0] = '<?=$server['index'];?>';
servers[<?=$sindex;?>][1] = new Array();
servers[<?=$sindex;
?>][2] = '<?=$server['mode'];?>';
<?
endforeach; ?>

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
	while (table.rows.length > 1 ) {
    table.deleteRow(1);
  }

	var index = document.getElementById("server").selectedIndex;

	if (servers[index][2] == 'p2p_shared_key') {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		cell0.innerHTML = "Other Shared Key OS Client";
    cell1.innerHTML += "<div>";
    cell1.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"skconf\")'>Configuration</button>";
    cell1.innerHTML += "&nbsp;";
    cell1.innerHTML += "<button type='button' class='btn btn-primary btn-xs' onclick='download_begin(\"skzipconf\")'>Configuration archive</button>";
    cell1.innerHTML += "</div>";

	}
}

function useaddr_changed(obj) {

	if (obj.value == "other")
		$('#HostName').show();
	else
		$('#HostName').hide();

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
                  $tab_array[] = array(gettext("Client Export"), false, "vpn_openvpn_export.php");
                  $tab_array[] = array(gettext("Shared Key Export"), true, "vpn_openvpn_export_shared.php");
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
                <td width="22%" valign="top" class="vncellreq"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Shared Key Server");?></td>
                <td width="78%" class="vtable">
                  <select name="server" id="server" class="formselect" onchange="server_changed()">
                    <?php foreach ($ras_server as & $server) :
    ?>
                    <option value="<?=htmlspecialchars($server['sindex']);
?>"><?=htmlspecialchars($server['name']);?></option>
                    <?php
endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <td width="22%" valign="top" class="vncell"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Host Name Resolution");?></td>
                <td width="78%" class="vtable">
                  <select name="useaddr" id="useaddr" class="formselect" onchange="useaddr_changed(this)">
                    <option value="serveraddr" ><?=gettext("Interface IP Address");?></option>
                    <option value="serverhostname" ><?=gettext("Installation hostname");?></option>
                    <?php if (isset($config['dyndnses']['dyndns'])) :
?>
                        <?php foreach ($config['dyndnses']['dyndns'] as $ddns) :
?>
                        <option value="<?= htmlspecialchars($ddns["host"]);
?>"><?=gettext("DynDNS:");
?> <?= htmlspecialchars($ddns["host"]);?></option>
                        <?php
endforeach; ?>
                    <?php
endif; ?>
                    <option value="other"><?=gettext("Other");?></option>
                  </select>
                  <div style="display:none;" id="HostName">
                    <?=gettext("Enter the hostname or IP address the client will use to connect to this server.");?>
                    <input name="useaddr_hostname" type="text" id="useaddr_hostname" size="40" />
                  </div>
                </td>
              </tr>
              <tr>
                <td width="22%" valign="top" class="vncell"><a id="help_for_use_proxy" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Proxy");?></td>
                <td width="78%" class="vtable">
                  <input name="useproxy" id="useproxy" type="checkbox" value="yes" onclick="useproxy_changed(this)" />

                  <div id="useproxy_opts" style="display:none">
                        <?=gettext("Type");?> :
                      <select name="useproxytype" id="useproxytype" class="formselect">
                        <option value="http"><?=gettext("HTTP");?></option>
                        <option value="socks"><?=gettext("Socks");?></option>
                      </select>

                        <?=gettext("IP Address")?> :
                      <input name="proxyaddr" id="proxyaddr" type="text" class="formfld unknown" size="30" value="" />
                        <?=gettext("Port");?> :
                      <input name="proxyport" id="proxyport" type="text" class="formfld unknown" size="5" value="" />
                        <?=gettext("Choose proxy authentication if any.");?>
                      <select name="useproxypass" id="useproxypass" class="formselect" onchange="useproxy_changed(this)">
                        <option value="none"><?=gettext("none");?></option>
                        <option value="basic"><?=gettext("basic");?></option>
                        <option value="ntlm"><?=gettext("ntlm");?></option>
                      </select>
                      <div id="useproxypass_opts">
                        <?=gettext("Username")?> :
                        <input name="proxyuser" id="proxyuser" type="text" class="formfld unknown" size="20" value="" />
                        <?=gettext("Password");?> :
                        <input name="proxypass" id="proxypass" type="password" class="formfld pwd" size="20" value="" />
                        <?=gettext("Confirm");?> :
                        <input name="proxyconf" id="proxyconf" type="password" class="formfld pwd" size="20" value="" />
                      </div>
                  </div>
                  <div class="hidden" for="help_for_use_proxy">
                        <?= gettext("Use proxy to communicate with the server.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_client_conf_pkg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Client Configuration Packages");?></td>
                <td>
                  <table width="100%" id="clients" border="0" cellpadding="0" cellspacing="0" class="table table-striped table-bordered ">
                    <tr>
                      <td width="25%" class="listhdrr"><b><?=gettext("Client Type");?></b></td>
                      <td width="50%" class="listhdrr"><b><?=gettext("Export");?></b></td>
                    </tr>
                  </table>
                  <div class="hidden" for="help_for_client_conf_pkg">
                        <?= gettext("NOTE:") ?> <br/>
                        <?= gettext("These are shared key configurations for use in site-to-site tunnels with other routers. Shared key tunnels are not normally used for remote access connections to end users.") ?>
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

<?php include("foot.inc"); ?>
