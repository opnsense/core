<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2010 Ermal Luçi
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

$input_errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array("authmode" => "", "username" => "", "password" => "");
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

    $authcfg = auth_get_authserver($_POST['authmode']);
    if (!$authcfg) {
        $input_errors[] = $_POST['authmode'] . " " . gettext("is not a valid authentication server");
    }

    if (empty($_POST['username']) || empty($_POST['password'])) {
        $input_errors[] = gettext("A username and password must be specified.");
    }

    if (count($input_errors) == 0) {
        $authName = $authcfg['name'];
        if ($authcfg['type'] == 'local') {
            // avoid gettext type issues on Local Database, authenticator should always be named "Local Database"
            $authName = 'Local Database';
        }

        $authFactory = new OPNsense\Auth\AuthenticationFactory();
        $authenticator = $authFactory->get($authName);
        if ($authenticator->authenticate($_POST['username'], $_POST['password'])) {
            $savemsg = gettext("User") . ": " . $_POST['username'] . " " . gettext("authenticated successfully.");
            $groups = getUserGroups($_POST['username']);
            $savemsg .= "<br />" . gettext("This user is a member of these groups") . ": <br />";
            foreach ($groups as $group) {
                $savemsg .= "{$group} ";
            }
            if (!empty($authenticator->getLastAuthProperties())) {
                $savemsg .= "<br/><br/>" . gettext("Attributes received from server") . ": <br />";
            }
            foreach ($authenticator->getLastAuthProperties() as $attr_name => $attr_value) {
                if (is_array($attr_value)) {
                    $attr_value = implode(",", $attr_value);
                }
                $attr_value = str_replace("\n", "<br/>", $attr_value);
                $savemsg .= "{$attr_name} => {$attr_value}<br/>";
            }
        } else {
            $input_errors[] = gettext("Authentication failed.");
        }
    }
}

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($savemsg)) print_info_box($savemsg);?>
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors);?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form id="iform" name="iform"  method="post">
            <table class="table table-striped opnsense_standard_table_form">
              <tbody>
                <tr>
                  <td style="width:22%"><?=gettext("Authentication Server"); ?></td>
                  <td style="width:78%">
                    <select class="selectpicker" name="authmode" id="authmode" >
<?php
                    foreach (auth_get_authserver_list() as $auth_server_id => $auth_server):?>
                      <option value="<?=$auth_server_id;?>" <?=$auth_server['name'] == $pconfig['authmode'] ? "selected=\"selected\"" : "";?>>
                        <?=htmlspecialchars($auth_server['name']);?>
                      </option>
<?php
                    endforeach; ?>
                  </select>
                  </td>
                </tr>
                <tr>
                  <td style="width:22%"><?=gettext("Username"); ?></td>
                  <td style="width:78%"><input type="text" name="username" value="<?=htmlspecialchars($pconfig['username']);?>"></td>
                </tr>
                <tr>
                  <td style="width:22%"><?=gettext("Password"); ?></td>
                  <td style="width:78%"><input type="password" name="password" value="<?=htmlspecialchars($pconfig['password']);?>"></td>
                </tr>
                <tr>
                  <td style="width:22%">&nbsp;</td>
                  <td style="width:78%"><input id="save" name="save" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Test')) ?>" /></td>
                </tr>
              </tbody>
            </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include('foot.inc');?>
