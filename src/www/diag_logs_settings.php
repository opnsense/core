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
    $it = new RecursiveDirectoryIterator('/var/log');

    foreach(new RecursiveIteratorIterator($it) as $file) {
        if ($file->isFile() && strpos($file->getFilename(), '.log') > -1) {
            if (strpos($file->getFilename(), 'flowd') === false) {
                @unlink((string)$file);
            }
        }
    }

    system_syslog_start();
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
    $pconfig['preservelogs'] =  !empty($config['syslog']['preservelogs']) ? $config['syslog']['preservelogs'] : null;
    $pconfig['maxfilesize'] =  !empty($config['syslog']['maxfilesize']) ? $config['syslog']['maxfilesize'] : null;
    $pconfig['logdefaultblock'] = empty($config['syslog']['nologdefaultblock']);
    $pconfig['logdefaultpass'] = empty($config['syslog']['nologdefaultpass']);
    $pconfig['logbogons'] = empty($config['syslog']['nologbogons']);
    $pconfig['logprivatenets'] = empty($config['syslog']['nologprivatenets']);
    $pconfig['logoutboundnat'] = !empty($config['syslog']['logoutboundnat']);
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
        if (!empty($pconfig['preservelogs']) && (strlen($pconfig['preservelogs']) > 0)) {
            if (!is_numeric($pconfig['preservelogs'])) {
                $input_errors[] = gettext("Preserve logs must be a positive integer value.");
            }
        }
        if (!empty($pconfig['maxfilesize']) && (strlen($pconfig['maxfilesize']) > 0)) {
            if (!is_numeric($pconfig['maxfilesize'])) {
                $input_errors[] = gettext("Max file size must be a positive integer value.");
            }
        }



        if (count($input_errors) == 0) {
            if (empty($config['syslog'])) {
                $config['syslog'] = [];
            }
            foreach (['preservelogs', 'maxfilesize'] as $fieldname) {
                if (isset($pconfig[$fieldname]) && (strlen($pconfig[$fieldname]) > 0)) {
                    $config['syslog'][$fieldname] = (int)$pconfig[$fieldname];
                } elseif (isset($config['syslog'][$fieldname])) {
                    unset($config['syslog'][$fieldname]);
                }
            }

            $config['syslog']['disablelocallogging'] = !empty($pconfig['disablelocallogging']);
            $oldnologdefaultblock = isset($config['syslog']['nologdefaultblock']);
            $oldnologdefaultpass = isset($config['syslog']['nologdefaultpass']);
            $oldnologbogons = isset($config['syslog']['nologbogons']);
            $oldnologprivatenets = isset($config['syslog']['nologprivatenets']);
            $oldnologlighttpd = isset($config['syslog']['nologlighttpd']);
            $oldlogoutboundnat = isset($config['syslog']['logoutboundnat']);
            $config['syslog']['nologdefaultblock'] = empty($pconfig['logdefaultblock']);
            $config['syslog']['nologdefaultpass'] = empty($pconfig['logdefaultpass']);
            $config['syslog']['nologbogons'] = empty($pconfig['logbogons']);
            $config['syslog']['nologprivatenets'] = empty($pconfig['logprivatenets']);
            $config['syslog']['logoutboundnat'] = !empty($pconfig['logoutboundnat']);
            $config['syslog']['nologlighttpd'] = empty($pconfig['loglighttpd']);

            write_config();

            system_syslog_start();

            if (($oldnologdefaultblock !== isset($config['syslog']['nologdefaultblock']))
              || ($oldnologdefaultpass !== isset($config['syslog']['nologdefaultpass']))
              || ($oldnologbogons !== isset($config['syslog']['nologbogons']))
              || ($oldlogoutboundnat !== isset($config['syslog']['logoutboundnat']))
              || ($oldnologprivatenets !== isset($config['syslog']['nologprivatenets']))) {
              filter_configure();
            }

            $savemsg = get_std_save_message();

            if ($oldnologlighttpd !== isset($config['syslog']['nologlighttpd'])) {
                configd_run('webgui restart 2', true);
                $savemsg .= "<br />" . gettext("WebGUI process is restarting.");
            }
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
                    <td><a id="help_for_preservelogs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Preserve logs') ?></td>
                    <td>
                      <input name="preservelogs" id="preservelogs" type="text" value="<?=$pconfig['preservelogs'];?>" />
                      <div class="hidden" data-for="help_for_preservelogs">
                          <?=gettext("Number of logs to preserve. By default 31 logs are preserved. When no max filesize is offered or the logs are smaller than the the size requested, this equals the number of days");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_maxfilesize" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Max log filesize (MB)') ?></td>
                    <td>
                      <input name="maxfilesize" id="maxfilesize" type="text" value="<?=$pconfig['maxfilesize'];?>" />
                      <div class="hidden" data-for="help_for_maxfilesize">
                          <?=gettext("Maximum filesize per log file, when set and a logfile exceeds the amount specified, it will be rotated.");?>
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
                      <input name="logoutboundnat" type="checkbox" id="logoutboundnat" value="yes" <?php if ($pconfig['logoutboundnat']) echo "checked=\"checked\""; ?> />
                      <?= gettext('Log packets processed by automatic outbound NAT rules') ?>
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
