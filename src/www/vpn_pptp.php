<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

require_once('guiconfig.inc');
require_once('interfaces.inc');
require_once('filter.inc');
require_once('services.inc');
require_once("system.inc");
require_once("plugins.inc");
require_once("pfsense-utils.inc");
require_once('plugins.inc.d/vpn.inc');

if (!is_array($config['pptpd']['radius'])) {
    $config['pptpd']['radius'] = array();
}
$pptpcfg = &$config['pptpd'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig['remoteip'] = $pptpcfg['remoteip'];
    $pconfig['localip'] = $pptpcfg['localip'];
    $pconfig['mode'] = $pptpcfg['mode'];
    $pconfig['wins'] = $pptpcfg['wins'];
    $pconfig['req128'] = isset($pptpcfg['req128']);
    $pconfig['n_pptp_units'] = $pptpcfg['n_pptp_units'];
    $pconfig['pptp_dns1'] = $pptpcfg['dns1'];
    $pconfig['pptp_dns2'] = $pptpcfg['dns2'];
    $pconfig['radiusenable'] = isset($pptpcfg['radius']['server']['enable']);
    $pconfig['radiusissueips'] = isset($pptpcfg['radius']['radiusissueips']);
    $pconfig['radiussecenable'] = isset($pptpcfg['radius']['server2']['enable']);
    $pconfig['radacct_enable'] = isset($pptpcfg['radius']['accounting']);
    $pconfig['radiusserver'] = $pptpcfg['radius']['server']['ip'];
    $pconfig['radiusserverport'] = $pptpcfg['radius']['server']['port'];
    $pconfig['radiusserveracctport'] = $pptpcfg['radius']['server']['acctport'];
    $pconfig['radiussecret'] = $pptpcfg['radius']['server']['secret'];
    $pconfig['radiusserver2'] = $pptpcfg['radius']['server2']['ip'];
    $pconfig['radiusserver2port'] = $pptpcfg['radius']['server2']['port'];
    $pconfig['radiusserver2acctport'] = $pptpcfg['radius']['server2']['acctport'];
    $pconfig['radiussecret2'] = $pptpcfg['radius']['server2']['secret2'];
    $pconfig['radius_acct_update'] = $pptpcfg['radius']['acct_update'];
    $pconfig['radius_nasip'] = $pptpcfg['radius']['nasip'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($input_errors) && count($input_errors) > 0) {
        unset($input_errors);
    }
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
        if ($_POST['remoteip'] && !is_ipaddr($_POST['remoteip'])) {
            $input_errors[] = gettext("A valid remote start address must be specified.");
        }
        if (($_POST['radiusserver'] && !is_ipaddr($_POST['radiusserver']))) {
            $input_errors[] = gettext("A valid RADIUS server address must be specified.");
        }

        if (!$input_errors) {
            $subnet_start = ip2ulong($_POST['remoteip']);
            $subnet_end = ip2ulong($_POST['remoteip']) + $_POST['n_pptp_units'] - 1;

            if ((ip2ulong($_POST['localip']) >= $subnet_start) &&
                (ip2ulong($_POST['localip']) <= $subnet_end)) {
                $input_errors[] = gettext("The specified server address lies in the remote subnet.");
            }
        }
    } elseif (isset($config['pptpd']['mode'])) {
        unset($config['pptpd']['mode']);
    }

    if (!$input_errors) {
        $pptpcfg['remoteip'] = $_POST['remoteip'];
        $pptpcfg['localip'] = $_POST['localip'];
        $pptpcfg['mode'] = $_POST['mode'];
        $pptpcfg['wins'] = $_POST['wins'];
        $pptpcfg['n_pptp_units'] = $_POST['n_pptp_units'];
        $pptpcfg['radius']['server']['ip'] = $_POST['radiusserver'];
        $pptpcfg['radius']['server']['port'] = $_POST['radiusserverport'];
        $pptpcfg['radius']['server']['acctport'] = $_POST['radiusserveracctport'];
        $pptpcfg['radius']['server']['secret'] = $_POST['radiussecret'];
        $pptpcfg['radius']['server2']['ip'] = $_POST['radiusserver2'];
        $pptpcfg['radius']['server2']['port'] = $_POST['radiusserver2port'];
        $pptpcfg['radius']['server2']['acctport'] = $_POST['radiusserver2acctport'];
        $pptpcfg['radius']['server2']['secret2'] = $_POST['radiussecret2'];
        $pptpcfg['radius']['nasip'] = $_POST['radius_nasip'];
        $pptpcfg['radius']['acct_update'] = $_POST['radius_acct_update'];

        if ($_POST['pptp_dns1'] == "") {
            if (isset($pptpcfg['dns1'])) {
                unset($pptpcfg['dns1']);
            }
        } else {
            $pptpcfg['dns1'] = $_POST['pptp_dns1'];
        }

        if ($_POST['pptp_dns2'] == "") {
            if (isset($pptpcfg['dns2'])) {
                unset($pptpcfg['dns2']);
            }
        } else {
            $pptpcfg['dns2'] = $_POST['pptp_dns2'];
        }

        if ($_POST['req128'] == "yes") {
            $pptpcfg['req128'] = true;
        } elseif (isset($pptpcfg['req128'])) {
            unset($pptpcfg['req128']);
        }

        if ($_POST['radiusenable'] == "yes") {
            $pptpcfg['radius']['server']['enable'] = true;
        } elseif (isset($pptpcfg['radius']['server']['enable'])) {
            unset($pptpcfg['radius']['server']['enable']);
        }

        if ($_POST['radiussecenable'] == "yes") {
            $pptpcfg['radius']['server2']['enable'] = true;
        } elseif (isset($pptpcfg['radius']['server2']['enable'])) {
            unset($pptpcfg['radius']['server2']['enable']);
        }

        if ($_POST['radacct_enable'] == "yes") {
            $pptpcfg['radius']['accounting'] = true;
        } elseif (isset($pptpcfg['radius']['accounting'])) {
            unset($pptpcfg['radius']['accounting']);
        }

        if ($_POST['radiusissueips'] == "yes") {
            $pptpcfg['radius']['radiusissueips'] = true;
        } elseif (isset($pptpcfg['radius']['radiusissueips'])) {
            unset($pptpcfg['radius']['radiusissueips']);
        }

        write_config();
        $savemsg = get_std_save_message();
        vpn_pptpd_configure();
        filter_configure();
    }
}

$service_hook = 'pptpd';
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
} ?>
        <?php if (isset($savemsg)) {
                    print_info_box($savemsg);
} ?>
        <?php print_alert_box(
          gettext(
            'PPTP is not considered a secure VPN technology, because it relies upon ' .
            'the compromised MS-CHAPv2 protocol. If you choose to use PPTP, be aware ' .
            'that your traffic can be decrypted by virtually any third party. ' .
            'It should be considered an unencrypted tunneling protocol.'
          ) .  ' <a href="https://isc.sans.edu/diary/End+of+Days+for+MS-CHAPv2/13807">' .
          gettext('Read more') . '</a>.',
          'warning'
        ); ?>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td width="22%"><b><?=gettext("PPTP settings"); ?></b></td>
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
                        <input type="radio" name="mode" value="server"  <?=($pconfig['mode'] == 'server') ? 'checked="checked"' : '';?>/>
                        <?=gettext("Enable PPTP server"); ?>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_n_pptp_units" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("No. PPTP users"); ?></td>
                      <td>
                        <select id="n_pptp_units" name="n_pptp_units">
<?php
                          $toselect = ($pconfig['n_pptp_units'] > 0) ? $pconfig['n_pptp_units'] : 16;
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
                        <div class="hidden" for="help_for_n_pptp_units">
                          <?=gettext("Hint: 10 is ten PPTP clients"); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_localip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Server address"); ?></td>
                      <td>
                        <input name="localip" type="text" id="localip" value="<?=$pconfig['localip'];?>" />
                        <div class="hidden" for="help_for_localip">
                          <?=gettext("Enter the IP address the PPTP server should give to clients for use as their \"gateway\"."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_remoteip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Remote address range"); ?></td>
                      <td>
                        <input name="remoteip" type="text" id="remoteip" value="<?=htmlspecialchars($pconfig['remoteip']);?>" />
                        <div class="hidden" for="help_for_remoteip">
                          <?=gettext("Specify the starting address for the client IP address subnet."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_pptp_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("PPTP DNS Servers"); ?></td>
                      <td>
                        <input name="pptp_dns1" type="text" id="pptp_dns1" value="<?=$pconfig['pptp_dns1'];?>" /><br />
                        <input name="pptp_dns2" type="text" id="pptp_dns2" value="<?=$pconfig['pptp_dns2'];?>" />
                        <div class="hidden" for="help_for_pptp_dns">
                          <?=gettext("primary and secondary DNS servers assigned to PPTP clients"); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WINS Server"); ?></td>
                      <td>
                          <input name="wins" type="text" id="wins" value="<?=htmlspecialchars($pconfig['wins']);?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_radius" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS"); ?></td>
                      <td>
                        <input name="radiusenable" type="checkbox" id="radiusenable" value="yes" <?=($pconfig['radiusenable']) ? "checked=\"checked\"" : "";?>/>
                        <strong><?=gettext("Use a RADIUS server for authentication"); ?></strong><br/>
                        <div class="hidden" for="help_for_radius">
                          <?=gettext("When set, all users will be authenticated using " .
                          "the RADIUS server specified below. The local user database " .
                          "will not be used."); ?>
                        </div>
                        <input name="radacct_enable" type="checkbox" id="radacct_enable" value="yes" <?=($pconfig['radacct_enable']) ? "checked=\"checked\"" : "";?>/>
                        <strong><?=gettext("Enable RADIUS accounting"); ?></strong><br/>
                        <div class="hidden" for="help_for_radius">
                          <?=gettext("Sends accounting packets to the RADIUS server."); ?>
                        </div>
                        <input name="radiussecenable" type="checkbox" id="radiussecenable" value="yes" <?=($pconfig['radiussecenable']) ? "checked=\"checked\"" : "";?>/>
                        <strong><?=gettext("Secondary RADIUS server for failover authentication"); ?></strong><br />
                        <div class="hidden" for="help_for_radius">
                          <?=gettext("When set, all requests will go to the secondary server when primary fails"); ?>
                        </div>
                        <input name="radiusissueips" value="yes" type="checkbox" id="radiusissueips"<?=($pconfig['radiusissueips']) ? " checked=\"checked\"" : "";?>/>
                        <strong><?=gettext("RADIUS issued IPs"); ?></strong>
                        <div class="hidden" for="help_for_radius">
                          <?=gettext("Issue IP addresses via RADIUS server."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("RADIUS NAS IP"); ?></td>
                      <td>
                          <input name="radius_nasip" type="text" id="radius_nasip" value="<?=$pconfig['radius_nasip'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_radius_acct_update" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS Accounting Update"); ?></td>
                      <td>
                          <input name="radius_acct_update" type="text" id="radius_acct_update" value="<?=$pconfig['radius_acct_update'];?>" />
                          <div class="hidden" for="help_for_radius_acct_update">
                            <?=gettext("RADIUS accounting update period in seconds"); ?>
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
                      <td><a id="help_for_radiussecret2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RADIUS secondary shared secret"); ?></td>
                      <td>
                        <input name="radiussecret2" type="password" id="radiussecret2" size="20" value="<?=htmlspecialchars($pconfig['radiussecret2']);?>" />
                        <div class="hidden" for="help_for_radiussecret2">
                          <?=gettext("Enter the shared secret that will be used to authenticate " ."to the RADIUS server"); ?>.
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_req128" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Security");?></td>
                      <td>
                        <input name="req128" type="checkbox" id="req128" value="yes" <?=($pconfig['req128']) ? "checked=\"checked\"" : "";?> />
                        <strong><?=gettext("Require 128-bit encryption"); ?></strong>
                        <div class="hidden" for="help_for_req128">
                          <?=gettext("When set, only 128-bit encryption will be accepted. Otherwise " .
                                    "40-bit and 56-bit encryption will be accepted as well. Note that " .
                                    "encryption will always be forced on PPTP connections (i.e. " .
                                    "unencrypted connections will not be accepted)."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td colspan="2"><?= gettext("Don't forget to add a firewall rule to permit traffic from PPTP clients.") ?></td>
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
