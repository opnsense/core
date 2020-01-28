<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2007 Seth Mos <seth.mos@dds.nl>
 * Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
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
require_once("interfaces.inc");
require_once("filter.inc");
require_once("system.inc");

function clear_all_log_files()
{
    killbyname('syslogd');

    $clog_files = array(
        'dhcpd',
        'configd',
        'filter',
        'gateways',
        'ipsec',
        'l2tps',
        'lighttpd',
        'mail',
        'ntpd',
        'openvpn',
        'pkg',
        'poes',
        'portalauth',
        'ppps',
        'pptps',
        'relayd',
        'resolver',
        'routing',
        'suricata',
        'system',
        'vpn',
        'wireless',
    );

    $log_files = array(
        'squid/access',
        'squid/cache',
        'squid/store',
    );

    foreach ($clog_files as $lfile) {
        system_clear_clog("/var/log/{$lfile}.log", false);
    }

    foreach ($log_files as $lfile) {
        system_clear_log("/var/log/{$lfile}.log", false);
    }

    system_syslogd_start();
    plugins_configure('dhcp');
}

function is_valid_syslog_server($target) {
    return (is_ipaddr($target)
      || is_ipaddrwithport($target)
      || is_hostname($target)
      || is_hostnamewithport($target));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['reverse'] = isset($config['syslog']['reverse']);
    $pconfig['logfilesize'] =  !empty($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : null;
    $pconfig['logdefaultblock'] = empty($config['syslog']['nologdefaultblock']);
    $pconfig['logdefaultpass'] = empty($config['syslog']['nologdefaultpass']);
    $pconfig['logbogons'] = empty($config['syslog']['nologbogons']);
    $pconfig['logprivatenets'] = empty($config['syslog']['nologprivatenets']);
    $pconfig['loglighttpd'] = empty($config['syslog']['nologlighttpd']);
    $pconfig['disablelocallogging'] = isset($config['syslog']['disablelocallogging']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action']) && $_POST['action'] == "resetlogs") {
        clear_all_log_files();
        $pconfig = $_POST;
        $savemsg = gettext("The log files have been reset.");
    } else {
        $input_errors = array();
        $pconfig = $_POST;

        /* input validation */
        if (!empty($pconfig['logfilesize']) && (strlen($pconfig['logfilesize']) > 0)) {
            if (!is_numeric($pconfig['logfilesize']) || ($pconfig['logfilesize'] < 5120)) {
                $input_errors[] = gettext("Log file size must be a positive integer greater than 5120.");
            }
        }
        if (count($input_errors) == 0) {
            $config['syslog']['reverse'] = !empty($pconfig['reverse']) ? true : false;
            if (isset($_POST['logfilesize']) && (strlen($pconfig['logfilesize']) > 0)) {
                $config['syslog']['logfilesize'] = (int)$pconfig['logfilesize'];
            } elseif (isset($config['syslog']['logfilesize'])) {
                unset($config['syslog']['logfilesize']);
            }
            $config['syslog']['disablelocallogging'] = !empty($pconfig['disablelocallogging']);
            $oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
            $oldnologdefaultpass = isset($config['syslog']['nologdefaultpass']);
            $oldnologbogons = isset($config['syslog']['nologbogons']);
            $oldnologprivatenets = isset($config['syslog']['nologprivatenets']);
            $oldnologlighttpd = isset($config['syslog']['nologlighttpd']);
            $config['syslog']['nologdefaultblock'] = empty($pconfig['logdefaultblock']);
            $config['syslog']['nologdefaultpass'] = empty($pconfig['logdefaultpass']);
            $config['syslog']['nologbogons'] = empty($pconfig['logbogons']);
            $config['syslog']['nologprivatenets'] = empty($pconfig['logprivatenets']);
            $config['syslog']['nologlighttpd'] = empty($pconfig['loglighttpd']);

            write_config();

            system_syslogd_start(false, true);

            if (($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock']))
              || ($oldnologdefaultpass !== isset($config['syslog']['nologdefaultpass']))
              || ($oldnologbogons !== isset($config['syslog']['nologbogons']))
              || ($oldnologprivatenets !== isset($config['syslog']['nologprivatenets']))) {
              filter_configure();
            }

            $savemsg = get_std_save_message();

            if ($oldnologlighttpd !== isset($config['syslog']['nologlighttpd'])) {
                log_error('Web GUI configuration has changed. Restarting now.');
                configd_run('webgui restart 2', true);
                $savemsg .= "<br />" . gettext("WebGUI process is restarting.");
            }

            filter_pflog_start();
        }
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>


<body>
<script>
$(document).ready(function() {
    // messagebox, flush all log files
    $("#resetlogs").click(function(event){
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= gettext("Syslog");?>",
            message: "<?=gettext('Do you really want to reset the log files? This will erase all local log data.');?>",
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#action").val("resetlogs");
                        $("#iform").submit()
                    }
                }]
        });
    });
});

//]]>
</script>
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
      }
?>
        <section class="col-xs-12">
          <form method="post" name="iform" id="iform">
            <input type="hidden" id="action" name="action" value="" />
            <div class="tab-content content-box col-xs-12 __mb">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Local Logging Options");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_reverse" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Reverse Display') ?></td>
                    <td>
                      <input name="reverse" type="checkbox" id="reverse" value="yes" <?=!empty($pconfig['reverse']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" data-for="help_for_reverse">
                        <?=gettext("Show log entries in reverse order (newest entries on top)");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_logfilesize" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Log File Size (Bytes)') ?></td>
                    <td>
                      <input name="logfilesize" type="text" value="<?=$pconfig['logfilesize'];?>" />
                      <div class="hidden" data-for="help_for_logfilesize">
                        <?=gettext("Logs are held in constant-size circular log files. This field controls how large each log file is, and thus how many entries may exist inside the log. By default this is approximately 500KB per log file, and there are nearly 20 such log files.") ?>
                        <br /><br />
                        <?=gettext("NOTE: Log sizes are changed the next time a log file is cleared or deleted. To immediately increase the size of the log files, you must first save the options to set the size, then clear all logs using the \"Reset Log Files\" option farther down this page. "); ?>
                        <?=gettext("Be aware that increasing this value increases every log file size, so disk usage will increase significantly."); ?>
                        <?=gettext("Disk space currently used by log files: ") ?><?= exec("/usr/bin/du -sh /var/log | /usr/bin/awk '{print $1;}'"); ?>.
                        <?=gettext("Remaining disk space for log files: ") ?><?= exec("/bin/df -h /var/log | /usr/bin/awk '{print $4;}'"); ?>.
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_logdefaultblock" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Log Firewall Default Blocks') ?></td>
                    <td>
                      <input name="logdefaultblock" type="checkbox" value="yes" <?=!empty($pconfig['logdefaultblock']) ? "checked=\"checked\"" : ""; ?> />
                      <?=gettext("Log packets matched from the default block rules put in the ruleset");?>
                      <div class="hidden" data-for="help_for_logdefaultblock">
                        <?=gettext("Hint: packets that are blocked by the implicit default block rule will not be logged if you uncheck this option. Per-rule logging options are still respected.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input name="logdefaultpass" type="checkbox" id="logdefaultpass" value="yes" <?=!empty($pconfig['logdefaultpass']) ? "checked=\"checked\"" :""; ?> />
                      <?=gettext("Log packets matched from the default pass rules put in the ruleset");?>
                      <div class="hidden" data-for="help_for_logdefaultblock">
                        <?=gettext("Hint: packets that are allowed by the implicit default pass rule will be logged if you check this option. Per-rule logging options are still respected.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input name="logbogons" type="checkbox" id="logbogons" value="yes" <?=!empty($pconfig['logbogons']) ? "checked=\"checked\"" : ""; ?> />
                      <?=gettext("Log packets blocked by 'Block Bogon Networks' rules");?>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input name="logprivatenets" type="checkbox" id="logprivatenets" value="yes" <?php if ($pconfig['logprivatenets']) echo "checked=\"checked\""; ?> />
                      <?=gettext("Log packets blocked by 'Block Private Networks' rules");?>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_loglighttpd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Web Server Log') ?></td>
                    <td>
                      <input name="loglighttpd" type="checkbox" id="loglighttpd" value="yes" <?=!empty($pconfig['loglighttpd']) ? "checked=\"checked\"" :""; ?> />
                      <?=gettext("Log errors from the web server process.");?>
                      <div class="hidden" data-for="help_for_loglighttpd">
                        <?=gettext('Hint: If this is checked, errors from the lighttpd web server process for the GUI or Captive Portal will appear in the main system log.');?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Local Logging') ?></td>
                    <td> <input name="disablelocallogging" type="checkbox" id="disablelocallogging" value="yes" <?=!empty($pconfig['disablelocallogging']) ? "checked=\"checked\"" :""; ?>  />
                      <?=gettext("Disable writing log files to the local disk");?></td>
                  </tr>
                  <tr>
                    <td><a id="help_for_resetlogs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Reset Logs') ?></td>
                    <td>
                      <input name="resetlogs" id="resetlogs" type="submit" class="btn btn-default" value="<?= html_safe(gettext('Reset Log Files')) ?>"/>
                      <div class="hidden" data-for="help_for_resetlogs">
                        <?= gettext("Note: Clears all local log files and reinitializes them as empty logs. This also restarts the DHCP daemon. Use the Save button first if you have made any setting changes."); ?>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
            <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"></td>
                    <td style="width:78%"><input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>"/>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </form>
        </section>
      </div>
    </div>
  </section>
<?php

include("foot.inc");
