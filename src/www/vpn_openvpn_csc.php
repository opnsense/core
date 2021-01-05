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

$all_form_fields = "custom_options,disable,common_name,block,description
    ,tunnel_network,tunnel_networkv6,local_network,local_networkv6,remote_network
    ,remote_networkv6,gwredir,push_reset,dns_domain,dns_server1
    ,dns_server2,dns_server3,dns_server4,ntp_server1,ntp_server2
    ,netbios_enable,netbios_ntype,netbios_scope,wins_server1
    ,wins_server2,ovpn_servers";

$a_csc = &config_read_array('openvpn', 'openvpn-csc');
$vpnid = 0;
$act = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    if (isset($_GET['dup']) && isset($a_csc[$_GET['dup']]))  {
        $configId = $_GET['dup'];
    } elseif (isset($_GET['id']) && isset($a_csc[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }

    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    }

    // 1 on 1 copy of config attributes
    foreach (explode(",", $all_form_fields) as $fieldname) {
        $fieldname = trim($fieldname);
        if (isset($a_csc[$configId][$fieldname])) {
            $pconfig[$fieldname] = $a_csc[$configId][$fieldname];
        } elseif (!isset($pconfig[$fieldname])) {
            // initialize element
            $pconfig[$fieldname] = null;
        }
    }
    // servers => array
    $pconfig['ovpn_servers'] = empty($pconfig['ovpn_servers']) ? array() : explode(',', $pconfig['ovpn_servers']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    }
    if (isset($_POST['id']) && isset($a_csc[$_POST['id']])) {
        $id = $_POST['id'];
    }

    if ($act == "del") {
        if (isset($id)) {
            unset($a_csc[$id]);
            write_config();
        }
        header(url_safe('Location: /vpn_openvpn_csc.php'));
        exit;
    } elseif ($act == "del_x") {
        if (!empty($pconfig['rule']) && is_array($pconfig['rule'])) {
            foreach ($pconfig['rule'] as $rulei) {
                if (isset($a_csc[$rulei])) {
                    unset($a_csc[$rulei]);
                }
            }
            write_config();
        }
        header(url_safe('Location: /vpn_openvpn_csc.php'));
        exit;
    } elseif ($act == "move"){
      // move selected items
      if (!isset($id)) {
          // if id not set/found, move to end
          $id = count($a_csc);
      }
      $a_csc = legacy_move_config_list_items($a_csc, $id,  $pconfig['rule']);
      write_config();
      header(url_safe('Location: /vpn_openvpn_csc.php'));
      exit;
    } elseif ($act == "toggle") {
        if (isset($id)) {
            if (isset($a_csc[$id]['disable'])) {
                unset($a_csc[$id]['disable']);
            } else {
                $a_csc[$id]['disable'] = true;
            }
            write_config();
        }
        header(url_safe('Location: /vpn_openvpn_csc.php'));
        exit;
    } else {
        if ($result = openvpn_validate_cidr($pconfig['tunnel_network'], gettext('IPv4 Tunnel Network'), false, 'ipv4', true)) {
            $input_errors[] = $result;
        }
        if ($result = openvpn_validate_cidr($pconfig['tunnel_networkv6'], gettext('IPv6 Tunnel Network'), false, 'ipv6', true)) {
            $input_errors[] = $result;
        }
        if ($result = openvpn_validate_cidr($pconfig['local_network'], gettext('IPv4 Local Network'), true, 'ipv4')) {
            $input_errors[] = $result;
        }
        if ($result = openvpn_validate_cidr($pconfig['local_networkv6'], gettext('IPv6 Local Network'), true, 'ipv6')) {
            $input_errors[] = $result;
        }
        if ($result = openvpn_validate_cidr($pconfig['remote_network'], gettext('IPv4 Remote Network'), true, 'ipv4')) {
            $input_errors[] = $result;
        }
        if ($result = openvpn_validate_cidr($pconfig['remote_networkv6'], gettext('IPv6 Remote Network'), true, 'ipv6')) {
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
        $prev_opt = (isset($id) && !empty($a_csc[$id])) ? $a_csc[$id]['custom_options'] : "";
        if ($prev_opt != str_replace("\r\n", "\n", $pconfig['custom_options']) && !userIsAdmin($_SESSION['Username'])) {
            $input_errors[] = gettext('Advanced options may only be edited by system administrators due to the increased possibility of privilege escalation.');
        }


        $reqdfields[] = 'common_name';
        $reqdfieldsn[] = 'Common name';

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (count($input_errors) == 0) {
            $csc = array();
            // 1 on 1 copy of config attributes
            foreach (explode(",", $all_form_fields) as $fieldname) {
                $fieldname = trim($fieldname);
                if (!empty($pconfig[$fieldname])) {
                    if (is_array($pconfig[$fieldname])) {
                        $csc[$fieldname] = implode(',', $pconfig[$fieldname]);
                    } else {
                        $csc[$fieldname] = $pconfig[$fieldname];
                    }
                }
            }

            // handle fields with some kind of logic
            if (!empty($pconfig['disable']) && $pconfig['disable'] == "yes") {
                $csc['disable'] = true;
            }

            if (isset($id)) {
                $old_csc_cn = $a_csc[$id]['common_name'];
                $a_csc[$id] = $csc;
            } else {
                $a_csc[] = $csc;
            }

            write_config();

            header(url_safe('Location: /vpn_openvpn_csc.php'));
            exit;
        }
    }
}

// escape form output before processing
legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

<body>

<script>
//<![CDATA[
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(){
    var id = $(this).data("id");
    if (id != 'x') {
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
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
    } else {
      // delete selected
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?=gettext("OpenVPN");?>",
        message: "<?=gettext("Do you really want to delete the selected csc's?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val("");
                    $("#action").val("del_x");
                    $("#iform2").submit()
                }
              }]
      });
    }
  });
  // link toggle buttons
  $(".act_toggle").click(function(event){
      event.preventDefault();
      $.post(window.location, {act: 'toggle', id:$(this).data("id")}, function(data) {
          location.reload();
      });
  });

  // link move buttons
  $(".act_move").click(function(event){
    event.preventDefault();
    $("#id").val($(this).data("id"));
    $("#action").val("move");
    $("#iform2").submit();
  });

  // checkboxes
  $("#dns_domain_enable").change(function(){
      if ($("#dns_domain_enable").is(":checked")) {
          $("#dns_domain_data").show();
      } else {
          $("#dns_domain_data").hide();
      }
  });

  $("#dns_server_enable").change(function(){
      if ($("#dns_server_enable").is(":checked")) {
          $("#dns_server_data").show();
      } else {
          $("#dns_server_data").hide();
      }
  });

  $("#wins_server_enable").change(function(){
      if ($("#wins_server_enable").is(":checked")) {
          $("#wins_server_data").show();
      } else {
          $("#wins_server_data").hide();
      }
  });

  $("#ntp_server_enable").change(function(){
      if ($("#ntp_server_enable").is(":checked")) {
          $("#ntp_server_data").show();
      } else {
          $("#ntp_server_data").hide();
      }
  });

  $("#netbios_enable").change(function(){
      if ($("#netbios_enable").is(":checked")) {
          $("#netbios_data").show();
          $("#wins_opts").show();
      } else {
          $("#netbios_data").hide();
          $("#wins_opts").hide();
      }
  });


  // init form (old stuff)
  if (document.iform != undefined) {
    $("#dns_domain_enable").change();
    $("#dns_server_enable").change();
    $("#wins_server_enable").change();
    $("#ntp_server_enable").change();
    $("#netbios_enable").change();
  }
  // watch scroll position and set to last known on page load
  watchScrollPosition();
});
//]]>
</script>

<?
if ($act!="new" && $act!="edit") {
    $main_buttons = array(
        array('href' => 'vpn_openvpn_csc.php?act=new', 'label' => gettext('Add')),
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
        }?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
<?php
          if ($act=="new" || $act=="edit") :?>
              <form method="post" name="iform" id="iform">
               <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td><?=gettext("General information"); ?></td>
                    <td style="text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%"><a id="help_for_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                    <td style="width:78%">
                      <input name="disable" type="checkbox" value="yes" <?= !empty($pconfig['disable']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_disable">
                        <?=gettext("Set this option to disable this client-specific override without removing it from the list"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_servers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Servers"); ?></td>
                    <td>
                      <select name="ovpn_servers[]" class="selectpicker" multiple="multiple" data-size="5" data-live-search="true">
<?php
                      foreach (openvpn_get_remote_access_servers() as $ra_server_vpnid => $ra_server):?>
                        <option value="<?=$ra_server_vpnid;?>" <?= !empty($pconfig['ovpn_servers']) && in_array($ra_server_vpnid, $pconfig['ovpn_servers']) ?  'selected="selected"' : '' ?>>
                           <?=!empty($ra_server['description']) ? $ra_server['description'] : ""?> ( <?=$ra_server['local_port'];?> / <?=$ra_server['protocol'];?>)
                        </option>
<?php
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_servers">
                        <?=gettext("Select the OpenVPN servers where this override applies to, leave empty for all"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_common_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Common name"); ?></td>
                    <td>
                      <input name="common_name" type="text" value="<?=$pconfig['common_name'];?>" />
                      <div class="hidden" data-for="help_for_common_name">
                        <?=gettext("Enter the client's X.509 common name here"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="description" type="text" value="<?=$pconfig['description'];?>" />
                      <div class="hidden" data-for="help_for_description">
                        <?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_block" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Connection blocking"); ?></td>
                    <td>
                      <input name="block" type="checkbox" value="yes" <?= !empty($pconfig['block']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_block">
                          <?=gettext("Block this client connection based on its common name"); ?>.<br/>
                          <?=gettext("Don't use this option to permanently disable a " .
                                                     "client due to a compromised key or password. " .
                                                     "Use a CRL (certificate revocation list) instead"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2" height="12"></td>
                  </tr>
                  <tr>
                    <td colspan="2" ><?=gettext("Tunnel Settings"); ?></td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Tunnel Network"); ?></td>
                    <td>
                      <input name="tunnel_network" type="text" size="20" value="<?=$pconfig['tunnel_network'];?>" />
                      <div class="hidden" data-for="help_for_tunnel_network">
                        <?=gettext("This is the IPv4 virtual network used for private " .
                                                "communications between this client and the " .
                                                "server expressed using CIDR (eg. 10.0.8.0/24). " .
                                                "The first network address is assumed to be the " .
                                                "server address and the second network address " .
                                                "will be assigned to the client virtual " .
                                                "interface"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Tunnel Network"); ?></td>
                    <td>
                      <input name="tunnel_networkv6" type="text" value="<?=$pconfig['tunnel_networkv6'];?>" />
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
                  <tr id="local_optsv4">
                    <td><a id="help_for_local_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Local Network"); ?></td>
                    <td>
                      <input name="local_network" type="text" size="40" value="<?=$pconfig['local_network'];?>" />
                      <div class="hidden" data-for="help_for_local_network">
                        <?=gettext("These are the IPv4 networks that will be accessible " .
                                                "from this particular client. Expressed as a comma-separated list of one or more CIDR ranges."); ?>
                      <br /><?=gettext("NOTE: You do not need to specify networks here if they have " .
                                            "already been defined on the main server configuration.");?>
                      </div>
                    </td>
                  </tr>
                  <tr id="local_optsv6">
                    <td><a id="help_for_local_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Local Network"); ?></td>
                    <td>
                      <input name="local_networkv6" type="text" size="40" value="<?=$pconfig['local_networkv6'];?>" />
                      <div class="hidden" data-for="help_for_local_networkv6">
                                                    <?=gettext("These are the IPv6 networks that will be accessible " .
                                                    "from this particular client. Expressed as a comma-separated list of one or more IP/PREFIX networks."); ?><br />
                                                    <?=gettext("NOTE: You do not need to specify networks here if they have " .
                                                    "already been defined on the main server configuration.");?>
                      </div>
                    </td>
                  </tr>
                  <tr id="remote_optsv4">
                    <td><a id="help_for_remote_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv4 Remote Network"); ?></td>
                    <td>
                      <input name="remote_network" type="text" size="40" value="<?=$pconfig['remote_network'];?>" />
                      <div class="hidden" data-for="help_for_remote_network">
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
                    <td><a id="help_for_remote_networkv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Remote Network"); ?></td>
                    <td>
                      <input name="remote_networkv6" type="text" size="40" value="<?=$pconfig['remote_networkv6'];?>" />
                      <div class="hidden" data-for="help_for_remote_networkv6">
                        <?=gettext("These are the IPv6 networks that will be routed " .
                                                "to this client specifically using iroute, so that a site-to-site " .
                                                "VPN can be established. " .
                                                "Expressed as a comma-separated list of one or more IP/PREFIX networks. " .
                                                "You may leave this blank if there are no client-side networks to " .
                                                "be routed."); ?><br />
                        <?=gettext("NOTE: Remember to add these subnets to the " .
                                                "IPv6 Remote Networks list on the corresponding OpenVPN server settings.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_gwredir" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect Gateway"); ?></td>
                    <td>
                      <input name="gwredir" type="checkbox" value="yes" <?= !empty($pconfig['gwredir']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_gwredir">
                        <?= gettext('Force all client generated traffic through the tunnel.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2" height="12"></td>
                  </tr>
                  <tr>
                    <td colspan="2"><?=gettext("Client Settings"); ?></td>
                  </tr>
                  <tr>
                    <td><a id="help_for_push_reset" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Server Definitions"); ?></td>
                    <td>
                      <input name="push_reset" type="checkbox" value="yes" <?= !empty($pconfig['push_reset']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_push_reset">
                          <?=gettext("Prevent this client from receiving any server-defined client settings."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dns_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Default Domain"); ?></td>
                    <td>
                      <input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes" <?= !empty($pconfig['dns_domain']) ? "checked=\"checked\"" : "";?> />
                      <div id="dns_domain_data" style="display:none">
                        <input name="dns_domain" type="text" id="dns_domain" value="<?=$pconfig['dns_domain'];?>" />
                      </div>
                      <div class="hidden" data-for="help_for_dns_domain">
                        <?=gettext("Provide a default domain name to clients"); ?><br />
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dns_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Servers"); ?></td>
                    <td>
                      <input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes" <?=!empty($pconfig['dns_server1']) || !empty($pconfig['dns_server2']) || !empty($pconfig['dns_server3']) || !empty($pconfig['dns_server4']) ? "checked=\"checked\"" : "" ;?> />
                      <div id="dns_server_data" style="display:none">
                        <?=gettext("Server #1:"); ?>&nbsp;
                        <input name="dns_server1" type="text" id="dns_server1" size="20" value="<?=htmlspecialchars($pconfig['dns_server1']);?>" />
                        <?=gettext("Server #2:"); ?>&nbsp;
                        <input name="dns_server2" type="text" id="dns_server2" size="20" value="<?=htmlspecialchars($pconfig['dns_server2']);?>" />
                        <?=gettext("Server #3:"); ?>&nbsp;
                        <input name="dns_server3" type="text" id="dns_server3" size="20" value="<?=htmlspecialchars($pconfig['dns_server3']);?>" />
                        <?=gettext("Server #4:"); ?>&nbsp;
                        <input name="dns_server4" type="text" id="dns_server4" size="20" value="<?=htmlspecialchars($pconfig['dns_server4']);?>" />
                      </div>
                      <div class="hidden" data-for="help_for_dns_server">
                        <?=gettext("Provide a DNS server list to clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_ntp_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NTP Servers"); ?></td>
                    <td>
                      <input name="ntp_server_enable" type="checkbox" id="ntp_server_enable" value="yes" <?=!empty($pconfig['ntp_server1']) || !empty($pconfig['ntp_server2']) ? "checked=\"checked\"" : "" ;?> />
                      <div id="ntp_server_data" style="display:none">
                        <?=gettext("Server #1:"); ?>&nbsp;
                        <input name="ntp_server1" type="text" id="ntp_server1" size="20" value="<?=$pconfig['ntp_server1'];?>" />
                        <?=gettext("Server #2:"); ?>&nbsp;
                        <input name="ntp_server2" type="text" id="ntp_server2" size="20" value="<?=$pconfig['ntp_server2'];?>" />
                      </div>
                      <div class="hidden" data-for="help_for_ntp_server">
                        <?=gettext("Provide a NTP server list to clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_netbios_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NetBIOS Options"); ?></td>
                    <td>
                      <input name="netbios_enable" type="checkbox" id="netbios_enable" value="yes" <?=!empty($pconfig['netbios_enable']) ? "checked=\"checked\"" : "" ;?> />
                      <div class="hidden" data-for="help_for_netbios_enable">
                        <?=gettext("Enable NetBIOS over TCP/IP");?><br/>
                        <?=gettext("If this option is not set, all NetBIOS-over-TCP/IP options (including WINS) will be disabled"); ?>.
                      </div>

                      <div id="netbios_data">
                        <?=gettext("Node Type"); ?>:&nbsp;
                        <select name='netbios_ntype'>
<?php
                        foreach ($netbios_nodetypes as $type => $name) :
                            $selected = "";
                            if ($pconfig['netbios_ntype'] == $type) {
                                $selected = "selected=\"selected\"";
                            }?>
                          <option value="<?=$type;?>" <?=$selected;?>><?=$name;?></option>
<?php
                        endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_netbios_enable">
                          <?=gettext("Possible options: b-node (broadcasts), p-node " .
                                                    "(point-to-point name queries to a WINS server), " .
                                                    "m-node (broadcast then query name server), and " .
                                                    "h-node (query name server, then broadcast)."); ?>
                        </div>
                        Scope ID:&nbsp;
                        <input name="netbios_scope" type="text" id="netbios_scope" value="<?=$pconfig['netbios_scope'];?>" />
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
                    <td><a id="help_for_wins_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WINS Servers"); ?></td>
                    <td>
                      <input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes"  <?=!empty($pconfig['wins_server1']) || !empty($pconfig['wins_server2']) ? "checked=\"checked\"" : "" ;?> />
                      <div id="wins_server_data" style="display:none">
                        <?=gettext("Server #1:"); ?>
                        <input name="wins_server1" type="text" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>" />
                        <?=gettext("Server #2:"); ?>
                        <input name="wins_server2" type="text" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>" />
                      </div>
                      <div class="hidden" data-for="help_for_wins_server">
                        <?=gettext("Provide a WINS server list to clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_custom_options" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Advanced"); ?></td>
                    <td>
                      <textarea rows="6" cols="70" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea>
                      <?=gettext("This option will be removed in the future due to being insecure by nature. In the mean time only full administrators are allowed to change this setting.");?>
                      <div class="hidden" data-for="help_for_custom_options">
                        <?=gettext("Enter any additional options you would like to add for this client specific override, separated by a semicolon"); ?><br />
                        <?=gettext("EXAMPLE: push \"route 10.0.0.0 255.255.255.0\""); ?>;
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                      <input name="act" type="hidden" value="<?=$act;?>" />
<?php
                      if (isset($id)) :?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
<?php
                      endif; ?>
                    </td>
                  </tr>
                </table>
               </div>
              </form>
<?php
              else :?>
              <form method="post" name="iform2" id="iform2">
                <input type="hidden" id="id" name="id" value="" />
                <input type="hidden" id="action" name="act" value="" />
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tr>
                      <td></td>
                      <td><?=gettext("Common Name"); ?></td>
                      <td><?=gettext("Tunnel Network");?></td>
                      <td><?=gettext("Description"); ?></td>
                      <td class="text-nowrap"></td>
                    </tr>
<?php
                    $i = 0;
                    foreach ($a_csc as $csc):?>
                    <tr>
                      <td>
                        <input type="checkbox" name="rule[]" value="<?=$i;?>"/>
                        <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=(empty($csc['disable'])) ? gettext("Disable") : gettext("Enable");?>">
                          <span class="fa fa-play fa-fw <?=(empty($csc['disable'])) ? "text-success" : "text-muted";?>"></span>
                        </a>
                      </td>
                      <td>
                          <?=htmlspecialchars($csc['common_name']);?>
                      </td>
                      <td>
                          <?=!empty($csc['tunnel_network']) ? htmlspecialchars($csc['tunnel_network']) : "";?>
                      </td>
                      <td>
                          <?=htmlspecialchars($csc['description']);?>
                      </td>
                      <td class="text-nowrap">
                        <a data-id="<?=$i;?>" data-toggle="tooltip" title="<?=gettext("Move selected before this item");?>" class="act_move btn btn-default btn-xs">
                          <span class="fa fa-arrow-left fa-fw"></span>
                        </a>
                        <a href="vpn_openvpn_csc.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><span class="fa fa-pencil fa-fw"></span></a>
                        <a data-id="<?=$i;?>" title="<?=gettext("delete csc"); ?>" class="act_delete btn btn-default btn-xs"><span class="fa fa-trash fa-fw"></span></a>
                        <a href="vpn_openvpn_csc.php?act=new&dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("clone rule");?>">
                          <span class="fa fa-clone fa-fw"></span>
                        </a>
                      </td>
                    </tr>
<?php
                    $i++;
                    endforeach;?>
                    <tr>
                      <td colspan="4">
                        <?=gettext("Additional OpenVPN client specific overrides can be added here.");?>
                      </td>
                      <td class="text-nowrap">
                        <a data-id="<?=$i;?>" data-toggle="tooltip" title="<?=gettext("Move selected items to end");?>" class="act_move btn btn-default btn-xs">
                          <span class="fa fa-arrow-down fa-fw"></span>
                        </a>
                        <a data-id="x" title="<?=gettext("delete selected rules"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="fa fa-trash fa-fw"></span>
                        </a>
                      </td>
                    </tr>
                  </table>
                </div>
              </form>
<?php
            endif; ?>

          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
