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
    $pconfig['session_timeout'] = $config['system']['webgui']['session_timeout'];
    $pconfig['authmode'] = $config['system']['webgui']['authmode'];
    $pconfig['authmode_fallback'] = !empty($config['system']['webgui']['authmode_fallback']) ? $config['system']['webgui']['authmode_fallback'] : "Local Database";
    $pconfig['backend'] = $config['system']['webgui']['backend'];
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

        if (!empty($pconfig['session_timeout'])) {
            $config['system']['webgui']['session_timeout'] = intval($pconfig['session_timeout']);
        } elseif (isset($config['system']['webgui']['session_timeout'])) {
            unset($config['system']['webgui']['session_timeout']);
        }

        if (!empty($pconfig['authmode'])) {
            $config['system']['webgui']['authmode'] = $pconfig['authmode'];
        } elseif (isset($config['system']['webgui']['authmode'])) {
            unset($config['system']['webgui']['authmode']);
        }

        if (!empty($pconfig['authmode_fallback'])) {
            $config['system']['webgui']['authmode_fallback'] = $pconfig['authmode_fallback'];
        } elseif (isset($config['system']['webgui']['authmode_fallback'])) {
            unset($config['system']['webgui']['authmode_fallback']);
        }

        write_config();
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>

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
                    <output class="hidden" for="help_for_session_timeout">
                      <?=gettext("Time in minutes to expire idle management sessions. The default is 4 hours (240 minutes).");?><br />
                      <?=gettext("Enter 0 to never expire sessions. NOTE: This is a security risk!");?><br />
                    </output>
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
