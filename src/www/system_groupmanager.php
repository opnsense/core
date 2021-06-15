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

require_once("guiconfig.inc");

$a_group = &config_read_array('system', 'group');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($a_group[$_GET['groupid']])) {
        $id = $_GET['groupid'];
    }
    if (isset($_GET['act']) && ($_GET['act'] == 'edit' || $_GET['act'] == 'new')) {
        $act = $_GET['act'];
    } else {
        $act = null;
    }
    $pconfig = array();
    if ($act == "edit" && isset($id)) {
        // read config
        $pconfig['name'] = $a_group[$id]['name'];
        $pconfig['gid'] = $a_group[$id]['gid'];
        $pconfig['scope'] = $a_group[$id]['scope'];
        $pconfig['description'] = $a_group[$id]['description'];
        $pconfig['members'] = isset($a_group[$id]['member']) ? $a_group[$id]['member'] : array();
        $pconfig['priv'] = isset($a_group[$id]['priv']) ? $a_group[$id]['priv'] : array();
    } elseif ($act != null) {
        // init defaults
        $pconfig['name'] = null;
        $pconfig['gid'] = null;
        $pconfig['scope'] = null;
        $pconfig['description'] = null;
        $pconfig['members'] = array();
        $pconfig['priv'] = array();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($a_group[$_POST['groupid']])) {
        $id = $_POST['groupid'];
    }
    $pconfig = $_POST;
    $input_errors = array();
    $act = (isset($pconfig['act']) ? $pconfig['act'] : '');

    $user = getUserEntry($_SESSION['Username']);
    $a_user = &config_read_array('system', 'user');
    if (userHasPrivilege($user, 'user-config-readonly')) {
        $input_errors[] = gettext('You do not have the permission to perform this action.');
    } elseif (isset($id) && $act == "delgroup" && isset($pconfig['groupname']) && $pconfig['groupname'] == $a_group[$id]['name']) {
        $prev_members = !empty($a_group[$id]['member']) ? $a_group[$id]['member'] : array();
        local_group_del($a_group[$id]);
        unset($a_group[$id]);
        write_config();
        // XXX: signal backend about changed users for the members of this group
        foreach ($prev_members as $member) {
            foreach ($a_user as & $user) {
                if ($user['uid'] == $member) {
                    configdp_run('auth user changed', [$user['name']]);
                }
            }
        }
        header(url_safe('Location: /system_groupmanager.php'));
        exit;
    } elseif (isset($pconfig['save'])) {
        $reqdfields = explode(" ", "name");
        $reqdfieldsn = array(gettext("Group Name"));

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (preg_match("/[^a-zA-Z0-9\.\-_]/", $pconfig['name'])) {
            $input_errors[] = gettext("The group name contains invalid characters.");
        }

        if (strlen($pconfig['name']) > 32) {
            $input_errors[] = gettext("The group name is longer than 32 characters.");
        }

        if (count($input_errors) == 0 && !isset($id)) {
            /* make sure there are no dupes */
            foreach ($a_group as $group) {
                if ($group['name'] == $pconfig['name']) {
                    $input_errors[] = gettext("Another entry with the same group name already exists.");
                    break;
                }
            }

            $sys_groups = file_get_contents('/etc/group');
            foreach (explode("\n", $sys_groups) as $line) {
                if (explode(":", $line)[0] ==  $pconfig['name']) {
                    $input_errors[] = gettext("That groupname is reserved by the system.");
                }
            }
        }

        if (count($input_errors) == 0) {
            $group = array();
            if (isset($id) && $a_group[$id]) {
                $group = $a_group[$id];
            }
            $prev_members = !empty($group['member']) ? $group['member'] : array();

            $group['name'] = $pconfig['name'];
            $group['description'] = $pconfig['description'];

            if (empty($pconfig['members'])) {
                unset($group['member']);
            } else {
                $group['member'] = $pconfig['members'];
            }

            if (isset($id) && $a_group[$id]) {
                $a_group[$id] = $group;
            } else {
                $group['gid'] = $config['system']['nextgid']++;
                $a_group[] = $group;
            }

            local_group_set($group);

            /* Refresh users in this group since their privileges may have changed.
               XXX: it looks like local_user_set's intend is to only change group assignments, if that's
                    the case, it should be safe to drop the block below and let configd handle it.
            */

            if (is_array($group['member'])) {
                foreach ($a_user as & $user) {
                    if (in_array($user['uid'], $group['member'])) {
                        local_user_set($user);
                    }
                }
            }
            if (isset($id) && $a_group[$id]) {
                $audit_msg = sprintf("group \"%s\" changed", $group['name']);
            } else {
                $audit_msg = sprintf("group \"%s\" created", $group['name']);
            }
            write_config($audit_msg);
            // XXX: signal backend which users have changed.
            //      core_user_changed_groups() would change local group assignments in that case as well.
            $new_members = !empty($group['member']) ? $group['member'] : array();
            $all_members = array_merge($prev_members, $new_members);
            foreach ($all_members as $member) {
                if (!in_array($member, $prev_members) || !in_array($member, $new_members)) {
                    foreach ($a_user as & $user) {
                        if ($user['uid'] == $member) {
                            configdp_run('auth user changed', [$user['name']]);
                        }
                    }
                }
            }
            header(url_safe('Location: /system_groupmanager.php'));
            exit;
        } else {
            // input errors, load page in edit mode
            $act = 'edit';
        }
    } else {
        // POST without a valid action, redirect to overview
        header(url_safe('Location: /system_groupmanager.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_group);

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<script>
$( document ).ready(function() {
    // remove group
    $(".act-del-group").click(function(event){
      var groupid = $(this).data('groupid');
      var groupname = $(this).data('groupname');
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("Group");?>",
          message: '<?=gettext("Do you really want to delete this group?");?>' + '<br/>('+groupname+")",
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#groupid").val(groupid);
                      $("#groupname").val(groupname);
                      $("#act").val("delgroup");
                      $("#iform2").submit();
                  }
          }]
      });
    });
    $("#add_users").click(function(){
        $("#members").append($("#notmembers option:selected"));
        $("#notmembers option:selected").remove();
        $("#members option:selected").prop('selected', false);
    });
    $("#add_groups").click(function(){
        $("#notmembers").append($("#members option:selected"));
        $("#members option:selected").remove();
        $("#notmembers option:selected").prop('selected', false);
    });
    $("#save").click(function(){
        $("#members > option").prop('selected', true);
        $("#notmembers > option").prop('selected', false);
    });
});
</script>



<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors)) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12 table-responsive">
<?php
        if ($act == "new" || $act == "edit") :?>
          <form method="post" name="iform" id="iform">
            <input type="hidden" id="act" name="act" value="" />
            <input type="hidden" id="groupid" name="groupid" value="<?=(isset($id) ? $id : '');?>" />
            <input type="hidden" id="privid" name="privid" value="" />
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Defined by");?></td>
                <td>
                  <strong><?=strtoupper($pconfig['scope']);?></strong>
                  <input name="scope" type="hidden" value="<?=$pconfig['scope']?>"/>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Group name");?></td>
                <td>
                  <input name="name" type="text" maxlength="32" value="<?=$pconfig['name'];?>" <?=$pconfig['scope'] == "system" ? "readonly=\"readonly\"" : "";?> />
                </td>
              </tr>
              <tr>
                <td><a id="help_for_desc" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                <td>
                  <input name="description" type="text" value="<?=$pconfig['description'];?>" />
                  <div class="hidden" data-for="help_for_desc">
                    <?=gettext("Group description, for your own information only");?>
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
                          <select size="10" name="notmembers[]" id="notmembers" onchange="clear_selected('members')" multiple="multiple">
<?php
                          foreach ($config['system']['user'] as $user) :
                              if (is_array($pconfig['members']) && in_array($user['uid'], $pconfig['members'])) {
                                  continue;
                              }
?>
                            <option value="<?=$user['uid'];?>">
                                <?=htmlspecialchars($user['name']);?>
                            </option>
<?php
                          endforeach;?>
                          </select>
                        </td>
                        <td class="text-center">
                          <br />
                          <a id="add_users" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("Add Users"); ?>">
                              <span class="fa fa-arrow-right fa-fw"></span>
                          </a>
                          <br /><br />
                          <a id="add_groups" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("Remove Users"); ?>">
                              <span class="fa fa-arrow-left fa-fw"></span>
                          </a>
                        </td>
                        <td>
                          <select size="10" name="members[]" id="members" onchange="clear_selected('notmembers')" multiple="multiple">
<?php
                          foreach ($config['system']['user'] as $user) :
                              if (!(is_array($pconfig['members']) && in_array($user['uid'], $pconfig['members']))) {
                                  continue;
                              }
?>
                            <option value="<?=$user['uid'];?>">
                                <?=htmlspecialchars($user['name']);?>
                            </option>
<?php
                          endforeach;
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
              if ($act != "new") :?>
              <tr>
                <td><b><?=gettext("Assigned Privileges");?></b></td>
                <td>
                  <table class="table table-hover table-condensed">
                    <tr>
                      <td><b><?=gettext("Type");?></b></td>
                      <td><b><?=gettext("Name");?></b></td>
                    </tr>
<?php
                    if (isset($pconfig['priv']) && is_array($pconfig['priv'])) :
                        foreach ($pconfig['priv'] as $priv) :
                    ?>
                    <tr>
                      <td>
<?php
                             switch (substr($priv, 0, 5)) {
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
                      <td><?=$priv_list[$priv]['name'];?></td>
                    </tr>
<?php
                        endforeach;
                    endif;?>
                    <tr>
                      <td colspan="2">
                        <a href="system_usermanager_addprivs.php?groupid=<?=htmlspecialchars($id)?>" class="btn btn-default btn-xs">
                          <span class="fa fa-pencil fa-fw"></span>
                        </a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
<?php
              endif;?>
              <tr>
                <td></td>
                <td>
                  <input name="save" id="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                  <input type="button" class="btn btn-default" value="<?=html_safe(gettext("Cancel"));?>" onclick="window.location.href='/system_groupmanager.php'" />
<?php
                  if (isset($id)) :?>
                  <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                  <input name="gid" type="hidden" value="<?=htmlspecialchars($pconfig['gid']);?>" />
<?php
                  endif; ?>
                </td>
              </tr>
            </table>
          </form>
<?php
          else :?>
          <form method="post" name="iform2" id="iform2">
            <input type="hidden" id="act" name="act" value="" />
            <input type="hidden" id="groupid" name="groupid" value="<?=(isset($id) ? $id : "");?>" />
            <input type="hidden" id="groupname" name="groupname" value="" />
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("Group name");?></th>
                  <th><?=gettext("Member Count");?></th>
                  <th><?=gettext("Description");?></th>
                  <th class="text-nowrap">
                     <a href="system_groupmanager.php?act=new" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                       <i class="fa fa-plus fa-fw"></i>
                    </a>
                  </th>
                </tr>
              </thead>
              <tbody>
<?php
              /* create a copy for sorting */
              $a_group_ro = $a_group;
              uasort($a_group_ro, function($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
              });
              foreach ($a_group_ro as $i => $group): ?>
                <tr>
                  <td>
                    <span class="fa fa-user <?= !empty($group['priv']) && in_array('page-all', $group['priv']) ? 'text-danger' : 'text-info' ?>"></span>
                    <?=$group['name']; ?>
                  </td>
                  <td><?=!empty($group['member']) ? count($group['member']) : 0; ?></td>
                  <td><?=$group['description'];?></td>
                  <td class="text-nowrap">
                    <a href="system_groupmanager.php?act=edit&groupid=<?=$i?>"
                       class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                        <span class="fa fa-pencil fa-fw"></span>
                    </a>
<?php if ($group['scope'] != 'system'): ?>
                    <button type="button" class="btn btn-default btn-xs act-del-group"
                        data-groupname="<?=$group['name'];?>"
                        data-groupid="<?=$i?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip">
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
                        <td style="width:200px"><?= gettext('Superuser group') ?></td>
                        <td style="width:20px"><span class="fa fa-user text-info"></span></td>
                        <td style="width:200px"><?= gettext('Normal group') ?></td>
                        <td></td>
                      </tr>
                    </table>
                  </td>
                  <td class="text-nowrap"></td>
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
