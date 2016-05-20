<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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
require_once("interfaces.inc");

function vpn_pppoe_get_id()
{
    global $config;

    $vpnid = 1;

    if (isset($config['pppoes']['pppoe'])) {
        foreach ($config['pppoes']['pppoe'] as $pppoe) {
            if ($vpnid == $pppoe['pppoeid']) {
                $vpnid++;
            } else {
                return $vpnid;
            }
        }
    }

    return $vpnid;
}

if (empty($config['pppoes']['pppoe']) || !is_array($config['pppoes']['pppoe'])) {
    $config['pppoes'] = array();
    $config['pppoes']['pppoe'] = array();
}
$a_pppoes = &$config['pppoes']['pppoe'];

$copy_fields = array('remoteip', 'localip', 'mode', 'interface', 'n_pppoe_units', 'pppoe_subnet', 'dns1', 'dns2', 'descr', 'pppoeid');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_pppoes[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    foreach ($copy_fields as $fieldname) {
        if (isset($id) && !empty($a_pppoes[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_pppoes[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    // split username / password
    $pconfig['users_username'] = array();
    $pconfig['users_password'] = array();
    $pconfig['users_ip'] = array();
    if (isset($id) && !empty($a_pppoes[$id]['username'])) {
        foreach (explode(' ', $a_pppoes[$id]['username']) as $userinfo) {
            $parts = explode(':', $userinfo);
            $pconfig['users_username'][] = $parts[0];
            $pconfig['users_password'][] = base64_decode($parts[1]);
            $pconfig['users_ip'][] = !empty($parts[2]) ? $parts[2] : "";
        }
    }

    // radius properties
    $pconfig['radacct_enable'] = isset($id) && isset($a_pppoes[$id]['radius']['accounting']);
    $pconfig['radiusissueips'] = isset($id) && isset($a_pppoes[$id]['radius']['radiusissueips']);
    $pconfig['radiusenable'] = isset($id) && isset($a_pppoes[$id]['radius']['server']['enable']);
    $pconfig['radiusserver'] = isset($id) && isset($a_pppoes[$id]['radius']['server']['ip']) ? $a_pppoes[$id]['radius']['server']['ip'] : null;
    $pconfig['radiusserverport'] = isset($id) && isset($a_pppoes[$id]['radius']['server']['port']) ? $a_pppoes[$id]['radius']['server']['port'] : null;
    $pconfig['radiusserveracctport'] = isset($id) && isset($a_pppoes[$id]['radius']['server']['acctport']) ? $a_pppoes[$id]['radius']['server']['acctport'] : null;
    $pconfig['radiussecret'] = isset($id) && isset($a_pppoes[$id]['radius']['server']['secret']) ? $a_pppoes[$id]['radius']['server']['secret'] : null;
    $pconfig['radiussecenable'] = isset($id) && isset($a_pppoes[$id]['radius']['server2']['enable']);
    $pconfig['radiusserver2'] = isset($id) && isset($a_pppoes[$id]['radius']['server2']['ip']) ? $a_pppoes[$id]['radius']['server2']['ip'] : null;
    $pconfig['radiusserver2port'] = isset($id) && isset($a_pppoes[$id]['radius']['server2']['port']) ? $a_pppoes[$id]['radius']['server2']['port'] : null;
    $pconfig['radiusserver2acctport'] = isset($id) && isset($a_pppoes[$id]['radius']['server2']['acctport']) ? $a_pppoes[$id]['radius']['server2']['acctport'] : null;
    $pconfig['radiussecret2'] = isset($id) && isset($a_pppoes[$id]['radius']['server2']['secret2']) ? $a_pppoes[$id]['radius']['server2']['secret2'] : null;
    $pconfig['radius_nasip'] = isset($id) && isset($a_pppoes[$id]['radius']['nasip']) ? $a_pppoes[$id]['radius']['nasip'] : null;
    $pconfig['radius_acct_update'] = isset($id) && isset($a_pppoes[$id]['radius']['acct_update']) ? $a_pppoes[$id]['radius']['acct_update'] : null;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_pppoes[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    foreach ($pconfig['users_username'] as $item_idx => $usr) {
        if (empty($pconfig['users_password'][$item_idx])) {
            $input_errors[] = sprintf(gettext("No password specified for username %s"), $usr);
        }
        if ($pconfig['users_ip'][$item_idx] <> "" && !is_ipaddr($pconfig['users_ip'][$item_idx])) {
            $input_errors[] = sprintf(gettext("Incorrect ip address  specified for username %s"), $usr);
        }
    }

    if ($pconfig['mode'] == "server") {
        $reqdfields = explode(" ", "localip remoteip");
        $reqdfieldsn = array(gettext("Server address"),gettext("Remote start address"));

        if (!empty($pconfig['radiusenable'])) {
            $reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
            $reqdfieldsn = array_merge(
                $reqdfieldsn,
                array(gettext("RADIUS server address"),gettext("RADIUS shared secret"))
            );
        }

        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

        if (!empty($pconfig['localip']) && !is_ipaddr($pconfig['localip'])) {
            $input_errors[] = gettext("A valid server address must be specified.");
        }
        if (!empty($pconfig['pppoe_subnet']) && !is_ipaddr($pconfig['remoteip'])) {
            $input_errors[] = gettext("A valid remote start address must be specified.");
        }
        if (!empty($pconfig['radiusserver']) && !is_ipaddr($pconfig['radiusserver'])) {
            $input_errors[] = gettext("A valid RADIUS server address must be specified.");
        }

        $subnet_start = ip2ulong($pconfig['remoteip']);
        $subnet_end = ip2ulong($pconfig['remoteip']) + $pconfig['pppoe_subnet'] - 1;
        if ((ip2ulong($pconfig['localip']) >= $subnet_start) &&
            (ip2ulong($pconfig['localip']) <= $subnet_end)) {
            $input_errors[] = gettext("The specified server address lies in the remote subnet.");
        }
    }

    if (!empty($pconfig['pppoeid']) && !is_numeric($_POST['pppoeid'])) {
        $input_errors[] = gettext("Wrong data submitted");
    }

    if (count($input_errors) == 0) {
        $pppoecfg = array();
        // convert user/pass/ip combination
        $pconfig['username'] = array();
        foreach ($pconfig['users_username'] as $item_idx => $usr) {
             $user_item = $usr . ":" . base64_encode($pconfig['users_password'][$item_idx]) ;
             if (!empty($pconfig['users_ip'][$item_idx])) {
                $user_item .= ":".$pconfig['users_ip'][$item_idx];
             }
             $pconfig['username'][] = $user_item ;
        }
        if (count($pconfig['username']) > 0) {
            $pppoecfg['username'] = implode(' ', $pconfig['username']);
        }

        // copy simple fields
        foreach ($copy_fields as $fieldname) {
            if (isset($pconfig[$fieldname]) && $pconfig[$fieldname] != "") {
                $pppoecfg[$fieldname] = $pconfig[$fieldname];
            }
        }

        // radius settings (array)
        if (!empty($pconfig['radiusserver']) || !empty($pconfig['radiusserver2'])) {
            $pppoecfg['radius'] = array();
            $pppoecfg['radius']['server']['enable'] = !empty($pconfig['radiusenable']);
            $pppoecfg['radius']['server2']['enable'] = !empty($pconfig['radiussecenable']);
            $pppoecfg['radius']['accounting'] = !empty($pconfig['radacct_enable']);
            $pppoecfg['radius']['radiusissueips'] = !empty($pconfig['radiusissueips']);
            $pppoecfg['radius']['nasip'] = $pconfig['radius_nasip'];
            $pppoecfg['radius']['acct_update'] = $pconfig['radius_acct_update'];
        }
        if (!empty($pconfig['radiusserver'])) {
            $pppoecfg['radius']['server'] = array();
            $pppoecfg['radius']['server']['ip'] = $pconfig['radiusserver'];
            $pppoecfg['radius']['server']['secret'] = $pconfig['radiussecret'];
            $pppoecfg['radius']['server']['port'] = $pconfig['radiusserverport'];
            $pppoecfg['radius']['server']['acctport'] = $pconfig['radiusserveracctport'];
        }
        if (!empty($pconfig['radiusserver2'])) {
            $pppoecfg['radius']['server2'] = array();
            $pppoecfg['radius']['server2']['ip'] = $pconfig['radiusserver2'];
            $pppoecfg['radius']['server2']['secret2'] = $pconfig['radiussecret2'];
            $pppoecfg['radius']['server2']['port'] = $pconfig['radiusserver2port'];
            $pppoecfg['radius']['server2']['acctport'] = $pconfig['radiusserver2acctport'];
        }

        if (!isset($pconfig['pppoeid'])) {
            $pppoecfg['pppoeid'] = vpn_pppoe_get_id();
        }

        if (file_exists('/tmp/.vpn_pppoe.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.vpn_pppoe.apply'));
        } else {
            $toapplylist = array();
        }

        $toapplylist[] = $pppoecfg['pppoeid'];
        if (!isset($id)) {
            $a_pppoes[] = $pppoecfg;
        } else {
            $a_pppoes[$id] = $pppoecfg;
        }

        write_config();
        mark_subsystem_dirty('vpnpppoe');
        file_put_contents('/tmp/.vpn_pppoe.apply', serialize($toapplylist));
        header("Location: vpn_pppoe.php");
        exit;
    }
}

include("head.inc");
legacy_html_escape_form_data($pconfig);
?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
  $( document ).ready(function() {
    /**
     *  Aliases
     */
    function removeRow() {
        if ( $('#users_table > tbody > tr').length == 1 ) {
            $('#users_table > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // add new detail record
    $("#addNew").click(function(){
        // copy last row and reset values
        $('#users_table > tbody').append('<tr>'+$('#users_table > tbody > tr:last').html()+'</tr>');
        $('#users_table > tbody > tr:last > td > input').each(function(){
          $(this).val("");
        });
        $(".act-removerow").click(removeRow);
    });
    $(".act-removerow").click(removeRow);
  });
</script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
        if (isset($input_errors) && count($input_errors) > 0) {
            print_input_errors($input_errors);
        }?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td width="22%"><strong><?=gettext("PPPoE server configuration");?></strong></td>
                    <td width="78%" align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Mode");?></td>
                    <td>
                      <input name="mode" type="radio" value="off" <?=$pconfig['mode'] != 'server' ? 'checked="checked"' : '';?> />
                      <?=gettext("Off"); ?>
                      &nbsp;
                      <input type="radio" name="mode" value="server" <?=$pconfig['mode'] == 'server' ? 'checked="checked"' : '';?>/>
                      <?=gettext("Enable PPPoE server"); ?></td>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface"); ?></td>
                    <td>
                      <select name="interface" class="selectpicker" id="interface">
<?php
                      foreach (get_configured_interface_with_descr() as $iface => $ifacename) :?>
                        <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($ifacename);?>
                        </option>
<?php
                      endforeach;?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_localip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Server address"); ?></td>
                    <td>
                      <input name="localip" type="text" value="<?=$pconfig['localip'];?>" />
                      <div class="hidden" for="help_for_localip">
                        <?=gettext("Enter the IP address the PPPoE server should give to clients for use as their \"gateway\"."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_n_pppoe_units" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("No. PPPoE users"); ?></td>
                    <td>
                      <select id="n_pppoe_units" name="n_pppoe_units">
<?php
                      $toselect = ($pconfig['n_pppoe_units'] > 0) ? $pconfig['n_pppoe_units'] : 16;
                      for ($x=1; $x<255; $x++):?>
                        <option value="<?=$x;?>" <?=$x == $toselect ? "selected=\"selected\"" : "" ;?>>
                            <?=$x;?>
<?php
                      endfor;?>
                      </select>
                      <div class="hidden" for="help_for_n_pppoe_units">
                        <?=gettext("Hint: 10 is ten PPPoE clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_remoteip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Remote address range"); ?></td>
                    <td>
                      <input name="remoteip" type="text" value="<?=$pconfig['remoteip'];?>" />
                      <div class="hidden" for="help_for_remoteip">
                        <?=gettext("Specify the starting address for the client IP address subnet."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_pppoe_subnet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Subnet netmask"); ?></td>
                    <td>
                      <select id="pppoe_subnet" name="pppoe_subnet">
<?php
                      for ($x=0; $x<33; $x++):?>
                        <option value="<?=$x;?>" <?=$x == $pconfig['pppoe_subnet'] ? "selected=\"selected\"" : "" ;?>>
                            <?=$x;?>
<?php
                      endfor;?>
                      </select>
                      <div class="hidden" for="help_for_pppoe_subnet">
                        <?=gettext("Hint: 24 is 255.255.255.0"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS servers"); ?></td>
                    <td>
                      <input name="dns1" type="text" value="<?=$pconfig['dns1'];?>" />
                      <br />
                      <input name="dns2" type="text" value="<?=$pconfig['dns2'];?>" />
                      <div class="hidden" for="help_for_dns">
                        <?=gettext("If entered they will be given to all PPPoE clients, else LAN DNS and one WAN DNS will go to all clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiusenable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS"); ?></td>
                    <td>
                      <input name="radiusenable" type="checkbox" value="yes" <?=!empty($pconfig['radiusenable']) ? "checked=\"checked\"" : ""; ?>/>
                      <strong><?=gettext("Use a RADIUS server for authentication"); ?></strong><br/>
                      <div class="hidden" for="help_for_radiusenable">
                        <?=gettext("When set, all users will be authenticated using " .
                                    "the RADIUS server specified below. The local user database " .
                                    "will not be used."); ?>
                      </div>
                      <input name="radacct_enable" type="checkbox" value="yes" <?=!empty($pconfig['radacct_enable']) ? "checked=\"checked\"" : "";?> />
                      <strong><?=gettext("Enable RADIUS accounting"); ?> <br /></strong>
                      <div class="hidden" for="help_for_radiusenable">
                        <?=gettext("Sends accounting packets to the RADIUS server"); ?>.
                      </div>
                      <input name="radiussecenable" type="checkbox" value="yes" <?=!empty($pconfig['radiussecenable']) ? "checked=\"checked\"" : "";?> />
                      <strong><?=gettext("Use Backup RADIUS Server"); ?></strong><br />
                      <div class="hidden" for="help_for_radiusenable">
                        <?=gettext("When set, if primary server fails all requests will be sent via backup server"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radius_nasip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NAS IP Address"); ?></td>
                    <td>
                      <input name="radius_nasip" type="text" id="radius_nasip" value="<?=$pconfig['radius_nasip'];?>" />
                      <div class="hidden" for="help_for_radius_nasip">
                        <?=gettext("RADIUS server NAS IP Address"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radius_acct_update" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS Accounting Update"); ?></td>
                    <td>
                      <input name="radius_acct_update" type="text"  value="<?=$pconfig['radius_acct_update'];?>" />
                      <div class="hidden" for="help_for_radius_acct_update">
                        <?=gettext("RADIUS accounting update period in seconds"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiusissueips" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS issued IPs"); ?></td>
                    <td>
                      <input name="radiusissueips" value="yes" type="checkbox" <?=!empty($pconfig['radiusissueips']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" for="help_for_radiusissueips">
                        <?=gettext("Issue IP Addresses via RADIUS server."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiusserver" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS server Primary"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <thead>
                          <tr>
                            <th><?=gettext("Server");?></th>
                            <th><?=gettext("Port");?></th>
                            <th><?=gettext("AccPort");?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td><input name="radiusserver" type="text" value="<?=$pconfig['radiusserver'];?>" /></td>
                            <td><input name="radiusserverport" type="text"  value="<?=$pconfig['radiusserverport'];?>" /></td>
                            <td><input name="radiusserveracctport" type="text"  value="<?=$pconfig['radiusserveracctport'];?>" /></td>
                          </tr>
                        </tbody>
                      </table>
                      <div class="hidden" for="help_for_radiusserver">
                        <?=gettext("Enter the IP address, authentication port and accounting port (optional) of the RADIUS server."); ?><br />
                        <br /> <?=gettext("standard port 1812 and 1813 accounting"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiussecret" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS primary shared secret"); ?></td>
                    <td>
                      <input name="radiussecret" type="password"  value="<?=$pconfig['radiussecret'];?>" />
                      <div class="hidden" for="help_for_radiussecret">
                        <?=gettext("Enter the shared secret that will be used to authenticate " .
                                                "to the RADIUS server"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiusserver2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS server Secondary"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <thead>
                          <tr>
                            <th><?=gettext("Server");?></th>
                            <th><?=gettext("Port");?></th>
                            <th><?=gettext("AccPort");?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td><input name="radiusserver2" type="text" value="<?=$pconfig['radiusserver2'];?>" /></td>
                            <td><input name="radiusserver2port" type="text"  value="<?=$pconfig['radiusserver2port'];?>" /></td>
                            <td><input name="radiusserver2acctport" type="text"  value="<?=$pconfig['radiusserver2acctport'];?>" /></td>
                          </tr>
                        </tbody>
                      </table>
                      <div class="hidden" for="help_for_radiusserver2">
                        <?=gettext("Enter the IP address, authentication port and accounting port (optional) of the backup RADIUS server."); ?><br />
                        <br /> <?=gettext("standard port 1812 and 1813 accounting"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiussecret2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a><?=gettext("RADIUS secondary shared secret"); ?></td>
                    <td>
                      <input name="radiussecret2" type="password" id="radiussecret2" size="20" value="<?=htmlspecialchars($pconfig['radiussecret2']);?>" />
                      <div class="hidden" for="help_for_radiussecret2">
                        <?=gettext("Enter the shared secret that will be used to authenticate " ."to the RADIUS server"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("User (s)");?></td>
                    <td>
                      <table class="table table-striped table-condensed" id="users_table">
                        <thead>
                          <tr>
                            <th></th>
                            <th><?=gettext("Username");?></th>
                            <th><?=gettext("Password");?></th>
                            <th><?=gettext("IP");?></th>
                          </tr>
                        </thead>
                        <tbody>
<?php
                        if (count($pconfig['users_username']) == 0 ) {
                            $pconfig['users_username'][] = "";
                            $pconfig['users_password'][] = "";
                            $pconfig['users_ip'][] = "";
                        }
                        foreach($pconfig['users_username'] as $item_idx => $user):?>
                          <tr>
                            <td>
                              <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs" alt="remove"><span class="glyphicon glyphicon-minus"></span></div>
                            </td>
                            <td>
                              <input name="users_username[]" type="text" value="<?=$user;?>" />
                            </td>
                            <td>
                              <input name="users_password[]" type="password" value="<?=$pconfig['users_password'][$item_idx];?>" />
                            </td>
                            <td>
                              <input name="users_ip[]" type="text" value="<?=$pconfig['users_ip'][$item_idx];?>" />
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="4">
                              <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><span class="glyphicon glyphicon-plus"></span></div>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td width="22%" valign="top">&nbsp;</td>
                    <td width="78%">
<?php
                    if (isset($id)) {
                        echo "<input type=\"hidden\" name=\"id\" id=\"id\" value=\"" . htmlspecialchars($id, ENT_QUOTES | ENT_HTML401) . "\" />";
                    }
                    if (!empty($pconfig['pppoeid'])) {
                        echo "<input type=\"hidden\" name=\"pppoeid\" id=\"pppoeid\" value=\"{$pconfig['pppoeid']}\" />";
                    }
                    ?>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>"   />
                      <a href="vpn_pppoe.php"><input name="Cancel" type="button" class="btn btn-default" value="<?=gettext("Cancel"); ?>" /></a>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2">
                      <?=gettext("Don't forget to add a firewall rule to permit traffic from PPPoE clients."); ?>
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc");
