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

$username = $_SESSION['Username'];

/* determine if user is not local to system */
$userFound = false;
foreach ($config['system']['user'] as $user) {
    if ($user['name'] == $username) {
        $userFound = true;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    if (isset($_GET['savemsg'])) {
        $savemsg = htmlspecialchars(sprintf(gettext($_GET['savemsg']), $username));
    } elseif (!empty($_SESSION['user_shouldChangePassword'])) {
        $savemsg = gettext("Your password has expired, please provide a new one");
    }

    if ($userFound) {
        $pconfig['language'] = $config['system']['user'][$userindex[$username]]['language'];
    }
    if (empty($pconfig['language'])) {
        $pconfig['language'] = $config['system']['language'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

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
        $config['system']['user'][$userindex[$username]]['language'] = $pconfig['language'];
        // only update password change date if there is a policy constraint
        if (!empty($config['system']['webgui']['enable_password_policy_constraints']) &&
            !empty($config['system']['webgui']['password_policy_length'])
        ) {
            $config['system']['user'][$userindex[$username]]['pwd_changed_at'] = microtime(true);
        }
        if (!empty($_SESSION['user_shouldChangePassword'])) {
            unset($_SESSION['user_shouldChangePassword']);
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

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

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
          <div class="content-box">
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
                      <select name="language" class="selectpicker" data-size="10" data-style="btn-default" data-width="auto">
<?php
                        foreach (get_locale_list() as $lcode => $ldesc):?>
                        <option value="<?=$lcode;?>" <?=$lcode == $pconfig['language'] ? "selected=\"selected\"" : "";?>>
                          <?=$ldesc;?>
                        </option>
<?php
                        endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_language">
                        <?= gettext('Choose a language for the web GUI.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                    </td>
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
