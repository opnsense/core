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
require_once("services.inc");
require_once("filter.inc");
require_once("interfaces.inc");
require_once("openvpn-client-export.inc");

global $current_openvpn_version, $current_openvpn_version_rev;

$ras_server = array();
if (isset($config['openvpn']['openvpn-server'])) {
    // collect info
    foreach ($config['openvpn']['openvpn-server'] as $sindex => $server) {
        if (isset($server['disable'])) {
            continue;
        }
        $ras_user = array();
        $ras_certs = array();
        if (stripos($server['mode'], "server") === false && $server['mode'] != "p2p_shared_key") {
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
            header("Location: vpn_openvpn_export.php");
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
                $input_errors[] = gettext("You need to specify a port for the proxy IP.");
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
        } elseif ($act == "visc") {
            $exp_name = urlencode($exp_name."-Viscosity.visc.zip");
            $exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, 'zip');
        } elseif ($act == "visz") {
            $exp_name = urlencode($exp_name."-Viscosity.visz");
            $exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, 'targz');
        } elseif ( $act == 'skconf')  {
            $exp_path = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, false);
            $exp_name = urlencode($exp_name."-config.ovpn");
        } elseif ( $act == 'skzipconf')  {
            $exp_path = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, true);
            $exp_name = urlencode(basename($exp_path));
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

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
    $( document ).ready(function() {
        $("#server").change(function(){
            $('.server_item').hide();
            $('tr[data-server-index="'+$(this).val()+'"]').show();
            switch ($("#server :selected").data('mode')) {
                case "p2p_shared_key":
                    $(".mode_server select,input").prop( "disabled", true );
                    $(".mode_server").hide();
                    break;
                default:
                    $(".mode_server select,input").prop( "disabled", false );
                    $(".mode_server").show();
            }
            $(window).resize(); // force zebra re-stripe (opnsense_standard_table_form)
        });
        $("#server").change();

        $("#useaddr").change(function(){
            if ($(this).val() == 'other') {
                $('#HostName').show();
                $("#useaddr_hostname").prop( "disabled", false );
            } else {
                $('#HostName').hide();
                $("#useaddr_hostname").prop( "disabled", true );
            }
        });
        $("#pass,#conf").keyup(function(){
          if ($("#usepass").is(':checked')) {
              if ($("#pass").val() != $("#conf").val()) {
                  $("#usepass_opts").addClass('has-error');
                  $("#usepass_opts").removeClass('has-success');
              } else {
                  $("#usepass_opts").addClass('has-success');
                  $("#usepass_opts").removeClass('has-error');
              }
          }
        });
        $("#proxypass,#proxyconf").keyup(function(){
          if ($("#useproxypass option:selected").text() != 'none') {
              if ($("#proxypass").val() != $("#proxyconf").val()) {
                  $("#useproxypass_opts").addClass('has-error');
                  $("#useproxypass_opts").removeClass('has-success');
              } else {
                  $("#useproxypass_opts").addClass('has-success');
                  $("#useproxypass_opts").removeClass('has-error');
              }
          }
        });


        $("#usepass").change(function(){
            if ($(this).is(':checked')) {
                $("#usepass_opts").show();
            } else {
                $("#usepass_opts").hide();
            }
        });

        $("#useproxy, #useproxypass").change(function(){
            if ($("#useproxy").prop("checked")){
                $("#useproxy_opts").show();
            } else {
                $("#useproxy_opts").hide();
            }
            if ($("#useproxypass option:selected").text() != 'none') {
                $("#useproxypass_opts").show();
            } else {
                $("#useproxypass_opts").hide();
            }
        });

        $(".export_select").change(function(){
            if ($(this).val() != "") {
                var params = {};
                params['act'] = $(this).val();
                params['srvid'] = $("#server").val();
                if ($("#useaddr").val() == 'other') {
                    params['useaddr'] = $("#useaddr_hostname").val();
                } else {
                    params['useaddr'] = $("#useaddr").val();
                }
                if ($("#randomlocalport").is(':checked')) {
                    params['randomlocalport'] = 1;
                } else {
                    params['randomlocalport'] = 0;
                }
                if ($("#usetoken").is(':checked')) {
                    params['usetoken'] = 1;
                } else {
                    params['usetoken'] = 0;
                }
                if ($("#usepass").is(':checked')) {
                    params['password'] = $("#pass").val();
                }
                if ($("#useproxy").is(':checked')) {
                    params['proxy_type'] = $("#useproxytype").val();
                    params['proxy_addr'] = $("#proxyaddr").val();
                    params['proxy_port'] = $("#proxyport").val();
                    params['proxy_authtype'] = $("#useproxypass").val();
                    if ($("#useproxypass").val() != "none") {
                        params['proxy_user'] = $("#proxyuser").val();
                        params['proxy_password'] = $("#proxypass").val();
                    }
                }
                if ($("#openvpnmanager").is(':checked')) {
                    params['openvpnmanager'] = 1;
                } else {
                    params['openvpnmanager'] = 0;
                }
                params['advancedoptions'] = escape($("#advancedoptions").val());
                params['verifyservercn'] = $("#verifyservercn").val();
                if ($(this).data('type') == 'cert') {
                    params['crtid'] = $(this).data('id');
                } else if ($(this).data('type') == 'user') {
                    params['usrid'] = $(this).data('id');
                    params['crtid'] = $(this).data('certid');
                }
                var link=document.createElement('a');
                document.body.appendChild(link);
                link.href= "/vpn_openvpn_export.php?" + $.param( params );
                link.click();
                $(this).val("");
            }
        });
    });
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
        <div class="tab-content content-box col-xs-12">
          <div class="table-responsive">
            <table class="table table-striped opnsense_standard_table_form" >
              <tr>
                <td width="22%"></td>
                <td width="78%" align="right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                </td>
              </tr>
              <tr>
                <td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Remote Access Server");?></td>
                <td>
                  <select name="server" id="server" class="formselect">
<?php
                    foreach ($ras_server as $server) :?>
                    <option value="<?=$server['index'];?>" data-mode="<?=$server['mode'];?>"><?=htmlspecialchars($server['name']);?></option>
<?php
                    endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Host Name Resolution");?></td>
                <td>
                      <select name="useaddr" id="useaddr">
                        <option value="serveraddr" ><?=gettext("Interface IP Address");?></option>
                        <option value="servermagic" ><?=gettext("Automagic Multi-WAN IPs (port forward targets)");?></option>
                        <option value="servermagichost" ><?=gettext("Automagic Multi-WAN dynamic DNS Hostnames (port forward targets)");?></option>
                        <option value="serverhostname" ><?=gettext("Installation hostname");?></option>
                        <?php if (isset($config['dyndnses']['dyndns'])) :
?>
                        <?php foreach ($config['dyndnses']['dyndns'] as $ddns) :
?>
                        <option value="<?= $ddns["host"] ?>"><?=gettext("Dynamic DNS");?>: <?= htmlspecialchars($ddns["host"]); ?></option>
<?php
                        endforeach; ?>
<?php
                        endif; ?>
                    <?php if (isset($config['dnsupdates']['dnsupdate'])) :
?>
                        <?php foreach ($config['dnsupdates']['dnsupdate'] as $ddns) :
?>
                        <option value="<?= $ddns["host"] ?>"><?=gettext("Dynamic DNS");?>: <?= htmlspecialchars($ddns["host"]); ?></option>
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
              <tr class="mode_server">
                <td valign="top"><a id="help_for_verify_server_cn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Verify Server CN");?></td>
                <td >
                      <select name="verifyservercn" id="verifyservercn" class="formselect">
                        <option value="auto"><?=gettext("Automatic - Use verify-x509-name (OpenVPN 2.3+) where possible");?></option>
                        <option value="tls-remote"><?=gettext("Use tls-remote (deprecated, use only on clients prior to OpenVPN 2.3)");?></option>
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
              <tr class="mode_server">
                <td valign="top"><a id="help_for_random_local_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Random Local Port");?></td>
                <td >
                      <input name="randomlocalport" id="randomlocalport" type="checkbox" value="yes" checked="CHECKED" />
                      <div class="hidden" for="help_for_random_local_port">
                        <?=gettext("Use a random local source port (lport) for traffic from the client. Without this set, two clients may not run concurrently.");?>
                        <br/>
                        <?=gettext("NOTE: Not supported on older clients. Automatically disabled for Yealink and Snom configurations."); ?>
                      </div>
              </tr>
              <tr class="mode_server">
                <td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate Export Options");?></td>
                <td >
                      <div>
                        <input name="usetoken" id="usetoken" type="checkbox" value="yes" />
                        <?=gettext("Use Microsoft Certificate Storage instead of local files.");?>
                      </div>
                      <div>
                        <input name="usepass" id="usepass" type="checkbox" value="yes" />
                        <?=gettext("Use a password to protect the pkcs12 file contents or key in Viscosity bundle.");?>
                      </div>
                      <div id="usepass_opts" style="display:none">
                        <label for="pass"><?=gettext("Password");?></label>
                        <input name="pass" id="pass" class="form-control" type="password" value="" />
                        <label for="conf"><?=gettext("Confirmation");?></label>
                        <input name="conf" id="conf" class="form-control" type="password" value="" />
                      </div>
                </td>
              </tr>
              <tr>
                <td valign="top"><a id="help_for_http_proxy" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Proxy");?></td>
                <td >
                      <input name="useproxy" id="useproxy" type="checkbox" value="yes" />
                      <div class="hidden" for="help_for_http_proxy">
                        <?=gettext("Use a proxy to communicate with the server.");?>
                      </div>
                      <div id="useproxy_opts" style="display:none" >
                        <label for="useproxytype"><?=gettext("Type");?></label>
                        <select name="useproxytype" id="useproxytype" class="formselect">
                          <option value="http"><?=gettext("HTTP");?></option>
                          <option value="socks"><?=gettext("SOCKS");?></option>
                        </select>
                        <label for="proxyaddr"><?=gettext("IP Address");?></label>
                        <input name="proxyaddr" id="proxyaddr" type="text" class="formfld unknown" size="30" value="" />
                        <label for="proxyport"><?=gettext("Port");?></label>
                        <input name="proxyport" id="proxyport" type="text" class="formfld unknown" size="5" value="" />
                        <div>
                          <label for="useproxypass"><?=gettext("Choose proxy authentication if any.");?></label>
                          <select name="useproxypass" id="useproxypass" class="formselect">
                            <option value="none"><?=gettext("none");?></option>
                            <option value="basic"><?=gettext("basic");?></option>
                            <option value="ntlm"><?=gettext("ntlm");?></option>
                          </select>
                          <div id="useproxypass_opts" style="display:none">
                            <label for="proxyuser"><?=gettext("Username");?></label>
                            <input name="proxyuser" id="proxyuser" type="text" class="formfld unknown" value="" />
                            <label for="proxypass"><?=gettext("Password");?></label>
                            <input name="proxypass" id="proxypass" type="password" class="form-control" value="" />
                            <label for="proxyconf"><?=gettext("Confirmation");?></label>
                            <input name="proxyconf" id="proxyconf" type="password" class="form-control" value="" />
                          </div>
                        </div>
                      </div>
                </td>
              </tr>
              <tr class="mode_server">
                <td valign="top"><a id="help_for_openvpnmanager" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Management Interface OpenVPN Manager");?></td>
                <td >
                      <input name="openvpnmanager" id="openvpnmanager" type="checkbox" value="yes" />
                      <div class="hidden" for="help_for_openvpnmanager">
                        <?=gettext('This will change the generated .ovpn configuration to allow for usage of the management interface. '.
                        'With this OpenVPN can be used also by non-administrator users. '.
                        'This is also useful for Windows systems where elevated permissions are needed to add routes to the system.');?>
                      </div>
                </td>
              </tr>
              <tr class="mode_server">
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
                <td><a id="help_for_clientpkg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Client Install Packages");?></td>
                <td>
                  <table id="export_users" class="table table-striped table-condensed">
                    <thead>
                      <tr>
                        <td width="25%" ><b><?=gettext("User");?></b></td>
                        <td width="35%" ><b><?=gettext("Certificate Name");?></b></td>
                        <td width="40%" ><b><?=gettext("Export");?></b></td>
                      </tr>
                    </thead>
                    <tbody>
<?php
                    foreach ($ras_server as $server) :
                      foreach ($server['users'] as $user):?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=$user['name'];?></td>
                        <td><?=str_replace("'", "\\'", $user['certname']);?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="user" data-id="<?=$user['uindex'];?>" data-certid="<?=$user['cindex'];?>">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="confzip"><?=gettext("Archive");?></option>
                              <option value="conf"><?=gettext("File Only");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Inline Configurations");?>">
                              <option value="confinlinedroid"><?=gettext("Android");?></option>
                              <option value="confinlineios"><?=gettext("OpenVPN Connect (iOS/Android)");?></option>
                              <option value="confinline"><?=gettext("Others");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Mac OSX / Windows");?>">
                              <option value="visc"><?=gettext("Viscosity Bundle (OSX)");?></option>
                              <option value="visz"><?=gettext("Viscosity Bundle (Windows)");?></option>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
<?php
                      endforeach;
                      foreach ($server['certs'] as $certidx => $cert) :?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=$server['mode'] == 'server_tls' ? gettext("Certificate (SSL/TLS, no Auth)") : gettext("Certificate with External Auth") ?></td>
                        <td><?=str_replace("'", "\\'", $cert['certname']);?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="cert" data-id="<?=$cert['cindex'];?>">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="confzip"><?=gettext("Archive");?></option>
                              <option value="conf"><?=gettext("File Only");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Inline Configurations");?>">
                              <option value="confinlinedroid"><?=gettext("Android");?></option>
                              <option value="confinlineios"><?=gettext("OpenVPN Connect (iOS/Android)");?></option>
                              <option value="confinline"><?=gettext("Others");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Mac OSX / Windows");?>">
                              <option value="visc"><?=gettext("Viscosity Bundle (OSX)");?></option>
                              <option value="visz"><?=gettext("Viscosity Bundle (Windows)");?></option>
                            </optgroup>
<?php
                            if ($server['mode'] == 'server_tls'):?>
                            <optgroup label="<?=gettext("Yealink SIP Handsets");?>">
                              <option value="conf_yealink_t28"><?=gettext("T28");?></option>
                              <option value="conf_yealink_t38g"><?=gettext("T38G (1)");?></option>
                              <option value="conf_yealink_t38g2"><?=gettext("T38G (2)");?></option>
                              <option value="conf_snom"><?=gettext("SNOM SIP Handset");?></option>

                            </optgroup>
<?php
                            endif;?>
                          </select>
                        </td>
                      </tr>
<?php
                      endforeach;
                      if ($server['mode'] == 'server_user'):?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=gettext("Authentication Only (No Cert)");?></td>
                        <td><?=gettext("none");?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="server">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="confzip"><?=gettext("Archive");?></option>
                              <option value="conf"><?=gettext("File Only");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Inline Configurations");?>">
                              <option value="confinlinedroid"><?=gettext("Android");?></option>
                              <option value="confinlineios"><?=gettext("OpenVPN Connect (iOS/Android)");?></option>
                              <option value="confinline"><?=gettext("Others");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Mac OSX / Windows");?>">
                              <option value="visc"><?=gettext("Viscosity Bundle (OSX)");?></option>
                              <option value="visz"><?=gettext("Viscosity Bundle (Windows)");?></option>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
<?php
                      endif;
                      if ($server['mode'] == 'p2p_shared_key'):?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=gettext("Other Shared Key OS Client");?></td>
                        <td><?=gettext("none");?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="server">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="skconf"><?=gettext("Configuration");?></option>
                              <option value="skzipconf"><?=gettext("Configuration archive");?></option>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
<?php
                      endif;
                    endforeach;?>
                      </tbody>
                    </table>
                    <div class="hidden" for="help_for_clientpkg">
                      <br/><br/>
                      <?= gettext("If you expect to see a certain client in the list but it is not there, it is usually due to a CA mismatch between the OpenVPN server instance and the client certificates found in the User Manager.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td valign="top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Links to OpenVPN clients");?></td>
                  <td>
                    <a href="http://www.sparklabs.com/viscosity/"><?= gettext("Viscosity") ?></a> - <?= gettext("Recommended client for Mac OSX and Windows") ?><br/>
                    <a href="http://openvpn.net/index.php/open-source/downloads.html"><?= gettext("OpenVPN Community Client") ?></a> - <?=gettext("Binaries for Windows, Source for other platforms.")?><br/>
                    <a href="https://play.google.com/store/apps/details?id=de.blinkt.openvpn"><?= gettext("OpenVPN For Android") ?></a> - <?=gettext("Recommended client for Android")?><br/>
                    <a href="http://www.featvpn.com/"><?= gettext("FEAT VPN For Android") ?></a> - <?=gettext("For older versions of Android")?><br/>
                    <?= gettext("OpenVPN Connect") ?>: <a href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn"><?=gettext("Android (Google Play)")?></a> or <a href="https://itunes.apple.com/us/app/openvpn-connect/id590379981"><?=gettext("iOS (App Store)")?></a> - <?= gettext("Recommended client for iOS") ?><br/>
                    <a href="http://code.google.com/p/tunnelblick/"><?= gettext("Tunnelblick") ?></a> - <?= gettext("Free client for OSX") ?>
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
