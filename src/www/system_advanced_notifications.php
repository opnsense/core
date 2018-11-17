<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
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
require_once("system.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // SMTP
    $pconfig['disable_smtp'] = isset($config['notifications']['smtp']['disable']);
    $pconfig['smtpipaddress'] = !empty($config['notifications']['smtp']['ipaddress']) ? $config['notifications']['smtp']['ipaddress'] : null;
    $pconfig['smtpport'] = !empty($config['notifications']['smtp']['port']) ? $config['notifications']['smtp']['port'] : null;
    $pconfig['smtpssl'] = isset($config['notifications']['smtp']['ssl']);
    $pconfig['smtptls'] = isset($config['notifications']['smtp']['tls']);
    $pconfig['smtpnotifyemailaddress'] = !empty($config['notifications']['smtp']['notifyemailaddress']) ? $config['notifications']['smtp']['notifyemailaddress'] : null;
    $pconfig['smtpusername'] =!empty($config['notifications']['smtp']['username']) ? $config['notifications']['smtp']['username'] : null;
    $pconfig['smtppassword'] = !empty($config['notifications']['smtp']['password']) ? $config['notifications']['smtp']['password'] : null;
    $pconfig['smtpfromaddress'] = !empty($config['notifications']['smtp']['fromaddress']) ? $config['notifications']['smtp']['fromaddress'] : null;
    // System Sounds
    $pconfig['disablebeep'] = isset($config['system']['disablebeep']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

    if (!empty($pconfig['Submit']) && $pconfig['Submit'] == gettext("Save")) {
        // SMTP
        $config['notifications']['smtp']['ipaddress'] = $pconfig['smtpipaddress'];
        $config['notifications']['smtp']['port'] = $pconfig['smtpport'];
        if (!empty($pconfig['smtpssl'])) {
            $config['notifications']['smtp']['ssl'] = true;
        } elseif (isset($config['notifications']['smtp']['ssl'])) {
            unset($config['notifications']['smtp']['ssl']);
        }
        if (!empty($pconfig['smtptls'])) {
            $config['notifications']['smtp']['tls'] = true;
        } elseif (isset($config['notifications']['smtp']['tls'])) {
            unset($config['notifications']['smtp']['tls']);
        }
        $config['notifications']['smtp']['notifyemailaddress'] = $pconfig['smtpnotifyemailaddress'];
        $config['notifications']['smtp']['username'] = $pconfig['smtpusername'];
        $config['notifications']['smtp']['password'] = $pconfig['smtppassword'];
        $config['notifications']['smtp']['fromaddress'] = $pconfig['smtpfromaddress'];

        if (!empty($pconfig['disable_smtp'])) {
            $config['notifications']['smtp']['disable'] = true;
        } elseif (isset($config['notifications']['smtp']['disable'])) {
            unset($config['notifications']['smtp']['disable']);
        }

        // System Sounds
        if (!empty($pconfig['disablebeep'])) {
            $config['system']['disablebeep'] = true;
        } elseif (isset($config['system']['disablebeep'])) {
            unset($config['system']['disablebeep']);
        }

        write_config();
        header(url_safe('Location: /system_advanced_notifications.php'));
        return;
    } elseif (!empty($pconfig['test_smtp']) && $pconfig['test_smtp'] == gettext("Test SMTP")) {
        // Send test message via smtp
        @unlink('/var/db/notices_lastmsg.txt');
        notify_via_smtp(sprintf(gettext("This is a test message from %s. It is safe to ignore this message."), $g['product_name']), true);
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<script>
//<![CDATA[
  $(document).ready(function() {
    if ($('#smtpssl').is(':checked')) {
      $('#smtptls').prop('disabled', true);
    } else if  ($('#smtptls').is(':checked')) {
      $('#smtpssl').prop('disabled', true);
    }
    $('#smtpssl').change( function() {
      $('#smtptls').prop('disabled', this.checked);
    });
    $('#smtptls').change( function() {
      $('#smtpssl').prop('disabled', this.checked);
    });
  });
//]]>
</script>

<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <form method="post">
<?php
      if (isset($savemsg)) {
          print_info_box($savemsg);
      }
?>
      </form>
      <section class="col-xs-12">
        <form method="post" name="iform">
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?=gettext("SMTP Email"); ?></strong></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disable_smtp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable SMTP Notifications"); ?></td>
                <td>
                  <input type="checkbox" name="disable_smtp" value="yes" <?=!empty($pconfig['disable_smtp']) ? "checked=\"checked\"" : "";?>/>
                  <div class="hidden" data-for="help_for_disable_smtp">
                    <?=gettext("Check this option to disable SMTP notifications but preserve the settings below. Some other mechanisms, such as packages, may need these settings in place to function."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpipaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email server"); ?></td>
                <td>
                  <input name="smtpipaddress" type="text" value="<?=$pconfig['smtpipaddress']; ?>" />
                  <div class="hidden" data-for="help_for_smtpipaddress">
                    <?=gettext("This is the FQDN or IP address of the SMTP Email server to which notifications will be sent."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SMTP Port of Email server"); ?></td>
                <td>
                  <input name="smtpport" type="text" value="<?=$pconfig['smtpport']; ?>" />
                  <div class="hidden" data-for="help_for_smtpport">
                    <?=gettext("This is the port of the SMTP Email server, typically 25, 587 (submission) or 465 (smtps)"); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Secure SMTP Connection"); ?></td>
                <td>
                  <input type="checkbox" id="smtpssl" name="smtpssl" <?=!empty($pconfig['smtpssl']) ? "checked=\"checked\"" : "";?> />
                  <?=gettext('Enable SMTP over SSL/TLS');?><br/>
                  <input type="checkbox" id="smtptls" name="smtptls" <?=!empty($pconfig['smtptls']) ? "checked=\"checked\"" : "";?> />
                  <?=gettext('Enable STARTTLS');?><br/>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpfromaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sender address"); ?></td>
                <td>
                  <input name="smtpfromaddress" type="text" value="<?=$pconfig['smtpfromaddress']; ?>" />
                  <div class="hidden" data-for="help_for_smtpfromaddress">
                    <?=gettext("This is the email address that will appear as the email notification sender."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpnotifyemailaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Recipient address"); ?></td>
                <td>
                  <input name="smtpnotifyemailaddress" type="text" value="<?=$pconfig['smtpnotifyemailaddress'];?>" />
                  <div class="hidden" data-for="help_for_smtpnotifyemailaddress">
                    <?=gettext("Enter the email address that you would like email notifications sent to."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpusername" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email auth username"); ?></td>
                <td>
                  <input name="smtpusername" type="text" value="<?=$pconfig['smtpusername']; ?>" />
                  <div class="hidden" data-for="help_for_smtpusername">
                    <?=gettext("Enter the email address username for SMTP authentication."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtppassword" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email auth password"); ?></td>
                <td>
                  <input name='smtppassword' type='password' value='<?=$pconfig['smtppassword']; ?>' />
                  <div class="hidden" data-for="help_for_smtppassword">
                    <?=gettext("Enter the email address password for SMTP authentication."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('System Sounds') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_disablebeep" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Startup/Shutdown Sound"); ?></td>
                <td>
                  <input name="disablebeep" type="checkbox" id="disablebeep" value="yes" <?=!empty($pconfig['disablebeep']) ? "checked=\"checked\"" : "";?>/>
                  <?=gettext("Disable the startup/shutdown beep"); ?>
                  <div class="hidden" data-for="help_for_disablebeep">
                    <?=gettext("When this is checked, startup and shutdown sounds will no longer play."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"></td>
                <td style="width:78%">
                  <input type="submit" id="Submit" name="Submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                  <input type="submit" id="test_smtp" name="test_smtp" value="<?=gettext("Test SMTP"); ?>" class="btn btn-default" /><br/>
                  <div data-for="help_for_notifytest">
                    <?= gettext('A test notification will be sent even if the service is marked as disabled.') ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
