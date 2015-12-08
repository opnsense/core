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

if (!isset($config['system']['group'])) {
    $config['system']['group'] = array();
}
$a_group = &$config['system']['group'];

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
    $act = (isset($pconfig['act']) ? $pconfig['act'] : '');
    if (isset($id) && $act == "delgroup" && isset($pconfig['groupname']) && $pconfig['groupname'] == $a_group[$id]['name']) {
        // remove group
        local_group_del($a_group[$id]);
        $groupdeleted = $a_group[$id]['name'];
        unset($a_group[$id]);
        write_config();
        // reload page
        header("Location: system_groupmanager.php");
        exit;
    } elseif (isset($id) && $act == "delpriv" && isset($a_group[$id]['priv']) && is_array($a_group[$id]['priv'])) {
        // remove by privid
        foreach ($a_group[$id]['priv'] as $key => $value) {
            if ($value == $pconfig['privid']) {
                unset($a_group[$id]['priv'][$key]);
            }
        }
        if (isset($a_group[$id]['member']) && is_array($a_group[$id]['member'])) {
            foreach ($a_group[$id]['member'] as $uid) {
                $user = getUserEntryByUID($uid);
                if ($user) {
                    local_user_set($user);
                }
            }
        }
        write_config();
        // reload page
        header("Location: system_groupmanager.php?act=edit&groupid={$id}");
        exit;
    } elseif (isset($pconfig['save'])) {
        $input_errors = array();

        /* input validation */
        $reqdfields = explode(" ", "name");
        $reqdfieldsn = array(gettext("Group Name"));

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (preg_match("/[^a-zA-Z0-9\.\-_ ]/", $pconfig['name'])) {
            $input_errors[] = gettext("The group name contains invalid characters.");
        }

        if (strlen($pconfig['name']) > 16) {
            $input_errors[] = gettext("The group name is longer than 16 characters.");
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

            /* Refresh users in this group since their privileges may have changed. */
            if (is_array($group['member'])) {
                $a_user = &$config['system']['user'];
                foreach ($a_user as & $user) {
                    if (in_array($user['uid'], $group['member'])) {
                        local_user_set($user);
                    }
                }
            }
            write_config();
            header("Location: system_groupmanager.php");
            exit;
        } else {
            // input errors, load page in edit mode
            $act = 'edit';
        }
    } else {
        // POST without a valid action, redirect to overview
        header("Location: system_groupmanager.php");
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_group);

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
function setall_selected(id) {
    selbox = document.getElementById(id);
    count = selbox.options.length;
    for (index = 0; index<count; index++) {
        selbox.options[index].selected = true;
    }
}

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
    clear_selected('notmembers');
    setall_selected('members');
}


$( document ).ready(function() {
  // delete privilege
  $(".act-del-priv").click(function(event){
      event.preventDefault();
      var priv_name = $(this).data('privname');
      var privid = $(this).data('privid');
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("Group");?>",
          message: "<?=gettext("Do you really want to delete this privilege?");?> " + "<br/>("+priv_name+")",
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#privid").val(privid);
                      $("#act").val("delpriv");
                      $("#iform").submit();
                  }
          }]
      });
    });

    // remove group
    $(".act-del-group").click(function(event){
      var groupid = $(this).data('groupid');
      var groupname = $(this).data('groupname');
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
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
});
//]]>
</script>



<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
      if (isset($input_errors) && count($input_errors) > 0) {
          print_input_errors($input_errors);
      }
?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12 table-responsive">
<?php
        if ($act == "new" || $act == "edit") :?>
          <form action="system_groupmanager.php" method="post" name="iform" id="iform" onsubmit="presubmit()">
            <input type="hidden" id="act" name="act" value="" />
            <input type="hidden" id="groupid" name="groupid" value="<?=(isset($id) ? $id : '');?>" />
            <input type="hidden" id="privid" name="privid" value="" />
            <table class="table table-striped">
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
                  <input name="name" type="text" maxlength="16" value="<?=$pconfig['name'];?>" <?=$pconfig['scope'] == "system" ? "readonly=\"readonly\"" : "";?> />
                </td>
              </tr>
              <tr>
                <td><a id="help_for_desc" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                <td>
                  <input name="description" type="text" value="<?=$pconfig['description'];?>" />
                  <div class="hidden" for="help_for_desc">
                    <?=gettext("Group description, for your own information only");?>
                  </div>
                </td>
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
                          <a href="javascript:move_selected('notmembers','members')" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"  title="<?=gettext("Add Groups"); ?>">
                              <span class="glyphicon glyphicon-arrow-right"></span>
                          </a>
                          <br /><br />
                          <a href="javascript:move_selected('members','notmembers')" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"  title="<?=gettext("Remove Groups"); ?>">
                              <span class="glyphicon glyphicon-arrow-left"></span>
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
                  <div class="hidden" for="help_for_groups">
                      <?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
                  </div>
                </td>
              </tr>
<?php
              if ($act != "new") :?>
              <tr>
                <td colspan="2"><b><?=gettext("Assigned Privileges");?></b></td>
              </tr>
              <tr>
                <td colspan="2">
                  <table class="table table-striped table-condensed">
                    <tr>
                      <td><b><?=gettext("Name");?></b></td>
                      <td><b><?=gettext("Description");?></b></td>
                      <td></td>
                    </tr>
<?php
                    if (isset($pconfig['priv']) && is_array($pconfig['priv'])) :
                        foreach ($pconfig['priv'] as $priv) :
                    ?>
                    <tr>
                      <td><?=$priv_list[$priv]['name'];?></td>
                      <td><?=$priv_list[$priv]['descr'];?></td>
                      <td>
                          <button type="button" data-privid="<?=$priv;?>" data-privname="<?=$priv_list[$priv]['name']?>" class="btn btn-default btn-xs act-del-priv" title="<?=gettext("delete privilege");?>" data-toggle="tooltip" data-placement="left">
                            <span class="glyphicon glyphicon-remove"></span>
                          </button>
                      </td>
                    </tr>
<?php
                        endforeach;
                    endif;?>
                    <tr>
                      <td colspan="2"></td>
                      <td>
                        <a href="system_groupmanager_addprivs.php?groupid=<?=htmlspecialchars($id)?>" class="btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-plus"></span>
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
                  <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                  <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='/system_groupmanager.php'" />
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
          <form action="system_groupmanager.php" method="post" name="iform2" id="iform2">
            <input type="hidden" id="act" name="act" value="" />
            <input type="hidden" id="groupid" name="groupid" value="<?=(isset($id) ? $id : "");?>" />
            <input type="hidden" id="groupname" name="groupname" value="" />
            <table class="table table-striped table-sort">
              <thead>
                <tr>
                  <th><?=gettext("Group name");?></th>
                  <th class="hidden-xs"><?=gettext("Description");?></th>
                  <th><?=gettext("Member Count");?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
<?php
              $i = 0;
              foreach ($a_group as $group) :?>
                <tr>
                  <td>
                    <span class="glyphicon glyphicon-user <?=$group['scope'] == "system" ? "text-mute" : "text-info";?>"></span>
                    &nbsp;
                    <?=$group['name']; ?>
                  </td>
                  <td class="hidden-xs"><?=$group['description'];?></td>
                  <td>
                    <?=$group["name"] == "all" ?  count($config['system']['user']) :count($group['member']) ;?>
                  </td>
                  <td>
                    <a href="system_groupmanager.php?act=edit&groupid=<?=$i?>"
                       class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left"
                       title="<?=gettext("edit group");?>">
                        <span class="glyphicon glyphicon-pencil"></span>
                    </a>

<?php
                    if ($group['scope'] != "system") :?>
                    <button type="button" class="btn btn-default btn-xs act-del-group"
                        data-groupname="<?=$group['name'];?>"
                        data-groupid="<?=$i?>" title="<?=gettext("delete group");?>" data-toggle="tooltip"
                        data-placement="left" ><span class="glyphicon glyphicon-remove"></span>
                    </button>
<?php
                    endif;?>
                  </td>
                </tr>
<?php
              $i++;
              endforeach;?>
              </tbody>
              <tfoot>
                <tr>
                  <td class="list" colspan="2"></td>
                  <td class="hidden-xs"> </td>
                  <td class="list">
                    <a href="system_groupmanager.php?act=new" class="btn btn-default btn-xs"
                       title="<?=gettext("add user");?>" data-toggle="tooltip" data-placement="left">
                      <span class="glyphicon glyphicon-plus"></span>
                    </a>
                  </td>
                </tr>
                <tr  class="hidden-xs">
                  <td colspan="4">
                    <p class="col-xs-12 col-sm-10">
                      <?=gettext("Additional webConfigurator groups can be added here.
                      Group permissions can be assigned which are inherited by users who are members of the group.
                      An icon that appears grey indicates that it is a system defined object.
                      Some system object properties can be modified but they cannot be deleted.");?>
                    </p>
                  </td>
                </tr>
              </tfoot>
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
