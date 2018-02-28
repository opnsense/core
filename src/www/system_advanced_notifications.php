<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>

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
require_once("system.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Growl
    $pconfig['disable_growl'] = isset($config['notifications']['growl']['disable']);
    $pconfig['password'] = !empty($config['notifications']['growl']['password']) ? $config['notifications']['growl']['password'] : null ;
    $pconfig['ipaddress'] = !empty($config['notifications']['growl']['ipaddress']) ? $config['notifications']['growl']['ipaddress'] : null;
    $pconfig['notification_name'] = !empty($config['notifications']['growl']['notification_name']) ? $config['notifications']['growl']['notification_name'] : "{$g['product_name']} growl alert";
    $pconfig['name'] = !empty($config['notifications']['growl']['name']) ? $config['notifications']['growl']['name'] : 'PHP-Growl';
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
        // Growl
        $config['notifications']['growl']['ipaddress'] = $pconfig['ipaddress'];
        $config['notifications']['growl']['password'] = $pconfig['password'];
        $config['notifications']['growl']['name'] = $pconfig['name'];
        $config['notifications']['growl']['notification_name'] = $pconfig['notification_name'];

        if (!empty($pconfig['disable_growl'])) {
            $config['notifications']['growl']['disable'] = true;
        } elseif (isset($config['notifications']['growl']['disable'])) {
            unset($config['notifications']['growl']['disable']);
        }

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

    } elseif (isset($pconfig['test_growl']) && $pconfig['test_growl'] == gettext("Test Growl")) {
        // Send test message via growl
        if (!empty($config['notifications']['growl']['ipaddress']) &&
            !empty($config['notifications']['growl']['password'])) {
            @unlink('/var/db/growlnotices_lastmsg.txt');
            register_via_growl();
            notify_via_growl(sprintf(gettext("This is a test message from %s. It is safe to ignore this message."), $g['product_name']), true);
        }
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
          <div class="content-box tab-content table-responsive">
            <form method="post" name="iform">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?=gettext("Growl");?></strong></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disable_growl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable Growl Notifications"); ?></td>
                <td>
                  <input type='checkbox' name='disable_growl' value="yes" <?=!empty($pconfig['disable_growl']) ? "checked=\"checked\"" : "";?>/>
                  <output class="hidden" for="help_for_disable_growl">
                    <?=gettext("Check this option to disable growl notifications but preserve the settings below."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Registration Name"); ?></td>
                <td>
                  <input name="name" type="text" value="<?=$pconfig['name']; ?>"/>
                  <output class="hidden" for="help_for_name">
                    <?=gettext("Enter the name to register with the Growl server (default: PHP-Growl)."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_notification_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Notification Name"); ?></td>
                <td>
                  <input name='notification_name' type='text' value='<?=$pconfig['notification_name']; ?>' /><br />
                  <output class="hidden" for="help_for_notification_name">
                    <?=sprintf(gettext("Enter a name for the Growl notifications (default: %s growl alert)."), $g['product_name']); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_ipaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Address"); ?></td>
                <td>
                  <input name="ipaddress" type="text" value="<?=$pconfig['ipaddress']; ?>" /><br />
                  <output class="hidden" for="help_for_ipaddress">
                    <?=gettext("This is the IP address that you would like to send growl notifications to."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_password" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Password"); ?></td>
                <td>
                  <input name="password" type="password" value="<?=$pconfig['password']; ?>"/><br />
                  <output class="hidden" for="help_for_password">
                    <?=gettext("Enter the password of the remote growl notification device."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <th colspan="2"><?=gettext("SMTP Email"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_disable_smtp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable SMTP Notifications"); ?></td>
                <td>
                  <input type="checkbox" name="disable_smtp" value="yes" <?=!empty($pconfig['disable_smtp']) ? "checked=\"checked\"" : "";?>/>
                  <output class="hidden" for="help_for_disable_smtp">
                    <?=gettext("Check this option to disable SMTP notifications but preserve the settings below. Some other mechanisms, such as packages, may need these settings in place to function."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpipaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email server"); ?></td>
                <td>
                  <input name="smtpipaddress" type="text" value="<?=$pconfig['smtpipaddress']; ?>" />
                  <output class="hidden" for="help_for_smtpipaddress">
                    <?=gettext("This is the FQDN or IP address of the SMTP Email server to which notifications will be sent."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SMTP Port of Email server"); ?></td>
                <td>
                  <input name="smtpport" type="text" value="<?=$pconfig['smtpport']; ?>" />
                  <output class="hidden" for="help_for_smtpport">
                    <?=gettext("This is the port of the SMTP Email server, typically 25, 587 (submission) or 465 (smtps)"); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Secure SMTP Connection"); ?></td>
                <td>
                  <input type="checkbox" id="smtpssl" name="smtpssl" <?=!empty($pconfig['smtpssl']) ? "checked=\"checked\"" : "";?> />
                  <strong><?=gettext('Enable SMTP over SSL/TLS');?></strong><br />
                  <input type="checkbox" id="smtptls" name="smtptls" <?=!empty($pconfig['smtptls']) ? "checked=\"checked\"" : "";?> />
                  <strong><?=gettext('Enable STARTTLS');?></strong><br />
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpfromaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sender address"); ?></td>
                <td>
                  <input name="smtpfromaddress" type="text" value="<?=$pconfig['smtpfromaddress']; ?>" />
                  <output class="hidden" for="help_for_smtpfromaddress">
                    <?=gettext("This is the email address that will appear as the email notification sender."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpnotifyemailaddress" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Recipient address"); ?></td>
                <td>
                  <input name="smtpnotifyemailaddress" type="text" value="<?=$pconfig['smtpnotifyemailaddress'];?>" />
                  <output class="hidden" for="help_for_smtpnotifyemailaddress">
                    <?=gettext("Enter the email address that you would like email notifications sent to."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtpusername" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email auth username"); ?></td>
                <td>
                  <input name="smtpusername" type="text" value="<?=$pconfig['smtpusername']; ?>" />
                  <output class="hidden" for="help_for_smtpusername">
                    <small><?=gettext("(optional)");?></small><br/>
                    <?=gettext("Enter the email address username for SMTP authentication."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_smtppassword" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Email auth password"); ?></td>
                <td>
                  <input name='smtppassword' type='password' value='<?=$pconfig['smtppassword']; ?>' /><br />
                  <output class="hidden" for="help_for_smtppassword">
                    <small><?=gettext("(optional)");?></small><br/>
                    <?=gettext("Enter the email address password for SMTP authentication."); ?>
                  </output>
                </td>
              </tr>
              <tr>
                <td colspan="2">&nbsp;</td>
              </tr>
              <!-- System Sounds -->
              <tr>
                <th colspan="2"><?=gettext("System Sounds"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_disablebeep" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Startup/Shutdown Sound"); ?></td>
                <td>
                  <input name="disablebeep" type="checkbox" id="disablebeep" value="yes" <?=!empty($pconfig['disablebeep']) ? "checked=\"checked\"" : "";?>/>
                  <strong><?=gettext("Disable the startup/shutdown beep"); ?></strong>
                  <br />
                  <output class="hidden" for="help_for_disablebeep">
                    <span class="vexpl"><?=gettext("When this is checked, startup and shutdown sounds will no longer play."); ?></span>
                  </output>
                </td>
              </tr>
              <tr>
                <td colspan="2">&nbsp;</td>
              </tr>
              <tr>
                <td></td>
                <td>
                  <input type="submit" id="Submit" name="Submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                </td>
              </tr>
              <tr>
                <td></td>
                <td>
                  <input type="submit" id="test_growl" name="test_growl" value="<?=gettext("Test Growl"); ?>" class="btn btn-primary" />
                  <input type="submit" id="test_smtp" name="test_smtp" value="<?=gettext("Test SMTP"); ?>" class="btn btn-primary" />
                  <br />
                  <small><?= gettext("NOTE: A test notification will be sent even if the service is marked as disabled.") ?></small>
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
