<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2008 Shrew Soft Inc.
  Copyright (C) 2005 Paul Taylor <paultaylor@winn-dixie.com>.
  Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

function get_user_privdesc(& $user)
{
    global $priv_list;

    $privs = array();

    if (!isset($user['priv']) || !is_array($user['priv'])) {
        $user_privs = array();
    } else {
        $user_privs = $user['priv'];
    }

    $names = local_user_get_groups($user, true);

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

// link user section
if (!isset($config['system']['user']) || !is_array($config['system']['user'])) {
    $config['system']['user'] = array();
}
$a_user = &$config['system']['user'];

// reset errors and action
$input_errors = array();
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
        $cert =& lookup_cert($a_user[$id]['cert'][$_GET['certid']]);

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
        $cert =& lookup_cert($a_user[$id]['cert'][$_GET['certid']]);
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
        $fieldnames = array('user_dn', 'descr', 'expires', 'scope', 'uid', 'priv', 'ipsecpsk', 'lifetime');
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
        } else {
            // set defaults
            $pconfig['groups'] = null;
            $pconfig['disabled'] = false;
            $pconfig['scope'] = "user";
            $pconfig['lifetime'] = 365;
            $pconfig['usernamefld'] = null;
            foreach ($fieldnames as $fieldname) {
                if (isset($pconfig[$fieldname])) {
                    $pconfig[$fieldname] = null;
                }
            }
        }
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


    if ($act == "deluser" && isset($id)) {
        // drop user
        local_user_del($a_user[$id]);
        $userdeleted = $a_user[$id]['name'];
        unset($a_user[$id]);
        write_config();
        $savemsg = gettext("User")." {$userdeleted} ". gettext("successfully deleted");
        redirectHeader("system_usermanager.php?savemsg=".$savemsg);
        exit;
    } elseif ($act == "delpriv" && !empty($pconfig['priv_delete']) && isset($id)) {
        // drop privilege from user
        // search for priv id to delete
        $privid = null;
        if (!empty($a_user[$id]['priv'])) {
            foreach ($a_user[$id]['priv'] as $key => $value) {
                if ($value == $pconfig['priv_delete']) {
                    $privid = $key;
                    $privdeleted = $value;
                }
            }
        }

        if ($privid !== null) {
            unset($a_user[$id]['priv'][$privid]);
            local_user_set($a_user[$id]);
            write_config();
            $savemsg = gettext("Privilege")." {$privdeleted} ".
                        gettext("successfully deleted");
            redirectHeader("system_usermanager.php?savemsg=".$savemsg."&act=edit&userid=".$id);
        } else {
            redirectHeader("system_usermanager.php?act=edit&userid=".$id);
        }
        exit;
    } elseif ($act == "delcert" && isset($id)) {
        // remove certificate association
        $certdeleted = lookup_cert($a_user[$id]['cert'][$pconfig['certid']]);
        $certdeleted = $certdeleted['descr'];
        unset($a_user[$id]['cert'][$pconfig['certid']]);
        write_config();
        $savemsg = gettext("Certificate")." {$certdeleted} ".
                    gettext("association removed.");
        redirectHeader("system_usermanager.php?savemsg=".$savemsg."&act=edit&userid=".$id);
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
    } elseif ($act =='delApiKey'  && isset($id)) {
        $username = $a_user[$id]['name'];
        if (!empty($pconfig['api_delete'])) {
            $authFactory = new \OPNsense\Auth\AuthenticationFactory();
            $authenticator = $authFactory->get("Local API");
            $authenticator->dropKey($username, $pconfig['api_delete']);
            $savemsg = gettext("API key")." {$pconfig['api_delete']} ".
                        gettext("removed.");
        } else {
            $savemsg = gettext('No API key found');
        }
        // redirect
        redirectHeader("system_usermanager.php?savemsg=".$savemsg."&act=edit&userid=".$id);
        exit;
    } elseif (isset($pconfig['save'])) {
        // save user
        /* input validation */
        if (isset($id)) {
            $reqdfields = explode(" ", "usernamefld");
            $reqdfieldsn = array(gettext("Username"));
        } else {
            if (empty($pconfig['name'])) {
                $reqdfields = explode(" ", "usernamefld passwordfld1");
                $reqdfieldsn = array(
                    gettext("Username"),
                    gettext("Password"));
            } else {
                $reqdfields = explode(" ", "usernamefld passwordfld1 name caref keylen lifetime");
                $reqdfieldsn = array(
                    gettext("Username"),
                    gettext("Password"),
                    gettext("Descriptive name"),
                    gettext("Certificate authority"),
                    gettext("Key length"),
                    gettext("Lifetime"));
            }
        }

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (preg_match("/[^a-zA-Z0-9\.\-_]/", $pconfig['usernamefld'])) {
            $input_errors[] = gettext("The username contains invalid characters.");
        }

        if (strlen($_POST['usernamefld']) > 16) {
            $input_errors[] = gettext("The username is longer than 16 characters.");
        }

        if (($pconfig['passwordfld1']) && ($pconfig['passwordfld1'] != $pconfig['passwordfld2'])) {
            $input_errors[] = gettext("The passwords do not match.");
        }

        if (isset($id)) {
            $oldusername = $a_user[$id]['name'];
        } else {
            $oldusername = "";
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

        if (count($input_errors)==0) {
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
            }

            isset($pconfig['scope']) ? $userent['scope'] = $pconfig['scope'] : $userent['scope'] = "system";

            $userent['name'] = $pconfig['usernamefld'];
            $userent['descr'] = $pconfig['descr'];
            $userent['expires'] = $pconfig['expires'];
            $userent['authorizedkeys'] = base64_encode($pconfig['authorizedkeys']);
            $userent['ipsecpsk'] = $pconfig['ipsecpsk'];

            if (!empty($pconfig['disabled'])) {
                $userent['disabled'] = true;
            } elseif (isset($userent['disabled'])) {
                unset($userent['disabled']);
            }

            if (isset($id)) {
                $a_user[$id] = $userent;
            } else {
                if (!empty($pconfig['name'])) {
                    $cert = array();
                    $cert['refid'] = uniqid();
                    $userent['cert'] = array();

                    $cert['descr'] = $pconfig['name'];

                    $subject = cert_get_subject_array($ca['crt']);

                    $dn = array(
                        'countryName' => $subject[0]['v'],
                        'stateOrProvinceName' => $subject[1]['v'],
                        'localityName' => $subject[2]['v'],
                        'organizationName' => $subject[3]['v'],
                        'emailAddress' => $subject[4]['v'],
                        'commonName' => $userent['name']);

                    cert_create(
                        $cert,
                        $pconfig['caref'],
                        $pconfig['keylen'],
                        (int)$pconfig['lifetime'],
                        $dn
                    );

                    if (!is_array($config['cert'])) {
                        $config['cert'] = array();
                    }
                    $config['cert'][] = $cert;
                    $userent['cert'][] = $cert['refid'];
                }
                $userent['uid'] = $config['system']['nextuid']++;
                /* Add the user to All Users group. */
                foreach ($config['system']['group'] as $gidx => $group) {
                    if ($group['name'] == "all") {
                        if (!is_array($config['system']['group'][$gidx]['member'])) {
                            $config['system']['group'][$gidx]['member'] = array();
                        }
                        $config['system']['group'][$gidx]['member'][] = $userent['uid'];
                        break;
                    }
                }

                $a_user[] = $userent;
            }

            local_user_set($userent);
            local_user_set_groups($userent, $pconfig['groups']);
            write_config();

            redirectHeader("system_usermanager.php");
            exit;
        }
    } elseif (isset($id)) {
        redirectHeader("system_usermanager.php?userid=".$id);
        exit;
    } else {
        redirectHeader("system_usermanager.php");
        exit;
    }
}

$pgtitle = array(gettext('System'), gettext('Users'));

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_user);
$closehead = false;
include("head.inc");
?>

<body>

<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
function clear_selected(id) {
    selbox = document.getElementById(id);
    count = selbox.options.length;
    for (index = 0; index<count; index++) {
        selbox.options[index].selected = false;
    }
}

function remove_selected(id) {
    selbox = document.getElementById(id);
    index = selbox.options.length - 1;
    for (; index >= 0; index--) {
      if (selbox.options[index].selected) {
          selbox.remove(index);
      }
    }
}

function copy_selected(srcid, dstid) {
    src_selbox = document.getElementById(srcid);
    dst_selbox = document.getElementById(dstid);
    count = dst_selbox.options.length;
    for (index = count - 1; index >= 0; index--) {
        if (dst_selbox.options[index].value == '') {
            dst_selbox.remove(index);
        }
    }
    count = src_selbox.options.length;
    for (index = 0; index < count; index++) {
        if (src_selbox.options[index].selected) {
            option = document.createElement('option');
            option.text = src_selbox.options[index].text;
            option.value = src_selbox.options[index].value;
            dst_selbox.add(option, null);
        }
    }
}

function move_selected(srcid, dstid) {
  copy_selected(srcid, dstid);
  remove_selected(srcid);
}

function presubmit() {
    clear_selected('notgroups');

    selbox = document.getElementById("groups");
    count = selbox.options.length;
    for (index = 0; index<count; index++) {
        selbox.options[index].selected = true;
    }
}
//]]>
</script>

<script type="text/javascript">
$( document ).ready(function() {
  // delete privilege
  $(".act-del-priv").click(function(event){
      event.preventDefault();
      var priv_name = $(this).data('priv');
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("User");?>",
          message: "<?=gettext("Do you really want to delete this privilege?");?> " + "<br/>("+priv_name+")",
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#priv_delete").val(priv_name);
                      $("#act").val("delpriv");
                      $("#iform").submit();
                  }
          }]
      });
    });

    // remove certificate association
    $(".act-del-cert").click(function(event){
      var certid = $(this).data('certid');
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("Certificate");?>",
          message: '<?=gettext("Do you really want to remove this certificate association?") .'\n'. gettext("(Certificate will not be deleted)");?>',
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
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
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("User");?>",
          message: '<?=gettext("Do you really want to delete this user?");?>' + '<br/>('+username+")",
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#userid").val(userid);
                      $("#act2").val("deluser");
                      $("#iform2").submit();
                  }
          }]
      });
    });

    // checkbox, add new cert for new user
    $("#chkNewCert").click(function(){
        $("#usercertchck").toggleClass('hidden visible');
        $("#usercert").toggleClass('hidden visible');
    });

    // expand ssh key section on click
    $("#authorizedkeys").click(function(){
        $(this).attr('rows', '7');
    });

    // import ldap users
    $("#import_ldap_users").click(function(){
      url="system_usermanager_import_ldap.php";
      var oWin = window.open(url,"OPNsense","width=620,height=400,top=150,left=150,scrollbars=yes");
      if (oWin==null || typeof(oWin)=="undefined") {
        alert("<?=gettext('Popup blocker detected.  Action aborted.');?>");
      }
    });


    // generate a new API key for this user
    $("#newApiKey").click(function(event){
        event.preventDefault();
        $.post(window.location, {act: 'newApiKey', userid: $("#userid").val() }, function(data) {
            if (data['key'] != undefined) {
                // only generate a key file if there's data
                output_data = 'key='+data['key'] +'\n' + 'secret='+data['secret'] +'\n';
                // create link, click and send to client
                $('<a></a>')
                        .attr('id','downloadFile')
                        .attr('href','data:text/csv;charset=utf8,' + encodeURIComponent(output_data))
                        .attr('download','apikey.ini')
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
            type:BootstrapDialog.TYPE_INFO,
            title: "<?= gettext("User");?>",
            message: '<?=gettext("Do you really want to delete this API key?");?>' + '<br/><small>('+apiKey.substring(0,40)+"...)</small>",
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                      dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#act").val("delApiKey");
                        $("#api_delete").val(apiKey);
                        $("#iform").submit();
                    }
            }]
        });
    });

});
</script>


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
<?php
            if ($act == "new" || $act == "edit" ) :?>
              <form action="system_usermanager.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
                <input type="hidden" id="act" name="act" value="<?=$act;?>" />
                <input type="hidden" id="userid" name="userid" value="<?=(isset($id) ? $id : '');?>" />
                <input type="hidden" id="priv_delete" name="priv_delete" value="" /> <!-- delete priv action -->
                <input type="hidden" id="api_delete" name="api_delete" value="" /> <!-- delete api ke action -->
                <input type="hidden" id="certid" name="certid" value="" /> <!-- remove cert association action -->
                <table class="table table-striped">
                  <tr>
                    <td width="22%"></td>
                    <td width="78%" align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
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
                      <input name="usernamefld" type="text" class="formfld user" id="usernamefld" size="20" maxlength="16" value="<?=$pconfig['usernamefld'];?>" <?= $pconfig['scope'] == "system" || !empty($pconfig['user_dn']) ? "readonly=\"readonly\"" : "";?> />
                      <input name="oldusername" type="hidden" id="oldusername" value="<?=$pconfig['usernamefld'];?>" />
                    </td>
                  </tr>
<?php
                  if (!empty($pconfig['user_dn'])):?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("User distinguished name");?></td>
                    <td>
                      <input name="user_dn" type="text" class="formfld user" id="user_dn" size="20" maxlength="16" value="<?=$pconfig['user_dn'];?>"/ readonly>
                    </td>
                  </tr>
<?php
                  else:?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Password");?></td>
                    <td>
                      <input name="passwordfld1" type="password" class="formfld pwd" id="passwordfld1" size="20" value="" /><br/>
                      <input name="passwordfld2" type="password" class="formfld pwd" id="passwordfld2" size="20" value="" />&nbsp;
                      <small><?= gettext("(confirmation)"); ?></small>
                    </td>
                  </tr>
<?php
                  endif;?>
                  <tr>
                    <td><a id="help_for_fullname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Full name");?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" <?= $pconfig['scope'] == "system" || !empty($pconfig['user_dn']) ? "readonly=\"readonly\"" : "";?> />
                      <div class="hidden" for="help_for_fullname">
                        <?=gettext("User's full name, for your own information only");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_expires" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Expiration date"); ?></td>
                    <td>
                      <input name="expires" type="text" id="expires" size="10" value="<?=$pconfig['expires'];?>" />
                      <br />
                      <div class="hidden" for="help_for_expires">
                          <?=gettext("Leave blank if the account shouldn't expire, otherwise enter the expiration date in the following format: mm/dd/yyyy"); ?>
                      </div>
                  </tr>
                  <tr>
                    <td><a id="help_for_groups" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Group Memberships");?></td>
                    <td>
                      <table class="table" width="100%" border="0" cellpadding="0" cellspacing="0">
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
                              <a href="javascript:move_selected('notgroups','groups')" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"  title="<?=gettext("Add Groups"); ?>">
                                  <span class="glyphicon glyphicon-arrow-right"></span>
                              </a>
                              <br /><br />
                              <a href="javascript:move_selected('groups','notgroups')" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"  title="<?=gettext("Remove Groups"); ?>">
                                  <span class="glyphicon glyphicon-arrow-left"></span>
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
                      <div class="hidden" for="help_for_groups">
                          <?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
                      </div>
                    </td>
                  </tr>
<?php
                  if (isset($pconfig['uid'])) :?>
                  <tr>
                    <td colspan="2"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Effective Privileges");?></td>
                  </tr>
                  <tr>
                    <td colspan="2">
                      <table class="table table-striped table-condensed">
                        <tr>
                          <td width="20%"><b><?=gettext("Inherited From");?></b></td>
                          <td width="30%"><b><?=gettext("Name");?></b></td>
                          <td width="40%"><b><?=gettext("Description");?></b></td>
                          <td></td>
                        </tr>
<?php
                        foreach (get_user_privdesc($a_user[$id]) as $priv) :?>
                        <tr>
                            <td><?=!empty($priv['group']) ? $priv['group'] : ""?></td>
                            <td><?=$priv['name']?></td>
                            <td><?=!empty($priv['descr']) ? $priv['descr'] : ""?></td>
                            <td class="text-center">
<?php
                            if (empty($priv['group'])) :?>
                              <button type="button" data-priv="<?=$priv['id']?>" class="btn btn-default btn-xs act-del-priv" title="<?=gettext("delete privilege");?>" data-toggle="tooltip" data-placement="left">
                                <span class="glyphicon glyphicon-remove"></span>
                              </button>
<?php
                            endif;?>
                            </td>
                        </tr>
<?php
                        endforeach;?>
                        <tr>
                          <td colspan="3"></td>
                          <td>
                            <a href="system_usermanager_addprivs.php?userid=<?=$id?>" class="btn btn-xs btn-default">
                              <span class="glyphicon glyphicon-plus"></span>
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
                          <td><?=gettext("Name");?></td>
                          <td><?=gettext("CA");?></td>
                          <td></td>
                        </tr>
<?php
                        if (isset($a_user[$id]['cert']) && is_array($a_user[$id]['cert'])) :
                          $i = 0;
                          foreach ($a_user[$id]['cert'] as $certref) :
                            $cert = lookup_cert($certref);
                            $ca = lookup_ca($cert['caref']);
?>
                        <tr>
                          <td><?=htmlspecialchars($cert['descr']);?>
                              <?=is_cert_revoked($cert) ? "(<b>".gettext('Revoked')."</b>)" : "";?>
                          </td>
                          <td>
                            <?=htmlspecialchars($ca['descr']);?>
                          </td>
                          <td>
                            <a href="system_usermanager.php?act=expckey&certid=<?=$i?>&userid=<?=$id?>"
                               class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"
                               title="<?=gettext("export private key");?>">
                                <span class="glyphicon glyphicon-arrow-down"></span>
                            </a>
                            <a href="system_usermanager.php?act=expcert&certid=<?=$i?>&userid=<?=$id?>"
                               class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"
                               title="<?=gettext("export cert");?>">
                                <span class="glyphicon glyphicon-arrow-down"></span>
                            </a>
                            <button type="submit" data-certid="<?=$i;?>" class="btn btn-default btn-xs act-del-cert"
                                title="<?=gettext("delete cert");?>" data-toggle="tooltip" data-placement="left" >
                                <span class="glyphicon glyphicon-remove"></span>
                            </button>
                          </td>
                        </tr>
<?php
                        $i++;
                            endforeach;
                        endif;?>
                        <tr>
                          <td colspan="2"></td>
                          <td>
                            <a href="system_certmanager.php?act=new&userid=<?=$id?>" class="btn btn-default btn-xs">
                              <span class="glyphicon glyphicon-plus"></span>
                            </a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_apikeys" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("API keys");?> </td>
                      <td>
                          <!-- -->
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
                                        <small>
                                      </td>
                                      <td>
                                        <button data-key="<?=$userApiKey['key'][0];?>" type="button" class="btn btn-default btn-xs act-del-api-key"
                                            title="<?=gettext("delete API key");?>" data-toggle="tooltip" data-placement="left" >
                                            <span class="glyphicon glyphicon-trash"></span>
                                        </button>
                                      </td>
                                  </tr>
<?php
                                    endforeach;
                                  endif;?>
                              </tbody>
                              <tfoot>
                                  <tr>
                                    <td></td>
                                    <td>
                                      <button type="button" class="btn btn-default btn-xs" id="newApiKey"
                                          title="<?=gettext("create API key");?>" data-toggle="tooltip" data-placement="left" >
                                          <span class="glyphicon glyphicon-plus"></span>
                                      </button>
                                    </td>
                                  </tr>
                              </tfoot>
                          </table>
                          <div class="hidden" for="help_for_apikeys">
                              <hr/>
                              <?=gettext('manage API keys here for machine to machine interaction using this users credentials');?>
                          </div>
                      </td>
                  </tr>
<?php
                else :
                  if (is_array($config['ca']) && count($config['ca']) > 0) :
                      $i = 0;
                      foreach ($config['ca'] as $ca) {
                          if (!$ca['prv']) {
                              continue;
                          }
                          $i++;
                      }
?>
                  <tr id="usercertchck">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate");?></td>
                    <td>
                      <input type="checkbox" id="chkNewCert" /> <?=gettext("Click to create a user certificate."); ?>
                    </td>
                  </tr>
                  <tr class="hidden"><td colspan=2><td></tr>

<?php
                  if ($i > 0) :?>
                  <tr id="usercert" class="hidden">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate");?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td><?=gettext("Descriptive name");?></td>
                          <td>
                            <input name="name" type="text" id="name" size="20" value="<?=$pconfig['usernamefld'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><?=gettext("Certificate authority");?></td>
                          <td>
                            <select name='caref' id='caref'>
<?php
                            foreach ($config['ca'] as $ca) :
                              if (empty($ca['prv'])) {
                                continue;
                              }
?>
                              <option value="<?=$ca['refid']?>"><?=htmlspecialchars($ca['descr']);?></option>
<?php
                          endforeach;?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><?=gettext("Key length");?> (<?=gettext("bits");?>)</td>
                          <td>
                            <select name='keylen'>
<?php
                          foreach (array( "2048", "512", "1024", "4096") as $len) :?>
                              <option value="<?=$len;?>"><?=$len;?></option>
<?php
                          endforeach;?>
                          </select>
                          </td>
                        </tr>
                        <tr>
                          <td><?=gettext("Lifetime");?> (<?=gettext("days");?>)</td>
                          <td>
                            <input name="lifetime" class="form-control" type="text" id="lifetime" size="5" value="<?=$pconfig['lifetime'];?>" />
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
<?php
                  endif;
                  endif;
                endif;?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authorized keys");?></td>
                    <td>
                      <textarea name="authorizedkeys" id="authorizedkeys" class="form-control" cols="65" rows="1" placeholder="<?=gettext("Paste an authorized keys file here.");?>" wrap='off'><?=$pconfig['authorizedkeys'];?></textarea>
                    </td>
                  </tr>
                  <tr id="ipsecpskrow">
                    <td><?=gettext("IPsec Pre-Shared Key");?></td>
                    <td>
                      <input name="ipsecpsk" type="text" size="65" value="<?=$pconfig['ipsecpsk'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>"
                             onclick="window.location.href='<?=isset($_SERVER['HTTP_REFERER']) ?  $_SERVER['HTTP_REFERER'] : '/system_usermanager.php';?>'" />
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
              <form action="system_usermanager.php" method="post" name="iform2" id="iform2">
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
                  $i = 0;
                  foreach ($a_user as $userent) :?>
                    <tr>
                      <td>
<?php
                        if ($userent['scope'] != "user") {
                            $usrimg = "glyphicon glyphicon-user text-danger";
                        } elseif (isset($userent['disabled'])) {
                                $usrimg = "glyphicon glyphicon-user text-muted";
                        } else {
                                $usrimg = "glyphicon glyphicon-user text-info";
                        }?>
                        <span class="<?=$usrimg;?>"></span> <?=$userent['name'];?>
                      </td>
                      <td><?=$userent['descr'];?></td>
                      <td>
                        <?=implode(",", local_user_get_groups($userent));?>
                      </td>
                      <td>
                        <a href="system_usermanager.php?act=edit&userid=<?=$i?>"
                           class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"
                           title="<?=gettext("edit user");?>">
                            <span class="glyphicon glyphicon-pencil"></span>
                        </a>
<?php
                        if ($userent['scope'] != "system") :?>
                        <button type="button" class="btn btn-default btn-xs act-del-user"
                            data-username="<?=$userent['name'];?>"
                            data-userid="<?=$i?>" title="<?=gettext("delete user");?>" data-toggle="tooltip"
                            data-placement="left" ><span class="glyphicon glyphicon-remove"></span>
                        </button>
<?php
                        endif;?>
                      </td>
                    </tr>
<?php
                  $i++;
                  endforeach;
?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="3"></td>
                      <td>
                        <a href="system_usermanager.php?act=new" class="btn btn-default btn-xs"
                           title="<?=gettext("add user");?>" data-toggle="tooltip" data-placement="left">
                          <span class="glyphicon glyphicon-plus"></span>
                        </a>
<?php
                        $authcfg_type = auth_get_authserver($config['system']['webgui']['authmode'])['type'];
                        if ($authcfg_type == 'ldap') :?>
                          <button type="submit" name="import"
                                  id="import_ldap_users"
                                  class="btn btn-default btn-xs"
                                  title="<?=gettext("import users")?>">
                              <i class="fa fa-cloud-download"></i>
                          </button>
<?php
                      endif;?>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="4">
                        <p class="col-xs-12 col-sm-10">
                          <?=gettext("Additional users can be added here. User permissions for accessing " .
                                        "the webConfigurator can be assigned directly or inherited from group memberships. " .
                                        "An icon that appears grey indicates that it is a system defined object. " .
                                        "Some system object properties can be modified but they cannot be deleted."); ?>
                          <br /><br />
                          <?=gettext("Accounts created here are also used for other parts of the system " .
                                        "such as OpenVPN, IPsec, and Captive Portal.");?>
                        </p>
                      </td>
                    </tr>
                  </tfoot>
                </table>
                <table>
                  <tr>
                    <td></td>
                    <td width="20px"></td>
                    <td width="20px"><span class="glyphicon glyphicon-user text-danger"></span></td>
                    <td width="200px"><?= gettext('System Admininistrator') ?></td>
                    <td width="20px"><span class="glyphicon glyphicon-user text-muted"></span></td>
                    <td width="200px"><?= gettext('Disabled User') ?></td>
                    <td width="20px"><span class="glyphicon glyphicon-user text-info"></span></td>
                    <td width="200px"><?= gettext('Normal User') ?></td>
                    <td></td>
                  </tr>
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
