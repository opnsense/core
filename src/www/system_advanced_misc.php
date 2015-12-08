<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2005-2007 Scott Ullrich
  Copyright (C) 2008 Shrew Soft Inc
  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("filter.inc");
require_once("vpn.inc");
require_once("vslb.inc");
require_once("system.inc");
require_once("pfsense-utils.inc");
require_once("services.inc");
require_once("interfaces.inc");


$crypto_modules = array('glxsb' => gettext("AMD Geode LX Security Block"),
                        'aesni' => gettext("AES-NI CPU-based Acceleration")
);

$thermal_hardware_modules = array('coretemp' => gettext("Intel Core* CPU on-die thermal sensor"),
                                  'amdtemp' => gettext("AMD K8, K10 and K11 CPU on-die thermal sensor")
);



if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['proxyurl'] = !empty($config['system']['proxyurl']) ? $config['system']['proxyurl'] : null;
    $pconfig['proxyport'] = !empty($config['system']['proxyport']) ? $config['system']['proxyport'] : null;
    $pconfig['proxyuser'] = !empty($config['system']['proxyuser']) ? $config['system']['proxyuser'] : null;
    $pconfig['proxypass'] = !empty($config['system']['proxypass']) ? $config['system']['proxypass'] : null;
    $pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
    $pconfig['srctrack'] = !empty($config['system']['srctrack']) ? $config['system']['srctrack'] : null;
    $pconfig['gw_switch_default'] = isset($config['system']['gw_switch_default']);
    $pconfig['powerd_enable'] = isset($config['system']['powerd_enable']);
    $pconfig['crypto_hardware'] = !empty($config['system']['crypto_hardware']) ? $config['system']['crypto_hardware'] : null;
    $pconfig['thermal_hardware'] = !empty($config['system']['thermal_hardware']) ? $config['system']['thermal_hardware'] : null;
    $pconfig['schedule_states'] = isset($config['system']['schedule_states']);
    $pconfig['kill_states'] = isset($config['system']['kill_states']);
    $pconfig['skip_rules_gw_down'] = isset($config['system']['skip_rules_gw_down']);
    $pconfig['use_mfs_tmpvar'] = isset($config['system']['use_mfs_tmpvar']);
    $pconfig['powerd_ac_mode'] = "hadp";
    $pconfig['rrdbackup'] = !empty($config['system']['rrdbackup']) ? $config['system']['rrdbackup'] : null;
    $pconfig['dhcpbackup'] = !empty($config['system']['dhcpbackup']) ? $config['system']['dhcpbackup'] : null;
    if (!empty($config['system']['powerd_ac_mode'])) {
        $pconfig['powerd_ac_mode'] = $config['system']['powerd_ac_mode'];
    }
    $pconfig['powerd_battery_mode'] = "hadp";
    if (!empty($config['system']['powerd_battery_mode'])) {
        $pconfig['powerd_battery_mode'] = $config['system']['powerd_battery_mode'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($pconfig['crypto_hardware']) && !array_key_exists($pconfig['crypto_hardware'], $crypto_modules)) {
        $input_errors[] = gettext("Please select a valid Cryptographic Accelerator.");
    }

    if (!empty($pconfig['thermal_hardware']) && !array_key_exists($pconfig['thermal_hardware'], $thermal_hardware_modules)) {
        $input_errors[] = gettext("Please select a valid Thermal Hardware Sensor.");
    }

    if (count($input_errors) == 0) {
        if (!empty($pconfig['proxyurl'])) {
            $config['system']['proxyurl'] = $_POST['proxyurl'];
        } elseif (isset($config['system']['proxyurl'])) {
            unset($config['system']['proxyurl']);
        }

        if (!empty($pconfig['proxyport'])) {
            $config['system']['proxyport'] = $pconfig['proxyport'];
        } elseif (isset($config['system']['proxyport'])) {
            unset($config['system']['proxyport']);
        }

        if (!empty($pconfig['proxyuser'])) {
            $config['system']['proxyuser'] = $pconfig['proxyuser'];
        } elseif (isset($config['system']['proxyuser'])) {
            unset($config['system']['proxyuser']);
        }

        if (!empty($pconfig['proxypass'])) {
            $config['system']['proxypass'] = $pconfig['proxypass'];
        } elseif (isset($config['system']['proxypass'])) {
            unset($config['system']['proxypass']);
        }

        $need_relayd_restart = false;
        if (!empty($pconfig['lb_use_sticky'])) {
            if (!isset($config['system']['lb_use_sticky'])) {
                $config['system']['lb_use_sticky'] = true;
                $need_relayd_restart = true;
            }
        } elseif (isset($config['system']['lb_use_sticky'])) {
            unset($config['system']['lb_use_sticky']);
            $need_relayd_restart = true;
        }
        if (!empty($pconfig['srctrack'])) {
            $config['system']['srctrack'] = $pconfig['srctrack'];
        } elseif (isset($config['system']['srctrack'])) {
            unset($config['system']['srctrack']);
        }


        if (!empty($pconfig['gw_switch_default'])) {
            $config['system']['gw_switch_default'] = true;
        } elseif (isset($config['system']['gw_switch_default'])) {
            unset($config['system']['gw_switch_default']);
        }

        if (!empty($pconfig['powerd_enable'])) {
            $config['system']['powerd_enable'] = true;
        } elseif (isset($config['system']['powerd_enable'])) {
            unset($config['system']['powerd_enable']);
        }

        $config['system']['powerd_ac_mode'] = $pconfig['powerd_ac_mode'];
        $config['system']['powerd_battery_mode'] = $pconfig['powerd_battery_mode'];

        if ($pconfig['crypto_hardware']) {
            $config['system']['crypto_hardware'] = $pconfig['crypto_hardware'];
        } elseif (isset($config['system']['crypto_hardware'])) {
            unset($config['system']['crypto_hardware']);
        }

        if (!empty($pconfig['thermal_hardware'])) {
            $config['system']['thermal_hardware'] = $pconfig['thermal_hardware'];
        } elseif (isset($config['system']['thermal_hardware'])) {
            unset($config['system']['thermal_hardware']);
        }

        if (!empty($pconfig['schedule_states'])) {
            $config['system']['schedule_states'] = true;
        } elseif (isset($config['system']['schedule_states'])) {
            unset($config['system']['schedule_states']);
        }

        if (!empty($pconfig['kill_states'])) {
            $config['system']['kill_states'] = true;
        } elseif (isset($config['system']['kill_states'])) {
            unset($config['system']['kill_states']);
        }

        if (!empty($pconfig['skip_rules_gw_down'])) {
            $config['system']['skip_rules_gw_down'] = true;
        } elseif (isset($config['system']['skip_rules_gw_down'])) {
            unset($config['system']['skip_rules_gw_down']);
        }

        if (!empty($pconfig['use_mfs_tmpvar'])) {
            $config['system']['use_mfs_tmpvar'] = true;
        } elseif (isset($config['system']['use_mfs_tmpvar'])) {
            unset($config['system']['use_mfs_tmpvar']);
        }

        if (!empty($pconfig['rrdbackup'])) {
            $config['system']['rrdbackup'] = $_POST['rrdbackup'];
            install_cron_job("/usr/local/etc/rc.backup_rrd", ($config['system']['rrdbackup'] > 0), $minute = "0", "*/{$config['system']['rrdbackup']}");
        } elseif (isset($config['system']['rrdbackup'])) {
            install_cron_job("/usr/local/etc/rc.backup_rrd", false, $minute = "0", "*/{$config['system']['rrdbackup']}");
            unset($config['system']['rrdbackup']);
        }
        if (!empty($pconfig['dhcpbackup'])) {
            $config['system']['dhcpbackup'] = $pconfig['dhcpbackup'];
            install_cron_job("/usr/local/etc/rc.backup_dhcpleases", ($config['system']['dhcpbackup'] > 0), $minute = "0", "*/{$config['system']['dhcpbackup']}");
        } elseif (isset($config['system']['dhcpbackup'])) {
            install_cron_job("/usr/local/etc/rc.backup_dhcpleases", false, $minute = "0", "*/{$config['system']['dhcpbackup']}");
            unset($config['system']['dhcpbackup']);
        }

        write_config();
        $savemsg = get_std_save_message();

        system_resolvconf_generate(true);
        filter_configure();
        activate_powerd();
        load_crypto();
        load_thermal_hardware();
        if ($need_relayd_restart) {
            relayd_configure();
        }
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
    <?php
    include("fbegin.inc");
    ?>
    <script type="text/javascript">
    //<![CDATA[
    function sticky_checked(obj) {
      if (obj.checked) {
        $('#srctrack').attr('disabled',false);
      } else {
        $('#srctrack').attr('disabled','true');
      }
    }
    function tmpvar_checked(obj) {
      if (obj.checked) {
        $('#rrdbackup').attr('disabled',false);
        $('#dhcpbackup').attr('disabled',false);
        $('#rrdbackup').selectpicker('refresh');
        $('#dhcpbackup').selectpicker('refresh');
      } else {
        $('#rrdbackup').attr('disabled','true');
        $('#dhcpbackup').attr('disabled','true');
        $('#rrdbackup').selectpicker('refresh');
        $('#dhcpbackup').selectpicker('refresh');
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
        print_info_box($savemsg);
    }
?>
      <section class="col-xs-12">
        <div class="content-box tab-content table-responsive">
          <form action="system_advanced_misc.php" method="post" name="iform" id="iform">
            <table class="table table-striped">
              <tr>
                <td width="22%"><strong><?=gettext("Proxy support"); ?></strong></td>
                <td  width="78%" align="right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_proxyurl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Proxy URL"); ?></td>
                <td>
                  <input name="proxyurl" id="proxyurl" type="text" value="<?=!empty($pconfig['proxyurl']) ? $pconfig['proxyurl'] : ""; ?>"/>
                  <div class="hidden" for="help_for_proxyurl">
                    <?php printf(gettext("Proxy url for allowing %s to use this proxy to connect outside."), $g['product']); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_proxyport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Proxy Port"); ?></td>
                <td>
                  <input name="proxyport" id="proxyport" type="text" value="<?=!empty($pconfig['proxyport']) ? $pconfig['proxyport'] :"";?>"/>
                  <div class="hidden" for="help_for_proxyport">
                    <?php printf(gettext("Proxy port to use when %s connects to the proxy URL configured above. Default is 8080 for http protocol or 443 for ssl."), $g['product']); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_proxyuser" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Proxy Username"); ?></td>
                <td>
                  <input name="proxyuser" id="proxyuser" type="text" value="<?= !empty($pconfig['proxyuser']) ? $pconfig['proxyuser'] : "";?>"/>
                  <div class="hidden" for="help_for_proxyuser">
                    <?php printf(gettext("Proxy username for allowing %s to use this proxy to connect outside"), $g['product']); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_proxypassword" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Proxy Pass"); ?></td>
                <td>
                  <input type="password" name="proxypass" id="proxypass" value="<?= !empty($pconfig['proxypass']) ? $pconfig['proxypass'] : "";?>"/>
                  <div class="hidden" for="help_for_proxypassword">
                    <?php printf(gettext("Proxy password for allowing %s to use this proxy to connect outside"), $g['product']); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Load Balancing"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_gw_switch_default" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway switching");?> </td>
                <td>
                  <input name="gw_switch_default" type="checkbox" id="gw_switch_default" value="yes" <?= !empty($pconfig['gw_switch_default']) ? "checked=\"checked\"" : "";?> />
                  <strong><?=gettext("Allow default gateway switching"); ?></strong><br />
                  <div class="hidden" for="help_for_gw_switch_default">
                    <?=gettext("If the link where the default gateway resides fails " .
                                        "switch the default gateway to another available one."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_lb_use_sticky" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sticky connections");?> </td>
                <td>
                  <input name="lb_use_sticky" type="checkbox" id="lb_use_sticky" value="yes" <?= !empty($pconfig['lb_use_sticky']) ? "checked=\"checked\"" :"";?> onclick="sticky_checked(this)" />
                  <strong><?=gettext("Use sticky connections"); ?></strong><br />
                  <div class="hidden" for="help_for_lb_use_sticky">
                    <?=gettext("Successive connections will be redirected to the servers " .
                                        "in a round-robin manner with connections from the same " .
                                        "source being sent to the same web server. This 'sticky " .
                                        "connection' will exist as long as there are states that " .
                                        "refer to this connection. Once the states expire, so will " .
                                        "the sticky connection. Further connections from that host " .
                                        "will be redirected to the next web server in the round " .
                                        "robin. Changing this option will restart the Load Balancing service."); ?>
                  </div>
                  <input placeholder="<?=gettext("Source tracking timeout");?>" title="<?=gettext("Source tracking timeout");?>" data-toggle="tooltip" data-placement="left" name="srctrack" id="srctrack" type="text" value="<?= !empty($pconfig['srctrack']) ? $pconfig['srctrack'] : "";?>" <?= empty($pconfig['lb_use_sticky']) ? "disabled=\"disabled\"" : "";?> />

                  <div class="hidden" for="help_for_lb_use_sticky">
                    <?=gettext("Set the source tracking timeout for sticky connections. " .
                                        "By default this is 0, so source tracking is removed as soon as the state expires. " .
                                        "Setting this timeout higher will cause the source/destination relationship to persist for longer periods of time."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Power savings"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_powerd_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use PowerD"); ?></td>
                <td>
                  <input name="powerd_enable" type="checkbox" id="powerd_enable" value="yes" <?=!empty($pconfig['powerd_enable']) ? "checked=\"checked\"" : "";?> />
                  <hr/>
                  <table class="table table-condensed">
                      <thead>
                        <tr>
                          <th><?=gettext("On AC Power Mode"); ?></th>
                          <th><?=gettext("On Battery Power Mode"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>
                            <select name="powerd_ac_mode" class="selectpicker" data-style="btn-default" data-width="auto">
                              <option value="hadp" <?=$pconfig['powerd_ac_mode']=="hadp" ? "selected=\"selected\"" : "";?>>
                                <?=gettext("Hiadaptive");?>
                              </option>
                              <option value="adp" <?=$pconfig['powerd_ac_mode']=="adp" ? "selected=\"selected\"" : "";?>>
                                <?=gettext("Adaptive");?>
                              </option>
                              <option value="min" <?=$pconfig['powerd_ac_mode']=="min" ? "selected=\"selected\"" : "";?>>
                                <?=gettext("Minimum");?>
                              </option>
                              <option value="max" <?=$pconfig['powerd_ac_mode']=="max" ? " selected=\"selected\"" : "";?>>
                                <?=gettext("Maximum");?>
                              </option>
                            </select>
                          </td>
                          <td>
                            <select name="powerd_battery_mode" class="selectpicker" data-style="btn-default" data-width="auto">
                              <option value="hadp"<?=$pconfig['powerd_battery_mode']=="hadp" ? "selected=\"selected\"" : "";?>>
                                <?=gettext("Hiadaptive");?>
                              </option>
                              <option value="adp" <?=$pconfig['powerd_battery_mode']=="adp" ? "selected=\"selected\"" : "";?>>
                                <?=gettext("Adaptive");?>
                              </option>
                              <option value="min" <?=$pconfig['powerd_battery_mode']=="min" ? "selected=\"selected\"" :"";?>>
                                <?=gettext("Minimum");?>
                              </option>
                              <option value="max" <?=$pconfig['powerd_battery_mode']=="max" ? "selected=\"selected\"" : "";?>>
                                <?=gettext("Maximum");?>
                              </option>
                            </select>
                          </td>
                        </tr>
                      </tbody>
                  </table>
                  <div class="hidden" for="help_for_powerd_enable">
                    <?=gettext("The powerd utility monitors the system state and sets various power control " .
                                        "options accordingly.  It offers four modes (maximum, minimum, adaptive " .
                                        "and hiadaptive) that can be individually selected while on AC power or batteries. " .
                                        "The modes maximum, minimum, adaptive and hiadaptive may be abbreviated max, " .
                                        "min, adp, hadp.  Maximum mode chooses the highest performance values.  Minimum " .
                                        "mode selects the lowest performance values to get the most power savings. " .
                                        "Adaptive mode attempts to strike a balance by degrading performance when " .
                                        "the system appears idle and increasing it when the system is busy.  It " .
                                        "offers a good balance between a small performance loss for greatly " .
                                        "increased power savings.  Hiadaptive mode is alike adaptive mode, but " .
                                        "tuned for systems where performance and interactivity are more important " .
                                        "than power consumption.  It raises frequency faster, drops slower and " .
                                        "keeps twice lower CPU load."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Cryptographic Hardware Acceleration"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_crypto_hardware" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware");?></td>
                <td>
                  <select name="crypto_hardware" id="crypto_hardware" class="selectpicker" data-style="btn-default">
                    <option value=""><?=gettext("None"); ?></option>
<?php
                    foreach ($crypto_modules as $cryptomod_name => $cryptomod_descr) :?>
                      <option value="<?=$cryptomod_name; ?>" <?=$pconfig['crypto_hardware'] == $cryptomod_name ? "selected=\"selected\"" :"";?>>
                        <?="{$cryptomod_descr} ({$cryptomod_name})"; ?>
                      </option>
<?php
                    endforeach; ?>
                  </select>
                  <div class="hidden" for="help_for_crypto_hardware">
                    <?=gettext("A cryptographic accelerator module will use hardware support to speed up some " .
                                            "cryptographic functions on systems which have the chip. Do not enable this " .
                                            "option if you have a Hifn cryptographic acceleration card, as this will take " .
                                            "precedence and the Hifn card will not be used. Acceleration should be automatic " .
                                            "for IPsec when using a cipher supported by your chip, such as AES-128. OpenVPN " .
                                            "should be set for AES-128-CBC and have cryptodev enabled for hardware " .
                                            "acceleration."); ?>
                  <br /><br />
                  <?=gettext("If you do not have a crypto chip in your system, this option will have no " .
                                      "effect. To unload the selected module, set this option to 'none' and then reboot."); ?>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Thermal Sensors"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_thermal_hardware" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware");?> </td>
                <td>
                  <select name="thermal_hardware" class="selectpicker" data-style="btn-default">
                    <option value=""><?=gettext("None/ACPI"); ?></option>
<?php
                    foreach ($thermal_hardware_modules as $themalmod_name => $themalmod_descr) :?>
                      <option value="<?=$themalmod_name; ?>" <?=$pconfig['thermal_hardware'] == $themalmod_name ? " selected=\"selected\"" :"";?>>
                        <?="{$themalmod_descr} ({$themalmod_name})"; ?>
                      </option>
<?php
                    endforeach; ?>
                  </select>
                  <div class="hidden" for="help_for_thermal_hardware">
                    <?=gettext("If you have a supported CPU, selecting a themal sensor will load the appropriate " .
                                              "driver to read its temperature. Setting this to 'None' will attempt to read the " .
                                              "temperature from an ACPI-compliant motherboard sensor instead, if one is present."); ?>
                    <br /><br />
                    <?=gettext("If you do not have a supported thermal sensor chip in your system, this option will have no " .
                                          "effect. To unload the selected module, set this option to 'none' and then reboot."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Schedules"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_schedule_states" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Schedule States"); ?></td>
                <td>
                  <input name="schedule_states" type="checkbox" value="yes" <?=!empty($pconfig['schedule_states']) ? "checked=\"checked\"" :"";?> />
                  <div class="hidden" for="help_for_schedule_states">
                    <?=gettext("By default schedules clear the states of existing connections when the expiration time has come. ".
                                        "This option overrides that behavior by not clearing states for existing connections."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Gateway Monitoring"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_kill_states" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Kill states");?> </td>
                <td>
                  <input name="kill_states" type="checkbox" id="kill_states" value="yes" <?= !empty($pconfig['kill_states']) ? "checked=\"checked\"" : "";?> />
                  <strong><?=gettext("State Killing on Gateway Failure"); ?></strong>
                  <div class="hidden" for="help_for_kill_states">
                    <?=gettext("The monitoring process will flush states for a gateway that goes down if this box is not checked. Check this box to disable this behavior."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_skip_rules_gw_down" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Skip rules");?> </td>
                <td>
                  <input name="skip_rules_gw_down" type="checkbox" id="skip_rules_gw_down" value="yes" <?=!empty($pconfig['skip_rules_gw_down']) ? "checked=\"checked\"" : "";?> />
                  <strong><?=gettext("Skip rules when gateway is down"); ?></strong>
                  <div class="hidden" for="help_for_skip_rules_gw_down">
                    <?=gettext("By default, when a rule has a specific gateway set, and this gateway is down, ".
                                        "rule is created and traffic is sent to default gateway.This option overrides that behavior ".
                                        "and the rule is not created when gateway is down"); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("RAM Disk Settings (Reboot to Apply Changes)"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_use_mfs_tmpvar" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use RAM Disks"); ?></td>
                <td>
                  <input name="use_mfs_tmpvar" type="checkbox" id="use_mfs_tmpvar" value="yes" <?=!empty($pconfig['use_mfs_tmpvar']) ? "checked=\"checked\"" : "";?> onclick="tmpvar_checked(this)" />
                  <div class="hidden" for="help_for_use_mfs_tmpvar">
                    <strong><?=gettext("Use memory file system for /tmp and /var"); ?></strong><br />
                    <?=gettext("Set this if you wish to use /tmp and /var as RAM disks (memory file system disks) on a full install " .
                                        "rather than use the hard disk. Setting this will cause the data in /tmp and /var to be lost at reboot, including log data. RRD and DHCP Leases will be retained."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_rrdbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic RRD Backup");?></td>
                  <td>
                    <select name="rrdbackup" class="selectpicker" data-style="btn-default" id="rrdbackup" <?=empty($pconfig['use_mfs_tmpvar']) ? "disabled=\"disabled\"" : "";?> >
                      <option value='0' <?=!$pconfig['rrdbackup'] == 0 ? "selected='selected'" : "";?>>
                        <?=gettext("Disable"); ?>
                      </option>
<?php
                      for ($x=1; $x<=24; $x++):?>
                      <option value='<?= $x ?>' <?= $pconfig['rrdbackup'] == $x ? "selected='selected'" : "";?>>
                        <?= $x ?> <?=gettext("hour"); ?><?=($x>1) ? "s" : "";?>
                      </option>
<?php
                      endfor; ?>
                  </select>
                  <br />
                  <div class="hidden" for="help_for_rrdbackup">
                    <?=gettext("This will periodically backup the RRD data so it can be restored automatically on the next boot. Keep in mind that the more frequent the backup, the more writes will happen to your media.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_dhcpbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic DHCP Leases Backup");?></td>
                <td>
                  <select name="dhcpbackup" class="selectpicker" data-style="btn-default" id="dhcpbackup" <?=empty($pconfig['use_mfs_tmpvar']) ? "disabled=\"disabled\"" : "";?> >
                    <option value='0' <?= $pconfig['dhcpbackup'] == 0 ? "selected='selected'" : ""; ?>><?=gettext("Disable"); ?></option>
<?php
                    for ($x=1; $x<=24; $x++):?>
                    <option value='<?= $x ?>' <?= $pconfig['dhcpbackup'] == $x ? "selected='selected'" : "";?>>
                      <?= $x ?> <?=gettext("hour"); ?><?=($x>1) ? "s" : "";?>
                    </option>
<?php
                    endfor; ?>
                  </select>
                  <div class="hidden" for="help_for_dhcpbackup">
                    <?=gettext("This will periodically backup the DHCP leases data so it can be restored automatically on the next boot. Keep in mind that the more frequent the backup, the more writes will happen to your media.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                </td>
              </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
