<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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
require_once("filter.inc");

$a_scrub = &config_read_array('filter', 'scrub', 'rule');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['scrubnodf'] = !empty($config['system']['scrubnodf']);
    $pconfig['scrubrnid'] = !empty($config['system']['scrubrnid']);
    $pconfig['scrub_interface_disable'] = !empty($config['system']['scrub_interface_disable']);
    if (!empty($_GET['savemsg'])) {
        $savemsg = gettext('The settings have been applied and the rules are now reloading in the background.');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_scrub[$pconfig['id']])) {
        $id = $pconfig['id'];
    }

    if (isset($pconfig['act']) && $pconfig['act'] == 'edit') {
        // update general settings
        if (!empty($pconfig['scrubnodf'])) {
            $config['system']['scrubnodf'] = "enabled";
        } elseif (isset($config['system']['scrubnodf'])) {
            unset($config['system']['scrubnodf']);
        }
        if (!empty($pconfig['scrubrnid'])) {
            $config['system']['scrubrnid'] = "enabled";
        } elseif (isset($config['system']['scrubrnid'])) {
            unset($config['system']['scrubrnid']);
        }
        if (!empty($pconfig['scrub_interface_disable'])) {
            $config['system']['scrub_interface_disable'] = "enabled";
        } elseif (isset($config['system']['scrub_interface_disable'])) {
            unset($config['system']['scrub_interface_disable']);
        }
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_scrub.php'));
        exit;
    } elseif (isset($pconfig['apply'])) {
        filter_configure();
        clear_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_scrub.php?savemsg=yes'));
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        // delete single item
        unset($a_scrub[$id]);
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_scrub.php'));
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // delete selected rules
        foreach ($pconfig['rule'] as $rule_index) {
            unset($a_scrub[$rule_index]);
        }
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_scrub.php'));
        exit;
    } elseif ( isset($pconfig['act']) && $pconfig['act'] == 'move' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // move selected rules
        if (!isset($id)) {
            // if rule not set/found, move to end
            $id = count($a_scrub);
        }
        $a_scrub = legacy_move_config_list_items($a_scrub, $id,  $pconfig['rule']);
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_scrub.php'));
        exit;

    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'toggle' && isset($id)) {
        // toggle item
        if(isset($a_scrub[$id]['disabled'])) {
            unset($a_scrub[$id]['disabled']);
        } else {
            $a_scrub[$id]['disabled'] = true;
        }
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_scrub.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_scrub);

include("head.inc");

?>
<body>
<script>
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(event){
    event.preventDefault();
    var id = $(this).data("id");
    if (id != 'x') {
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Rules");?>",
        message: "<?=gettext("Do you really want to delete this rule?");?>",
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
    } else {
      // delete selected
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Rules");?>",
        message: "<?=gettext("Do you really want to delete the selected rules?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val("");
                    $("#action").val("del_x");
                    $("#iform").submit()
                }
              }]
      });
    }
  });

  // link move buttons
  $(".act_move").click(function(event){
    event.preventDefault();
    $("#id").val($(this).data("id"));
    $("#action").val("move");
    $("#iform").submit();
  });

  // link toggle buttons
  $(".act_toggle").click(function(event){
    event.preventDefault();
    $("#id").val($(this).data("id"));
    $("#action").val("toggle");
    $("#iform").submit();
  });

  $("#save").click(function(event){
    event.preventDefault();
    $("#action").val("edit");
    $("#iform").submit();
  });

  $("#scrub_interface_disable").change(function(){
    if ($("#scrub_interface_disable:checked").val() == undefined) {
        $(".scrub_settings").show();
    } else{
        $(".scrub_settings").hide();
    }
  });
  $("#scrub_interface_disable").change();

  // select All
  $("#selectAll").click(function(){
      $(".rule_select").prop("checked", $(this).prop("checked"));
  });

  // watch scroll position and set to last known on page load
  watchScrollPosition();

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
        <form method="post" name="iform" id="iform">
          <input type="hidden" id="id" name="id" value="" />
          <input type="hidden" id="action" name="act" value="" />
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive" >
                <table class="table table-striped table-hover opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <td style="width:22%"><strong><?=gettext("General settings");?></strong></td>
                      <td style="width:78%; text-align:right">
                           <small><?=gettext("full help"); ?> </small>
                           <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page">&nbsp;</i>
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_scrub_interface_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable interface scrub");?></td>
                      <td>
                        <input id="scrub_interface_disable" name="scrub_interface_disable" type="checkbox" value="yes" <?=!empty($pconfig['scrub_interface_disable']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" data-for="help_for_scrub_interface_disable">
                          <?=gettext("Disable all default interface scrubing rules,".
                                     " mss clamping will also be disabled when you check this.".
                                     " Detailed settings specified below will still be used.");?>
                        </div>
                      </td>
                    </tr>
                    <tr class="scrub_settings">
                      <td><a id="help_for_scrubnodf" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Do-Not-Fragment");?></td>
                      <td>
                        <input name="scrubnodf" type="checkbox" value="yes" <?=!empty($pconfig['scrubnodf']) ? "checked=\"checked\"" : ""; ?>/>
                        <div class="hidden" data-for="help_for_scrubnodf">
                          <?=gettext("This allows for communications with hosts that generate fragmented " .
                                              "packets with the don't fragment (DF) bit set. Linux NFS is known to " .
                                              "do this. This will cause the filter to not drop such packets but " .
                                              "instead clear the don't fragment bit.");?>
                        </div>
                      </td>
                    </tr>
                    <tr class="scrub_settings">
                      <td><a id="help_for_scrubrnid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Random id");?></td>
                      <td>
                        <input name="scrubrnid" type="checkbox" value="yes" <?= !empty($pconfig['scrubrnid']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" data-for="help_for_scrubrnid">
                          <?=gettext("Replaces the IP identification field of packets with random values to " .
                                              "compensate for operating systems that use predictable values. " .
                                              "This option only applies to packets that are not fragmented after the " .
                                              "optional packet reassembly.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                          <input name="Submit" id="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                      </td>
                    </tr>
                    </tbody>
                  </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive" >
                <table class="table table-striped table-hover" id="rules">
                  <thead>
                    <tr>
                      <th colspan="2"><?=gettext("Detailed settings");?></th>
                      <th colspan="2" class="hidden-xs hidden-sm"> </th>
                      <th colspan="2"> </th>
                    </tr>
                    <tr>
                      <th><input type="checkbox" id="selectAll"></th>
                      <th><?=gettext("Interfaces");?></th>
                      <th class="hidden-xs hidden-sm"><?=gettext("Source");?></th>
                      <th class="hidden-xs hidden-sm"><?=gettext("Destination");?></th>
                      <th><?=gettext("Description");?></th>
                      <th class="text-nowrap">
                        <a href="firewall_scrub_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                          <i class="fa fa-plus fa-fw"></i>
                        </a>
                        <a id="move_<?= count($a_scrub) ?>" name="move_<?= count($a_scrub) ?>_x" data-toggle="tooltip" title="<?= html_safe(gettext("Move selected rules to end")) ?>" class="act_move btn btn-default btn-xs">
                          <span class="fa fa-arrow-left fa-fw"></span>
                        </a>
                        <a data-id="x" title="<?= html_safe(gettext("delete selected rules")) ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="fa fa-trash fa-fw"></span>
                        </a>
                      </th>
                    </tr>
                  </thead>
                <tbody>
<?php
                $special_nets = get_specialnets();
                legacy_html_escape_form_data($special_nets);
                foreach ($a_scrub as $i => $scrubEntry):?>
                  <tr>
                    <td>
                        <input class="rule_select" type="checkbox" name="rule[]" value="<?=$i;?>"  />
                        <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" data-html="true" title="<?=(empty($scrubEntry['disabled'])) ? gettext("Disable") : gettext("Enable");?>">
                          <span class="fa fa-play fa-fw <?=(empty($scrubEntry['disabled'])) ? "text-success" : "text-muted";?>"></span>
                        </a>
                    </td>
                    <td><?=strtoupper($scrubEntry['interface']);?></td>
                    <td class="hidden-xs hidden-sm">
<?php
                        if (is_alias($scrubEntry['src'])):?>
                        <span title="<?=htmlspecialchars(get_alias_description($scrubEntry['src']));?>" data-toggle="tooltip" data-html="true">
                          <?=$scrubEntry['src'];?>&nbsp;
                        </span>
                        <a href="/ui/firewall/alias/index/<?=$scrubEntry['src'];?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list fa-fw"></i>
                        </a>
<?php
                        elseif (!empty($special_nets[$scrubEntry['src']])):?>
                        <?=$special_nets[$scrubEntry['src']];?>
<?php
                        else:?>
                        <?=$scrubEntry['src'];?>
<?php
                        endif;?>

                    </td>
                    <td class="hidden-xs hidden-sm">
<?php
                        if (is_alias($scrubEntry['dst'])):?>
                        <span title="<?=htmlspecialchars(get_alias_description($scrubEntry['dst']));?>" data-toggle="tooltip" data-html="true">
                          <?=$scrubEntry['dst'];?>&nbsp;
                        </span>
                        <a href="/ui/firewall/alias/index/<?=$scrubEntry['dst'];?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list fa-fw"></i>
                        </a>
<?php
                        elseif (!empty($special_nets[$scrubEntry['dst']])):?>
                        <?=$special_nets[$scrubEntry['dst']];?>
<?php
                        else:?>
                        <?=$scrubEntry['dst'];?>
<?php
                        endif;?>

                    </td>
                    <td>
                        <?=$scrubEntry['descr'];?>
                    </td>
                    <td>
                        <a data-id="<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?= html_safe(gettext("Move selected rules before this rule")) ?>" class="act_move btn btn-default btn-xs">
                          <span class="fa fa-arrow-left fa-fw"></span>
                        </a>
                        <a href="firewall_scrub_edit.php?id=<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs">
                          <span class="fa fa-pencil fa-fw"></span>
                        </a>
                        <a data-id="<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="fa fa-trash fa-fw"></span>
                        </a>
                        <a href="firewall_scrub_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>">
                          <span class="fa fa-clone fa-fw"></span>
                        </a>
                    </td>
                  </tr>
<?php
                  endforeach;
?>
                </tbody>
                <tfoot>
                    <tr>
                      <td colspan="3">
                        <a><i class="fa fa-list fa-fw"></i></a> <?=gettext("Alias (click to view/edit)");?>
                      </td>
                      <td colspan="2" class="hidden-xs hidden-sm"></td>
                      <td></td>
                    </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </section>
      </form>
    </div>
  </div>
</section>
<?php

include("foot.inc");
