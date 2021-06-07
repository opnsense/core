<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
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
require_once("plugins.inc.d/ipsec.inc");

config_read_array('ipsec', 'mobilekey');
ipsec_mobilekey_sort();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    if (isset($_GET['id']) && is_numericint($_GET['id']) && isset($config['ipsec']['mobilekey'][$_GET['id']])) {
        // fetch record
        $id = $_GET['id'];
        $pconfig['ident'] = $config['ipsec']['mobilekey'][$id]['ident'];
        $pconfig['psk'] = $config['ipsec']['mobilekey'][$id]['pre-shared-key'];
        $pconfig['type'] = $config['ipsec']['mobilekey'][$id]['type'];
    } else {
        // init new
        $pconfig['ident'] = '';
        $pconfig['psk'] = '';
        $pconfig['type'] = 'PSK';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    // fetch record number if valid
    if (isset($_POST['id']) && is_numericint($_POST['id']) && isset($config['ipsec']['mobilekey'][$_POST['id']])) {
        $id = $_POST['id'];
    } else {
        $id = null;
    }

    /* input validation */
    $userids = array();
    foreach ($config['system']['user'] as $uid => $user) {
        $userids[$user['name']] = $uid;
    }
    if (isset($pconfig['ident']) && array_key_exists($pconfig['ident'], $userids)) {
        $input_errors[] = gettext("A user with this name already exists. Add the key to the user instead.");
    }
    unset($userids);

    $reqdfields = explode(" ", "ident psk");
    $reqdfieldsn = array(gettext("Identifier"),gettext("Pre-Shared Key"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (empty($pconfig['ident']) || preg_match("/[^a-zA-Z0-9@\.\-]/", $pconfig['ident'])) {
        $input_errors[] = gettext("The identifier contains invalid characters.");
    }

    /* make sure there are no dupes on new entries */
    $recidx = 0 ;
    foreach ($config['ipsec']['mobilekey'] as $secretent) {
        if ($secretent['ident'] == $pconfig['ident'] && ($recidx != $id || $id === null)) {
            $input_errors[] = gettext("Another entry with the same identifier already exists.");
            break;
        }
        $recidx++;
    }

    if (count($input_errors) == 0) {
        $secretent = array();
        $secretent['ident'] = $pconfig['ident'];
        $secretent['pre-shared-key'] = $pconfig['psk'];
        $secretent['type'] = $pconfig['type'];

        if ($id !== null) {
            // edit existing key
            $config['ipsec']['mobilekey'][$id] = $secretent;
            $config_write_text = gettext("Edited");
        } else {
            $config_write_text = gettext("Added");
            $config['ipsec']['mobilekey'][] = $secretent;
        }

        write_config("{$config_write_text} IPsec Pre-Shared Keys");
        mark_subsystem_dirty('ipsec');

        header(url_safe('Location: /vpn_ipsec_keys.php'));
        exit;
    }
}

$service_hook = 'strongswan';

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) {
                print_input_errors($input_errors);
}
        ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td><a id="help_for_ident" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Identifier"); ?></td>
                    <td>
                      <input name="ident" type="text" id="ident" size="30" value="<?=$pconfig['ident'];?>" />
                      <div class="hidden" data-for="help_for_ident">
                        <?=gettext("This can be either an IP address, fully qualified domain name or an email address."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Pre-Shared Key"); ?></td>
                    <td>
                      <input name="psk" type="text" id="psk" size="40" value="<?=$pconfig['psk'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?></td>
                    <td>
                      <select name="type" class="selectpicker">
                        <option value="PSK" <?=empty($pconfig['type']) || $pconfig['type'] == 'PSK' ?  "selected=\"selected\"" : ""; ?>><?=gettext("PSK");?></option>
                        <option value="EAP" <?=$pconfig['type'] == "EAP" ?  "selected=\"selected\"" : ""; ?>><?=gettext("EAP");?></option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
<?php                 if (isset($id) && isset($config['ipsec']['mobilekey'][$id])) :
?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
endif; ?>
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
