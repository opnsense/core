<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2008 Ermal Luçi
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

$a_checkipservices = &config_read_array('checkipservices', 'service');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && is_numericint($_GET['id'])) {
        $id = $_GET['id'];
    }
    if (isset($id) && isset($a_checkipservices[$id])) {
        $pconfig['enable'] = isset($a_checkipservices[$id]['enable']);
        $pconfig['name'] = $a_checkipservices[$id]['name'];
        $pconfig['url'] = $a_checkipservices[$id]['url'];
        $pconfig['username'] = $a_checkipservices[$id]['username'];
        $pconfig['password'] = $a_checkipservices[$id]['password'];
        $pconfig['verifysslpeer'] = isset($a_checkipservices[$id]['verifysslpeer']);
        $pconfig['descr'] = $a_checkipservices[$id]['descr'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && is_numericint($_POST['id'])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = array();
    $reqdfieldsn = array();
    $reqdfields = array_merge($reqdfields, explode(" ", "name url"));
    $reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Name"), gettext("URL")));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (($_POST['name'] && !is_validaliasname($_POST['name']))) {
        $input_errors[] = gettext("The Check IP Service name contains invalid characters.");
    }
    if (($_POST['url'] && !is_URL($_POST['url']))) {
        $input_errors[] = gettext("The Check IP Service URL is not valid.");
    }

    if (count($input_errors) == 0) {
        $checkip = array();
        $checkip['enable'] = $_POST['enable'] ? true : false;
        $checkip['name'] = $_POST['name'];
        $checkip['url'] = $_POST['url'];
        $checkip['username'] = $_POST['username'];
        $checkip['password'] = $_POST['passwordfld'];
        $checkip['verifysslpeer'] = $_POST['verifysslpeer'] ? true : false;
        $checkip['descr'] = $_POST['descr'];

        if (isset($id) && $a_checkipservices[$id]) {
            $a_checkipservices[$id] = $checkip;
        } else {
            $a_checkipservices[] = $checkip;
        }

        write_config(sprintf(gettext("New/edited check IP service: %s"), $checkip['name']));

        header(url_safe('Location: /services_checkip.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_checkipservices);

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main"><h2 style="display:none">Check IP Service Add/Edit</h2>
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12"><h3 style="display:none">Input Form</h3>
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">

                  <tr>
                    <td style="width:22%"><strong><?= gettext("Check IP Service") ?></strong></td>
                    <td style="width:78%" class="text-right">
                      <small><?= gettext("full help") ?> </small>
                      <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>

                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Enable") ?></td>
                    <td>
                      <label for="enable">
                        <input name="enable" type="checkbox" id="enable" value="<?= gettext("yes") ?>" <?= empty($pconfig['enable']) ? '' : 'checked="checked"' ?> />
                        <?= gettext('Enable this service.');?>
                      </label>
                    </td>
                  </tr>

                  <tr>
                    <td><a id="help_for_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Name") ?></td>
                    <td>
                      <input name="name" type="text" id="name" value="<?= $pconfig['name'] ?>" />
                      <output class="hidden" for="help_for_name">
                        <?= gettext('The service name may only consist of the characters "a-z, A-Z, 0-9 and _".');?>
                      </output>
                    </td>
                  </tr>

                  <tr>
                    <td><a id="help_for_url" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("URL") ?></td>
                    <td>
                      <input name="url" type="text" id="url" value="<?= $pconfig['url'] ?>" />
                      <output class="hidden" for="help_for_url">
                        <?= gettext('The service URL to use.');?>
                      </output>
                    </td>
                  </tr>

                  <tr>
                    <td><a id="help_for_username" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("User name") ?></td>
                    <td>
                      <input name="username" type="text" id="username" value="<?= $pconfig['username'] ?>" />
                      <output class="hidden" for="help_for_username">
                        <?= gettext('The user name to authenticate with the service.');?>
                      </output>
                    </td>
                  </tr>

                  <tr>
                    <td><a id="help_for_password" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Password") ?></td>
                    <td>
                      <input name="passwordfld" type="password" id="passwordfld" value="<?= $pconfig['password'] ?>" />
                      <output class="hidden" for="help_for_password">
                        <?= gettext('The password to authenticate with the service.');?>
                      </output>
                    </td>
                  </tr>

                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Verify SSL Peer") ?></td>
                    <td>
                      <label for="verifysslpeer">
                        <input name="verifysslpeer" type="checkbox" id="verifysslpeer" value="<?= gettext("yes") ?>" <?= empty($pconfig['verifysslpeer']) ? '' : 'checked="checked"' ?> />
                        <?= gettext('Require SSL peer verification.');?>
                      </label>
                    </td>
                  </tr>

                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Description") ?></td>
                    <td>
                      <input name="descr" type="text" id="descr" value="<?= $pconfig['descr'] ?>" />
                      <output class="hidden" for="help_for_descr">
                        <?= gettext('A description for administrative reference (not parsed).');?>
                      </output>
                    </td>
                  </tr>

                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <button name="submit" type="submit" class="btn btn-primary" value="save"><?= gettext('Save') ?></button>
<?php
                      if (isset($id) && $a_checkipservices[$id]): ?>
                        <input name="id" type="hidden" value="<?= $id ?>" />
<?php
                      endif; ?>
                      <a href="services_checkip.php" class="btn btn-default"><?= gettext('Cancel') ?></a>
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
<?php include("foot.inc"); ?>
