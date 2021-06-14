<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2009 Ermal Luçi
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
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
require_once("filter.inc");
require_once("system.inc");

$a_ifgroups = &config_read_array('ifgroups', 'ifgroupentry');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($a_ifgroups[$_POST['id']])) {
        $id = $_POST['id'];
    }

    if (isset($_POST['apply'])) {
        system_cron_configure();
        filter_configure();
        clear_subsystem_dirty('filter');
        $savemsg = gettext('The settings have been applied and the rules are now reloading in the background.');
    } elseif (!empty($_POST['action']) && $_POST['action'] == "del" && isset($id)) {
        $members = explode(" ", $a_ifgroups[$id]['members']);
        foreach ($members as $ifs) {
            mwexecf('/sbin/ifconfig %s -group %s', array(get_real_interface($ifs), $a_ifgroups[$id]['ifname']));
        }
        unset($a_ifgroups[$id]);
        write_config();
        header(url_safe('Location: /interfaces_groups.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_ifgroups);

include("head.inc");

?>
<body>
  <script>
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Group");?>",
        message: "<?=gettext("Do you really want to delete this group? All elements that still use it will become invalid (e.g. filter rules)!");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#action").val("del");
                    $("#iform").submit()
                }
              }]
      });
    });

  });
  </script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php print_service_banner('firewall'); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <?php if (is_subsystem_dirty('filter')): ?><p>
        <?php print_info_box_apply(gettext("The firewall rule configuration has been changed.<br />You must apply the changes in order for them to take effect."));?>
        <?php endif; ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form  method="post" name="iform" id="iform">
              <input type="hidden" id="action" name="action" value="">
              <input type="hidden" id="id" name="id" value="">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("Name");?></th>
                    <th><?=gettext("Members");?></th>
                    <th><?=gettext("Description");?></th>
                    <th class="text-nowrap">
                      <a href="interfaces_groups_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                        <i class="fa fa-plus fa-fw"></i>
                      </a>
		    </th>
                  </tr>
                </thead>
                <tbody>
<?php
                $i = 0;
                foreach ($a_ifgroups as $ifgroupentry): ?>
                  <tr>
                    <td>
                      <a href="/firewall_rules.php?if=<?=$ifgroupentry['ifname'];?>"><?=$ifgroupentry['ifname'];?></a>
                    </td>
                    <td>
<?php
                    $iflist = legacy_config_get_interfaces();
                    foreach (explode(" ", $ifgroupentry['members']) as $id => $memb):?>
                      <?=$id > 0 ? "," : "";?>
                      <?=!empty($iflist[$memb]) ? $iflist[$memb]['descr'] : $memb;?>
<?php
                    endforeach;?>
                    </td>
                    <td><?=$ifgroupentry['descr'];?></td>
                    <td class="text-nowrap">
                      <a href="interfaces_groups_edit.php?id=<?=$i;?>" class="btn btn-xs btn-default" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                        <i class="fa fa-pencil fa-fw"></i>
                      </a>
                      <button title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" data-id="<?=$i;?>" class="btn btn-default btn-xs act_delete" type="submit">
                        <i class="fa fa-trash fa-fw"></i>
                      </button>
                    </td>
                  </tr>
<?php
                $i++;
                endforeach; ?>
                  <tr>
                    <td colspan="4">
                      <?=gettext("Interface Groups allow you to create rules that apply to multiple interfaces without duplicating the rules. If you remove members from an interface group, the group rules no longer apply to that interface.");?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
