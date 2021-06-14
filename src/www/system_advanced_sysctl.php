<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("system.inc");

$a_tunable = &config_read_array('sysctl', 'item');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && isset($a_tunable[$_GET['id']])) {
        $id = $_GET['id'];
    }
    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    } else {
        $act = null;
    }
    $pconfig = array();
    if (isset($id)) {
        $pconfig['tunable'] = $a_tunable[$id]['tunable'];
        $pconfig['value'] = $a_tunable[$id]['value'];
        $pconfig['descr'] = $a_tunable[$id]['descr'];
    } else {
        $pconfig['tunable'] = null;
        $pconfig['value'] = null;
        $pconfig['descr'] = null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && isset($a_tunable[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (isset($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }
    $pconfig = $_POST;

    if (isset($id) && $act == "del") {
        unset($a_tunable[$id]);
        write_config();
        mark_subsystem_dirty('sysctl');
        header(url_safe('Location: /system_advanced_sysctl.php'));
        exit;
    } else if ($act == 'reset') {
        // reset tunables to factory defaults (when available)
        if (file_exists('/usr/local/etc/config.xml.sample')) {
            $factory_config = load_config_from_file('/usr/local/etc/config.xml.sample');
            if (!empty($factory_config['sysctl']) && !empty($factory_config['sysctl']['item'])){
                $a_tunable = $factory_config['sysctl']['item'];
                mark_subsystem_dirty('sysctl');
                write_config();
            }
        }
        header(url_safe('Location: /system_advanced_sysctl.php'));
        exit;
    } else if (!empty($pconfig['apply'])) {
        system_sysctl_configure();
        system_login_configure();
        clear_subsystem_dirty('sysctl');
        header(url_safe('Location: /system_advanced_sysctl.php'));
        exit;
    } elseif (!empty($pconfig['Submit'])) {
        $tunableent = array();
        $tunableent['tunable'] = $pconfig['tunable'];
        $tunableent['value'] = $pconfig['value'];
        $tunableent['descr'] = $pconfig['descr'];

        if (isset($id)) {
            $a_tunable[$id] = $tunableent;
        } else {
            $a_tunable[] = $tunableent;
        }

        mark_subsystem_dirty('sysctl');
        write_config();
        header(url_safe('Location: /system_advanced_sysctl.php'));
        exit;
    }
}

/* translate hidden strings before HTML escape */
foreach ($a_tunable as &$tunable) {
    if (!empty($tunable['descr'])) {
        $tunable['descr'] = gettext($tunable['descr']);
    }
}

uasort($a_tunable, function($a, $b) {
    return strnatcmp($a['tunable'], $b['tunable']);
});

include("head.inc");

legacy_html_escape_form_data($a_tunable);
legacy_html_escape_form_data($pconfig);

?>
<body>
<script>
$( document ).ready(function() {
  // delete entry
  $(".act_delete").click(function(event){
    event.preventDefault();
    var id = $(this).data('id');
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext("Tunable");?>",
      message: "<?=gettext("Do you really want to delete this entry?");?>",
      buttons: [{
                label: "<?=gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?=gettext("Yes");?>",
                action: function(dialogRef) {
                  $("#id").val(id);
                  $("#action").val("del");
                  $("#iform").submit()
              }
            }]
    });
  });

  if ($("a[href='#set_defaults']").length > 0) {
      $("a[href='#set_defaults']").click(function(event){
          event.preventDefault();
          BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= gettext("Tunable");?>",
            message: "<?=gettext("Are you sure you want to reset all tunables back to factory defaults?");?>",
            buttons: [{
                      label: "<?=gettext("No");?>",
                      action: function(dialogRef) {
                          dialogRef.close();
                      }}, {
                      label: "<?=gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#action").val("reset");
                        $("#iform").submit()
                    }
                  }]
          });
      });
  }
});
</script>

<?php include("fbegin.inc"); ?>


<!-- row -->
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
        if (isset($savemsg)) {
            print_info_box($savemsg);
        }
        if (is_subsystem_dirty('sysctl') && ($act != "edit" )) {
            print_info_box_apply(gettext('The firewall tunables have changed. You must apply the configuration to take affect.'). '<br>' .gettext('Tunables are composed of runtime settings for sysctl.conf which take effect ' .
                    'immediately after apply and boot settings for loader.conf which require a reboot.'));
        }
?>
      <form method="post" id="iform">
        <input type="hidden" id="id" name="id" value="" />
        <input type="hidden" id="action" name="act" value="" />
      </form>
      <section class="col-xs-12">
        <div class="table-responsive content-box tab-content" style="overflow: auto;">
<?php if ($act != 'edit'): ?>
          <table class="table table-striped">
            <tr>
              <th><?=gettext("Tunable Name"); ?></th>
              <th><?=gettext("Description"); ?></th>
              <th><?=gettext("Value"); ?></th>
              <th class="text-nowrap">
<?php if ($act != 'edit'): ?>
                <a href="system_advanced_sysctl.php?act=edit" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                  <i class="fa fa-plus fa-fw"></i>
                </a>
                <a href="#set_defaults" class="btn btn-danger btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Default')) ?>">
                  <i class="fa fa-trash-o fa-fw"></i>
                </a>
<?php endif ?>
              </th>
            </tr>
<?php foreach ($a_tunable as $i => &$tunable): ?>
              <tr>
                <td><?=$tunable['tunable']; ?></td>
                <td><?=$tunable['descr']; ?></td>
                <td>
                  <?=$tunable['value']; ?>
                  <?=$tunable['value'] == "default" ? "(" . get_default_sysctl_value($tunable['tunable']) . ")" : "";?>
                </td>
                <td class="text-nowrap">
                  <a href="system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("Edit Tunable"); ?>">
                    <i class="fa fa-pencil fa-fw"></i>
                  </a>
                  <a id="del_<?=$i;?>" data-id="<?=$i;?>" title="<?=gettext("Delete Tunable"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                    <span class="fa fa-trash fa-fw"></span>
                  </a>
                </td>
              </tr>
<?php endforeach ?>
            </table>
<?php else: ?>
            <form method="post">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?= gettext('Edit system tunable') ?></strong></td>
                  <td style="width:78%"></td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Tunable"); ?></td>
                  <td>
                    <input type="text" name="tunable" value="<?=$pconfig['tunable']; ?>" />
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Description"); ?></td>
                  <td>
                    <textarea name="descr"><?=$pconfig['descr']; ?></textarea>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Value"); ?></td>
                  <td>
                    <input name="value" type="text" value="<?=$pconfig['value']; ?>" />
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                    <input type="button" class="btn btn-default" value="<?=html_safe(gettext("Cancel"));?>" onclick="window.location.href='/system_advanced_sysctl.php'" />
<?php if (isset($id)): ?>
                    <input name="id" type="hidden" value="<?=$id;?>" />
<?php endif ?>
                  </td>
                </tr>
              </table>
            </form>
<?php endif ?>
          </div>
        </section>
      </div>
  </div>
</section>
<?php include("foot.inc");
