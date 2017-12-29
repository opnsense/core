<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005-2007 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("system.inc");

function default_table_entries_size()
{
    $current = `pfctl -sm | grep table-entries | awk '{print $4};'`;
    return $current;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
    $pconfig['disablefilter'] = !empty($config['system']['disablefilter']);
    $pconfig['optimization'] = isset($config['system']['optimization']) ? $config['system']['optimization'] : "normal";
    $pconfig['rulesetoptimization'] = isset($config['system']['rulesetoptimization']) ? $config['system']['rulesetoptimization'] : "basic";
    $pconfig['maximumstates'] = isset($config['system']['maximumstates']) ? $config['system']['maximumstates'] : null;
    $pconfig['maximumfrags'] = isset($config['system']['maximumfrags']) ? $config['system']['maximumfrags'] : null;
    $pconfig['adaptivestart'] = isset($config['system']['adaptivestart']) ? $config['system']['adaptivestart'] : null;
    $pconfig['adaptiveend'] = isset($config['system']['adaptiveend']) ? $config['system']['adaptiveend'] : null;
    $pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
    $pconfig['aliasesresolveinterval'] = isset($config['system']['aliasesresolveinterval']) ? $config['system']['aliasesresolveinterval'] : null;
    $pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
    $pconfig['maximumtableentries'] = !empty($config['system']['maximumtableentries']) ? $config['system']['maximumtableentries'] : null ;
    $pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
    $pconfig['bogonsinterval'] = !empty($config['system']['bogons']['interval']) ? $config['system']['bogons']['interval'] : null;
    $pconfig['schedule_states'] = isset($config['system']['schedule_states']);
    $pconfig['kill_states'] = isset($config['system']['kill_states']);
    $pconfig['skip_rules_gw_down'] = isset($config['system']['skip_rules_gw_down']);
    $pconfig['gw_switch_default'] = isset($config['system']['gw_switch_default']);
    $pconfig['lb_use_sticky'] = isset($config['system']['lb_use_sticky']);
    $pconfig['pf_share_forward'] = isset($config['system']['pf_share_forward']);
    $pconfig['pf_disable_force_gw'] = isset($config['system']['pf_disable_force_gw']);
    $pconfig['srctrack'] = !empty($config['system']['srctrack']) ? $config['system']['srctrack'] : null;
    if (!isset($config['system']['disablenatreflection'])) {
        $pconfig['natreflection'] = "purenat";
    } else {
        $pconfig['natreflection'] = "disable";
    }
    $pconfig['enablebinatreflection'] = !empty($config['system']['enablebinatreflection']);
    $pconfig['enablenatreflectionhelper'] = isset($config['system']['enablenatreflectionhelper']) ? $config['system']['enablenatreflectionhelper'] : null;
    $pconfig['snat_use_sticky'] = !empty($config['system']['snat_use_sticky']);
    $pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();

    /* input validation */
    if ((empty($pconfig['adaptivestart']) && !empty($pconfig['adaptiveend'])) || (!empty($pconfig['adaptivestart']) && empty($pconfig['adaptiveend']))) {
        $input_errors[] = gettext("The Firewall Adaptive values must be set together.");
    }
    if (!empty($pconfig['adaptivestart']) && !is_numericint($pconfig['adaptivestart'])) {
        $input_errors[] = gettext("The Firewall Adaptive Start value must be an integer.");
    }
    if (!empty($pconfig['adaptiveend']) && !is_numericint($pconfig['adaptiveend'])) {
        $input_errors[] = gettext("The Firewall Adaptive End value must be an integer.");
    }
    if (!empty($pconfig['maximumstates']) && !is_numericint($pconfig['maximumstates'])) {
        $input_errors[] = gettext("The Firewall Maximum States value must be an integer.");
    }
    if (!empty($pconfig['maximumfrags']) && !is_numericint($pconfig['maximumfrags'])) {
        $input_errors[] = gettext("The Firewall Maximum Frags value must be an integer.");
    }
    if (!empty($pconfig['aliasesresolveinterval']) && !is_numericint($pconfig['aliasesresolveinterval'])) {
        $input_errors[] = gettext("The Aliases Hostname Resolve Interval value must be an integer.");
    }
    if (!empty($pconfig['maximumtableentries']) && !is_numericint($pconfig['maximumtableentries'])) {
        $input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
    }
    if (count($input_errors) == 0) {
        if (!empty($pconfig['pf_share_forward'])) {
            $config['system']['pf_share_forward'] = true;
        } elseif (isset($config['system']['pf_share_forward'])) {
            unset($config['system']['pf_share_forward']);
        }

        if (!empty($pconfig['pf_disable_force_gw'])) {
            $config['system']['pf_disable_force_gw'] = true;
        } elseif (isset($config['system']['pf_disable_force_gw'])) {
            unset($config['system']['pf_disable_force_gw']);
        }

        if (!empty($pconfig['lb_use_sticky'])) {
            $config['system']['lb_use_sticky'] = true;
        } elseif (isset($config['system']['lb_use_sticky'])) {
            unset($config['system']['lb_use_sticky']);
        }

        if ($pconfig['noantilockout'] == "yes") {
            $config['system']['webgui']['noantilockout'] = true;
        } elseif (isset($config['system']['webgui']['noantilockout'])) {
            unset($config['system']['webgui']['noantilockout']);
        }

        if (!empty($pconfig['srctrack'])) {
            $config['system']['srctrack'] = $pconfig['srctrack'];
        } elseif (isset($config['system']['srctrack'])) {
            unset($config['system']['srctrack']);
        }

        if (!empty($pconfig['ipv6allow'])) {
            $config['system']['ipv6allow'] = true;
        } elseif (isset($config['system']['ipv6allow'])) {
            unset($config['system']['ipv6allow']);
        }

        if (!empty($pconfig['disablefilter'])) {
            $config['system']['disablefilter'] = "enabled";
        } elseif (isset($config['system']['disablefilter'])) {
            unset($config['system']['disablefilter']);
        }

        if (!empty($pconfig['adaptiveend'])) {
            $config['system']['adaptiveend'] = $pconfig['adaptiveend'];
        } elseif (isset($config['system']['adaptiveend'])) {
            unset($config['system']['adaptiveend']);
        }
        if (!empty($pconfig['adaptivestart'])) {
            $config['system']['adaptivestart'] = $pconfig['adaptivestart'];
        } elseif (isset($config['system']['adaptivestart'])) {
            unset($config['system']['adaptivestart']);
        }

        if (!empty($pconfig['checkaliasesurlcert'])) {
            $config['system']['checkaliasesurlcert'] = true;
        } elseif (isset($config['system']['checkaliasesurlcert'])) {
            unset($config['system']['checkaliasesurlcert']);
        }

        if ($pconfig['natreflection'] == "purenat") {
            if (isset($config['system']['disablenatreflection'])) {
                unset($config['system']['disablenatreflection']);
            }
        } else {
            $config['system']['disablenatreflection'] = "yes";
        }

        if (!empty($pconfig['enablebinatreflection'])) {
            $config['system']['enablebinatreflection'] = "yes";
        } elseif (isset($config['system']['enablebinatreflection'])) {
            unset($config['system']['enablebinatreflection']);
        }
        $config['system']['snat_use_sticky'] = !empty($pconfig['snat_use_sticky']);

        if (!empty($pconfig['disablereplyto'])) {
            $config['system']['disablereplyto'] = $pconfig['disablereplyto'];
        } elseif (isset($config['system']['disablereplyto'])) {
            unset($config['system']['disablereplyto']);
        }

        if (!empty($pconfig['enablenatreflectionhelper'])) {
            $config['system']['enablenatreflectionhelper'] = "yes";
        } elseif (isset($config['system']['enablenatreflectionhelper']))  {
            unset($config['system']['enablenatreflectionhelper']);
        }

        $config['system']['optimization'] = $pconfig['optimization'];
        $config['system']['rulesetoptimization'] = $pconfig['rulesetoptimization'];
        $config['system']['maximumstates'] = $pconfig['maximumstates'];
        $config['system']['maximumfrags'] = $pconfig['maximumfrags'];
        $config['system']['aliasesresolveinterval'] = $pconfig['aliasesresolveinterval'];
        $config['system']['maximumtableentries'] = $pconfig['maximumtableentries'];

        if (!empty($pconfig['bypassstaticroutes'])) {
            $config['filter']['bypassstaticroutes'] = $pconfig['bypassstaticroutes'];
        } elseif (isset($config['filter']['bypassstaticroutes'])) {
            unset($config['filter']['bypassstaticroutes']);
        }

        if ($pconfig['bogonsinterval'] != $config['system']['bogons']['interval']) {
            $config['system']['bogons']['interval'] = $pconfig['bogonsinterval'];
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

        if (!empty($pconfig['gw_switch_default'])) {
            $config['system']['gw_switch_default'] = true;
        } elseif (isset($config['system']['gw_switch_default'])) {
            unset($config['system']['gw_switch_default']);
        }

        write_config();

        $savemsg = get_std_save_message();

        system_cron_configure();
        filter_configure();
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <!-- row -->
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
          <div class="content-box tab-content  table-responsive">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td width="22%"><strong><?=gettext("IPv6 Options");?></strong></td>
                  <td  width="78%" align="right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ipv6allow" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Allow IPv6"); ?></td>
                  <td>
                    <input name="ipv6allow" type="checkbox" value="yes" <?= !empty($pconfig['ipv6allow']) ? "checked=\"checked\"" :"";?> onclick="enable_change(false)" />
                    <strong><?=gettext("Allow IPv6"); ?></strong>
                    <div class="hidden" for="help_for_ipv6allow">
                      <?=gettext("All IPv6 traffic will be blocked by the firewall unless this box is checked."); ?><br />
                      <?=gettext("NOTE: This does not disable any IPv6 features on the firewall, it only blocks traffic."); ?><br />
                    </div>
                  </td>
                </tr>
<?php           if (count($config['interfaces']) > 1): ?>
                <tr>
                  <th colspan="2" valign="top" class="listtopic"><?=gettext("Network Address Translation");?></th>
                </tr>
                <tr>
                  <td><a id="help_for_natreflection" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Reflection for port forwards");?></td>
                  <td>
                    <select name="natreflection" class="formselect selectpicker" data-style="btn-default">
                      <option value="disable" <?=$pconfig['natreflection'] == "disable" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Disable"); ?>
                      </option>
                      <option value="purenat" <?=$pconfig['natreflection'] == "purenat" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Enable (Pure NAT)"); ?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_natreflection">
                      <strong><?=gettext("When enabled, this automatically creates additional NAT redirect rules for access to port forwards on your external IP addresses from within your internal networks.");?></strong>
                      <br /><br />
                      <?=gettext("The pure NAT mode uses a set of NAT rules to direct packets to the target of the port forward. It has better scalability, but it must be possible to accurately determine the interface and gateway IP used for communication with the target at the time the rules are loaded. There are no inherent limits to the number of ports other than the limits of the protocols. All protocols available for port forwards are supported.");?>
                      <br /><br />
                      <?=gettext("Individual rules may be configured to override this system setting on a per-rule basis.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_enablebinatreflection" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Reflection for 1:1");?></td>
                  <td>
                    <input name="enablebinatreflection" type="checkbox" id="enablebinatreflection" value="yes" <?=!empty($pconfig['enablebinatreflection']) ? "checked=\"checked\"" : "";?>/>
                    <div class="hidden" for="help_for_enablebinatreflection">
                      <strong><?=gettext("Enables the automatic creation of additional NAT redirect rules for access to 1:1 mappings of your external IP addresses from within your internal networks.");?></strong><br />
                      <?=gettext("Note: Reflection on 1:1 mappings is only for the inbound component of the 1:1 mappings. This functions the same as the pure NAT mode for port forwards. For more details, refer to the pure NAT mode description above.");?>
                      <br /><br />
                      <?=gettext("Individual rules may be configured to override this system setting on a per-rule basis.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_enablenatreflectionhelper" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Automatic outbound NAT for Reflection");?></td>
                  <td>
                    <input name="enablenatreflectionhelper" type="checkbox" id="enablenatreflectionhelper" value="yes" <?=!empty($pconfig['enablenatreflectionhelper']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_enablenatreflectionhelper">
                      <strong><?=gettext("Automatically create outbound NAT rules which assist inbound NAT rules that direct traffic back out to the same subnet it originated from.");?></strong><br />
                      <?=gettext("Required for full functionality of the pure NAT mode of NAT Reflection for port forwards or NAT Reflection for 1:1 NAT.");?>
                      <br /><br />
                      <?=gettext("Note: This only works for assigned interfaces. Other interfaces require manually creating the outbound NAT rules that direct the reply packets back through the router.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_snat_use_sticky" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sticky oubound NAT");?></td>
                  <td>
                    <input name="snat_use_sticky" type="checkbox" id="snat_use_sticky" <?=!empty($pconfig['snat_use_sticky']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_snat_use_sticky">
                      <?=gettext("When using automatic outbound nat rules make addresses sticky when there are more configured on the same interface.");?>
                    </div>
                  </td>
                </tr>
<?php           endif; ?>
                <tr>
                  <th colspan="2" valign="top" class="listtopic"><?=gettext("Bogon Networks");?></th>
                </tr>
                <tr>
                  <td><a id="help_for_bogonsinterval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Update Frequency");?></td>
                  <td>
                    <select name="bogonsinterval" class="formselect selectpicker" data-style="btn-default">
                    <option value="monthly" <?=empty($pconfig['bogonsinterval']) || $pconfig['bogonsinterval'] == 'monthly' ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Monthly"); ?>
                    </option>
                    <option value="weekly" <?=$pconfig['bogonsinterval'] == 'weekly' ? "selected=\"selected\"" :"";?>>
                      <?=gettext("Weekly"); ?>
                    </option>
                    <option value="daily" <?=$pconfig['bogonsinterval'] == 'daily' ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Daily"); ?>
                    </option>
                    </select>
                    <div class="hidden" for="help_for_bogonsinterval">
                      <?=gettext("The frequency of updating the lists of IP addresses that are reserved (but not RFC 1918) or not yet assigned by IANA.");?>
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
                    <strong><?=gettext("Disable State Killing on Gateway Failure"); ?></strong>
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
                  <td><a id="help_for_gw_switch_default" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Gateway switching') ?></td>
                  <td>
                    <input name="gw_switch_default" type="checkbox" id="gw_switch_default" value="yes" <?= !empty($pconfig['gw_switch_default']) ? 'checked="checked"' : '' ?> />
                    <strong><?=gettext("Allow default gateway switching"); ?></strong><br />
                    <div class="hidden" for="help_for_gw_switch_default">
                      <?= gettext('If the link where the default gateway resides fails switch the default gateway to another available one. This feature has been deprecated.') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" valign="top" class="listtopic"><?= gettext('Multi-WAN') ?></th>
                </tr>
                <tr>
                  <td><a id="help_for_lb_use_sticky" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sticky connections");?> </td>
                  <td>
                    <input name="lb_use_sticky" type="checkbox" id="lb_use_sticky" value="yes" <?= !empty($pconfig['lb_use_sticky']) ? 'checked="checked"' : '';?>/>
                    <strong><?=gettext("Use sticky connections"); ?></strong><br />
                    <div class="hidden" for="help_for_lb_use_sticky">
                      <?=gettext("Successive connections will be redirected to the servers " .
                                          "in a round-robin manner with connections from the same " .
                                          "source being sent to the same gateway. This 'sticky " .
                                          "connection' will exist as long as there are states that " .
                                          "refer to this connection. Once the states expire, so will " .
                                          "the sticky connection. Further connections from that host " .
                                          "will be redirected to the next gateway in the round-robin."); ?>
                    </div><br/>
                    <input placeholder="<?=gettext("Source tracking timeout");?>" title="<?=gettext("Source tracking timeout");?>" name="srctrack" id="srctrack" type="text" value="<?= !empty($pconfig['srctrack']) ? $pconfig['srctrack'] : "";?>"/>
                    <div class="hidden" for="help_for_lb_use_sticky">
                      <?=gettext("Set the source tracking timeout for sticky connections in seconds. " .
                                          "By default this is 0, so source tracking is removed as soon as the state expires. " .
                                          "Setting this timeout higher will cause the source/destination relationship to persist for longer periods of time."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_pf_share_forward" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Shared forwarding');?> </td>
                  <td>
                    <input name="pf_share_forward" type="checkbox" id="pf_share_forward" value="yes" <?= !empty($pconfig['pf_share_forward']) ? 'checked="checked"' : '' ?>/>
                    <strong><?=gettext('Use shared forwarding between packet filter, traffic shaper and captive portal'); ?></strong><br />
                    <div class="hidden" for="help_for_pf_share_forward">
                      <?= gettext('Using policy routing in the packet filter rules causes packets to skip ' .
                                  'processing for the traffic shaper and captive portal tasks. ' .
                                  'Using this option enables the sharing of such forwarding decisions ' .
                                  'between all components to accomodate complex setups. Use with care.') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_pf_disable_force_gw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Disable force gateway');?> </td>
                  <td>
                    <input name="pf_disable_force_gw" type="checkbox" id="pf_disable_force_gw" value="yes" <?= !empty($pconfig['pf_disable_force_gw']) ? 'checked="checked"' : '' ?>/>
                    <strong><?=gettext('Disable automatic rules which force local services to use the assigned interface gateway.'); ?></strong><br />
                    <div class="hidden" for="help_pf_disable_force_gw">
                      <?= gettext('Outgoing packets from this firewall on an interface which has a gateway ' .
                                  'will normally use the specified gateway for that interface. ' .
                                  'When this option is set, the default routing rules apply (automatic rules will be disabled).') ?>
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
                  <th colspan="2" valign="top" class="listtopic"><?=gettext("Miscellaneous");?></th>
                </tr>
                <tr>
                  <td><a id="help_for_optimization" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Optimization");?></td>
                  <td>
                    <select onchange="update_description(this.selectedIndex);" name="optimization" id="optimization" class="selectpicker" data-style="btn-default">
                      <option value="normal"<?=$pconfig['optimization']=="normal" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("normal");?>
                      </option>
                      <option value="high-latency"<?=$pconfig['optimization']=="high-latency" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("high-latency");?>
                      </option>
                      <option value="aggressive"<?=$pconfig['optimization']=="aggressive" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("aggressive");?>
                      </option>
                      <option value="conservative"<?=$pconfig['optimization']=="conservative" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("conservative");?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_optimization">
                      <?=gettext("Select the type of state table optimization to use");?>
                      <table class="table table-condensed">
                        <tr>
                          <td><strong><?=gettext("normal");?></strong></td>
                          <td><?=gettext("As the name says, it is the normal optimization algorithm");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("high-latency");?></strong></td>
                          <td><?=gettext("Used for high latency links, such as satellite links. Expires idle connections later than default");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("aggressive");?></strong></td>
                          <td><?=gettext("Expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate idle connections");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("conservative");?></strong></td>
                          <td><?=gettext("Tries to avoid dropping any legitimate idle connections at the expense of increased memory usage and CPU utilization.");?></td>
                        </tr>
                      </table>
                      <hr/>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_rulesetoptimization" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Rules Optimization");?></td>
                  <td>
                    <select onchange="update_description(this.selectedIndex);" name="rulesetoptimization" id="rulesetoptimization" class="selectpicker" data-style="btn-default">
                      <option value="none"<?=$pconfig['rulesetoptimization']=="none" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("none");?>
                      </option>
                      <option value="basic"<?=$pconfig['rulesetoptimization']=="basic" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("basic");?>
                      </option>
                      <option value="profile"<?=$pconfig['rulesetoptimization']=="profile" ? " selected=\"selected\"" : ""; ?>>
                        <?=gettext("profile");?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_rulesetoptimization">
                      <?=gettext("Select the type of rules optimization to use");?>
                      <table class="table table-condensed">
                        <tr>
                          <td><strong><?=gettext("none");?></strong></td>
                          <td><?=gettext("Disable the ruleset optimizer.");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("basic");?></strong></td>
                          <td><?=gettext("(default) Basic ruleset optimization does four things to improve the performance of ruleset evaluations: remove duplicate rules; remove rules that are a subset of another rule; combine multiple rules into a table when advantageous; re-order the rules to improve evaluation performance");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("profile");?></strong></td>
                          <td><?=gettext("Uses the currently loaded ruleset as a feedback profile to tailor the ordering of quick rules to actual network traffic.");?></td>
                        </tr>
                      </table>
                      <hr/>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablefilter" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable Firewall");?></td>
                  <td>
                    <input name="disablefilter" type="checkbox" value="yes" <?= !empty($pconfig['disablefilter']) ? "checked=\"checked\"" : "";?>/>
                    <strong><?=gettext("Disable all packet filtering.");?></strong>
                    <div class="hidden" for="help_for_disablefilter">
                      <?= gettext('Warning: This will convert into a routing-only platform!') ?><br />
                      <?= gettext('Warning: This will also turn off NAT!') ?><br />
                      <?=sprintf(
                        gettext('If you only want to disable NAT, and not firewall rules, visit the %sOutbound NAT%s page.'),
                        '<a href="/firewall_nat_out.php">', '</a>'
                      )?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_adaptive" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Adaptive Timeouts");?></td>
                  <td>
                    <table class="table table-condensed">
                      <thead>
                        <tr>
                          <td><?=gettext("start");?></td>
                          <td><?=gettext("end");?></td>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>
                            <input name="adaptivestart" type="text" value="<?=$pconfig['adaptivestart']; ?>" />
                          </td>
                          <td>
                            <input name="adaptiveend" type="text" value="<?=$pconfig['adaptiveend']; ?>" />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="hidden" for="help_for_adaptive">
                      <strong><?=gettext("Timeouts for states can be scaled adaptively as the number of state table entries grows.");?></strong>
                      <br />
                      <strong><?=gettext("start");?></strong></br>
                      <?=gettext("When the number of state entries exceeds this value, adaptive scaling begins. All timeout values are scaled linearly with factor (adaptive.end - number of states) / (adaptive.end - adaptive.start).");?><br/>
                      <strong><?=gettext("end");?></strong></br>
                      <?=gettext("When reaching this number of state entries, all timeout values become zero, effectively purging all state entries immediately. This value is used to define the scale factor, it should not actually be reached (set a lower state limit, see below).");?>
                      <br/>
                      <strong><?=gettext("Note: Leave this blank for the default(0).");?></strong>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_maximumstates" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum States");?></td>
                  <td>
                    <input name="maximumstates" type="text" id="maximumstates" value="<?=$pconfig['maximumstates'];?>" />
                    <div class="hidden" for="help_for_maximumstates">
                      <strong><?=gettext("Maximum number of connections to hold in the firewall state table.");?></strong>
                      <br />
                      <?=gettext("Note: Leave this blank for the default. On your system the default size is:");?> <?= default_state_size() ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_maximumfrags" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum Fragments");?></td>
                  <td>
                    <input name="maximumfrags" type="text" id="maximumfrags" value="<?=$pconfig['maximumfrags'];?>" />
                    <div class="hidden" for="help_for_maximumfrags">
                      <strong><?=gettext("Sets the maximum number of entries in the memory pool used for fragment reassembly.");?></strong>
                      <br />
                      <?=gettext("Note: Leave this blank for the default.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_maximumtableentries" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum Table Entries");?></td>
                  <td>
                    <input name="maximumtableentries" type="text" id="maximumtableentries" value="<?= html_safe($pconfig['maximumtableentries']) ?>"/>
                    <div class="hidden" for="help_for_maximumtableentries">
                      <strong><?=gettext("Maximum number of table entries for systems such as aliases, sshlockout, snort, etc, combined.");?></strong>
                      <br />
                      <?=gettext("Note: Leave this blank for the default.");?>
<?php
                      if (empty($pconfig['maximumtableentries'])) :?>
                        <?= gettext("On your system the default size is:");?> <?= default_table_entries_size(); ?>
<?php
                      endif;?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_bypassstaticroutes" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Static route filtering");?></td>
                  <td>
                    <input name="bypassstaticroutes" type="checkbox" value="yes" <?=!empty($pconfig['bypassstaticroutes']) ? "checked=\"checked\"" : "";?>/>
                    <strong><?=gettext("Bypass firewall rules for traffic on the same interface");?></strong>
                    <div class="hidden" for="help_for_bypassstaticroutes">
                      <?=gettext("This option only applies if you have defined one or more static routes. If it is enabled, traffic that enters and " .
                                          "leaves through the same interface will not be checked by the firewall. This may be desirable in some situations where " .
                                          "multiple subnets are connected to the same interface.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablereplyto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Disable reply-to') ?></td>
                  <td>
                    <input name="disablereplyto" type="checkbox" value="yes" <?=!empty($pconfig['disablereplyto']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Disable reply-to on WAN rules");?></strong>
                    <div class="hidden" for="help_for_disablereplyto">
                      <?=gettext("With Multi-WAN you generally want to ensure traffic leaves the same interface it arrives on, hence reply-to is added automatically by default. " .
                                          "When using bridging, you must disable this behavior if the WAN gateway IP is different from the gateway IP of the hosts behind the bridged interface.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_noantilockout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable anti-lockout"); ?></td>
                  <td>
                    <input name="noantilockout" type="checkbox" value="yes" <?= empty($pconfig['noantilockout']) ? '' : 'checked="checked"' ?>/>
                    <strong><?= gettext('Disable administration anti-lockout rule') ?></strong>
                    <div class="hidden" for="help_for_noantilockout">
                      <?= sprintf(gettext("When this is unchecked, access to the web GUI or SSH " .
                                  "on the %s interface is always permitted, regardless of the user-defined firewall " .
                                  "rule set. Check this box to disable the automatically added rule, so access " .
                                  "is controlled only by the user-defined firewall rules. Ensure you have a firewall rule " .
                                  "in place that allows you in, or you will lock yourself out."),
                                  count($config['interfaces']) == 1 && !empty($config['interfaces']['wan']['if']) ?
                                  gettext('WAN') : gettext('LAN')) ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_aliasesresolveinterval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Aliases Resolve Interval");?></td>
                  <td>
                    <input name="aliasesresolveinterval" type="text" value="<?=$pconfig['aliasesresolveinterval']; ?>" />
                    <div class="hidden" for="help_for_aliasesresolveinterval">
                      <strong><?=gettext("Interval, in seconds, that will be used to resolve hostnames configured on aliases.");?></strong>
                      <br />
                      <?=gettext("Note: Leave this blank for the default (300s).");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_checkaliasesurlcert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Check certificate of aliases URLs");?></td>
                  <td>
                    <input name="checkaliasesurlcert" type="checkbox" value="yes" <?=!empty($pconfig['checkaliasesurlcert']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Verify HTTPS certificates when downloading alias URLs");?></strong>
                    <div class="hidden" for="help_for_checkaliasesurlcert">
                      <?=gettext("Make sure the certificate is valid for all HTTPS addresses on aliases. If it's not valid or is revoked, do not download it.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" /></td>
                </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
