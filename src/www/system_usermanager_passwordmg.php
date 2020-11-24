<?php

/*
 * Copyright (C) 2017-2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2011 Ermal Luçi
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
require_once("system.inc");
require_once("base32/Base32.php");

$username = $_SESSION['Username'];

/* determine if user is not local to system */
$userFound = false;
foreach ($config['system']['user'] as $user) {
    if ($user['name'] == $username) {
        $userFound = true;
        break;
    }
}

/* determine if the user is allowed to request a new OTP seed */
$user_allow_gen_token = false;
if (isset($config['system']['user_allow_gen_token'])) {
    $usergroups = getUserGroups($username);
    foreach(explode(",", $config['system']['user_allow_gen_token']) as $groupname) {
        if (in_array($groupname, $usergroups)) {
            $user_allow_gen_token = true;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    if (isset($_GET['savemsg'])) {
        $savemsg = htmlspecialchars(sprintf(gettext($_GET['savemsg']), $username));
    } elseif (!empty($_SESSION['user_shouldChangePassword'])) {
        $savemsg = gettext('Your password does not match the selected security policies. Please provide a new one.');
    }

    $pconfig['language'] = $userFound ? $config['system']['user'][$userindex[$username]]['language'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($pconfig['request_otp_seed'])) {
        if ($user_allow_gen_token && $userFound) {
            $new_seed = Base32\Base32::encode(openssl_random_pseudo_bytes(20));
            $config['system']['user'][$userindex[$username]]['otp_seed'] = $new_seed;
            write_config();
            $otp_url = "otpauth://totp/";
            $otp_url .= $username."@".htmlspecialchars($config['system']['hostname'])."?secret=";
            $otp_url .= $new_seed;
            echo json_encode([
              "otp_seed" => $new_seed ,
              "otp_seed_url" => $otp_url,
              "status" => "ok"
            ]);
        } else {
            echo json_encode(["status" => "failed"]);
        }
        exit;
    } else {
        /* we can continue without a password if nothing was provided */
        if ($pconfig['passwordfld1'] !== '' || $pconfig['passwordfld2'] !== '') {
            if ($pconfig['passwordfld1'] != $pconfig['passwordfld2'] ||
                !password_verify($pconfig['passwordfld0'], $config['system']['user'][$userindex[$username]]['password'])) {
                $input_errors[] = gettext("The passwords do not match.");
            }

            if (!$userFound) {
                $input_errors[] = gettext("Sorry, you cannot change settings for a non-local user.");
            } elseif (count($input_errors) == 0) {
                $authenticator = get_authenticator();
                $input_errors = $authenticator->checkPolicy($username, $pconfig['passwordfld0'], $pconfig['passwordfld1']);
            }
        }

        if (count($input_errors) == 0) {
            if (!empty($pconfig['language'])) {
                $config['system']['user'][$userindex[$username]]['language'] = $pconfig['language'];
            } elseif (isset($config['system']['user'][$userindex[$username]]['language'])) {
                unset($config['system']['user'][$userindex[$username]]['language']);
            }

            // only update password change date if there is a policy constraint
            if (!empty($config['system']['webgui']['enable_password_policy_constraints']) &&
                !empty($config['system']['webgui']['password_policy_length'])
            ) {
                $config['system']['user'][$userindex[$username]]['pwd_changed_at'] = microtime(true);
            }
            if (!empty($_SESSION['user_shouldChangePassword'])) {
                session_start();
                unset($_SESSION['user_shouldChangePassword']);
                session_write_close();
            }
            if ($pconfig['passwordfld1'] !== '' || $pconfig['passwordfld2'] !== '') {
                local_user_set_password($config['system']['user'][$userindex[$username]], $pconfig['passwordfld1']);
                local_user_set($config['system']['user'][$userindex[$username]]);
            }

            write_config();

            $unused_but_needed_for_translation = gettext('Saved settings for user "%s"');
            header(url_safe('Location: /system_usermanager_passwordmg.php?savemsg=%s', array('Saved settings for user "%s"')));
            exit;
        }
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

<script>
$( document ).ready(function() {
    $("#btn_new_otp_seed").click(function(){
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("OTP");?>",
          message: "<?= gettext("Are you sure you want to request a new OTP token? The previous token will not be valid after this action.");?>",
          buttons: [{
              label: "<?= gettext("No");?>",
              action: function(dialogRef) {
                  dialogRef.close();
              }}, {
              label: "<?= gettext("Yes");?>",
              action: function(dialogRef) {
                $.post(window.location, {request_otp_seed: '1'}, function(data) {
                      if (data.status && data.status === "ok") {
                          $('#otp_qrcode').qrcode(data.otp_seed_url);
                      } else {
                          $('#otp_qrcode').append(
                              $("<i/>").addClass("fa fa-4x fa-close")
                          );
                      }
                      $("#btn_new_otp_seed").hide();
                      $('#otp_qrcode').show();
                }, "json");
                dialogRef.close();
            }
          }]
      });

    });
});

</script>

<script src="<?= cache_safe('/ui/js/jquery.qrcode.js') ?>"></script>
<script src="<?= cache_safe('/ui/js/qrcode.js') ?>"></script>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?
                if (isset($input_errors) && count($input_errors) > 0) {
                    print_input_errors($input_errors);
                }
                if (isset($savemsg)) {
                    print_info_box($savemsg);
                }
?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12 __mb">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td style="width:22%"><strong><?= gettext('User Settings') ?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Old password"); ?></td>
                    <td>
                      <input name="passwordfld0" type="password" id="passwordfld0" size="20" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("New password"); ?></td>
                    <td>
                      <input name="passwordfld1" type="password" id="passwordfld1" size="20" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Confirmation");?></td>
                    <td>
                      <input name="passwordfld2" type="password" id="passwordfld2" size="20" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_language" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Language");?></td>
                    <td>
                      <select name="language" class="selectpicker" data-style="btn-default">
                        <option value="" <?= empty($pconfig['language']) ? "selected='selected'" : '' ?>><?=gettext('System defaults') ?></option>
<?php foreach (get_locale_list() as $lcode => $ldesc): ?>
                        <option value="<?= html_safe($lcode) ?>" <?= $lcode == $pconfig['language'] ? 'selected="selected"' : '' ?>><?= $ldesc ?></option>
<?php endforeach ?>
                      </select>
                      <div class="hidden" data-for="help_for_language">
                        <?= gettext('Choose a language for the web GUI.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                    </td>
                  </tr>
                </table>
            </div>
          </form>
        </div>
<?php if ($user_allow_gen_token):?>
        <div class="tab-content content-box col-xs-12 __mb">
          <div class="table-responsive">
            <table class="table table-striped">
              <tr>
                <td style="width:22%"><strong><?= gettext('OTP') ?></strong></td>
                <td style="width:78%; text-align:right">
              </tr>
              <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Request new OTP seed");?></td>
                  <td>
                      <button class="btn btn-primary" id="btn_new_otp_seed"><i class="fa fa-ticket fa-fw"></i></button>
                      <div style="display:none;" id="otp_qrcode"></div>
                  </td>
              </tr>
            </table>
          </div>
        </div>
<?php endif; ?>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
