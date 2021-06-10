<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2006 Daniel S. Haischt
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

function sort_user_privs($privs)
{
    /* Privileges to place first, to redirect properly. */
    $priority_privs = array('page-dashboard-all', 'page-system-login-logout');

    $fprivs = array_intersect($privs, $priority_privs);
    $sprivs  = array_diff($privs, $priority_privs);

    return array_merge($fprivs, $sprivs);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['userid']) && isset($config['system']['user'][$_GET['userid']]['name'])) {
        $input_type = "user";
        $id = $_GET['userid'];
    } elseif (isset($_GET['groupid']) &&  isset($config['system']['group'][$_GET['groupid']])) {
        $input_type = "group";
        $id = $_GET['groupid'];
    } else {
        header(url_safe('Location: /system_usermanager.php'));
        exit;
    }
    if ($input_type == "group") {
        $a_privs = &config_read_array('system', 'group', $id, 'priv');
    } else {
        $a_privs = &config_read_array('system', 'user', $id, 'priv');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    $user = getUserEntry($_SESSION['Username']);
    if (userHasPrivilege($user, 'user-config-readonly')) {
        $input_errors[] = gettext('You do not have the permission to perform this action.');
    }

    if (count($input_errors)) {
        /* FALLTHROUGH */
    } elseif (isset($pconfig['input_type']) && isset($pconfig['id'])) {
        if ($pconfig['input_type'] == 'user' && isset($config['system']['user'][$pconfig['id']]['name'])) {
            $userid = $_POST['id'];
            $a_user = &config_read_array('system', 'user', $userid);
            $a_user['priv'] = is_array($pconfig['sysprivs']) ? $pconfig['sysprivs'] : array();
            $a_user['priv'] = sort_user_privs($a_user['priv']);
            local_user_set($a_user);
            $retval = write_config();
            $savemsg = get_std_save_message(true);

            header(url_safe('Location: /system_usermanager.php?act=edit&userid=%d&savemsg=%s', array($userid, $savemsg)));
            exit;
        } elseif ($_POST['input_type'] == 'group' && isset($config['system']['group'][$pconfig['id']]['name'])) {
            $groupid = $_POST['id'];
            $a_group = &config_read_array('system', 'group', $groupid);
            $a_group['priv'] = is_array($pconfig['sysprivs']) ? $pconfig['sysprivs'] : array();
            $a_group['priv'] = sort_user_privs($a_group['priv']);
            if (is_array($a_group['member'])) {
                foreach ($a_group['member'] as $uid) {
                    $user = getUserEntryByUID($uid);
                    if ($user) {
                        local_user_set($user);
                    }
                }
            }

            if (!empty($config['system']['group'])) {
                usort($config['system']['group'], function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
            }

            write_config();
            header(url_safe('Location: /system_groupmanager.php?act=edit&groupid=%d', array($groupid)));
            exit;
        }
    } else {
        header(url_safe('Location: /system_usermanager.php'));
        exit;
    }
}

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<script>
    $( document ).ready(function() {
        $("#search").keyup(function(event){
            event.preventDefault();
            $(".acl_item").each(function(){
                if ($(this).data('search-phrase').toLowerCase().indexOf($("#search").val().toLowerCase()) > -1) {
                    if ($("#search_selected:checked").val() != undefined) {
                        if ($(this).find('td > input:checked').val() != undefined) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    } else {
                        $(this).show();
                    }
                } else {
                    $(this).hide();
                }

                $("#priv_container").scrollTop(0);
            })
        });

        $("#selectall").click(function(event){
            event.preventDefault();
            $(".acl_item").each(function(){
                if ($(this).is(':visible')) {
                    $(this).find('td > input').prop('checked', true);
                }
            });
        });

        $("#deselectall").click(function(event){
            event.preventDefault();
            $(".acl_item").each(function(){
                if ($(this).is(':visible')) {
                    $(this).find('td > input').prop('checked', false);
                }
            });
        });

        $("#search_selected").click(function(){
            $("#search").keyup();
        });

        // Warn user about future removal.
        $("input[value='user-config-readonly']").change(function(){
            if ($(this).is(':checked')) {
              BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: "<?= gettext("Privileges");?>",
                message: "<?=gettext("Please be aware that this option does not cover all areas of the system and will be removed in a future release.");?>",
                buttons: [{ label: "<?= gettext("Ok");?>", action: function(dialogRef) {
                              dialogRef.close();
                          }
                }]
              });
            }
        });
    });
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post" name="iform">
            <input name="id" type="hidden" value="<?=$id;?>" />
            <input name="input_type" type="hidden" value="<?=$input_type;?>" />
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><?=gettext("System Privileges");?></td>
                <td style="width:78%">
                    <table class="table table-condensed table-hoover">
                        <thead>
                            <tr>
                                <th style="width:70px;"><?=gettext("Allowed");?></th>
                                <th><?=gettext("Description");?></th>
                            </tr>
                            <tr>
                                <th>
                                    <input type="checkbox" id="search_selected"> <small><?=gettext("(filter)");?></small>
                                </th>
                                <th>
                                    <input type="text" placeholder="<?=gettext("search");?>" id="search">
                                </th>
                            </tr>
                        </thead>
                    </table>
                    <div style="max-height: 400px; width: 100%; margin: 0; overflow-y: auto;" id="priv_container">
                        <table class="table table-condensed table-hoover">
                            <thead>
                                <tr>
                                    <th style="width:70px;"></th>
                                    <th style="width:50px;"></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
<?php
                            foreach ($priv_list as $pname => $pdata) {
                                 $pnamesafe = !empty($pdata['name']) ? $pdata['name'] : $pname;
                                 switch (substr($pname, 0, 5)) {
                                     case 'page-':
                                         $pdesc = gettext('GUI');
                                         break;
                                     case 'user-':
                                         $pdesc = gettext('User');
                                         break;
                                     default:
                                         $pdesc = gettext('N/A');
                                         break;
                                 } ?>
                                <tr class="acl_item" data-search-phrase="<?= $pdesc . ' ' . $pnamesafe ?>">
                                    <td>
                                        <input name="sysprivs[]" type="checkbox" value="<?= $pname ?>" <?= !empty($a_privs) && in_array($pname, $a_privs) ? 'checked="checked"' : '' ?>>
                                    </td>
                                    <td><?= $pdesc ?></td>
                                    <td><?= $pnamesafe ?>
<?php
                                      if (!empty($pdata['match'])):?>
                                      <i class="fa fa-info-circle" style="cursor: pointer" data-toggle="collapse" href="#<?=$pname;?>"></i>
                                      <div class="collapse" id="<?=$pname;?>">
                                        <table class="table table-condensed">
                                          <thead>
                                            <tr>
                                                <th><?=gettext("endpoint");?>
                                            </tr>
                                          </thead>
                                          <tbody>
<?php
                                          foreach ($pdata['match'] as $match):?>
                                            <tr><td>/<?=$match;?></td></tr>
<?php
                                          endforeach;?>
                                          </tbody>
                                        </table>
                                      </div>
<?php
                                      endif;?>
                                    </td>
                                </tr>
<?php
                            } ?>
                            </tbody>
                        </table>
                    </div>
                    <table class="table table-condensed table-hoover">
                        <thead>
                            <tr>
                                <th style="width:50px;"><input type="checkbox" id="selectall"></th>
                                <th><?=gettext("Select all (visible)");?></th>
                            </tr>
                            <tr>
                                <th style="width:50px;"><input type="checkbox" id="deselectall"></th>
                                <th><?=gettext("Deselect all (visible)");?></th>
                            </tr>
                        </thead>
                    </table>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                  <input class="btn btn-default" type="button" value="<?=html_safe(gettext("Cancel"));?>" onclick="history.back()" />
                </td>
              </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
