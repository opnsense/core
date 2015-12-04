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
require_once("services.inc");
require_once("system.inc");

function default_table_entries_size()
{
        $current = `pfctl -sm | grep table-entries | awk '{print $4};'`;

        return $current;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['disablefilter'] = !empty($config['system']['disablefilter']);
    $pconfig['scrubnodf'] = !empty($config['system']['scrubnodf']);
    $pconfig['scrubrnid'] = !empty($config['system']['scrubrnid']);
    $pconfig['optimization'] = isset($config['system']['optimization']) ? $config['system']['optimization'] : "normal";
    $pconfig['maximumstates'] = isset($config['system']['maximumstates']) ? $config['system']['maximumstates'] : null;
    $pconfig['adaptivestart'] = isset($config['system']['adaptivestart']) ? $config['system']['adaptivestart'] : null;
    $pconfig['adaptiveend'] = isset($config['system']['adaptiveend']) ? $config['system']['adaptiveend'] : null;
    $pconfig['aliasesresolveinterval'] = isset($config['system']['aliasesresolveinterval']) ? $config['system']['aliasesresolveinterval'] : null;
    $pconfig['checkaliasesurlcert'] = isset($config['system']['checkaliasesurlcert']);
    $pconfig['maximumtableentries'] = !empty($config['system']['maximumtableentries']) ? $config['system']['maximumtableentries'] : null ;
    $pconfig['disablereplyto'] = isset($config['system']['disablereplyto']);
    $pconfig['disablenegate'] = isset($config['system']['disablenegate']);
    $pconfig['bogonsinterval'] = !empty($config['system']['bogons']['interval']) ? $config['system']['bogons']['interval'] : null;
    if (!isset($config['system']['disablenatreflection']) && !isset($config['system']['enablenatreflectionpurenat'])) {
        $pconfig['natreflection'] = "proxy";
    } elseif (isset($config['system']['enablenatreflectionpurenat'])) {
        $pconfig['natreflection'] = "purenat";
    } else {
        $pconfig['natreflection'] = "disable";
    }
    $pconfig['enablebinatreflection'] = !empty($config['system']['enablebinatreflection']);
    $pconfig['enablenatreflectionhelper'] = isset($config['system']['enablenatreflectionhelper']) ? $config['system']['enablenatreflectionhelper'] : null;
    $pconfig['reflectiontimeout'] = !empty($config['system']['reflectiontimeout']) ? $config['system']['reflectiontimeout'] : null;
    $pconfig['bypassstaticroutes'] = isset($config['filter']['bypassstaticroutes']);
    $pconfig['disablescrub'] = isset($config['system']['disablescrub']);
    $pconfig['ftp-proxy-client'] = isset($config['system']['ftp-proxy']['client']);
    $pconfig['disablevpnrules'] = isset($config['system']['disablevpnrules']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $old_aliasesresolveinterval = $config['system']['aliasesresolveinterval'];
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
    if (!empty($pconfig['aliasesresolveinterval']) && !is_numericint($pconfig['aliasesresolveinterval'])) {
        $input_errors[] = gettext("The Aliases Hostname Resolve Interval value must be an integer.");
    }
    if (!empty($pconfig['maximumtableentries']) && !is_numericint($pconfig['maximumtableentries'])) {
        $input_errors[] = gettext("The Firewall Maximum Table Entries value must be an integer.");
    }
    if (!empty($pconfig['reflectiontimeout']) && !is_numericint($pconfig['reflectiontimeout'])) {
        $input_errors[] = gettext("The Reflection timeout must be an integer.");
    }
    if (count($input_errors) == 0) {
        if (!empty($pconfig['disablefilter'])) {
            $config['system']['disablefilter'] = "enabled";
        } elseif (isset($config['system']['disablefilter'])) {
            unset($config['system']['disablefilter']);
        }

        if (!empty($pconfig['disablevpnrules'])) {
            $config['system']['disablevpnrules'] = true;
        }  elseif (isset($config['system']['disablevpnrules'])) {
            unset($config['system']['disablevpnrules']);
        }

        if (!empty($pconfig['scrubnodf'])) {
            $config['system']['scrubnodf'] = "enabled";
        } elseif (isset($config['system']['scrubnodf'])) {
            unset($config['system']['scrubnodf']);
        }

        if (!empty($pconfig['scrubrnid'])) {
            $config['system']['scrubrnid'] = "enabled";
        } elseif (isset($config['system']['scrubrnid'])) {
            unset($config['system']['scrubrnid']);
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

        if ($pconfig['natreflection'] == "proxy") {
            unset($config['system']['disablenatreflection']);
            unset($config['system']['enablenatreflectionpurenat']);
        } elseif ($pconfig['natreflection'] == "purenat") {
            unset($config['system']['disablenatreflection']);
            $config['system']['enablenatreflectionpurenat'] = "yes";
        } else {
            $config['system']['disablenatreflection'] = "yes";
            if (isset($config['system']['enablenatreflectionpurenat'])) {
                unset($config['system']['enablenatreflectionpurenat']);
            }
        }

        if (!empty($pconfig['enablebinatreflection'])) {
            $config['system']['enablebinatreflection'] = "yes";
        } elseif (isset($config['system']['enablebinatreflection'])) {
            unset($config['system']['enablebinatreflection']);
        }

        if (!empty($pconfig['disablereplyto'])) {
            $config['system']['disablereplyto'] = $pconfig['disablereplyto'];
        } elseif (isset($config['system']['disablereplyto'])) {
            unset($config['system']['disablereplyto']);
        }

        if (!empty($pconfig['disablenegate'])) {
            $config['system']['disablenegate'] = $pconfig['disablenegate'];
        } elseif (isset($config['system']['disablenegate'])) {
            unset($config['system']['disablenegate']);
        }

        if (!empty($pconfig['enablenatreflectionhelper'])) {
            $config['system']['enablenatreflectionhelper'] = "yes";
        } elseif (isset($config['system']['enablenatreflectionhelper']))  {
            unset($config['system']['enablenatreflectionhelper']);
        }

        $config['system']['optimization'] = $pconfig['optimization'];
        $config['system']['maximumstates'] = $pconfig['maximumstates'];
        $config['system']['aliasesresolveinterval'] = $pconfig['aliasesresolveinterval'];
        $config['system']['maximumtableentries'] = $pconfig['maximumtableentries'];
        $config['system']['reflectiontimeout'] = $pconfig['reflectiontimeout'];

        if (!empty($pconfig['bypassstaticroutes'])) {
            $config['filter']['bypassstaticroutes'] = $pconfig['bypassstaticroutes'];
        } elseif (isset($config['filter']['bypassstaticroutes'])) {
            unset($config['filter']['bypassstaticroutes']);
        }

        if (!empty($pconfig['disablescrub'])) {
            $config['system']['disablescrub'] = $pconfig['disablescrub'];
        } elseif (isset($config['system']['disablescrub'])) {
            unset($config['system']['disablescrub']);
        }

        if (!empty($pconfig['ftp-proxy-client'])) {
            $config['system']['ftp-proxy']['client'] = true;
        } elseif (isset($config['system']['ftp-proxy']['client'])) {
            unset($config['system']['ftp-proxy']['client']);
        }

        if ($pconfig['bogonsinterval'] != $config['system']['bogons']['interval']) {
            switch ($pconfig['bogonsinterval']) {
                case 'daily':
                    install_cron_job("/usr/local/etc/rc.update_bogons", true, "1", "3", "*", "*", "*");
                    break;
                case 'weekly':
                    install_cron_job("/usr/local/etc/rc.update_bogons", true, "1", "3", "*", "*", "0");
                    break;
                case 'monthly':
                    // fall through
                default:
                    install_cron_job("/usr/local/etc/rc.update_bogons", true, "1", "3", "1", "*", "*");
            }
            $config['system']['bogons']['interval'] = $pconfig['bogonsinterval'];
        }

        write_config();

        // Kill filterdns when value changes, filter_configure() will restart it
        if ($old_aliasesresolveinterval != $config['system']['aliasesresolveinterval']) {
            killbypid('/var/run/filterdns.pid');
        }

        $retval = 0;
        $retval = filter_configure();
        if (stristr($retval, "error") <> true) {
            $savemsg = get_std_save_message();
        } else {
            $savemsg = $retval;
        }
    }
}

legacy_html_escape_form_data($pconfig);

$pgtitle = array(gettext("System"),gettext("Settings"),gettext("Firewall and NAT"));
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
            <form action="system_advanced_firewall.php" method="post" name="iform" id="iform">
              <table class="table table-striped ">
                <tr>
                  <td width="22%"><strong><?=gettext("Firewall Advanced");?></strong></td>
                  <td  width="78%" align="right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_scrubnodf" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Do-Not-Fragment");?></td>
                  <td>
                    <input name="scrubnodf" type="checkbox" value="yes" <?=!empty($pconfig['scrubnodf']) ? "checked=\"checked\"" : ""; ?>/>
                    <div class="hidden" for="help_for_scrubnodf">
                      <strong><?=gettext("Clear invalid DF bits instead of dropping the packets");?></strong><br />
                      <?=gettext("This allows for communications with hosts that generate fragmented " .
                                          "packets with the don't fragment (DF) bit set. Linux NFS is known to " .
                                          "do this. This will cause the filter to not drop such packets but " .
                                          "instead clear the don't fragment bit.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_scrubrnid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Random id");?></td>
                  <td>
                    <input name="scrubrnid" type="checkbox" value="yes" <?= !empty($pconfig['scrubrnid']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_scrubrnid">
                      <strong><?=gettext("Insert a stronger id into IP header of packets passing through the filter.");?></strong><br />
                      <?=gettext("Replaces the IP identification field of packets with random values to " .
                                          "compensate for operating systems that use predictable values. " .
                                          "This option only applies to packets that are not fragmented after the " .
                                          "optional packet reassembly.");?>
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
                    <div class="hidden" for="help_for_optimization">
                      <?=gettext("Select the type of state table optimization to use");?>
                      <table class="table table-condensed">
                        <tr>
                          <td><strong><?=gettext("normal");?></strong></td>
                          <td><?=gettext("as the name says, it is the normal optimization algorithm");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("high-latency");?></strong></td>
                          <td><?=gettext("used for high latency links, such as satellite links.  Expires idle connections later than default");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("aggressive");?></strong></td>
                          <td><?=gettext("expires idle connections quicker. More efficient use of CPU and memory but can drop legitimate idle connections");?></td>
                        </tr>
                        <tr>
                          <td><strong><?=gettext("conservative");?></strong></td>
                          <td><?=gettext("tries to avoid dropping any legitimate idle connections at the expense of increased memory usage and CPU utilization.");?></td>
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
                    <div class="hidden" for="help_for_disablefilter">
                      <strong><?=gettext("Disable all packet filtering.");?></strong><br/>
                      <?php printf(gettext("Warning: This converts %s into a routing only platform!"), $g['product_name']);?>
                      <?=gettext("Warning: This will also turn off NAT!");?><br />
                      <?=sprintf(
                        gettext('If you only want to disable NAT, and not firewall rules, visit the %sOutbound NAT%s page.'),
                        '<a href="/firewall_nat_out.php">', '</a>'
                      )?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablescrub" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable Firewall Scrub");?></td>
                  <td>
                    <input name="disablescrub" type="checkbox" value="yes" <?=!empty($pconfig['disablescrub']) ? "checked=\"checked\"" : "";?>/>
                    <div class="hidden" for="help_for_disablescrub">
                      <?=gettext("Disables the PF scrubbing option which can sometimes interfere with NFS and PPTP traffic.");?>
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
                      <?=gettext("When the number of state entries exceeds this value, adaptive scaling begins.  All timeout values are scaled linearly with factor (adaptive.end - number of states) / (adaptive.end - adaptive.start).");?><br/>
                      <strong><?=gettext("end");?></strong></br>
                      <?=gettext("When reaching this number of state entries, all timeout values become zero, effectively purging all state entries immediately.  This value is used to define the scale factor, it should not actually be reached (set a lower state limit, see below).");?>
                      <br/>
                      <strong><?=gettext("Note:  Leave this blank for the default(0).");?></strong>
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
                      <?=gettext("Note:  Leave this blank for the default.  On your system the default size is:");?> <?= default_state_size() ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_maximumtableentries" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firewall Maximum Table Entries");?></td>
                  <td>
                    <input name="maximumtableentries" type="text" id="maximumtableentries" value="<?php echo $pconfig['maximumtableentries']; ?>" />
                    <div class="hidden" for="help_for_maximumtableentries">
                      <strong><?=gettext("Maximum number of table entries for systems such as aliases, sshlockout, snort, etc, combined.");?></strong>
                      <br />
                      <?=gettext("Note:  Leave this blank for the default.");?>
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
                    <div class="hidden" for="help_for_bypassstaticroutes">
                      <strong><?=gettext("Bypass firewall rules for traffic on the same interface");?></strong>
                      <br />
                      <?=gettext("This option only applies if you have defined one or more static routes. If it is enabled, traffic that enters and " .
                                          "leaves through the same interface will not be checked by the firewall. This may be desirable in some situations where " .
                                          "multiple subnets are connected to the same interface.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablevpnrules" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Disable Auto-added VPN rules') ?></td>
                  <td>
                    <input name="disablevpnrules" type="checkbox" value="yes" <?=!empty($pconfig['disablevpnrules']) ? "checked=\"checked\"" :"";?> />
                    <div class="hidden" for="help_for_disablevpnrules">
                      <strong><?=gettext("Disable all auto-added VPN rules.");?></strong>
                      <br />
                      <?=gettext("Note: This disables automatically added rules for IPsec, PPTP.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablereplyto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Disable reply-to') ?></td>
                  <td>
                    <input name="disablereplyto" type="checkbox" value="yes" <?=!empty($pconfig['disablereplyto']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_disablereplyto">
                      <strong><?=gettext("Disable reply-to on WAN rules");?></strong>
                      <br />
                      <?=gettext("With Multi-WAN you generally want to ensure traffic leaves the same interface it arrives on, hence reply-to is added automatically by default. " .
                                          "When using bridging, you must disable this behavior if the WAN gateway IP is different from the gateway IP of the hosts behind the bridged interface.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablenegate" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Disable Negate rules') ?></td>
                  <td>
                    <input name="disablenegate" type="checkbox" value="yes" <?=!empty($pconfig['disablenegate']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_disablenegate">
                      <strong><?=gettext("Disable Negate rule on policy routing rules");?></strong>
                      <br />
                      <?=gettext("With Multi-WAN you generally want to ensure traffic reaches directly connected networks and VPN networks when using policy routing. You can disable this for special purposes but it requires manually creating rules for these networks");?>
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
                      <?=gettext("Note:  Leave this blank for the default (300s).");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_aliasesresolveinterval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Check certificate of aliases URLs");?></td>
                  <td>
                    <input name="checkaliasesurlcert" type="checkbox" value="yes" <?=!empty($pconfig['checkaliasesurlcert']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_aliasesresolveinterval">
                      <strong><?=gettext("Verify HTTPS certificates when downloading alias URLs");?></strong>
                      <br />
                      <?=gettext("Make sure the certificate is valid for all HTTPS addresses on aliases. If it's not valid or is revoked, do not download it.");?>
                    </div>
                  </td>
                </tr>
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
<?php
                if (count($config['interfaces']) > 1) :?>
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
                      <option value="proxy" <?=$pconfig['natreflection'] == "proxy" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Enable (NAT + Proxy)"); ?>
                      </option>
                      <option value="purenat" <?=$pconfig['natreflection'] == "purenat" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Enable (Pure NAT)"); ?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_natreflection">
                      <strong><?=gettext("When enabled, this automatically creates additional NAT redirect rules for access to port forwards on your external IP addresses from within your internal networks.");?></strong>
                      <br /><br />
                      <?=gettext("The NAT + proxy mode uses a helper program to send packets to the target of the port forward.  It is useful in setups where the interface and/or gateway IP used for communication with the target cannot be accurately determined at the time the rules are loaded.  Reflection rules are not created for ranges larger than 500 ports and will not be used for more than 1000 ports total between all port forwards.  Only TCP and UDP protocols are supported.");?>
                      <br /><br />
                      <?=gettext("The pure NAT mode uses a set of NAT rules to direct packets to the target of the port forward.  It has better scalability, but it must be possible to accurately determine the interface and gateway IP used for communication with the target at the time the rules are loaded.  There are no inherent limits to the number of ports other than the limits of the protocols.  All protocols available for port forwards are supported.");?>
                      <br /><br />
                      <?=gettext("Individual rules may be configured to override this system setting on a per-rule basis.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_reflectiontimeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Reflection Timeout");?></td>
                  <td>
                    <input name="reflectiontimeout" type="text" value="<?=$pconfig['reflectiontimeout']; ?>" />
                    <div class="hidden" for="help_for_reflectiontimeout">
                      <strong><?=gettext("Enter value for Reflection timeout in seconds.");?></strong>
                      <br /><br />
                      <?=gettext("Note: Only applies to Reflection on port forwards in NAT + proxy mode.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_enablebinatreflection" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable Reflection for 1:1");?></td>
                  <td>
                    <input name="enablebinatreflection" type="checkbox" id="enablebinatreflection" value="yes" <?=!empty($pconfig['enablebinatreflection']) ? "checked=\"checked\"" : "";?>/>
                    <div class="hidden" for="help_for_enablebinatreflection">
                      <strong><?=gettext("Enables the automatic creation of additional NAT redirect rules for access to 1:1 mappings of your external IP addresses from within your internal networks.");?></strong>
                      <br /><br />
                      <?=gettext("Note: Reflection on 1:1 mappings is only for the inbound component of the 1:1 mappings.  This functions the same as the pure NAT mode for port forwards.  For more details, refer to the pure NAT mode description above.");?>
                      <br /><br />
                      <?=gettext("Individual rules may be configured to override this system setting on a per-rule basis.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_enablenatreflectionhelper" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable automatic outbound NAT for Reflection");?></td>
                  <td>
                    <input name="enablenatreflectionhelper" type="checkbox" id="enablenatreflectionhelper" value="yes" <?=!empty($pconfig['enablenatreflectionhelper']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_enablenatreflectionhelper">
                      <strong><?=gettext("Automatically create outbound NAT rules which assist inbound NAT rules that direct traffic back out to the same subnet it originated from.");?></strong>
                      <br />
                      <?=gettext("Required for full functionality of the pure NAT mode of NAT Reflection for port forwards or NAT Reflection for 1:1 NAT.");?>
                      <br /><br />
                      <?=gettext("Note: This only works for assigned interfaces.  Other interfaces require manually creating the outbound NAT rules that direct the reply packets back through the router.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ftp_proxy_client" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("FTP Proxy");?></td>
                  <td>
                    <input name="ftp-proxy-client" type="checkbox" value="yes" <?= !empty($pconfig['ftp-proxy-client']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_ftp_proxy_client">
                      <strong><?=gettext("Enable FTP proxy for clients");?></strong>
                      <br />
                      <?=gettext("Configures the FTP proxy to allow for client connections behind the firewall using active file transfer mode.");?>
                    </div>
                  </td>
                </tr>

                  <?php
endif; ?>
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
