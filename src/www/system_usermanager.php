<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2005 Paul Taylor <paultaylor@winn-dixie.com>
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

require_once 'guiconfig.inc';
require_once 'system.inc';
require_once 'base32/Base32.php';

function get_user_privdesc(& $user)
{
    global $priv_list;

    $privs = array();

    if (!isset($user['priv']) || !is_array($user['priv'])) {
        $user_privs = array();
    } else {
        $user_privs = $user['priv'];
    }

    $names = local_user_get_groups($user);

    foreach ($names as $name) {
        $group = getGroupEntry($name);
        if (isset($group['priv']) && is_array($group['priv'])) {
          foreach ($group['priv'] as $pname) {
              if (in_array($pname, $user_privs)) {
                  continue;
              }
              if (empty($priv_list[$pname])) {
                  continue;
              }
              $priv = $priv_list[$pname];
              $priv['group'] = $group['name'];
              $priv['id'] = $pname;
              $privs[] = $priv;
          }
        }
    }

    foreach ($user_privs as $pname) {
        if (!empty($priv_list[$pname])) {
            $priv_list[$pname]['id'] = $pname;
            $privs[] = $priv_list[$pname];
        }
    }

    legacy_html_escape_form_data($privs);
    return $privs;
}

$a_user = &config_read_array('system', 'user');

// reset errors and action
$act = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // process get type actions
    if (isset($_GET['userid']) && isset($a_user[$_GET['userid']])) {
        $id = $_GET['userid'];
    }
    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    }
    if (isset($_GET['savemsg'])) {
        $savemsg = htmlspecialchars($_GET['savemsg']);
    }
    if ($act == "expcert" && isset($id)) {
        // export certificate
        $cert = &lookup_cert($a_user[$id]['cert'][$_GET['certid']]);

        $exp_name = urlencode("{$a_user[$id]['name']}-{$cert['descr']}.crt");
        $exp_data = base64_decode($cert['crt']);
        $exp_size = strlen($exp_data);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename={$exp_name}");
        header("Content-Length: $exp_size");
        echo $exp_data;
        exit;
    } elseif ($act == "expckey" && isset($id)) {
        // export private key
        $cert = &lookup_cert($a_user[$id]['cert'][$_GET['certid']]);
        $exp_name = urlencode("{$a_user[$id]['name']}-{$cert['descr']}.key");
        $exp_data = base64_decode($cert['prv']);
        $exp_size = strlen($exp_data);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename={$exp_name}");
        header("Content-Length: $exp_size");
        echo $exp_data;
        exit;
    } elseif ($act == 'new' || $act == 'edit') {
        // edit user, load or init data
        $fieldnames = array('user_dn', 'descr', 'expires', 'scope', 'uid', 'priv', 'ipsecpsk',
                            'otp_seed', 'email', 'shell', 'comment', 'landing_page');
        if (isset($id)) {
            if (isset($a_user[$id]['authorizedkeys'])) {
                $pconfig['authorizedkeys'] = base64_decode($a_user[$id]['authorizedkeys']);
            }
            if (isset($a_user[$id]['name'])) {
                $pconfig['usernamefld'] = $a_user[$id]['name'];
            }
            $pconfig['groups'] = local_user_get_groups($a_user[$id]);
            $pconfig['disabled'] = isset($a_user[$id]['disabled']);
            foreach ($fieldnames as $fieldname) {
                if (isset($a_user[$id][$fieldname])) {
                    $pconfig[$fieldname] = $a_user[$id][$fieldname];
                } else {
                    $pconfig[$fieldname] = null;
                }
            }

            foreach (get_locale_list() as $lcode => $ldesc) {
                if ($a_user[$id]['language'] == $lcode) {
                    $pconfig['language'] = $ldesc;
                    break;
                }
            }
        } else {
            // set defaults
            $pconfig['groups'] = null;
            $pconfig['disabled'] = false;
            $pconfig['scope'] = "user";
            $pconfig['usernamefld'] = null;
            foreach ($fieldnames as $fieldname) {
                if (!isset($pconfig[$fieldname])) {
                    $pconfig[$fieldname] = null;
                }
            }
        }
    }
    if (empty($pconfig['language'])) {
        $pconfig['language'] = gettext('Default');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // process post type requests
    if (isset($_POST['userid']) && isset($a_user[$_POST['userid']])) {
        $id = $_POST['userid'];
    }
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    }
    $pconfig = $_POST;
    $input_errors = array();

    $user = getUserEntry($_SESSION['Username']);
    if (userHasPrivilege($user, 'user-config-readonly')) {
        $input_errors[] = gettext('You do not have the permission to perform this action.');
    } elseif ($act == "deluser" && isset($id)) {
        // drop user
        if ($_SESSION['Username'] === $a_user[$id]['name']) {
            $input_errors[] = gettext('You cannot delete yourself.');
        } else {
            local_user_del($a_user[$id]);
            $userdeleted = $a_user[$id]['name'];
            unset($a_user[$id]);
            write_config();
            $savemsg = sprintf(gettext('The user "%s" was successfully removed.'), $userdeleted);
            header(url_safe('Location: /system_usermanager.php?savemsg=%s', array($savemsg)));
            exit;
        }
    } elseif ($act == "delcert" && isset($id)) {
        // remove certificate association
        $certdeleted = lookup_cert($a_user[$id]['cert'][$pconfig['certid']]);
        $certdeleted = $certdeleted['descr'];
        unset($a_user[$id]['cert'][$pconfig['certid']]);
        write_config();
        $savemsg = sprintf(gettext('The certificate association "%s" was successfully removed.'), $certdeleted);
        header(url_safe('Location: /system_usermanager.php?savemsg=%s&act=edit&userid=%d', array($savemsg, $id)));
        exit;
    } elseif ($act == "newApiKey" && isset($id)) {
        // every action is using the sequence of the user, to keep it understandable, we will use
        // the same strategy here (although we need a username to work with)
        //
        // the client side is (jquery) generates the actual download file.
        $username = $a_user[$id]['name'];
        $authFactory = new \OPNsense\Auth\AuthenticationFactory();
        $authenticator = $authFactory->get("Local API");
        $keyData = $authenticator->createKey($username);
        if ($keyData != null) {
            echo json_encode($keyData);
        }
        exit;
    } elseif ($act =='delApiKey' && isset($id)) {
        $username = $a_user[$id]['name'];
        if (!empty($pconfig['api_delete'])) {
            $authFactory = new \OPNsense\Auth\AuthenticationFactory();
            $authenticator = $authFactory->get("Local API");
            $authenticator->dropKey($username, $pconfig['api_delete']);
            $savemsg = sprintf(gettext('The API key "%s" was successfully removed.'), $pconfig['api_delete']);
        } else {
            $savemsg = gettext('No API key found');
        }
        // redirect
        header(url_safe('Location: /system_usermanager.php?savemsg=%s&act=edit&userid=%d', array($savemsg, $id)));
        exit;
    } elseif (isset($pconfig['save']) || isset($pconfig['save_close'])) {
        $reqdfields = explode(' ', 'usernamefld');
        $reqdfieldsn = array(gettext('Username'));

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (preg_match("/[^a-zA-Z0-9\.\-_]/", $pconfig['usernamefld'])) {
            $input_errors[] = gettext("The username contains invalid characters.");
        }

        if (strlen($pconfig['usernamefld']) > 32) {
            $input_errors[] = gettext("The username is longer than 32 characters.");
        }

        if (!empty($pconfig['passwordfld1']) || !empty($pconfig['passwordfld2'])) {
            if ($pconfig['passwordfld1'] != $pconfig['passwordfld2']) {
                $input_errors[] = gettext('The passwords do not match.');
            } elseif (empty($pconfig['gen_new_password'])) {
                // check against local password policy
                $authenticator = get_authenticator();
                $input_errors = array_merge(
                    $input_errors,
                    $authenticator->checkPolicy($pconfig['usernamefld'], null, $pconfig['passwordfld1'])
                );
            } else {
                $input_errors[] = gettext('Cannot set random password due to explicit input.');
            }
        }

        if (!empty($pconfig['disabled']) && $_SESSION['Username'] === $a_user[$id]['name']) {
            $input_errors[] = gettext('You cannot disable yourself.');
        }

        if (isset($id)) {
            $oldusername = $a_user[$id]['name'];
        } else {
            $oldusername = '';

            if (empty($pconfig['passwordfld1']) && empty($pconfig['gen_new_password'])) {
                $input_errors[] = gettext('A password is required.');
            }
        }

        /* make sure this user name is unique */
        if (count($input_errors) == 0) {
            foreach ($a_user as $userent) {
                if ($userent['name'] == $pconfig['usernamefld'] && $oldusername != $pconfig['usernamefld']) {
                    $input_errors[] = gettext("Another entry with the same username already exists.");
                    break;
                }
            }
        }

        /* also make sure it is not reserved */
        if (count($input_errors) == 0) {
            $system_users = explode("\n", file_get_contents("/etc/passwd"));
            foreach ($system_users as $s_user) {
                $ent = explode(":", $s_user);
                if ($ent[0] == $pconfig['usernamefld'] && $oldusername != $pconfig['usernamefld']) {
                    $input_errors[] = gettext("That username is reserved by the system.");
                    break;
                }
            }
        }

        /*
         * Check for a valid expirationdate if one is set at all (valid means,
         * DateTime puts out a time stamp so any DateTime compatible time
         * format may be used. to keep it simple for the enduser, we only
         * claim to accept MM/DD/YYYY as inputs. Advanced users may use inputs
         * like "+1 day", which will be converted to MM/DD/YYYY based on "now".
         * Otherwhise such an entry would lead to an invalid expiration data.
         */
        if (!empty($pconfig['expires'])) {
            try {
                $expdate = new DateTime($pconfig['expires']);
                //convert from any DateTime compatible date to MM/DD/YYYY
                $pconfig['expires'] = $expdate->format("m/d/Y");
            } catch (Exception $ex) {
                $input_errors[] = gettext("Invalid expiration date format; use MM/DD/YYYY instead.");
            }
        }

        if (!empty($pconfig['name'])) {
            $ca = lookup_ca($pconfig['caref']);
            if (!$ca) {
                $input_errors[] = gettext("Invalid internal Certificate Authority") . "\n";
            }
        }

        if (!empty($pconfig['shell']) && !in_array($pconfig['shell'], auth_get_shells(isset($id) ? $a_user[$id]['uid'] : $config['system']['nextuid']))) {
            $input_errors[] = gettext('Invalid login shell provided.');
        }

        if (!count($input_errors)) {
            $userent = array();

            if (isset($id)) {
                $userent = $a_user[$id];
                /* the user name was modified */
                if ($pconfig['usernamefld'] != $pconfig['oldusername']) {
                    local_user_del($userent);
                }
            }

            /* the user password was modified */
            if (!empty($pconfig['passwordfld1'])) {
                local_user_set_password($userent, $pconfig['passwordfld1']);
            } elseif (!empty($pconfig['gen_new_password'])) {
                local_user_set_password($userent);
            }

            isset($pconfig['scope']) ? $userent['scope'] = $pconfig['scope'] : $userent['scope'] = "system";

            $userent['name'] = $pconfig['usernamefld'];
            $userent['descr'] = $pconfig['descr'];
            $userent['expires'] = $pconfig['expires'];
            $userent['authorizedkeys'] = base64_encode(trim($pconfig['authorizedkeys']));
            $userent['ipsecpsk'] = $pconfig['ipsecpsk'];
            if (!empty($pconfig['gen_otp_seed'])) {
                // generate 160bit base32 encoded secret
                $userent['otp_seed'] = Base32\Base32::encode(openssl_random_pseudo_bytes(20));
            } else {
                $userent['otp_seed'] = trim($pconfig['otp_seed']);
            }

            if (!empty($pconfig['disabled'])) {
                $userent['disabled'] = true;
            } elseif (isset($userent['disabled'])) {
                unset($userent['disabled']);
            }

            if (!empty($pconfig['email'])) {
                $userent['email'] = $pconfig['email'];
            } elseif (isset($userent['email'])) {
                unset($userent['email']);
            }

            if (!empty($pconfig['comment'])) {
                $userent['comment'] = $pconfig['comment'];
            } elseif (isset($userent['comment'])) {
                unset($userent['comment']);
            }
            if (!empty($pconfig['landing_page'])) {
                $userent['landing_page'] = $pconfig['landing_page'];
            } elseif (isset($userent['landing_page'])) {
                unset($userent['landing_page']);
            }

            if (!empty($pconfig['shell'])) {
                $userent['shell'] = $pconfig['shell'];
            } elseif (isset($userent['shell'])) {
                unset($userent['shell']);
            }

            if (isset($id)) {
                $a_user[$id] = $userent;
            } else {
                $userent['uid'] = $config['system']['nextuid']++;
                $a_user[] = $userent;
            }

            local_user_set_groups($userent, $pconfig['groups']);
            local_user_set($userent);
            write_config();
            // XXX: signal backend that the user has changed.
            configdp_run('auth user changed', [$userent['name']]);

            if (!empty($pconfig['chkNewCert'])) {
                header(url_safe('Location: /system_certmanager.php?act=new&userid=%d', array(isset($id) ? $id : count($a_user) - 1)));
            } elseif (isset($pconfig['save_close'])) {
                header(url_safe('Location: /system_usermanager.php?savemsg=%s', array(get_std_save_message(true))));
            } else {
                header(url_safe('Location: /system_usermanager.php?act=edit&userid=%d&savemsg=%s', array(isset($id) ? $id : count($a_user) - 1, get_std_save_message(true))));
            }
            exit;
        }
    } else {
        header(url_safe('Location: /system_usermanager.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_user);

include("head.inc");

$main_buttons = array();
if (!isset($_GET['act'])) {
    $main_buttons[] = array('label' => gettext('Add'), 'href' => 'system_usermanager.php?act=new');
}

?>
<script src="<?= cache_safe('/ui/js/jquery.qrcode.js') ?>"></script>
<script src="<?= cache_safe('/ui/js/qrcode.js') ?>"></script>

<body>

<?php include("fbegin.inc"); ?>

<script>
$( document ).ready(function() {
    // unhide otp QR code if found
    $('#otp_unhide').click(function () {
        $(this).hide();
        $('#otp_qrcode').show();
    });
    // remove certificate association
    $(".act-del-cert").click(function(event){
      var certid = $(this).data('certid');
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= html_safe(gettext('Certificate')) ?>",
          message: "<?= html_safe(gettext('Do you really want to remove this certificate association?')) .'\n'. html_safe(gettext('(Certificate will not be deleted)')) ?>",
          buttons: [{
                  label: "<?= html_safe(gettext('No')) ?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= html_safe(gettext('Yes')) ?>",
                    action: function(dialogRef) {
                      $("#certid").val(certid);
                      $("#act").val("delcert");
                      $("#iform").submit();
                  }
          }]
      });
    });

    // remove user
    $(".act-del-user").click(function(event){
      var userid = $(this).data('userid');
      var username = $(this).data('username');
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= html_safe(gettext('User')) ?>",
          message: "<?= html_safe(gettext('Do you really want to delete this user?')) ?>" + "<br/>("+username+")",
          buttons: [{
                  label: "<?= html_safe(gettext('No')) ?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= html_safe(gettext('Yes')) ?>",
                    action: function(dialogRef) {
                      $("#userid").val(userid);
                      $("#act2").val("deluser");
                      $("#iform2").submit();
                  }
          }]
      });
    });

    // expand ssh key section on click
    $("#authorizedkeys").click(function(){
        $(this).attr('rows', '10');
    });

    // import ldap users
    $("#import_ldap_users").click(function(event){
      event.preventDefault();
      const url="system_usermanager_import_ldap.php";
      var oWin = window.open(url,"OPNsense","width=620,height=400,top=150,left=150,scrollbars=yes");
      if (oWin==null || typeof(oWin)=="undefined") {
        alert("<?= html_safe(gettext('Popup blocker detected. Action aborted.')) ?>");
      }
    });


    // generate a new API key for this user
    $("#newApiKey").click(function(event){
        event.preventDefault();
        $.post(window.location, {act: 'newApiKey', userid: $("#userid").val() }, function(data) {
            if (data['key'] != undefined) {
                // only generate a key file if there's data
                const output_data = 'key='+data['key'] +'\n' + 'secret='+data['secret'] +'\n';
                // create link, click and send to client
                $('<a></a>')
                        .attr('id','downloadFile')
                        .attr('href','data:text/plain;charset=utf8,' + encodeURIComponent(output_data))
                        .attr('download','apikey.txt')
                        .appendTo('body');

                $('#downloadFile').ready(function() {
                    $('#downloadFile').get(0).click();
                });
                // reload form
                location.reload();
            }
        },'json');
    });

    // delete API key
    $(".act-del-api-key").click(function(event){
        event.preventDefault();
        var apiKey = $(this).data('key');
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= html_safe(gettext('User')) ?>",
            message: '<?= html_safe(gettext('Do you really want to delete this API key?')) ?>' + '<br/><small>('+apiKey.substring(0,40)+"...)</small>",
            buttons: [{
                    label: "<?= html_safe(gettext('No')) ?>",
                    action: function(dialogRef) {
                      dialogRef.close();
                    }}, {
                      label: "<?= html_safe(gettext('Yes')) ?>",
                      action: function(dialogRef) {
                        $("#act").val("delApiKey");
                        $("#api_delete").val(apiKey);
                        $("#iform").submit();
                    }
            }]
        });
    });

    $('.datepicker').datepicker();

    $("#add_groups").click(function(){
        $("#groups").append($("#notgroups option:selected"));
        $("#notgroups option:selected").remove();
        $("#groups option:selected").prop('selected', false);
    });
    $("#remove_groups").click(function(){
        $("#notgroups").append($("#groups option:selected"));
        $("#groups option:selected").remove();
        $("#notgroups option:selected").prop('selected', false);
    });
    $("#save").click(function(){
        $("#groups > option").prop('selected', true);
        $("#notgroups > option").prop('selected', false);
    });
    $("#save_close").click(function(){
        $("#groups > option").prop('selected', true);
        $("#notgroups > option").prop('selected', false);
    });
});
</script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors)) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12 table-responsive">
<?php
            if ($act == "new" || $act == "edit" ) :?>
              <form method="post" name="iform" id="iform">
                <input type="hidden" id="act" name="act" value="<?=$act;?>" />
                <input type="hidden" id="userid" name="userid" value="<?=(isset($id) ? $id : '');?>" />
                <input type="hidden" id="priv_delete" name="priv_delete" value="" /> <!-- delete priv action -->
                <input type="hidden" id="api_delete" name="api_delete" value="" /> <!-- delete api ke action -->
                <input type="hidden" id="certid" name="certid" value="" /> <!-- remove cert association action -->
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><?=gettext("Defined by");?></td>
                    <td>
                      <strong><?=strtoupper($pconfig['scope']);?></strong>
                      <input name="scope" type="hidden" value="<?=$pconfig['scope']?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Disabled");?></td>
                    <td>
                      <input name="disabled" type="checkbox" id="disabled" <?= $pconfig['disabled'] ? "checked=\"checked\"" : "" ?> />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Username");?></td>
                    <td>
                      <input name="usernamefld" type="text" class="formfld user" id="usernamefld" size="20" maxlength="32" value="<?=$pconfig['usernamefld'];?>" <?= $pconfig['scope'] == "system" || !empty($pconfig['user_dn']) ? "readonly=\"readonly\"" : "";?> />
                      <input name="oldusername" type="hidden" id="oldusername" value="<?=$pconfig['usernamefld'];?>" />
                    </td>
                  </tr>
<?php
                  if (!empty($pconfig['user_dn'])):?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("User distinguished name");?></td>
                    <td>
                      <input name="user_dn" type="text" class="formfld user" id="user_dn" size="20" value="<?=$pconfig['user_dn'];?>" readonly="readonly" />
                    </td>
                  </tr>
<?php
                  endif;?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Password");?></td>
                    <td>
                      <input name="passwordfld1" type="password" class="formfld pwd" id="passwordfld1" size="20" value="" /><br/>
                      <input name="passwordfld2" type="password" class="formfld pwd" id="passwordfld2" size="20" value="" />
                      <small><?= gettext("(confirmation)"); ?></small><br/><br/>
                      <input type="checkbox" name="gen_new_password" <?= !empty($pconfig['gen_new_password']) ? 'checked="checked"' : '' ?>/>
                      <small><?=gettext('Generate a scrambled password to prevent local database logins for this user.') ?></small>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_fullname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Full name");?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" <?= $pconfig['scope'] == "system" ? "readonly=\"readonly\"" : "";?> />
                      <div class="hidden" data-for="help_for_fullname">
                        <?=gettext("User's full name, for your own information only");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_email" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("E-Mail");?></td>
                    <td>
                      <input name="email" type="text" value="<?= $pconfig['email'] ?>" />
                      <div class="hidden" data-for="help_for_email">
                        <?= gettext('User\'s e-mail address, for your own information only') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_comment" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Comment");?></td>
                    <td>
                      <textarea name="comment" id="comment" class="form-control" cols="65" rows="3"><?= $pconfig['comment'] ?></textarea>
                      <div class="hidden" data-for="help_for_comment">
                        <?= gettext('User comment, for your own information only') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_landing_page" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Preferred landing page");?></td>
                    <td>
                      <input name="landing_page" type="text" value="<?=$pconfig['landing_page'];?>">
                      <div class="hidden" data-for="help_for_landing_page">
                        <?= gettext('Preferred landing page after login or authentication failure') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Language");?></td>
                    <td>
                      <input name="language" type="hidden" value="<?= $pconfig['language'] ?>" />
                      <?= $pconfig['language'] ?>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Login shell') ?></td>
                    <td>
                      <select name="shell" class="selectpicker" data-style="btn-default">
<?php
                      foreach (auth_get_shells(isset($id) ? $a_user[$id]['uid'] : $config['system']['nextuid']) as $shell_key => $shell_value) :?>
                        <option value="<?= html_safe($shell_key) ?>" <?= $pconfig['shell'] == $shell_key ? 'selected="selected"' : '' ?>><?= html_safe($shell_value) ?></option>
<?php
                      endforeach;?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_expires" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Expiration date"); ?></td>
                    <td>
                      <input name="expires" type="text" id="expires" class="datepicker" data-date-format="mm/dd/yyyy" value="<?=$pconfig['expires'];?>" />
                      <div class="hidden" data-for="help_for_expires">
                          <?=gettext("Leave blank if the account shouldn't expire, otherwise enter the expiration date in the following format: mm/dd/yyyy"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_groups" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Group Memberships");?></td>
                    <td>
                      <table class="table" style="width:100%; border:0;">
                        <thead>
                          <tr>
                            <th><?=gettext("Not Member Of"); ?></th>
                            <th>&nbsp;</th>
                            <th><?=gettext("Member Of"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>
                              <select size="10" name="notgroups[]" id="notgroups" onchange="clear_selected('groups')" multiple="multiple">
<?php
                              foreach ($config['system']['group'] as $group) :
                                if (!empty($pconfig['groups']) && in_array($group['name'], $pconfig['groups'])) {
                                  continue;
                                }
?>
                                <option value="<?=$group['name'];?>">
                                    <?=htmlspecialchars($group['name']);?>
                                </option>
<?php
                              endforeach;?>
                              </select>
                            </td>
                            <td class="text-center">
                              <br />
                              <a id="add_groups" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("Add groups"); ?>">
                                  <span class="fa fa-arrow-right fa-fw"></span>
                              </a>
                              <br /><br />
                              <a id="remove_groups" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("Remove groups"); ?>">
                                  <span class="fa fa-arrow-left fa-fw"></span>
                              </a>
                            </td>
                            <td>
                              <select size="10" name="groups[]" id="groups" onchange="clear_selected('notgroups')" multiple="multiple">
<?php
                              if (!empty($pconfig['groups'])) :
                                foreach ($config['system']['group'] as $group) :
                                  if (!in_array($group['name'], $pconfig['groups'])) {
                                    continue;
                                  }
?>
                                <option value="<?=$group['name'];?>">
                                    <?=htmlspecialchars($group['name']);?>
                                </option>
<?php
                                endforeach;
                            endif;
?>
                            </select>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_groups">
                          <?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
                      </div>
                    </td>
                  </tr>
<?php
                  if ($pconfig['uid'] != "") :?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Effective Privileges");?></td>
                    <td>
                      <table class="table table-hover table-condensed">
                        <tr>
                          <td><b><?=gettext("Inherited from");?></b></td>
                          <td><b><?=gettext("Type");?></b></td>
                          <td><b><?=gettext("Name");?></b></td>
                        </tr>
<?php
                        foreach (get_user_privdesc($a_user[$id]) as $priv) :?>
                        <tr>
                          <td><?=!empty($priv['group']) ? $priv['group'] : ''?></td>
                          <td>
<?php
                             switch (substr($priv['id'], 0, 5)) {
                                 case 'page-':
                                     echo gettext('GUI');
                                     break;
                                 case 'user-':
                                     echo gettext('User');
                                     break;
                                 default:
                                     echo gettext('N/A');
                                     break;
                             } ?>
                          </td>
                          <td><?=$priv['name']?></td>
                        </tr>
<?php
                        endforeach;?>
                        <tr>
                          <td colspan="3">
                              <a href="system_usermanager_addprivs.php?userid=<?=$id?>" class="btn btn-xs btn-default"
                                  title="<?=gettext("edit privileges");?>" data-toggle="tooltip">
                                <span class="fa fa-pencil fa-fw"></span>
                              </a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("User Certificates");?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td><strong><?=gettext("Name");?></strong></td>
                          <td><strong><?=gettext("CA");?></strong></td>
                          <td><strong><?=gettext("Valid From");?></strong></td>
                          <td><strong><?=gettext("Valid To");?></strong></td>
                          <td></td>
                        </tr>
<?php
                        $new_cert_link_suffix = "";
                        if (isset($a_user[$id]['cert']) && is_array($a_user[$id]['cert'])) :
                          $i = 0;
                          foreach ($a_user[$id]['cert'] as $certref) :
                            $cert = lookup_cert($certref);
                            $ca = lookup_ca($cert['caref']);
                            list($cert_validfrom, $cert_validto) = cert_get_dates($cert['crt']);
                            $new_cert_link_suffix = "&amp;method=internal&amp;caref={$cert['caref']}";
?>
                        <tr>
                          <td><?=htmlspecialchars($cert['descr']);?>
                              <?=is_cert_revoked($cert) ? "(<b>".gettext('Revoked')."</b>)" : "";?>
                          </td>
                          <td>
                            <?=htmlspecialchars($ca['descr']);?>
                          </td>
                          <td><?=$cert_validfrom;?></td>
                          <td><?=$cert_validto;?></td>
                          <td>
                            <a href="system_usermanager.php?act=expckey&amp;certid=<?=$i?>&amp;userid=<?=$id?>"
                                class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export private key");?>">
                              <span class="fa fa-arrow-down fa-fw"></span>
                            </a>
                            <a href="system_usermanager.php?act=expcert&amp;certid=<?=$i?>&amp;userid=<?=$id?>"
                                class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("export certificate");?>">
                              <span class="fa fa-arrow-down fa-fw"></span>
                            </a>
                            <button type="submit" data-certid="<?=$i;?>" class="btn btn-default btn-xs act-del-cert"
                                title="<?=gettext("unlink certificate");?>" data-toggle="tooltip">
                              <span class="fa fa-trash fa-fw"></span>
                            </button>
                          </td>
                        </tr>
<?php
                        $i++;
                            endforeach;
                        endif;?>
                        <tr>
                          <td colspan="5">
                            <a href="system_certmanager.php?act=new&amp;userid=<?=$id?><?=$new_cert_link_suffix;?>" class="btn btn-default btn-xs"
                                title="<?=gettext("create or link user certificate");?>" data-toggle="tooltip">
                              <span class="fa fa-plus fa-fw"></span>
                            </a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_apikeys" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("API keys");?> </td>
                      <td>
                          <table class="table table-condensed">
                              <thead>
                                  <tr>
                                    <th>
                                        <?=gettext('key');?>
                                    </th>
                                    <th>
                                    </th>
                                  </tr>
                              </thead>
                              <tbody>
<?php
                                  if (isset($a_user[$id]['apikeys']['item'])):
                                    foreach ($a_user[$id]['apikeys']['item'] as $userApiKey):?>
                                  <tr>
                                      <td>
                                        <small>
                                          <?php // listtags always changes our key item to an array.. ?>
                                          <?php // don't want to change "key" to something less sane. ?>
                                          <?=$userApiKey['key'][0];?>
                                        </small>
                                      </td>
                                      <td>
                                        <button data-key="<?=$userApiKey['key'][0];?>" type="button" class="btn btn-default btn-xs act-del-api-key"
                                            title="<?=gettext("delete API key");?>" data-toggle="tooltip">
                                          <span class="fa fa-trash fa-fw"></span>
                                        </button>
                                      </td>
                                  </tr>
<?php
                                    endforeach;
                                  endif;?>
                              </tbody>
                              <tfoot>
                                  <tr>
                                    <td colspan="2">
                                      <button type="button" class="btn btn-default btn-xs" id="newApiKey"
                                          title="<?=gettext('Create API key');?>" data-toggle="tooltip">
                                        <span class="fa fa-plus fa-fw"></span>
                                      </button>
                                    </td>
                                  </tr>
                              </tfoot>
                          </table>
                          <div class="hidden" data-for="help_for_apikeys">
                              <hr/>
                              <?=gettext('Manage API keys here for machine to machine interaction using this user\'s credentials.');?>
                          </div>
                      </td>
                  </tr>
<?php
                else :?>
                  <tr id="usercertchck">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate");?></td>
                    <td>
                      <input type="checkbox" id="chkNewCert" name="chkNewCert" /> <?= gettext('Click to create a user certificate.') ?>
                    </td>
                  </tr>
<?php
                endif;?>
                  <tr>
                    <td><a id="help_for_otp_seed" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('OTP seed') ?></td>
                    <td>
                      <input name="otp_seed" type="text" value="<?=$pconfig['otp_seed'];?>"/>
                      <input type="checkbox" name="gen_otp_seed"/>
                      <small><?= gettext('Generate new secret (160 bit)') ?></small>
                      <div class="hidden" data-for="help_for_otp_seed">
                        <?=gettext("OTP (base32) seed to use when a one time password authenticator is used");?><br/>
                      </div>
                    </td>
                  </tr>
<?php
                        if (!empty($pconfig['otp_seed'])):
                            // construct google url, using token, username and this machines hostname
                            $otp_url = "otpauth://totp/";
                            $otp_url .= $pconfig['usernamefld']."@".htmlspecialchars($config['system']['hostname'])."?secret=";
                            $otp_url .= $pconfig['otp_seed'];
                        ?>
                  <tr>
                    <td><a id="help_for_otp_code" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('OTP QR code') ?></td>
                    <td>
                      <label class="btn btn-primary" id="otp_unhide"><?= gettext('Click to unhide') ?></label>
                      <div style="display:none;" id="otp_qrcode"></div>
                      <script>
                        $('#otp_qrcode').qrcode('<?= $otp_url ?>');
                      </script>
                      <div class="hidden" data-for="help_for_otp_code">
                        <?= gettext('Scan this QR code for easy setup with external apps.') ?>
                      </div>
                    </td>
                  </tr>
<?php
                        endif;?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authorized keys");?></td>
                    <td>
                      <textarea name="authorizedkeys" id="authorizedkeys" style="max-width: inherit;" class="form-control" cols="65" rows="1" placeholder="<?=gettext("Paste an authorized keys file here.");?>" wrap='off'><?=$pconfig['authorizedkeys'];?></textarea>
                    </td>
                  </tr>
                  <tr id="ipsecpskrow">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPsec Pre-Shared Key");?></td>
                    <td>
                      <input name="ipsecpsk" type="text" size="65" value="<?=$pconfig['ipsecpsk'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <button name="save" id="save" type="submit" class="btn btn-primary" value="save" /><?= gettext('Save') ?></button>
                      <button name="save_close" id="save_close" type="submit" class="btn btn-primary" value="save_close" /><?= gettext('Save and go back') ?></button>
                      <button name="cancel" id="cancel" type="submit" class="btn btn-default" value="cancel" /><?= gettext('Cancel') ?></button>
<?php
                      if (isset($id) && !empty($a_user[$id])) :?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
                      endif;?>
                    </td>
                  </tr>
                </table>
              </form>
<?php
              else :?>
              <form method="post" name="iform2" id="iform2">
                <input type="hidden" id="act2" name="act" value="" />
                <input type="hidden" id="userid" name="userid" value="<?=(isset($id) ? $id : '');?>" />
                <input type="hidden" id="username" name="username" value="" />
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?=gettext("Username"); ?></th>
                      <th><?=gettext("Full name"); ?></th>
                      <th><?=gettext("Groups"); ?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  /* create a copy for sorting */
                  $a_user_ro = $a_user;
                  uasort($a_user_ro, function($a, $b) {
                    return strnatcasecmp($a['name'], $b['name']);
                  });
                  foreach ($a_user_ro as $i => $userent): ?>
                    <tr>
                      <td>
<?php
                        if (isset($userent['disabled'])) {
                            $usrimg = 'text-muted';
                        } elseif (userIsAdmin($userent['name'])) {
                            $usrimg = 'text-danger';
                        } else {
                            $usrimg = 'text-info';
                        }?>
                        <span class="fa fa-user <?=$usrimg;?>"></span> <?=$userent['name'];?>
                      </td>
                      <td><?= $userent['descr'] ?></td>
                      <td><?= implode(', ', local_user_get_groups($userent)) ?></td>
                      <td class="text-nowrap">
                        <a href="system_usermanager.php?act=edit&userid=<?=$i?>"
                            class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                          <span class="fa fa-pencil fa-fw"></span>
                        </a>
<?php if ($userent['scope'] != 'system'): ?>
                        <button type="button" class="btn btn-default btn-xs act-del-user"
                            data-username="<?=$userent['name'];?>"
                            data-userid="<?=$i?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip">
                          <span class="fa fa-trash fa-fw"></span>
                        </button>
<?php endif ?>
                      </td>
                    </tr>
<?php endforeach ?>
                    <tr>
                      <td colspan="3">
                        <table>
                          <tr>
                            <td></td>
                            <td style="width:20px"></td>
                            <td style="width:20px"><span class="fa fa-user text-danger"></span></td>
                            <td style="width:200px"><?= gettext('System Administrator') ?></td>
                            <td style="width:20px"><span class="fa fa-user text-muted"></span></td>
                            <td style="width:200px"><?= gettext('Disabled User') ?></td>
                            <td style="width:20px"><span class="fa fa-user text-info"></span></td>
                            <td style="width:200px"><?= gettext('Normal User') ?></td>
                            <td></td>
                          </tr>
                        </table>
                      </td>
                      <td class="text-nowrap">
<?php
                        $can_import = false;
                        if (!empty($config['system']['webgui']['authmode'])) {
                            $servers = explode(',', $config['system']['webgui']['authmode']);
                            foreach ($servers as $server) {
                                $authcfg_type = auth_get_authserver($server)['type'];
                                if ($authcfg_type == 'ldap' || $authcfg_type == 'ldap-totp') {
                                    $can_import = true;
                                }
                            }
                        }
?>
<?php if ($can_import): ?>
                          <button type="submit" name="import"
                                  id="import_ldap_users"
                                  data-toggle="tooltip"
                                  class="btn btn-primary btn-xs"
                                  title="<?= html_safe(gettext('Import')) ?>">
                              <i class="fa fa-cloud-download fa-fw"></i>
                          </button>
<?php endif ?>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </form>
<?php
              endif;?>
            </div>
          </section>
        </div>
      </div>
    </section>

<?php include("foot.inc");
