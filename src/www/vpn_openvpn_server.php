<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/openvpn.inc");

$a_server = &config_read_array('openvpn', 'openvpn-server');

$act = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // fetch id if provided
    if (isset($_GET['dup']) && isset($a_server[$_GET['dup']]))  {
        $configId = $_GET['dup'];
    } elseif (isset($_GET['id']) && is_numericint($_GET['id'])) {
        $id = $_GET['id'];
        $configId = $id;
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
    if (isset($configId) && isset($a_server[$configId])) {
        if ($a_server[$configId]['mode'] != "p2p_shared_key") {
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
            ,wins_server2,no_tun_ipv6,push_register_dns,push_block_outside_dns,dns_domain,local_group
            ,client_mgmt_port,verbosity_level,caref,crlref,certref,dh_length
            ,cert_depth,strictusercn,digest,disable,duplicate_cn,vpnid,reneg-sec,use-common-name,cso_login_matching";

        foreach (explode(",", $copy_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if (isset($a_server[$configId][$fieldname])) {
                $pconfig[$fieldname] = $a_server[$configId][$fieldname];
            } elseif (!isset($pconfig[$fieldname])) {
              // initialize element
                $pconfig[$fieldname] = null;
            }
        }

        // load / convert
        if (!empty($a_server[$configId]['ipaddr'])) {
            $pconfig['interface'] = $pconfig['interface'] . '|' . $a_server[$configId]['ipaddr'];
        }
        if (!empty($a_server[$configId]['shared_key'])) {
            $pconfig['shared_key'] = base64_decode($a_server[$configId]['shared_key']);
        } else {
            $pconfig['shared_key'] = null;
        }
        if (!empty($a_server[$configId]['tls'])) {
            $pconfig['tlsauth_enable'] = "yes";
            $pconfig['tls'] = base64_decode($a_server[$configId]['tls']);
        } else {
            $pconfig['tls'] = null;
            $pconfig['tlsauth_enable'] = null;
        }
    } elseif ($act == "new") {
        $pconfig['tlsauth_enable'] = "yes";
        $pconfig['dh_length'] = 2048;
        $pconfig['dev_mode'] = "tun";
        $pconfig['interface'] = 'any';
        $pconfig['protocol'] = 'UDP';
        $pconfig['local_port'] = openvpn_port_next($pconfig['protocol']);
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
            ,wins_server2,no_tun_ipv6,push_register_dns,push_block_outside_dns,dns_domain
            ,client_mgmt_port,verbosity_level,caref,crlref,certref,dh_length
            ,cert_depth,strictusercn,digest,disable,duplicate_cn,vpnid,shared_key,tls,reneg-sec,use-common-name
            ,cso_login_matching";
        foreach (explode(",", $init_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if (!isset($pconfig[$fieldname])) {
                $pconfig[$fieldname] = null;
            }
        }

    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && isset($a_server[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    }

    if ($act == "del") {
        // action delete
        if (isset($a_server[$id])) {
            openvpn_delete('server', $a_server[$id]);
            unset($a_server[$id]);
            write_config();
        }
        header(url_safe('Location: /vpn_openvpn_server.php'));
        exit;
    } elseif ($act == "toggle") {
        if (isset($id)) {
            if (isset($a_server[$id]['disable'])) {
                unset($a_server[$id]['disable']);
            } else {
                $a_server[$id]['disable'] = true;
            }
            write_config();
            openvpn_configure_single($a_server[$id]['vpnid']);
        }
        header(url_safe('Location: /vpn_openvpn_server.php'));
        exit;
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
        if (strpos($pconfig['interface'], '|') !== false) {
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

        if ($result = openvpn_validate_port($pconfig['local_port'], gettext('Local port'))) {
            $input_errors[] = $result;
        }

        if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], gettext('IPv4 Tunnel Network'), false, 'ipv4')) {
            $input_errors[] = $result;
        }

        if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], gettext('IPv6 Tunnel Network'), false, 'ipv6')) {
            $input_errors[] = $result;
        }

        if ($result = openvpn_validate_cidr($pconfig['remote_network'], gettext('IPv4 Remote Network'), true, 'ipv4')) {
            $input_errors[] = $result;
        }

        if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], gettext('IPv6 Remote Network'), true, 'ipv6')) {
            $input_errors[] = $result;
        }

        if ($result = openvpn_validate_cidr($pconfig['local_network'], gettext('IPv4 Local Network'), true, 'ipv4')) {
            $input_errors[] = $result;
        }

        if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], gettext('IPv6 Local Network'), true, 'ipv6')) {
            $input_errors[] = $result;
        }

        if (!empty($pconfig['local_port'])) {
            $portused = openvpn_port_used($pconfig['protocol'], $pconfig['interface'], $pconfig['local_port'], $vpnid);
            if ($portused) {
                $input_errors[] = gettext("The specified 'Local port' is in use. Please select another value");
            }
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

        if (!empty($pconfig['wins_server_enable'])) {
            if (!empty($pconfig['wins_server1']) && !is_ipaddr(trim($pconfig['wins_server1']))) {
                $input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
            }
            if (!empty($pconfig['wins_server2']) && !is_ipaddr(trim($pconfig['wins_server2']))) {
                $input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
            }
        }

        if (!empty($pconfig['client_mgmt_port_enable'])) {
            if ($result = openvpn_validate_port($pconfig['client_mgmt_port'], gettext('Client management port'))) {
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

        $reqdfields[] = 'local_port';
        $reqdfieldsn[] = gettext('Local port');

        if ($pconfig['dev_mode'] != "tap") {
            $reqdfields[] = 'tunnel_network,tunnel_networkv6';
            $reqdfieldsn[] = gettext('Tunnel Network');
        } else {
            if ($pconfig['serverbridge_dhcp'] && ($pconfig['tunnel_network'] || $pconfig['tunnel_networkv6'])) {
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
        if (isset($pconfig['reneg-sec']) && $pconfig['reneg-sec'] != "" && (string)((int)$pconfig['reneg-sec']) != $pconfig['reneg-sec']) {
            $input_errors[] = gettext("Renegotiate time should contain a valid number of seconds.");
        }

        // When server certificate is set, check type.
        if (!empty($pconfig['certref'])) {
            foreach ($config['cert'] as $cert) {
                if ($cert['refid'] == $pconfig['certref']) {
                    if (cert_get_purpose($cert['crt'])['server'] == 'No') {
                        $input_errors[] = gettext(
                            sprintf("certificate %s is not intended for server use", $cert['descr'])
                        );
                    }
                }
            }
        }
        $prev_opt = (isset($id) && !empty($a_server[$id])) ? $a_server[$id]['custom_options'] : "";
        if ($prev_opt != str_replace("\r\n", "\n", $pconfig['custom_options']) && !userIsAdmin($_SESSION['Username'])) {
            $input_errors[] = gettext('Advanced options may only be edited by system administrators due to the increased possibility of privilege escalation.');
        }

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (count($input_errors) == 0) {
            // validation correct, save data
            $server = array();

            // delete(rename) old interface so a new TUN or TAP interface can be created.
            if (isset($id) && $pconfig['dev_mode'] != $a_server[$id]['dev_mode']) {
                openvpn_delete('server', $a_server[$id]);
            }
            // 1 on 1 copy of config attributes
            $copy_fields = "mode,protocol,dev_mode,local_port,description,crypto,digest,engine
                ,tunnel_network,tunnel_networkv6,remote_network,remote_networkv6
                ,gwredir,local_network,local_networkv6,maxclients,compression
                ,passtos,client2client,dynamic_ip,pool_enable,topology_subnet,local_group
                ,serverbridge_dhcp,serverbridge_interface,serverbridge_dhcp_start
                ,serverbridge_dhcp_end,dns_domain,dns_server1,dns_server2,dns_server3
                ,dns_server4,push_register_dns,push_block_outside_dns,ntp_server1,ntp_server2,netbios_enable
                ,netbios_ntype,netbios_scope,no_tun_ipv6,verbosity_level,wins_server1
                ,wins_server2,client_mgmt_port,strictusercn,reneg-sec,use-common-name,cso_login_matching";

            foreach (explode(",", $copy_fields) as $fieldname) {
                $fieldname = trim($fieldname);
                if (!empty($pconfig[$fieldname]) || $pconfig[$fieldname] == '0') {
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
            } else {
                $server['interface'] = $pconfig['interface'];
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

            write_config();

            openvpn_configure_single($server['vpnid']);

            header(url_safe('Location: /vpn_openvpn_server.php'));
            exit;
        } elseif (!empty($pconfig['authmode'])) {
            $pconfig['authmode'] = implode(",", $pconfig['authmode']);
        }
    }
}

include("head.inc");

$main_buttons = array();

if (empty($act)) {
    $main_buttons[] = array('href' => 'vpn_openvpn_server.php?act=new', 'label' => gettext('Add'));
}

legacy_html_escape_form_data($pconfig);

?>

<body>
<?php include("fbegin.inc"); ?>
<script>
$( document ).ready(function() {
  // watch scroll position and set to last known on page load
  watchScrollPosition();
  // link delete buttons
  $(".act_delete").click(function(){
    var id = $(this).attr("id").split('_').pop(-1);
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
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

  // link toggle buttons
  $(".act_toggle").click(function(event){
      event.preventDefault();
      $.post(window.location, {act: 'toggle', id:$(this).data("id")}, function(data) {
          location.reload();
      });
  });

  // input form events
  if ($("#iform").length) {
      $("#mode,#gwredir").change(function(){
          $(".opt_mode").hide();
          $(".opt_mode :input").prop( "disabled", true );
          $(".opt_mode_"+$("#mode").val()).show();
          $(".opt_mode_"+$("#mode").val()+" :input").prop( "disabled", false );
          if ($("#gwredir").is(":checked")) {
              $(".opt_gwredir").hide();
          }
          $("#dev_mode").change();
          $('.selectpicker').selectpicker('refresh');
          $(window).resize();
      });
      $("#mode").change();

      $("#dev_mode,#serverbridge_dhcp").change(function(){
          $(".dev_mode").hide();
          $(".dev_mode_"+$("#dev_mode").val()).show();
          if ($("#mode").val().indexOf('p2p_tls') == 0) {
              $("#serverbridge_dhcp").prop('disabled', true);
          } else {
              $("#serverbridge_dhcp").prop('disabled', false);
          }

          if ($("#mode").val().indexOf('p2p_tls') == 0 || $("#serverbridge_dhcp").is(':checked') == false) {
              $("#serverbridge_interface").prop('disabled', true);
              $("#serverbridge_dhcp_start").prop('disabled', true);
              $("#serverbridge_dhcp_end").prop('disabled', true);
          } else {
              $("#serverbridge_interface").prop('disabled', false);
              $("#serverbridge_dhcp_start").prop('disabled', false);
              $("#serverbridge_dhcp_end").prop('disabled', false);
          }
          $('.selectpicker').selectpicker('refresh');
      });
      $("#dev_mode").change();

      $("#autokey_enable").change(function(){
          if ($("#autokey_enable").is(':checked')) {
              $("#autokey_opts").hide();
          } else {
              $("#autokey_opts").show();
          }
      });
      $("#autokey_enable").change();

      $("#tlsauth_enable,#autotls_enable").change(function(){
          if ($("#autotls_enable").is(':checked') || !$("#tlsauth_enable").is(':checked')) {
              $("#tls").parent().hide();
          } else {
              $("#tls").parent().show();
          }
          if ($("#tlsauth_enable").is(':checked')) {
              $("#autotls_enable").parent().show();
          } else {
              $("#autotls_enable").parent().hide();
          }
      });
      $("#tlsauth_enable").change();

      $("#dns_domain_enable").change(function(){
          if ($("#dns_domain_enable").is(':checked')) {
              $("#dns_domain_data").show();
          } else {
              $("#dns_domain_data").hide();
          }
      });
      $("#dns_domain_enable").change();

      $("#dns_server_enable").change(function(){
          if ($("#dns_server_enable").is(':checked')) {
              $("#dns_server_data").show();
          } else {
              $("#dns_server_data").hide();
          }
      });
      $("#dns_server_enable").change();

      $("#wins_server_enable").change(function(){
          if ($("#wins_server_enable").is(':checked')) {
              $("#wins_server_data").show();
          } else {
              $("#wins_server_data").hide();
          }
      });
      $("#wins_server_enable").change();

      $("#netbios_enable").change(function(){
          if ($("#netbios_enable").is(':checked')) {
              $("#wins_opts").show();
              $("#netbios_data").show();
          } else {
              $("#wins_opts").hide();
              $("#netbios_data").hide();
          }
      });
      $("#netbios_enable").change();

      $("#ntp_server_enable").change(function(){
          if ($("#ntp_server_enable").is(':checked')) {
              $("#ntp_server_data").show();
          } else {
              $("#ntp_server_data").hide();
          }
      });
      $("#ntp_server_enable").change();

      $("#client_mgmt_port_enable").change(function(){
          if ($("#client_mgmt_port_enable").is(':checked')) {
              $("#client_mgmt_port_data").show();
          } else {
              $("#client_mgmt_port_data").hide();
          }
      });
      $("#client_mgmt_port_enable").change();
      $(window).resize();
  }

});
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
        }?>
<?php
          if ($act=="new" || $act=="edit") :?>
          <form method="post" name="iform" id="iform">
            <section class="col-xs-12">
              <div class="tab-content content-box col-xs-12">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"><strong><?=gettext("General information"); ?></strong></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <a id="help_for_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?>
                      </td>
                      <td>
                        <div>
                          <input name="disable" type="checkbox" value="yes" <?= !empty($pconfig['disable']) ? "checked=\"checked\"" : "";?> />
                        </div>
                        <div class="hidden" data-for="help_for_disable">
                        <?=gettext("Set this option to disable this server without removing it from the list"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                      <td>
                        <input name="description" type="text" class="form-control unknown" size="30" value="<?=htmlspecialchars($pconfig['description']);?>" />
                        <div class="hidden" data-for="help_for_description">
                            <?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Mode");?></td>
                        <td>
                        <select name='mode' id="mode" class="selectpicker">
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
                            }?>
                        <option value="<?=$name;?>" <?=$selected;?>><?=$desc;?></option>
<?php
                        endforeach; ?>
                        </select>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Backend for authentication");?></td>
                      <td>
                        <select name='authmode[]' id='authmode' class="selectpicker" multiple="multiple" size="5">
<?php
                        if (isset($pconfig['authmode'])) {
                            $authmodes = explode(",", $pconfig['authmode']);
                        } else {
                            $authmodes = array();
                        }
                        $auth_servers = auth_get_authserver_list();
                        foreach ($auth_servers as $auth_key => $auth_server) :
                                $selected = "";
                            if (in_array($auth_key, $authmodes)) {
                                    $selected = "selected=\"selected\"";
                            }?>
                            <option value="<?=htmlspecialchars($auth_key); ?>" <?=$selected; ?>><?=htmlspecialchars($auth_server['name']);?></option>
<?php
                        endforeach; ?>
                        </select>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td><a id="help_for_local_group" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Enforce local group') ?></td>
                      <td>
                        <select name='local_group' id="local_group" class="selectpicker">
                          <option value="" <?= empty($pconfig['local_group']) ? 'selected="selected"' : '' ?>>(<?= gettext('none') ?>)</option>
<?php
                        foreach (config_read_array('system', 'group') as $group):
                            $selected = $pconfig['local_group'] == $group['name'] ? 'selected="selected"' : ''; ?>
                          <option value="<?= $group['name'] ?>" <?= $selected ?>><?= $group['name'] ?></option>
<?php
                        endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_local_group">
                          <?= gettext('Restrict access to users in the selected local group. Please be aware ' .
                            'that other authentication backends will refuse to authenticate when using this option.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_protocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol");?></td>
                        <td>
                          <select name='protocol' class="selectpicker">
<?php
                          foreach (openvpn_get_protocols() as $prot):
                              $selected = "";
                              if ($pconfig['protocol'] == $prot) {
                                  $selected = "selected=\"selected\"";
                              }?>
                            <option value="<?=$prot;?>" <?=$selected;?>><?=$prot;?></option>
<?php
                          endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_protocol">
                          <?= gettext('Select the protocol family to be used. Note that using both families with UDP/TCP ' .
                                      'does not work with an explicit interface as OpenVPN does not support listening to more ' .
                                      'than one specified IP address. In this case IPv4 is currently assumed.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Device Mode"); ?></td>
                      <td>
                        <select name="dev_mode" id="dev_mode" class="selectpicker">
<?php
                        foreach (array("tun", "tap") as $device) :
                               $selected = "";
                            if (! empty($pconfig['dev_mode'])) {
                                if ($pconfig['dev_mode'] == $device) {
                                        $selected = "selected=\"selected\"";
                                }
                            } else {
                                if ($device == "tun") {
                                        $selected = "selected=\"selected\"";
                                }
                            }?>
                        <option value="<?=$device;?>" <?=$selected;?>><?=$device;?></option>
<?php
                        endforeach; ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                      <td>
                        <select name="interface" class="selectpicker" data-size="5" data-live-search="true">
<?php
                        $interfaces = get_configured_interface_with_descr();
                        foreach (get_configured_carp_interface_list() as $cif => $carpip) {
                            $interfaces[$cif.'|'.$carpip] = $carpip." (".get_vip_descr($carpip).")";
                        }
                        foreach (get_configured_ip_aliases_list() as $aliasip => $aliasif) {
                            $interfaces[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                        }
                        $interfaces['lo0'] = "Localhost";
                        $interfaces['any'] = "any";
                        foreach ($interfaces as $iface => $ifacename) :?>
                          <option value="<?=$iface; ?>"<?=$iface == $pconfig['interface'] ? ' selected="selected"' : '';?>>
                            <?=htmlspecialchars($ifacename);?>
                          </option>
<?php
                        endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_interface">
                            <?=gettext(
                              "When selecting any in combination with UDP, we will assume the server is used multi-homed. ".
                              "This has some small performance implications to assure proper return address lookup."
                            ); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Local port");?></td>
                      <td>
                        <input name="local_port" type="text" class="form-control unknown" size="5" value="<?=$pconfig['local_port'];?>" />
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
            </section>
            <section class="col-xs-12">
              <div class="tab-content content-box col-xs-12">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td colspan="2"><strong><?=gettext("Cryptographic Settings"); ?></strong></td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("TLS Authentication"); ?></td>
                      <td style="width:78%">
                        <div>
                          <input name="tlsauth_enable" id="tlsauth_enable" type="checkbox" value="yes" <?=!empty($pconfig['tlsauth_enable']) ? "checked=\"checked\"" : "" ;?>/>
                          <?=gettext("Enable authentication of TLS packets"); ?>.
                        </div>
                        <?php if (!$pconfig['tls']) :
?>
                        <div>
                          <input name="autotls_enable" id="autotls_enable" type="checkbox" value="yes" <?=!empty($pconfig['autotls_enable']) ? "checked=\"checked\"" : "" ;?>  />
                          <?=gettext("Automatically generate a shared TLS authentication key"); ?>.
                         </div>
                        <?php
endif; ?>
                        <div>
                          <textarea id="tls" name="tls" cols="65" rows="7" class="formpre"><?=$pconfig['tls'];?></textarea>
                          <?=gettext("Paste your shared key here"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer Certificate Authority"); ?></td>
                        <td>
<?php
                        if (isset($config['ca'])) :?>
                          <select name='caref' class="selectpicker" data-size="5" data-live-search="true">
<?php
                          foreach ($config['ca'] as $ca) :
                              $selected = "";
                              if ($pconfig['caref'] == $ca['refid']) {
                                  $selected = "selected=\"selected\"";
                              }
                          ?>
                            <option value="<?=htmlspecialchars($ca['refid']);?>" <?=$selected;?>>
                              <?=htmlspecialchars($ca['descr']);?>
                            </option>
<?php
                          endforeach; ?>
                          </select>
<?php
                        else :?>
                          <b><?=gettext("No Certificate Authorities defined.");?></b>
                          <br /><?=gettext("Create one under")?> <a href="system_camanager.php"> <?=gettext("System: Certificates");?></a>.
<?php
                        endif; ?>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer Certificate Revocation List"); ?></td>
                      <td>
<?php
                        if (isset($config['crl'])) :?>
                        <select name='crlref' class="selectpicker" data-size="5" data-live-search="true">
                          <option value="">None</option>
<?php
                          foreach ($config['crl'] as $crl) :
                              if (!isset($crl['refid'])) {
                                  continue;
                              }
                              $ca = lookup_ca($crl['caref']);
                              if ($ca) {
                                  $selected = $pconfig['crlref'] == $crl['refid'] ? 'selected="selected"' : ''; ?>
                            <option value="<?=htmlspecialchars($crl['refid']);?>" <?=$selected;?>><?=htmlspecialchars("{$crl['descr']} ({$ca['descr']})");?></option>
<?php
                              }
                          endforeach; ?>
                        </select>
<?php
                        else :?>
                        <b><?=gettext("No Certificate Revocation Lists (CRLs) defined.");?></b>
                        <br /><?=gettext("Create one under");?> <a href="system_crlmanager.php"><?=gettext("System: Certificates");?></a>.
<?php
                        endif; ?>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Certificate"); ?></td>
                      <td>
<?php
                      if (isset($config['cert'])) :?>
                        <select name='certref' class="selectpicker" data-size="5" data-live-search="true">
<?php
                        foreach ($config['cert'] as $cert) :
                            $selected = "";
                            $caname = "";
                            $inuse = "";
                            $revoked = "";
                            if (!isset($cert['prv'])) {
                                continue;
                            }
                            if (isset($cert['caref'])) {
                                $ca = lookup_ca($cert['caref']);
                                if (!empty($ca)) {
                                    $caname = " ({$ca['descr']})";
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
                          <option value="<?=htmlspecialchars($cert['refid']);?>" <?=$selected;?>>
                            <?=htmlspecialchars($cert['descr'] . $caname . $inuse . $revoked);?>
                          </option>
<?php
                        endforeach; ?>
                        </select>
<?php
                      else :?>
                          <b><?=gettext("No Certificates defined.");?></b>
                          <br /><?=gettext("Create one under");?> <a href="system_certmanager.php"><?=gettext("System: Certificates");?></a>.
<?php
                      endif; ?>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("DH Parameters Length"); ?></td>
                      <td>
                        <select name="dh_length" class="selectpicker">
<?php
                        foreach (list_dh_parameters() as $length):
                            $selected = "";
                            if ($length == $pconfig['dh_length']) {
                                $selected = ' selected="selected"';
                            }
                        ?>
                          <option value="<?= html_safe($length) ?>" <?=$selected?>><?= sprintf(gettext('%s bit'), $length) ?></option>
<?php
                        endforeach; ?>
                        </select>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_shared_key">
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Shared Key"); ?></td>
                      <td>
<?php
                        if (empty($pconfig['shared_key'])) :?>
                        <div>
                          <input name="autokey_enable" id="autokey_enable" type="checkbox" value="yes"  <?=!empty($pconfig['autokey_enable']) ? "checked=\"checked\"" : "" ;?>  />
                          <?=gettext("Automatically generate a shared key"); ?>.
                        </div>
<?php
                        endif; ?>
                        <div id="autokey_opts">
                          <textarea name="shared_key" cols="65" rows="7"><?=$pconfig['shared_key'];?></textarea>
                          <?=gettext("Paste your shared key here"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Encryption algorithm"); ?></td>
                      <td>
                        <select name="crypto" class="selectpicker">
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
                      <td><a id="help_for_digest" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Auth Digest Algorithm"); ?></td>
                      <td>
                        <select name="digest" class="selectpicker" data-size="5" data-live-search="true">
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
                        <div class="hidden" data-for="help_for_digest">
                            <?= gettext('Leave this set to SHA1 unless all clients are set to match. SHA1 is the default for OpenVPN.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr id="engine">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hardware Crypto"); ?></td>
                      <td>
                        <select name="engine" class="selectpicker" data-size="5" data-live-search="true">
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
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_cert_depth" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Certificate Depth"); ?></td>
                      <td>
                        <table>
                        <tr><td>
                        <select name="cert_depth" class="selectpicker">
                          <option value=""><?=gettext('Do Not Check') ?></option>
<?php
                          $openvpn_cert_depths = array(
                            1 => gettext('One (Client+Server)'),
                            2 => gettext('Two (Client+Intermediate+Server)'),
                            3 => gettext('Three (Client+2xIntermediate+Server)'),
                            4 => gettext('Four (Client+3xIntermediate+Server)'),
                            5 => gettext('Five (Client+4xIntermediate+Server)')
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
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <div class="hidden" data-for="help_for_cert_depth">
                          <span>
                            <?=gettext("When a certificate-based client logs in, do not accept certificates below this depth. Useful for denying certificates made with intermediate CAs generated from the same CA as the server."); ?>
                          </span>
                        </div>
                        </td></tr>
                        </table>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_strictusercn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Strict User/CN Matching"); ?></td>
                      <td>
                        <input name="strictusercn" type="checkbox" value="yes" <?=!empty($pconfig['strictusercn']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_strictusercn">
                          <span>
                              <?=gettext("When authenticating users, enforce a match between the Common Name of the client certificate and the username given at login."); ?>
                          </span>
                        </div>
                      </td>
                    </tr>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td colspan="2"><strong><?=gettext("Tunnel Settings"); ?></strong></td>
                    </tr>
                    <tr>
                      <td style="width:22%" id="ipv4_tunnel_network"><a id="help_for_tunnel_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Tunnel Network"); ?></td>
                      <td style="width:78%">
                        <input name="tunnel_network" type="text" class="form-control unknown" size="20" value="<?=$pconfig['tunnel_network'];?>" />
                        <div class="hidden" data-for="help_for_tunnel_network">
                            <?=gettext("This is the IPv4 virtual network used for private " .
                                                  "communications between this server and client " .
                                                  "hosts expressed using CIDR (eg. 10.0.8.0/24). " .
                                                  "The first network address will be assigned to " .
                                                  "the server virtual interface. The remaining " .
                                                  "network addresses can optionally be assigned " .
                                                  "to connecting clients. (see Address Pool)"); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_tunnel_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Tunnel Network"); ?></td>
                      <td>
                        <input name="tunnel_networkv6" type="text" class="form-control unknown" size="20" value="<?=$pconfig['tunnel_networkv6'];?>" />
                        <div class="hidden" data-for="help_for_tunnel_networkv6">
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
                    <tr class="dev_mode dev_mode_tap">
                      <td style="width:22%"><a id="help_for_serverbridge_dhcp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bridge DHCP"); ?></td>
                      <td>
                              <input id="serverbridge_dhcp" name="serverbridge_dhcp" type="checkbox" value="yes" <?=!empty($pconfig['serverbridge_dhcp']) ? "checked=\"checked\"" : "" ;?>/>
                              <div class="hidden" data-for="help_for_serverbridge_dhcp">
                                <span>
                                    <?=gettext("Allow clients on the bridge to obtain DHCP."); ?><br />
                                </span>
                              </div>
                      </td>
                    </tr>
                    <tr class="dev_mode dev_mode_tap">
                      <td style="width:22%"><a id="help_for_serverbridge_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bridge Interface"); ?></td>
                      <td>
                        <select id="serverbridge_interface" name="serverbridge_interface" class="selectpicker" data-size="5" data-live-search="true">
<?php
                        $serverbridge_interface['none'] = "none";
                        $serverbridge_interface = array_merge($serverbridge_interface, get_configured_interface_with_descr());
                        foreach (get_configured_carp_interface_list() as $cif => $carpip) {
                            $serverbridge_interface[$cif.'|'.$carpip] = $carpip." (".get_vip_descr($carpip).")";
                        }
                        foreach (get_configured_ip_aliases_list() as $aliasip => $aliasif) {
                            $serverbridge_interface[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                        }
                        foreach ($serverbridge_interface as $iface => $ifacename) :
                          $selected = "";
                          if ($iface == $pconfig['serverbridge_interface']) {
                              $selected = "selected=\"selected\"";
                          }
                        ?>
                            <option value="<?=$iface;?>" <?=$selected;?>>
                                <?=htmlspecialchars($ifacename);?>
                            </option>
<?php
                        endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_serverbridge_interface">
                            <?=gettext("The interface to which this tap instance will be " .
                                                  "bridged. This is not done automatically. You must assign this " .
                                                  "interface and create the bridge separately. " .
                                                  "This setting controls which existing IP address and subnet " .
                                                  "mask are used by OpenVPN for the bridge. Setting this to " .
                                                  "'none' will cause the Server Bridge DHCP settings below to be ignored."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr class="dev_mode dev_mode_tap">
                      <td style="width:22%"><a id="help_for_serverbridge_dhcp_start" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Server Bridge DHCP Start"); ?></td>
                      <td>
                        <input  id="serverbridge_dhcp_start" name="serverbridge_dhcp_start" type="text" class="form-control unknown" size="20" value="<?=$pconfig['serverbridge_dhcp_start'];?>" />
                        <div class="hidden" data-for="help_for_serverbridge_dhcp_start">
                            <?=gettext("When using tap mode as a multi-point server, " .
                                                  "you may optionally supply a DHCP range to use on the " .
                                                  "interface to which this tap instance is bridged. " .
                                                  "If these settings are left blank, DHCP will be passed " .
                                                  "through to the LAN, and the interface setting above " .
                                                  "will be ignored."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr class="dev_mode dev_mode_tap">
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server Bridge DHCP End"); ?></td>
                      <td>
                        <input id="serverbridge_dhcp_end" name="serverbridge_dhcp_end" type="text" class="form-control unknown" size="20" value="<?=$pconfig['serverbridge_dhcp_end'];?>" />
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_p2p_shared_key opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_gwredir" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect Gateway"); ?></td>
                      <td>
                        <input name="gwredir" id="gwredir" type="checkbox" value="yes" <?=!empty($pconfig['gwredir']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_gwredir">
                            <span>
                                <?= gettext('Force all client generated traffic through the tunnel.') ?>
                            </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_p2p_shared_key opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user opt_gwredir">
                      <td style="width:22%"><a id="help_local_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Local Network"); ?></td>
                      <td>
                        <input name="local_network" type="text" class="form-control unknown" size="40" value="<?=$pconfig['local_network'];?>" />
                        <div class="hidden" data-for="help_local_network">
                            <?=gettext("These are the IPv4 networks that will be accessible " .
                                                  "from the remote endpoint. Expressed as a comma-separated list of one or more CIDR ranges. " .
                                                  "You may leave this blank if you don't " .
                                                  "want to add a route to the local network " .
                                                  "through this tunnel on the remote machine. " .
                                                  "This is generally set to your LAN network"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_p2p_shared_key opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user opt_gwredir">
                      <td style="width:22%"><a id="help_for_local_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Local Network"); ?></td>
                      <td>
                        <input name="local_networkv6" type="text" class="form-control unknown" size="40" value="<?=$pconfig['local_networkv6'];?>" />
                        <div class="hidden" data-for="help_for_local_networkv6">
                            <?=gettext("These are the IPv6 networks that will be accessible " .
                                                  "from the remote endpoint. Expressed as a comma-separated list of one or more IP/PREFIX. " .
                                                  "You may leave this blank if you don't " .
                                                  "want to add a route to the local network " .
                                                  "through this tunnel on the remote machine. " .
                                                  "This is generally set to your LAN network"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_p2p_shared_key opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_remote_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Remote Network"); ?></td>
                      <td>
                        <input name="remote_network" type="text" class="form-control unknown" size="40" value="<?=$pconfig['remote_network'];?>" />
                        <div class="hidden" data-for="help_for_remote_network">
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
                    <tr class="opt_mode opt_mode_p2p_tls opt_mode_p2p_shared_key opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_remote_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Remote Network"); ?></td>
                      <td>
                        <input name="remote_networkv6" type="text" class="form-control unknown" size="40" value="<?=$pconfig['remote_networkv6'];?>" />
                        <div class="hidden" data-for="help_for_remote_networkv6">
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
                      <td style="width:22%"><a id="help_for_maxclients" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Concurrent connections");?></td>
                      <td>
                        <input name="maxclients" type="text" class="form-control unknown" size="5" value="<?=$pconfig['maxclients'];?>" />
                        <div class="hidden" data-for="help_for_maxclients">
                            <?=gettext("Specify the maximum number of clients allowed to concurrently connect to this server"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_compression" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Compression"); ?></td>
                      <td>
                        <select name="compression" class="selectpicker">
<?php
                        foreach (openvpn_compression_modes() as $cmode => $cmodedesc):
                            $selected = "";
                            if ($cmode == $pconfig['compression']) {
                                $selected = " selected=\"selected\"";
                            }
                          ?>
                            <option value="<?= $cmode ?>" <?= $selected ?>><?= $cmodedesc ?></option>
<?php
                        endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_compression">
                            <?=gettext("Compress tunnel packets using the LZ4/LZO algorithm. The LZ4 generally offers the best preformance with least CPU usage. For backwards compatibility use the LZO (which is identical to the older option --comp-lzo yes). In the partial mode (the option --compress with an empty algorithm) compression is turned off, but the packet framing for compression is still enabled, allowing a different setting to be pushed later. The legacy LZO algorithm with adaptive compression mode will dynamically disable compression for a period of time if OpenVPN detects that the data in the packets is not being compressed efficiently."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_passtos" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type-of-Service"); ?></td>
                      <td>
                        <input name="passtos" type="checkbox" value="yes" <?=!empty($pconfig['passtos']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_passtos">
                          <span>
                            <?=gettext("Set the TOS IP header value of tunnel packets to match the encapsulated packet value"); ?>.
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_client2client" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Inter-client communication"); ?></td>
                      <td>
                          <input name="client2client" type="checkbox" value="yes"  <?=!empty($pconfig['client2client']) ? "checked=\"checked\"" : "" ;?> />
                          <div class="hidden" data-for="help_for_client2client">
                            <span>
                                <?=gettext("Allow communication between clients connected to this server"); ?>
                            </span>
                          </div>
                      </td>
                    </tr>
                    <tr id="duplicate_cn">
                      <td style="width:22%"><a id="help_for_duplicate_cn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Duplicate Connections"); ?></td>
                      <td>
                            <input name="duplicate_cn" type="checkbox" value="yes" <?=!empty($pconfig['duplicate_cn']) ? "checked=\"checked\"" : "" ;?> />
                            <div class="hidden" data-for="help_for_duplicate_cn">
                              <span>
                                <?=gettext("Allow multiple concurrent connections from clients using the same Common Name.<br />NOTE: This is not generally recommended, but may be needed for some scenarios."); ?>
                              </span>
                            </div>
                      </td>
                    </tr>
                    <tr class="dev_mode dev_mode_tun">
                      <td style="width:22%"><a id="help_for_no_tun_ipv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable IPv6"); ?></td>
                      <td>
                        <input name="no_tun_ipv6" type="checkbox" value="yes" <?=!empty($pconfig['no_tun_ipv6']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_no_tun_ipv6">
                          <span>
                            <?=gettext("Don't forward IPv6 traffic"); ?>.
                          </span>
                        </div>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
            </section>
            <section class="col-xs-12">
              <div class="tab-content content-box col-xs-12">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td colspan="2"><strong><?=gettext("Client Settings"); ?></strong></td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_dynamic_ip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Dynamic IP"); ?></td>
                      <td style="width:78%">
                        <input name="dynamic_ip" type="checkbox" id="dynamic_ip" value="yes" <?=!empty($pconfig['dynamic_ip']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_dynamic_ip">
                          <span>
                            <?=gettext("Allow connected clients to retain their connections if their IP address changes"); ?>.<br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_pool_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Address Pool"); ?></td>
                      <td>
                        <input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?=!empty($pconfig['pool_enable']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_pool_enable">
                          <span>
                            <?=gettext("Provide a virtual adapter IP address to clients (see Tunnel Network)"); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="dev_mode dev_mode_tun" id="topology_subnet_opt">
                      <td style="width:22%"><a id="help_for_topology_subnet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Topology"); ?></td>
                      <td>
                        <input name="topology_subnet" type="checkbox" id="topology_subnet" value="yes"  <?=!empty($pconfig['topology_subnet']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_topology_subnet">
                          <span>
                            <?=gettext("Allocate only one IP per client (topology subnet), rather than an isolated subnet per client (topology net30)."); ?><br />
                            <?=gettext("Relevant when supplying a virtual adapter IP address to clients when using tun mode on IPv4."); ?><br />
                            <?=gettext("Some clients may require this even for IPv6, such as OpenVPN Connect (iOS/Android). Others may break if it is present, such as older versions of OpenVPN or clients such as Yealink phones."); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td style="width:22%"><a id="help_for_dns_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Default Domain"); ?></td>
                      <td>
                        <input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?=!empty($pconfig['dns_domain']) ? "checked=\"checked\"" : "" ;?> />
                        <div id="dns_domain_data">
                              <input name="dns_domain" type="text" class="form-control unknown" id="dns_domain" size="30" value="<?=htmlspecialchars($pconfig['dns_domain']);?>" />
                        </div>
                        <div class="hidden" data-for="help_for_dns_domain">
                          <span>
                              <?=gettext("Provide a default domain name to clients"); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td style="width:22%"><a id="help_for_dns_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Servers"); ?></td>
                      <td>
                        <input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=!empty($pconfig['dns_server1']) || !empty($pconfig['dns_server2']) || !empty($pconfig['dns_server3']) || !empty($pconfig['dns_server4']) ? "checked=\"checked\"" : "" ;?> />
                        <div id="dns_server_data">
                              <span>
                                <?=gettext("Server #1:"); ?>&nbsp;
                              </span>
                              <input name="dns_server1" type="text" class="form-control unknown" id="dns_server1" size="20" value="<?=$pconfig['dns_server1'];?>" />
                              <span>
                                <?=gettext("Server #2:"); ?>&nbsp;
                              </span>
                              <input name="dns_server2" type="text" class="form-control unknown" id="dns_server2" size="20" value="<?=$pconfig['dns_server2'];?>" />
                              <span>
                                <?=gettext("Server #3:"); ?>&nbsp;
                              </span>
                              <input name="dns_server3" type="text" class="form-control unknown" id="dns_server3" size="20" value="<?=$pconfig['dns_server3'];?>" />
                              <span>
                                <?=gettext("Server #4:"); ?>&nbsp;
                              </span>
                              <input name="dns_server4" type="text" class="form-control unknown" id="dns_server4" size="20" value="<?=$pconfig['dns_server4'];?>" />
                        </div>
                        <div class="hidden" data-for="help_for_dns_server">
                          <span>
                            <?=gettext("Provide a DNS server list to clients"); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr id="chkboxPushRegisterDNS" class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td style="width:22%"><a id="help_for_push_register_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Force DNS cache update"); ?></td>
                      <td>
                        <input name="push_register_dns" type="checkbox" value="yes" <?=!empty($pconfig['push_register_dns']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_push_register_dns">
                          <span>
                            <?=gettext("Run ''net stop dnscache'', ''net start dnscache'', ''ipconfig /flushdns'' and ''ipconfig /registerdns'' on connection initiation. This is known to kick Windows into recognizing pushed DNS servers."); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr id="chkboxBlockOutsideDNS" class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td style="width:22%"><a id="help_for_push_block_outside_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prevent DNS leaks"); ?></td>
                      <td>
                        <input name="push_block_outside_dns" type="checkbox" value="yes" <?=!empty($pconfig['push_block_outside_dns']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_push_block_outside_dns">
                          <span>
                            <?=gettext("Block DNS servers on other network adapters to prevent DNS leaks. Compatible with Windows clients only."); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td style="width:22%"><a id="help_for_ntp_server_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NTP Servers"); ?></td>
                      <td>
                        <input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=!empty($pconfig['ntp_server1']) || !empty($pconfig['ntp_server2']) ? "checked=\"checked\"" : "" ;?>  />
                        <div id="ntp_server_data">
                          <span>
                            <?=gettext("Server #1:"); ?>&nbsp;
                          </span>
                          <input name="ntp_server1" type="text" class="form-control unknown" id="ntp_server1" size="20" value="<?=$pconfig['ntp_server1'];?>" />
                          <span>
                            <?=gettext("Server #2:"); ?>&nbsp;
                          </span>
                          <input name="ntp_server2" type="text" class="form-control unknown" id="ntp_server2" size="20" value="<?=$pconfig['ntp_server2'];?>" />
                        </div>
                        <div class="hidden" data-for="help_for_ntp_server_enable">
                          <span>
                            <?=gettext("Provide a NTP server list to clients"); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls opt_mode_server_user opt_mode_server_tls_user" style="display:none">
                      <td style="width:22%"><a id="help_for_netbios_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NetBIOS Options"); ?></td>
                      <td>
                        <input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=!empty($pconfig['netbios_enable']) ? "checked=\"checked\"" : "" ;?>  />
                        <div class="hidden" data-for="help_for_netbios_enable">
                          <span>
                            <?=gettext("Enable NetBIOS over TCP/IP"); ?><br />
                            <?=gettext("If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled"); ?>.
                          </span>
                        </div>
                        <div id="netbios_data">
                          <span>
                            <?=gettext("Node Type"); ?>:&nbsp;
                          </span><br>
                          <select name='netbios_ntype' class="selectpicker">
<?php
                          foreach ($netbios_nodetypes as $type => $name) :
                              $selected = "";
                              if ($pconfig['netbios_ntype'] == $type) {
                                  $selected = "selected=\"selected\"";
                              }
                          ?>
                            <option value="<?=$type;?>" <?=$selected;?>><?=$name;?></option>
<?php
                          endforeach; ?>
                          </select>
                          <div class="hidden" data-for="help_for_netbios_enable">
                            <?=gettext("Possible options: b-node (broadcasts), p-node " .
                                                        "(point-to-point name queries to a WINS server), " .
                                                        "m-node (broadcast then query name server), and " .
                                                        "h-node (query name server, then broadcast)."); ?>
                          </div><br>
                          <span>
                            <?=gettext("Scope ID"); ?>:&nbsp;
                          </span>
                          <input name="netbios_scope" type="text" class="form-control unknown" id="netbios_scope" size="30" value="<?=$pconfig['netbios_scope'];?>" />
                          <div class="hidden" data-for="help_for_netbios_enable">
                            <?=gettext("A NetBIOS Scope ID provides an extended naming " .
                                                        "service for NetBIOS over TCP/IP. The NetBIOS " .
                                                        "Scope ID isolates NetBIOS traffic on a single " .
                                                        "network to only those nodes with the same " .
                                                        "NetBIOS Scope ID."); ?>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr id="wins_opts">
                      <td style="width:22%"><a id="help_for_wins_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WINS Servers"); ?></td>
                      <td>
                        <input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?=!empty($pconfig['wins_server1']) || !empty($pconfig['wins_server2']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_wins_server">
                          <span>
                            <?=gettext("Provide a WINS server list to clients"); ?><br />
                          </span>
                        </div>
                        <div id="wins_server_data">
                          <span>
                            <?=gettext("Server #1:"); ?>&nbsp;
                          </span>
                          <input name="wins_server1" type="text" class="form-control unknown" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>" />
                          <span>
                            <?=gettext("Server #2:"); ?>&nbsp;
                          </span>
                          <input name="wins_server2" type="text" class="form-control unknown" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>" />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_client_mgmt_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Client Management Port"); ?></td>
                      <td>
                        <input name="client_mgmt_port_enable" type="checkbox" id="client_mgmt_port_enable" value="yes" <?=!empty($pconfig['client_mgmt_port']) ? "checked=\"checked\"" : "" ;?> />
                        <div id="client_mgmt_port_data">
                              <input name="client_mgmt_port" type="text" class="form-control unknown" id="client_mgmt_port" size="30" value="<?=htmlspecialchars($pconfig['client_mgmt_port']);?>" />
                        </div>
                        <div class="hidden" data-for="help_for_client_mgmt_port">
                          <span>
                            <?=gettext("Use a different management port on clients. The default port is 166. Specify a different port if the client machines need to select from multiple OpenVPN links."); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls_user">
                      <td style="width:22%"><a id="help_for_use-common-name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use common name"); ?></td>
                      <td>
                        <input name="use-common-name" type="checkbox" value="1" <?=!empty($pconfig['use-common-name']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_use-common-name">
                          <span>
                            <?=gettext("When using a client certificate, use certificate common name for indexing purposes instead of username"); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
            </section>
            <section class="col-xs-12">
              <div class="tab-content content-box col-xs-12">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td colspan="2"><strong><?=gettext("Advanced configuration"); ?></strong></td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_custom_options" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Advanced"); ?></td>
                      <td>
                        <textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea>
                        <?=gettext("This option will be removed in the future due to being insecure by nature. In the mean time only full administrators are allowed to change this setting.");?>
                        <div class="hidden" data-for="help_for_custom_options">
                          <?=gettext("Enter any additional options you would like to add to the configuration file here."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr id="comboboxVerbosityLevel">
                      <td style="width:22%"><a id="help_for_verbosity_level" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Verbosity level");?></td>
                      <td>
                        <select name="verbosity_level" class="selectpicker">
<?php
                        foreach (openvpn_verbosity_level() as $verb_value => $verb_desc):
                            $selected = '';
                            if ($pconfig['verbosity_level'] == $verb_value) {
                                $selected = 'selected="selected"';
                            }
                        ?>
                          <option value="<?=$verb_value; ?>" <?=$selected; ?>><?=$verb_desc;?></option>
<?php
                        endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_verbosity_level">
                          <?=gettext("Each level shows all info from the previous levels. Level 3 is recommended if you want a good summary of what's happening without being swamped by output."); ?><br /> <br />
                          <?=sprintf(gettext("%s0%s -- No output except fatal errors."),'<strong>','</strong>') ?> <br />
                          <?=sprintf(gettext("%s1%s -- startup info + connection initiated messages + non-fatal encryption & net errors."),'<strong>','</strong>') ?> <br />
                          <?=sprintf(gettext("%s2,3%s -- show TLS negotiations & route info."),'<strong>','</strong>') ?> <br />
                          <?=sprintf(gettext("%s4%s -- Normal usage range."),'<strong>','</strong>') ?> <br />
                          <?=sprintf(gettext("%s5%s -- Output R and W characters to the console for each packet read and write, uppercase is used for TCP/UDP packets and lowercase is used for TUN/TAP packets."),'<strong>','</strong>') ?> <br />
                          <?=sprintf(gettext("%s6%s-%s11%s -- Debug info range."),'<strong>','</strong>','<strong>','</strong>') ?>
                        </div>
                      </td>
                    </tr>
                    <tr class="opt_mode opt_mode_server_tls_user opt_mode_server_user">
                      <td><a id="help_for_reneg-sec" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Renegotiate time"); ?></td>
                      <td>
                        <input type="text" name="reneg-sec" value="<?=$pconfig['reneg-sec'];?>">
                        <div class="hidden" data-for="help_for_reneg-sec">
                          <?=sprintf(
                              gettext('Renegotiate data channel key after n seconds (default=3600).%s' .
                                     'When using a one time password, be advised that your connection will automatically drop because your password is not valid anymore.%sSet to 0 to disable, remember to change your client as well.'),
                                     '<br/>','<br/>');?>
                        </div>
                      </td>
                    </tr>
                    <tr id="chkboxLoginMatching">
                      <td style="width:22%"><a id="help_for_cso_login_matching" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Force CSO Login Matching"); ?></td>
                      <td>
                        <input name="cso_login_matching" type="checkbox" value="yes" <?=!empty($pconfig['cso_login_matching']) ? "checked=\"checked\"" : "" ;?> />
                        <div class="hidden" data-for="help_for_cso_login_matching">
                          <span>
                            <?=gettext("Use username instead of common name to match client specfic override."); ?><br />
                          </span>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%">&nbsp;</td>
                      <td style="width:78%">
                        <input name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                        <input name="act" type="hidden" value="<?=$act;?>" />
<?php
                        if (isset($id) && $a_server[$id]) :?>
                        <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
                        endif; ?>
                      </td>
                    </tr>
                  </table>
                </div>
               </div>
             </section>
           </form>

<?php
              else :?>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <table class="table table-striped">
                <thead>
                <tr>
                  <td></td>
                  <td><?=gettext("Protocol / Port"); ?></td>
                  <td><?=gettext("Tunnel Network"); ?></td>
                  <td><?=gettext("Description"); ?></td>
                  <td class="text-nowrap"></td>
                </tr>
                </thead>

                <tbody>
<?php
                  $i = 0;
                  foreach ($a_server as $server) :?>
                  <tr>
                    <td>
                      <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=(empty($server['disable'])) ? gettext("Disable") : gettext("Enable");?>">
                        <span class="fa fa-play <?=(empty($server['disable'])) ? "text-success" : "text-muted";?>"></span>
                      </a>
                    </td>
                    <td>
                        <?=htmlspecialchars($server['protocol']);?> / <?=htmlspecialchars($server['local_port']);?>
                    </td>
                    <td>
                        <?= htmlspecialchars($server['tunnel_network'])  ?>
                        <?= !empty($server['tunnel_networkv6']) && !empty($server['tunnel_network']) ? ',' : '' ?>
                        <?= htmlspecialchars($server['tunnel_networkv6']) ?>
                    </td>
                    <td>
                        <?=htmlspecialchars($server['description']);?>
                    </td>
                    <td class="text-nowrap">
                        <a href="vpn_openvpn_server.php?act=edit&amp;id=<?=$i;?>"  title="<?= html_safe(gettext('Edit')) ?>" data-toggle="tooltip" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                        <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" class="act_delete btn btn-default btn-xs"><i class="fa fa-trash fa-fw"></i></a>
                        <a href="vpn_openvpn_server.php?act=new&amp;dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>">
                          <span class="fa fa-clone fa-fw"></span>
                        </a>
                    </td>
                  </tr>
<?php
                  $i++;
                  endforeach;?>
                  <tr>
                    <td colspan="5">
                      <a href="wizard.php?xml=openvpn" class="btn btn-default">
                        <i class="fa fa-magic fa-fw"></i> <?= gettext('Use a wizard to setup a new server') ?>
                       </a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
<?php
              endif; ?>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
