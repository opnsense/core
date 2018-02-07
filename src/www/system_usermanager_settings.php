<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2007 Bill Marquette <bill.marquette@gmail.com>
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

$save_and_test = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['authmode_fallback'] = !empty($config['system']['webgui']['authmode_fallback']) ? $config['system']['webgui']['authmode_fallback'] : "Local Database";
    foreach (array('session_timeout', 'authmode', 'password_policy_duration',
                   'enable_password_policy_constraints',
                   'password_policy_complexity', 'password_policy_length') as $fieldname) {
        if (!empty($config['system']['webgui'][$fieldname])) {
            $pconfig[$fieldname] = $config['system']['webgui'][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (!empty($pconfig['session_timeout']) && (!is_numeric($pconfig['session_timeout']) || $pconfig['session_timeout'] <= 0)) {
        $input_errors[] = gettext("Session timeout must be an integer value.");
    }

    if (count($input_errors) == 0) {
        $authsrv = auth_get_authserver($pconfig['authmode']);
        if (!empty($pconfig['savetest'])) {
            if ($authsrv['type'] == "ldap") {
                $save_and_test = true;
            } else {
                $savemsg = gettext("The test was not performed because it is supported only for ldap based backends.");
            }
        }

        foreach (array('session_timeout', 'authmode', 'authmode_fallback', 'password_policy_duration',
                       'enable_password_policy_constraints',
                       'password_policy_complexity', 'password_policy_length') as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $config['system']['webgui'][$fieldname] = $pconfig[$fieldname];
            } elseif (isset($config['system']['webgui'][$fieldname])) {
                unset($config['system']['webgui'][$fieldname]);
            }
        }


        write_config();
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<style>
    .password_policy_constraints {
        display:none;
    }
</style>
<script>
    $(document).ready(function() {
        $("#enable_password_policy_constraints").change(function(){
            if ($("#enable_password_policy_constraints").prop('checked')) {
                $(".password_policy_constraints").show();
            } else {
                $(".password_policy_constraints").hide();
            }
        });
        $("#enable_password_policy_constraints").change();
    });

</script>
<?php
if ($save_and_test):?>
<script>
    myRef = window.open('system_usermanager_settings_test.php?authserver=<?=$pconfig['authmode'];?>','mywin','left=20,top=20,width=700,height=550,toolbar=1,resizable=0');
    if (myRef==null || typeof(myRef)=='undefined') alert('<?=gettext("Popup blocker detected. Action aborted.");?>');
</script>;
<?php
endif;?>
<?php include("fbegin.inc");?>
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
          <div class="tab-content content-box col-xs-12 table-responsive">
            <form method="post">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><a id="help_for_session_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Session Timeout"); ?></td>
                  <td style="width:78%">
                    <input class="form-control" name="session_timeout" id="session_timeout" type="text" size="8" value="<?=$pconfig['session_timeout'];?>" />
                    <div class="hidden" data-for="help_for_session_timeout">
                      <?=gettext("Time in minutes to expire idle management sessions. The default is 4 hours (240 minutes).");?><br />
                      <?=gettext("Enter 0 to never expire sessions. NOTE: This is a security risk!");?><br />
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authentication Server"); ?></td>
                  <td>
                      <select name="authmode" class="selectpicker" data-style="btn-default" >
<?php
                      foreach (auth_get_authserver_list() as $auth_key => $auth_server) :?>
                        <option value="<?=$auth_key; ?>" <?=$auth_key == $pconfig['authmode'] ? "selected=\"selected\"" : "";?>>
                          <?=htmlspecialchars($auth_server['name']);?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authentication Server (fallback)"); ?></td>
                    <td>
                        <select name="authmode_fallback" class="selectpicker" data-style="btn-default" >
<?php
                        foreach (auth_get_authserver_list() as $auth_key => $auth_server) :?>
                          <option value="<?=$auth_key; ?>" <?=$auth_key == $pconfig['authmode_fallback'] ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($auth_server['name']);?>
                          </option>
 <?php
                        endforeach; ?>
                          <option value="__NO_FALLBACK__" <?= $pconfig['authmode_fallback'] == "__NO_FALLBACK__" ? "selected=\"selected\"" : "";?> >
                            <?=gettext("--No Fallback--");?>
                          </option>
                        </select>
                      </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_enable_password_policy_constraints" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Policy'); ?></td>
                    <td>
                      <input id="enable_password_policy_constraints" name="enable_password_policy_constraints" type="checkbox"  <?= empty($pconfig['enable_password_policy_constraints']) ? '' : 'checked="checked"';?> />
                      <strong><?= gettext('Enable password policy constraints') ?></strong>
                      <output class="hidden" for="help_for_enable_password_policy_constraints">
                        <?= gettext("Harden security on local accounts, for methods other then local these will usually be configured on the " .
                                            "respective provider (e.g. ldap/radius/..). ");?>
                      </output>
                    </td>
                  </tr>
                  <tr class="password_policy_constraints">
                      <td><a id="help_for_password_policy_duration" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Duration'); ?></td>
                      <td>
                          <select id="password_policy_duration" name="password_policy_duration" class="selectpicker" data-style="btn-default">
                              <option <?=empty($pconfig['password_policy_duration']) ? "selected=\"selected\"" : "";?> value="0"><?=gettext("Disable");?></option>
                              <option <?=$pconfig['password_policy_duration'] == '30' ? "selected=\"selected\"" : "";?> value="30"><?=sprintf(gettext("%d days"), "30");?></option>
                              <option <?=$pconfig['password_policy_duration'] == '90' ? "selected=\"selected\"" : "";?> value="90"><?=sprintf(gettext("%d days"), "90");?></option>
                              <option <?=$pconfig['password_policy_duration'] == '180' ? "selected=\"selected\"" : "";?> value="180"><?=sprintf(gettext("%d days"), "180");?></option>
                              <option <?=$pconfig['password_policy_duration'] == '360' ? "selected=\"selected\"" : "";?> value="360"><?=sprintf(gettext("%d days"), "360");?></option>
                          </select>
                          <output class="hidden" for="help_for_password_policy_duration">
                            <?= gettext("Password duration settings, the interval in days in which passwords stay valid. ".
                                        "When reached, the user will be forced to change his or her password before continuing.");?>
                          </output>
                      </td>
                  </tr>
                  <tr class="password_policy_constraints">
                      <td><a id="help_for_password_policy_length" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Length'); ?></td>
                      <td>
                          <select id="password_policy_length" name="password_policy_length" class="selectpicker" data-style="btn-default">
                              <option <?=empty($pconfig['password_policy_length'])  || $pconfig['password_policy_length'] == '8' ? "selected=\"selected\"" : "";?> value="8">8</option>
                              <option <?=$pconfig['password_policy_length'] == '10' ? "selected=\"selected\"" : "";?> value="10">10</option>
                              <option <?=$pconfig['password_policy_length'] == '12' ? "selected=\"selected\"" : "";?> value="12">12</option>
                              <option <?=$pconfig['password_policy_length'] == '14' ? "selected=\"selected\"" : "";?> value="14">14</option>
                              <option <?=$pconfig['password_policy_length'] == '16' ? "selected=\"selected\"" : "";?> value="16">16</option>
                          </select>
                          <output class="hidden" for="help_for_password_policy_length">
                            <?= gettext("Sets the minimum length for a password");?>
                          </output>
                      </td>
                  </tr>
                  <tr class="password_policy_constraints">
                    <td><a id="help_for_password_policy_complexity" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Complexity'); ?></td>
                    <td>
                      <input id="password_policy_complexity" name="password_policy_complexity" type="checkbox"  <?= empty($pconfig['password_policy_complexity']) ? '' : 'checked="checked"';?> />
                      <strong><?= gettext('Enable complexity requirements') ?></strong>
                      <output class="hidden" for="help_for_password_policy_complexity">
                        <?= gettext("Require passwords to meet complexity rules");?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input name="savetest" type="submit" class="btn btn-default" value="<?=gettext("Save and Test");?>" />
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
