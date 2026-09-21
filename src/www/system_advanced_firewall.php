<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("filter.inc");
require_once("system.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['disablefilter'] = !empty($config['system']['disablefilter']);
    $pconfig['optimization'] = isset($config['system']['optimization']) ? $config['system']['optimization'] : "normal";
    $pconfig['state-policy'] = isset($config['system']['state-policy']) ;
    $pconfig['maximumstates'] = isset($config['system']['maximumstates']) ? $config['system']['maximumstates'] : null;
    $pconfig['maximumfrags'] = isset($config['system']['maximumfrags']) ? $config['system']['maximumfrags'] : null;
    $pconfig['adaptivestart'] = isset($config['system']['adaptivestart']) ? $config['system']['adaptivestart'] : null;
    $pconfig['adaptiveend'] = isset($config['system']['adaptiveend']) ? $config['system']['adaptiveend'] : null;
    $pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
    $pconfig['aliasesresolveinterval'] = isset($config['system']['aliasesresolveinterval']) ? $config['system']['aliasesresolveinterval'] : null;
    $pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
    $pconfig['maximumtableentries'] = !empty($config['system']['maximumtableentries']) ? $config['system']['maximumtableentries'] : null ;
    $pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
    $pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
    $pconfig['syncookies'] = isset($config['system']['syncookies']) ? $config['system']['syncookies'] : null;
    $pconfig['syncookies_adaptstart'] = isset($config['system']['syncookies_adaptstart']) ? $config['system']['syncookies_adaptstart'] : null;
    $pconfig['syncookies_adaptend'] = isset($config['system']['syncookies_adaptend']) ? $config['system']['syncookies_adaptend'] : null;
    $pconfig['keepcounters'] = !empty($config['system']['keepcounters']);
    $pconfig['pfdebug'] = !empty($config['system']['pfdebug']) ?  $config['system']['pfdebug'] : 'urgent';
    $pconfig['no_ipv6_rfc4890_req'] = isset($config['system']['no_ipv6_rfc4890_req']);
    $pconfig['no_port0_block'] = isset($config['system']['no_port0_block']);
    $pconfig['no_sshlockout'] = isset($config['system']['no_sshlockout']);
    $pconfig['no_virusprot'] = isset($config['system']['no_virusprot']);
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

    if (!empty($pconfig['syncookies'])) {
        if (!in_array($pconfig['syncookies'], ['always', 'adaptive'])) {
            $input_errors[] = sprintf(gettext("Unknown syncookie type %s.", $pconfig['syncookies']));
        }
        if ($pconfig['syncookies'] == 'adaptive' && (empty($pconfig['syncookies_adaptstart']) || empty($pconfig['syncookies_adaptend']))) {
            $input_errors[] = gettext("Syncookie Adaptive values must be set together.");
        }
        if (!empty($pconfig['syncookies_adaptstart']) && !is_numericint($pconfig['syncookies_adaptstart'])) {
            $input_errors[] = gettext("Syncookie Adaptive Start value must be an integer.");
        }
        if (!empty($pconfig['syncookies_adaptend']) && !is_numericint($pconfig['syncookies_adaptend'])) {
            $input_errors[] = gettext("Syncookie Adaptive End value must be an integer.");
        }
        if (!empty($pconfig['syncookies_adaptend']) && !empty($pconfig['syncookies_adaptstart']) && $pconfig['syncookies_adaptstart'] < $pconfig['syncookies_adaptend']) {
            $input_errors[] = gettext("Syncookie Adaptive Start must be a higher value than End.");
        }
    }
    if (!empty($pconfig['pfdebug'])) {
        if (!in_array($pconfig['pfdebug'], ['none', 'urgent', 'misc', 'loud'])) {
            $input_errors[] = sprintf(gettext("Unknown debug type %s.", $pconfig['pfdebug']));
        }
    }
    if (count($input_errors) == 0) {
        if (!empty($pconfig['noantilockout'])) {
            $config['system']['webgui']['noantilockout'] = true;
        } elseif (isset($config['system']['webgui']['noantilockout'])) {
            unset($config['system']['webgui']['noantilockout']);
        }

        if (!empty($pconfig['no_ipv6_rfc4890_req'])) {
            $config['system']['no_ipv6_rfc4890_req'] = true;
        } elseif (isset($config['system']['no_ipv6_rfc4890_req'])) {
            unset($config['system']['no_ipv6_rfc4890_req']);
        }

        if (!empty($pconfig['no_port0_block'])) {
            $config['system']['no_port0_block'] = true;
        } elseif (isset($config['system']['no_port0_block'])) {
            unset($config['system']['no_port0_block']);
        }

        if (!empty($pconfig['no_sshlockout'])) {
            $config['system']['no_sshlockout'] = true;
        } elseif (isset($config['system']['no_sshlockout'])) {
            unset($config['system']['no_sshlockout']);
        }

        if (!empty($pconfig['no_virusprot'])) {
            $config['system']['no_virusprot'] = true;
        } elseif (isset($config['system']['no_virusprot'])) {
            unset($config['system']['no_virusprot']);
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

        if (!empty($pconfig['disablereplyto'])) {
            $config['system']['disablereplyto'] = $pconfig['disablereplyto'];
        } elseif (isset($config['system']['disablereplyto'])) {
            unset($config['system']['disablereplyto']);
        }

        if (!empty($pconfig['state-policy'])) {
            $config['system']['state-policy'] = true;
        } elseif (!empty($config['system']['state-policy'])) {
            unset($config['system']['state-policy']);
        }

        $config['system']['optimization'] = $pconfig['optimization'];
        $config['system']['maximumstates'] = $pconfig['maximumstates'];
        $config['system']['maximumfrags'] = $pconfig['maximumfrags'];
        $config['system']['aliasesresolveinterval'] = $pconfig['aliasesresolveinterval'];
        $config['system']['maximumtableentries'] = $pconfig['maximumtableentries'];

        if (!empty($pconfig['bypassstaticroutes'])) {
            /* XXX likely stored as 'yes' */
            config_merge_array('filter', ['bypassstaticroutes' => $pconfig['bypassstaticroutes']]);
        } elseif (isset($config['filter']['bypassstaticroutes'])) {
            unset($config['filter']['bypassstaticroutes']);
        }

        if (!empty($pconfig['syncookies'])) {
            $config['system']['syncookies'] = $pconfig['syncookies'];
            $config['system']['syncookies_adaptstart'] = $pconfig['syncookies_adaptstart'];
            $config['system']['syncookies_adaptend'] = $pconfig['syncookies_adaptend'];
        } else {
            unset($config['system']['syncookies']);
            unset($config['system']['syncookies_adaptstart']);
            unset($config['system']['syncookies_adaptend']);
        }

        $config['system']['keepcounters'] = !empty($pconfig['keepcounters']);
        $config['system']['pfdebug'] = !empty($pconfig['pfdebug']) ? $pconfig['pfdebug'] : '';

        if (write_config()) {
            configd_run('cron restart');
            configd_run('service restart sysctl');
            configd_run('filter reload');
        }

        $savemsg = get_std_save_message();

    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>
<script>
    $( document ).ready(function() {
        window_highlight_table_option();
        $("#syncookies").change(function(){
            if ($(this).val() == 'adaptive') {
                $("#syncookies_adaptive").show();
            } else {
                $("#syncookies_adaptive").hide();
            }
        });
        $("#syncookies").change();
    });
</script>
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
        <form method="post" name="iform" id="iform">
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Miscellaneous') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_keepcounters" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Keep counters");?></td>
                <td>
                  <input name="keepcounters" type="checkbox" <?= !empty($pconfig['keepcounters']) ? "checked=\"checked\"" : "";?>/>
                  <div class="hidden" data-for="help_keepcounters">
                    <?= gettext('Preserve rule counters across rule updates.  Usually rule counters are reset to zero on every update of the ruleset.') ?><br />
                    <?= gettext('When this is set the system will try to match the counters to the still existing rules on filter reloads.') ?><br />
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_pfdebug" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Debug");?></td>
                <td>
                  <select onchange="update_description(this.selectedIndex);" name="pfdebug" id="pfdebug" class="selectpicker" data-style="btn-default">
                    <option value="none"<?=$pconfig['pfdebug']=="none" ? " selected=\"selected\"" : ""; ?>>
                      <?=gettext("Do not generate debug messages (none)");?>
                    </option>
                    <option value="urgent"<?=$pconfig['pfdebug']=="urgent" ? " selected=\"selected\"" : ""; ?>>
                      <?=gettext("Generate debug messages only for serious errors (urgent)");?>
                    </option>
                    <option value="misc"<?=$pconfig['pfdebug']=="misc" ? " selected=\"selected\"" : ""; ?>>
                      <?=gettext("Generate debug messages for various errors (misc)");?>
                    </option>
                    <option value="loud"<?=$pconfig['pfdebug']=="loud" ? " selected=\"selected\"" : ""; ?>>
                      <?=gettext("Generate debug messages for common conditions (loud)");?>
                    </option>
                  </select>
                  <div class="hidden" data-for="help_for_pfdebug">
                    <?=gettext("Set the level of verbosity for various conditions");?><br/><br/>
                  </div>
                </td>
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
                  <div class="hidden" data-for="help_for_optimization">
                    <?=gettext("Select the type of state table optimization to use");?><br/><br/>
                    <table class="table table-condensed">
                      <tr>
                        <td><?=gettext("normal");?></td>
                        <td><?=gettext("As the name says, it is the normal optimization algorithm");?></td>
                      </tr>
                      <tr>
                        <td><?=gettext("high-latency");?></td>
                        <td><?=gettext("Used for high latency links, such as satellite links. Expires idle connections later than default");?></td>
                      </tr>
                      <tr>
                        <td><?=gettext("aggressive");?></td>
                        <td><?=gettext("Expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate idle connections");?></td>
                      </tr>
                      <tr>
                        <td><?=gettext("conservative");?></td>
                        <td><?=gettext("Tries to avoid dropping any legitimate idle connections at the expense of increased memory usage and CPU utilization.");?></td>
                      </tr>
                    </table>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_state-policy" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bind states to interface");?></td>
                <td>
                  <input name="state-policy" type="checkbox" <?= !empty($pconfig['state-policy']) ? "checked=\"checked\"" : "";?>/>
                  <div class="hidden" data-for="help_for_state-policy">
                    <?= gettext('Set behaviour for keeping states, by default states are floating, but when this option is set they should match the interface.') ?><br />
                    <?= gettext('The default option (unchecked) matches states regardless of the interface, which is in most setups the best choice.') ?><br />
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablefilter" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable Firewall");?></td>
                <td>
                  <input name="disablefilter" type="checkbox" value="yes" <?= !empty($pconfig['disablefilter']) ? "checked=\"checked\"" : "";?>/>
                  <?=gettext("Disable all packet filtering.");?>
                  <div class="hidden" data-for="help_for_disablefilter">
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
                  <div class="hidden" data-for="help_for_adaptive">
                    <?=gettext("Timeouts for states can be scaled adaptively as the number of state table entries grows.");?><br/><br/>
                    <?=gettext("start");?><br/><br/>
                    <?=gettext("When the number of state entries exceeds this value, adaptive scaling begins. All timeout values are scaled linearly with factor (adaptive.end - number of states) / (adaptive.end - adaptive.start).");?><br/><br/>
                    <?=gettext("end");?><br/><br/>
                    <?=gettext("When reaching this number of state entries, all timeout values become zero, effectively purging all state entries immediately. This value is used to define the scale factor, it should not actually be reached (set a lower state limit, see below).");?><br/><br/>
                    <?=gettext("Note: Leave this blank for the default(0).");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_maximumstates" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum States");?></td>
                <td>
                  <input name="maximumstates" type="text" id="maximumstates" value="<?=$pconfig['maximumstates'];?>" />
                  <div class="hidden" data-for="help_for_maximumstates">
                    <?=gettext("Maximum number of connections to hold in the firewall state table.");?><br/>
                    <?=gettext("Note: Leave this blank for the default. On your system the default size is:");?> <?= filter_default_state_size() ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_maximumfrags" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum Fragments");?></td>
                <td>
                  <input name="maximumfrags" type="text" id="maximumfrags" value="<?=$pconfig['maximumfrags'];?>" />
                  <div class="hidden" data-for="help_for_maximumfrags">
                    <?=gettext("Sets the maximum number of entries in the memory pool used for fragment reassembly.");?><br/>
                    <?=gettext("Note: Leave this blank for the default.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_maximumtableentries" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum Table Entries");?></td>
                <td>
                  <input name="maximumtableentries" type="text" id="maximumtableentries" value="<?= html_safe($pconfig['maximumtableentries']) ?>"/>
                  <div class="hidden" data-for="help_for_maximumtableentries">
                    <?= gettext('Maximum number of table entries for systems such as aliases, sshlockout, bogons, etc, combined.') ?><br/>
                    <?=gettext("Note: Leave this blank for the default.");?>
<?php
                     if (empty($pconfig['maximumtableentries'])) :?>
                        <?= gettext("On your system the default size is:");?> <?= filter_default_table_entries(); ?>
<?php
                      endif;?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_bypassstaticroutes" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Static route filtering");?></td>
                <td>
                  <input name="bypassstaticroutes" type="checkbox" value="yes" <?=!empty($pconfig['bypassstaticroutes']) ? "checked=\"checked\"" : "";?>/>
                  <?=gettext("Bypass firewall rules for traffic on the same interface");?>
                  <div class="hidden" data-for="help_for_bypassstaticroutes">
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
                  <?=gettext("Disable reply-to on WAN rules");?>
                  <div class="hidden" data-for="help_for_disablereplyto">
                    <?=gettext("With Multi-WAN you generally want to ensure traffic leaves the same interface it arrives on, hence reply-to is added automatically by default. " .
                                        "When using bridging, you must disable this behavior if the WAN gateway IP is different from the gateway IP of the hosts behind the bridged interface.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_noantilockout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable anti-lockout"); ?></td>
                <td>
                  <input name="noantilockout" type="checkbox" value="yes" <?= empty($pconfig['noantilockout']) ? '' : 'checked="checked"' ?>/>
                  <?= gettext('Disable administration anti-lockout rule') ?>
                  <div class="hidden" data-for="help_for_noantilockout">
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
                <td><a id="help_for_no_ipv6_rfc4890_req" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable RFC4890 requirement rules"); ?></td>
                <td>
                  <input name="no_ipv6_rfc4890_req" type="checkbox" value="1" <?= empty($pconfig['no_ipv6_rfc4890_req']) ? '' : 'checked="checked"' ?>/>
                  <div class="hidden" data-for="help_for_no_ipv6_rfc4890_req">
                    <?= gettext("When selected, no automatic RFC4890 requirement rules will be created, blocking IPv6 without additional rules configured.") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_no_port0_block" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable port 0 block"); ?></td>
                <td>
                  <input name="no_port0_block" type="checkbox" value="1" <?= empty($pconfig['no_port0_block']) ? '' : 'checked="checked"' ?>/>
                  <div class="hidden" data-for="help_for_no_port0_block">
                    <?= gettext("When selected, no automatic rule blocking port 0 will be created.") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_no_sshlockout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable sshlockout"); ?></td>
                <td>
                  <input name="no_sshlockout" type="checkbox" value="1" <?= empty($pconfig['no_sshlockout']) ? '' : 'checked="checked"' ?>/>
                  <div class="hidden" data-for="help_for_no_sshlockout">
                    <?= gettext("When selected, no automatic rule blocking failed login attempts will be created. You can still specify your own rule using the sshlockout table") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_no_virusprot" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable rate limit rule"); ?></td>
                <td>
                  <input name="no_virusprot" type="checkbox" value="1" <?= empty($pconfig['no_virusprot']) ? '' : 'checked="checked"' ?>/>
                  <div class="hidden" data-for="help_for_no_virusprot">
                    <?= gettext("When selected, no automatic rule blocking rate limited sessions will be created. You can still specify your own rule using the virusprot table") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_aliasesresolveinterval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Aliases Resolve Interval");?></td>
                <td>
                  <input name="aliasesresolveinterval" type="text" value="<?=$pconfig['aliasesresolveinterval']; ?>" />
                  <div class="hidden" data-for="help_for_aliasesresolveinterval">
                    <?=gettext("Interval, in seconds, that will be used to resolve hostnames configured on aliases.");?>
                    <br />
                    <?=gettext("Note: Leave this blank for the default (300s).");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_checkaliasesurlcert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Check certificate of aliases URLs");?></td>
                <td>
                  <input name="checkaliasesurlcert" type="checkbox" value="yes" <?=!empty($pconfig['checkaliasesurlcert']) ? "checked=\"checked\"" : "";?> />
                  <?=gettext("Verify HTTPS certificates when downloading alias URLs");?>
                  <div class="hidden" data-for="help_for_checkaliasesurlcert">
                    <?=gettext("Make sure the certificate is valid for all HTTPS addresses on aliases. If it's not valid or is revoked, do not download it.");?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Anti DDOS') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_syncookies" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable syncookies");?></td>
                <td>
                  <select name="syncookies" id="syncookies" class="selectpicker">
                    <option value="" <?= empty($pconfig['syncookies']) ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("never (default)");?>
                    </option>
                    <option value="always" <?=$pconfig['syncookies']=="always" ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("always");?>
                    </option>
                    <option value="adaptive" <?=$pconfig['syncookies']=="adaptive" ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("adaptive");?>
                    </option>
                  </select>
                  <div id="syncookies_adaptive">
                    <br/>
                    <table class="table table-condensed" style="width:348px;">
                        <thead>
                          <tr>
                              <th colspan="2"><?=gettext("Statetable usage");?><th>
                          </tr>
                          <tr>
                            <th><?=gettext("Start (%)");?></th>
                            <th><?=gettext("End (%)");?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>
                              <input name="syncookies_adaptstart" type="text" value="<?=$pconfig['syncookies_adaptstart']; ?>" />
                            </td>
                            <td>
                              <input name="syncookies_adaptend" type="text" value="<?=$pconfig['syncookies_adaptend']; ?>" />
                            </td>
                          </tr>
                        </tbody>
                    </table>
                  </div>
                  <div class="hidden" data-for="help_for_syncookies">
                      <?=gettext('When syncookies are active, the firewall will answer each incoming TCP SYN with a syncookie SYN ACK for all state tracked connections ' .
                                 'without allocating any resources. TCP connections bound to stateless rules will be silently dropped for implementational reasons.') ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"></td>
                <td style="width:78%"><input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" /></td>
              </tr>
            </table>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
