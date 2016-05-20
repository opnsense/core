<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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
require_once("pfsense-utils.inc");
require_once("interfaces.inc");
require_once("services.inc");
require_once("system.inc");
require_once("plugins.inc");
require_once("plugins.inc.d/vpn.inc");

if (!isset($config['l2tp']['radius']) || !is_array($config['l2tp']['radius'])) {
    $config['l2tp']['radius'] = array();
}
$l2tpcfg = &$config['l2tp'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig['remoteip'] = $l2tpcfg['remoteip'];
    $pconfig['localip'] = $l2tpcfg['localip'];
    $pconfig['mode'] = $l2tpcfg['mode'];
    $pconfig['interface'] = $l2tpcfg['interface'];
    $pconfig['l2tp_dns1'] = $l2tpcfg['dns1'];
    $pconfig['l2tp_dns2'] = $l2tpcfg['dns2'];
    $pconfig['wins'] = $l2tpcfg['wins'];
    $pconfig['radiusenable'] = isset($l2tpcfg['radius']['enable']);
    $pconfig['radacct_enable'] = isset($l2tpcfg['radius']['accounting']);
    $pconfig['radiusserver'] = $l2tpcfg['radius']['server'];
    $pconfig['radiussecret'] = $l2tpcfg['radius']['secret'];
    $pconfig['radiusissueips'] = isset($l2tpcfg['radius']['radiusissueips']);
    $pconfig['n_l2tp_units'] = $l2tpcfg['n_l2tp_units'];
    $pconfig['paporchap'] = $l2tpcfg['paporchap'];
    $pconfig['secret'] = $l2tpcfg['secret'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($input_errors);
    $pconfig = $_POST;

    /* input validation */
    if ($_POST['mode'] == "server") {
        $reqdfields = explode(" ", "localip remoteip");
        $reqdfieldsn = array(gettext("Server address"),gettext("Remote start address"));

        if ($_POST['radiusenable']) {
            $reqdfields = array_merge($reqdfields, explode(" ", "radiusserver radiussecret"));
            $reqdfieldsn = array_merge(
                $reqdfieldsn,
                array(gettext("RADIUS server address"),gettext("RADIUS shared secret"))
            );
        }

        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

        if ($_POST['localip'] && !is_ipaddr($_POST['localip'])) {
            $input_errors[] = gettext("A valid server address must be specified.");
        }
        if ($_POST['localip'] && !is_ipaddr($_POST['remoteip'])) {
            $input_errors[] = gettext("A valid remote start address must be specified.");
        }
        if ($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver'])) {
            $input_errors[] = gettext("A valid RADIUS server address must be specified.");
        }

        if (!$input_errors) {
            $subnet_start = ip2ulong($_POST['remoteip']);
            $subnet_end = ip2ulong($_POST['remoteip']) + $_POST['n_l2tp_units'] - 1;

            if ((ip2ulong($_POST['localip']) >= $subnet_start) &&
                (ip2ulong($_POST['localip']) <= $subnet_end)) {
                $input_errors[] = gettext("The specified server address lies in the remote subnet.");
            }
        }
    }

    if (!$input_errors) {
        $l2tpcfg['remoteip'] = $_POST['remoteip'];
        $l2tpcfg['localip'] = $_POST['localip'];
        $l2tpcfg['mode'] = $_POST['mode'];
        $l2tpcfg['interface'] = $_POST['interface'];
        $l2tpcfg['n_l2tp_units'] = $_POST['n_l2tp_units'];

        $l2tpcfg['radius']['server'] = $_POST['radiusserver'];
        $l2tpcfg['radius']['secret'] = $_POST['radiussecret'];
        $l2tpcfg['secret'] = $_POST['secret'];

        if ($_POST['wins']) {
            $l2tpcfg['wins'] = $_POST['wins'];
        } else {
            unset($l2tpcfg['wins']);
        }

        $l2tpcfg['paporchap'] = $_POST['paporchap'];


        if ($_POST['l2tp_dns1'] == "") {
            if (isset($l2tpcfg['dns1'])) {
                unset($l2tpcfg['dns1']);
            }
        } else {
            $l2tpcfg['dns1'] = $_POST['l2tp_dns1'];
        }

        if ($_POST['l2tp_dns2'] == "") {
            if (isset($l2tpcfg['dns2'])) {
                unset($l2tpcfg['dns2']);
            }
        } else {
            $l2tpcfg['dns2'] = $_POST['l2tp_dns2'];
        }

        if ($_POST['radiusenable'] == "yes") {
            $l2tpcfg['radius']['enable'] = true;
        } else {
            unset($l2tpcfg['radius']['enable']);
        }

        if ($_POST['radacct_enable'] == "yes") {
            $l2tpcfg['radius']['accounting'] = true;
        } else {
            unset($l2tpcfg['radius']['accounting']);
        }

        if ($_POST['radiusissueips'] == "yes") {
            $l2tpcfg['radius']['radiusissueips'] = true;
        } else {
            unset($l2tpcfg['radius']['radiusissueips']);
        }

        write_config();

        vpn_l2tp_configure();
        header("Location: vpn_l2tp.php");
        exit;
    }
}

$service_hook = 'l2tpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) {
                  print_input_errors($input_errors);
              }
              if (isset($savemsg)) {
                  print_info_box($savemsg);
              }
        ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td width="22%"><b><?=gettext("L2TP settings"); ?></b></td>
                    <td width="78%" align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Mode");?></td>
                    <td>
                      <input name="mode" type="radio" value="off" <?=($pconfig['mode'] != 'server') ? 'checked="checked"' : '';?>/>
                      <?=gettext("Off"); ?>
                      &nbsp;
                      <input type="radio" name="mode" value="server"  <?=$pconfig['mode'] == 'server' ? 'checked="checked"' : '';?>/>
                      <?=gettext("Enable L2TP server"); ?></td>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface");?></td>
                    <td>
                      <select name="interface" class="form-control" id="interface">
<?php
                      foreach (get_configured_interface_with_descr() as $iface => $ifacename) :?>
                        <option value="<?=$iface;?>" <?=$iface == $pconfig['interface'] ? "selected=\"selected\"" : "";?>>
                          <?=htmlspecialchars($ifacename);?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_localip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Server Address");?></td>
                    <td>
                      <input name="localip" type="text" id="localip" value="<?=$pconfig['localip'];?>" />
                      <div class="hidden" for="help_for_localip">
                        <?=gettext("Enter the IP address the L2TP server should give to clients for use as their \"gateway\"."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_remoteip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Remote Address Range");?></td>
                    <td>
                      <input name="remoteip" type="text" id="remoteip" value="<?=$pconfig['remoteip'];?>" />
                      <div class="hidden" for="help_for_remoteip">
                        <?=gettext("Specify the starting address for the client IP address subnet.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_n_l2tp_units" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Number of L2TP users"); ?></td>
                    <td>
                      <select id="n_l2tp_units" name="n_l2tp_units">
                      <?php
                                $toselect = ($pconfig['n_l2tp_units'] > 0) ? $pconfig['n_l2tp_units'] : 16;
                                for ($x=1; $x<255; $x++) {
                                    if ($x == $toselect) {
                                           $SELECTED = " selected=\"selected\"";
                                    } else {
                                        $SELECTED = "";
                                    }
                                    echo "<option value=\"{$x}\"{$SELECTED}>{$x}</option>\n";
                                }
                                ?>
                      </select>
                      <div class="hidden" for="help_for_n_l2tp_units">
                        <?=gettext("Hint: 10 is ten L2TP clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_secret" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Secret");?></td>
                    <td>
                      <input type="password" name="secret" id="secret" value="<?=$pconfig['secret']; ?>" />
                      <div class="hidden" for="help_for_secret">
                        <?=gettext("Specify optional secret shared between peers. Required on some devices/setups.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_paporchap" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication Type");?></td>
                    <td>
                      <select name="paporchap" id="paporchap">
                        <option value='chap' <?=$pconfig['paporchap'] == "chap" ? " selected=\"selected\"" : "";?>>
                          <?=gettext("CHAP"); ?>
                        </option>
                        <option value='pap' <?=$pconfig['paporchap'] == "pap" ?  " selected=\"selected\"" :"";?>>
                          <?=gettext("PAP"); ?>
                        </option>
                      </select>
                      <div class="hidden" for="help_for_paporchap">
                        <?=gettext("Specifies which protocol to use for authentication.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_l2tp_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("L2TP DNS Servers"); ?></td>
                    <td>
                      <input name="l2tp_dns1" type="text" id="l2tp_dns1" value="<?=$pconfig['l2tp_dns1'];?>" /><br/>
                      <input name="l2tp_dns2" type="text" id="l2tp_dns2" value="<?=$pconfig['l2tp_dns2'];?>" />
                      <div class="hidden" for="help_for_l2tp_dns">
                        <?=gettext("primary and secondary DNS servers assigned to L2TP clients"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WINS Server"); ?></td>
                    <td>
                      <input type="text" name="wins" id="wins" value="<?=$pconfig['wins'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radius" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS"); ?></td>
                    <td>
                      <input name="radiusenable" type="checkbox" id="radiusenable" value="yes" <?=($pconfig['radiusenable']) ? "checked=\"checked\"" : "";?>/>
                      <strong> <?=gettext("Use a RADIUS server for authentication");?><br /></strong>
                      <div class="hidden" for="help_for_radius">
                        <?=gettext("When set, all users will be authenticated using the RADIUS server specified below. The local user database will not be used.");?>
                      </div>
                      <input name="radacct_enable" type="checkbox" id="radacct_enable" value="yes" <?=($pconfig['radacct_enable']) ? "checked=\"checked\"" : "";?>/>
                      <strong><?=gettext("Enable RADIUS accounting");?></strong><br />
                      <div class="hidden" for="help_for_radius">
                        <?=gettext("Sends accounting packets to the RADIUS server.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiusserver" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS Server");?></td>
                    <td>
                      <input name="radiusserver" type="text" id="radiusserver" value="<?=$pconfig['radiusserver'];?>" />
                      <div class="hidden" for="help_for_radiusserver">
                        <?=gettext("Enter the IP address of the RADIUS server.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiussecret" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS Shared Secret");?></td>
                    <td>
                      <input name="radiussecret" type="password" class="form-control pwd" id="radiussecret" value="<?=$pconfig['radiussecret'];?>" />
                      <div class="hidden" for="help_for_radiussecret">
                        <?=gettext("Enter the shared secret that will be used to authenticate to the RADIUS server.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radiusissueips" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS Issued IP's");?></td>
                    <td>
                      <input name="radiusissueips" value="yes" type="checkbox" class="form-control" id="radiusissueips"<?=$pconfig['radiusissueips'] ? " checked=\"checked\"" : "";?>>
                      <div class="hidden" for="help_for_radiusissueips">
                        <?=gettext("Issue IP Addresses via RADIUS server.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td width="78%">
                      <input id="submit" name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2">
                      <?=gettext("Don't forget to add a firewall rule to permit traffic from L2TP clients!");?>
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
