<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2009 Janne Enberg <janne.enberg@lietu.net>
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
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
require_once("interfaces.inc");
require_once("filter.inc");

/****f* itemid/delete_id (duplicate to remove itemid.inc)
 * NAME
 *   delete_id - delete an item with ['id'] = $id from $array
 * INPUTS
 *   $id       - int: The ID to delete
 *   $array    - array to delete the item from
 * RESULT
 *   boolean   - true if item was found and deleted
 ******/
function delete_id($id, &$array)
{
    // Index to delete
    $delete_index = NULL;

    if (!isset($array)) {
        return false;
    }

    // Search for the item in the array
    foreach ($array as $key => $item){
        // If this item is the one we want to delete
        if(isset($item['associated-rule-id']) && $item['associated-rule-id']==$id ){
            $delete_index = $key;
            break;
        }
    }

    // If we found the item, unset it
    if( $delete_index!==NULL ){
        unset($array[$delete_index]);
        return true;
    } else {
        return false;
    }
}


$a_nat = &config_read_array('nat', 'rule');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_nat[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }
    if (isset($pconfig['apply'])) {
        write_config();
        filter_configure();
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('natconf');
        clear_subsystem_dirty('filter');
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        // delete nat rule and associated rule if it exists
        if (isset($a_nat[$id]['associated-rule-id'])) {
            delete_id($a_nat[$id]['associated-rule-id'], $config['filter']['rule']);
            mark_subsystem_dirty('filter');
        }
        unset($a_nat[$id]);
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat.php'));
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        /* delete selected rules */
        foreach ($pconfig['rule'] as $rulei) {
            if (isset($a_nat[$rulei])) {
                $target = $rule['target'];
                // Check for filter rule associations
                if (isset($a_nat[$rulei]['associated-rule-id'])){
                    delete_id($a_nat[$rulei]['associated-rule-id'], $config['filter']['rule']);
                    mark_subsystem_dirty('filter');
                }
                unset($a_nat[$rulei]);
            }
        }
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat.php'));
        exit;
    } elseif (isset($pconfig['act']) && in_array($pconfig['act'], array('toggle_enable', 'toggle_disable')) && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        foreach ($pconfig['rule'] as $rulei) {
            $a_nat[$rulei]['disabled'] = $pconfig['act'] == 'toggle_disable';
        }
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_nat.php'));
        exit;
    } elseif ( isset($pconfig['act']) && $pconfig['act'] == 'move') {
        // move records
        if (isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
            // if rule not set/found, move to end
            if (!isset($id)) {
                $id = count($a_nat);
            }
            $a_nat = legacy_move_config_list_items($a_nat, $id,  $pconfig['rule']);
        }
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat.php'));
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'toggle' && isset($id)) {
        // toggle item
        if(isset($a_nat[$id]['disabled'])) {
            unset($a_nat[$id]['disabled']);
        } else {
            $a_nat[$id]['disabled'] = true;
        }
        write_config('Firewall: NAT: Outbound, toggle NAT rule');
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat.php'));
        exit;
    }
}

include("head.inc");

legacy_html_escape_form_data($a_nat);

$lockout_spec = filter_core_get_antilockout();

$main_buttons = array(
    array('label' => gettext('Add'), 'href' => 'firewall_nat_edit.php'),
);

?>

<body>
<script>
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(event){
    event.preventDefault();
    var id = $(this).attr("id").split('_').pop(-1);
    if (id != 'x') {
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Port Forward");?>",
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
        title: "<?= gettext("Port Forward");?>",
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

  // enable/disable selected
  $(".act_toggle_enable").click(function(event){
    event.preventDefault();
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext("Rules");?>",
      message: "<?=gettext("Enable selected rules?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $("#id").val("");
                  $("#action").val("toggle_enable");
                  $("#iform").submit()
              }
            }]
    });
  });
  $(".act_toggle_disable").click(function(event){
    event.preventDefault();
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext("Rules");?>",
      message: "<?=gettext("Disable selected rules?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $("#id").val("");
                  $("#action").val("toggle_disable");
                  $("#iform").submit()
              }
            }]
    });
  });

  // link move buttons
  $(".act_move").click(function(event){
    event.preventDefault();
    var id = $(this).attr("id").split('_').pop(-1);
    $("#id").val(id);
    $("#action").val("move");
    $("#iform").submit();
  });

  // link toggle buttons
  $(".act_toggle").click(function(event){
    event.preventDefault();
    var id = $(this).attr("id").split('_').pop(-1);
    $("#id").val(id);
    $("#action").val("toggle");
    $("#iform").submit();
  });

  // select All
  $("#selectAll").click(function(){
      $(".rule_select").prop("checked", $(this).prop("checked"));
  });

  // move category block
  $("#category_block").detach().appendTo($(".page-content-head > .container-fluid > .list-inline"));
  $("#category_block").addClass("pull-right");

  // our usual zebra striping doesn't respect hidden rows, hook repaint on .opnsense-rules change() and fire initially
  $(".opnsense-rules > tbody > tr").each(function(){
      // save zebra color
      let tr_color = $(this).children(0).css("background-color");
      if (tr_color != 'transparent' && !tr_color.includes('(0, 0, 0')) {
          $("#fw_category").data('stripe_color', tr_color);
      }
  });
  $(".opnsense-rules").removeClass("table-striped");
  $(".opnsense-rules").change(function(){
      $(".opnsense-rules > tbody > tr:visible").each(function (index) {
          $(this).css("background-color", "inherit");
          if ( index % 2 == 0) {
              $(this).css("background-color", $("#fw_category").data('stripe_color'));
          }
      });
  });

  // hook category functionality
  hook_firewall_categories();

  // watch scroll position and set to last known on page load
  watchScrollPosition();
});
</script>
<?php include("fbegin.inc"); ?>
  <div class="hidden">
    <div id="category_block" style="z-index:-100;">
        <select class="selectpicker hidden-xs hidden-sm hidden-md" data-live-search="true" data-size="5"  multiple title="<?=gettext("Select category");?>" id="fw_category">
        </select>
    </div>
  </div>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php   print_service_banner('firewall'); ?>
<?php   if (isset($savemsg)) print_info_box($savemsg); ?>
<?php   if (is_subsystem_dirty('natconf')): ?>
<?php     print_info_box_apply(gettext("The NAT configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
<?php   endif; ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <div class="table-responsive">
                <table class="table table-striped opnsense-rules">
                  <thead>
                    <tr>
                      <td colspan="5"> </td>
                      <td class="hidden-xs hidden-sm" colspan="2"><?=gettext("Source");?></td>
                      <td class="hidden-xs hidden-sm" colspan="2"><?=gettext("Destination");?></td>
                      <td colspan="2"><?=gettext("NAT");?></td>
                      <td colspan="2"> </td>
                    </tr>
                    <tr>
                      <th style="width:2%"><input type="checkbox" id="selectAll"></th>
                      <th style="width:2%">&nbsp;</th>
                      <th style="width:2%">&nbsp;</th>
                      <th><?=gettext("Interface");?></th>
                      <th><?=gettext("Proto");?></th>
                      <th class="hidden-xs hidden-sm"><?=gettext("Address");?></th>
                      <th class="hidden-xs hidden-sm"><?=gettext("Ports");?></th>
                      <th class="hidden-xs hidden-sm"><?=gettext("Address");?></th>
                      <th class="hidden-xs hidden-sm"><?=gettext("Ports");?></th>
                      <th><?=gettext("IP");?></th>
                      <th><?=gettext("Ports");?></th>
                      <th><?=gettext("Description");?></th>
                      <th>&nbsp;</th>
                  </tr>
                </thead>
                <tbody>
<?php foreach ($lockout_spec as $lockout_intf => $lockout_prts): ?>
                  <tr>
                    <td></td>
                    <td><i class="fa fa-exclamation fa-fw text-success"></i></td>
                    <td></td>
                    <td><?= html_safe(convert_friendly_interface_to_friendly_descr($lockout_intf)) ?></td>
                    <td>TCP</td>
                    <td class="hidden-xs hidden-sm">*</td>
                    <td class="hidden-xs hidden-sm">*</td>
                    <td class="hidden-xs hidden-sm"><?= html_safe(sprintf(gettext('%s address'), convert_friendly_interface_to_friendly_descr($lockout_intf))) ?></td>
                    <td class="hidden-xs hidden-sm"><?= html_safe(implode(', ', $lockout_prts)) ?></td>
                    <td>*</td>
                    <td>*</td>
                    <td><?= gettext('Anti-Lockout Rule') ?></td>
                    <td>
                      <a href="system_advanced_firewall.php" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                    </td>
                  </tr>
<?php endforeach ?>
<?php               $nnats = 0;
                    foreach ($a_nat as $natent):
?>
                    <tr class="rule <?=isset($natent['disabled'])?"text-muted":"";?>" data-category="<?=!empty($natent['category']) ? $natent['category'] : "";?>" ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                      <td>
                        <input class="rule_select" type="checkbox" name="rule[]" value="<?=$nnats;?>"  />
                      </td>
                      <td>
<?php                 if (isset($natent['nordr'])): ?>
                        <i class="fa fa-exclamation fa-fw <?=isset($natent['disabled']) ? "text-muted" : "text-success" ;?>"></i>
<?php                 endif; ?>
                      </td>
                      <td>
                        <a href="#" class="act_toggle" id="toggle_<?=$nnats;?>" data-toggle="tooltip" title="<?=(!isset($natent['disabled'])) ? gettext("Disable") : gettext("Enable");?>">
<?php                     if (!empty($natent['associated-rule-id'])): ?>
<?php                     if(isset($natent['disabled'])):?>
                          <i class="fa fa-arrows-h fa-fw text-muted"></i>
<?php                        else:?>
                          <i class="fa fa-arrows-h fa-fw text-success"></i>
<?php                     endif; ?>
<?php                        elseif(isset($natent['disabled'])):?>
                          <i class="fa fa-play fa-fw text-muted"></i>
<?php                        else:?>
                          <i class="fa fa-play fa-fw text-success"></i>
<?php                     endif; ?>
                        </a>
                      </td>
                      <td>
<?php
                          foreach (explode(",", $natent['interface']) as $intf):?>
                              <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($intf));?>
<?php
                          endforeach;?>
                      </td>
                      <td>
                        <?=strtoupper($natent['protocol']);?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['source']['address']) && is_alias($natent['source']['address'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['source']['address']));?>" data-toggle="tooltip" data-html="true">
                            <?=htmlspecialchars(pprint_address($natent['source'])); ?>
                          </span>
                          <a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['source']['address']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_address($natent['source'])); ?>
<?php                   endif; ?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['source']['port']) && is_alias($natent['source']['port'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['source']['port']));?>" data-toggle="tooltip" data-html="true">
                            <?=htmlspecialchars(pprint_port($natent['source']['port'])); ?>&nbsp;
                          </span>
                          <a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['source']['port']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_port(isset($natent['source']['port']) ? $natent['source']['port'] : null)); ?>
<?php                   endif; ?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['destination']['address']) && is_alias($natent['destination']['address'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['destination']['address']));?>" data-toggle="tooltip" data-html="true">
                            <?=htmlspecialchars(pprint_address($natent['destination'])); ?>
                          </span>
                          <a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['destination']['address']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_address($natent['destination'])); ?>
<?php                   endif; ?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['destination']['port']) && is_alias($natent['destination']['port'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['destination']['port']));?>" data-toggle="tooltip" data-html="true">
                            <?=htmlspecialchars(pprint_port($natent['destination']['port'])); ?>&nbsp;
                          </span>
                          <a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['destination']['port']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_port(isset($natent['destination']['port']) ? $natent['destination']['port'] : null)); ?>
<?php                   endif; ?>
                      </td>

                      <td>
                        <span title="<?=htmlspecialchars(get_alias_description($natent['target']));?>" data-toggle="tooltip" data-html="true">
                          <?=$natent['target'];?>
                        </span>

<?php                   if (is_alias($natent['target'])): ?>
                        &nbsp;<a href="/ui/firewall/alias/index/<?=$natent['target'];?>"
                                 title="<?=gettext("edit alias");?>" data-toggle="tooltip"><i class="fa fa-list"></i> </a>
<?php                   endif; ?>
                      </td>

                      <td>
<?php
                        $localport = $natent['local-port'];
                         if (strpos($natent['destination']['port'],'-') !== false) {
                            list($dstbeginport, $dstendport) = explode("-", $natent['destination']['port']);
                            $localendport = $natent['local-port'] + $dstendport - $dstbeginport;
                            $localport   .= '-' . $localendport;
                        }
?>
<?php                   if (isset($natent['local-port']) && is_alias($natent['local-port'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($localport));?>" data-toggle="tooltip" data-html="true">
                            <?=htmlspecialchars(pprint_port($localport));?>&nbsp;
                          </span>
                          <a href="/ui/firewall/alias/index/<?=htmlspecialchars($localport);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_port($localport));?>
<?php                   endif; ?>
                      </td>

                      <td>
                        <?=$natent['descr'];?>
                      </td>

                      <td>
                        <a type="submit" id="move_<?=$nnats;?>" name="move_<?=$nnats;?>_x" data-toggle="tooltip" title="<?=html_safe(gettext("Move selected rules before this rule"))?>" class="act_move btn btn-default btn-xs">
                          <i class="fa fa-arrow-left fa-fw"></i>
                        </a>
                        <a href="firewall_nat_edit.php?id=<?=$nnats;?>" data-toggle="tooltip" title="<?=html_safe(gettext("Edit"))?>" class="btn btn-default btn-xs">
                          <i class="fa fa-pencil fa-fw"></i>
                        </a>
                        <a id="del_<?=$nnats;?>" title="<?=html_safe(gettext("Delete"))?>" data-toggle="tooltip" class="act_delete btn btn-default btn-xs">
                          <i class="fa fa-trash fa-fw"></i>
                        </a>
                        <a href="firewall_nat_edit.php?dup=<?=$nnats;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=html_safe(gettext("Clone"))?>">
                          <i class="fa fa-clone fa-fw"></i>
                        </a>
                      </td>
                     </tr>
<?php $nnats++; endforeach; ?>
<?php if ($nnats != 0): ?>
                    <tr>
                      <td colspan="8"></td>
                      <td class="hidden-xs hidden-sm" colspan="4"> </td>
                      <td>
                        <button id="move_<?=$nnats;?>" name="move_<?=$nnats;?>_x" data-toggle="tooltip" title="<?=html_safe(gettext("Move selected rules to end"))?>" class="act_move btn btn-default btn-xs">
                          <i class="fa fa-arrow-left fa-fw"></i>
                        </button>
                        <button id="del_x" title="<?=html_safe(gettext("Delete selected"))?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <i class="fa fa-trash fa-fw"></i>
                        </button>
                        <button title="<?= html_safe(gettext('Enable selected')) ?>" data-toggle="tooltip" class="act_toggle_enable btn btn-default btn-xs">
                          <i class="fa fa-check-square-o fa-fw"></i>
                        </button>
                        <button title="<?= html_safe(gettext('Disable selected')) ?>" data-toggle="tooltip" class="act_toggle_disable btn btn-default btn-xs">
                          <i class="fa fa-square-o fa-fw"></i>
                        </button>
                      </td>
                    </tr>
<?php endif ?>
                  </tbody>
                  <tfoot>
                    <tr class="hidden-xs hidden-sm">
                      <td colspan="13">
                        <table style="width:100%; border:0;">
                          <tr>
                            <td><i class="fa fa-play fa-fw text-success"></i></td>
                            <td><?=gettext("Enabled rule"); ?></td>
                            <td><i class="fa fa-exclamation fa-fw text-success"></i></td>
                            <td><?=gettext("No redirect"); ?></td>
                            <td><i class="fa fa-arrows-h fa-fw text-success"></i></td>
                            <td><?=gettext("Linked rule");?></td>
                          </tr>
                          <tr>
                            <td><i class="fa fa-play fa-fw text-muted"></i></td>
                            <td><?=gettext("Disabled rule"); ?></td>
                            <td><i class="fa fa-exclamation fa-fw text-muted"></i></td>
                            <td><?=gettext("Disabled no redirect"); ?></td>
                            <td><i class="fa fa-arrows-h fa-fw text-muted"></i></td>
                            <td><?=gettext("Disabled linked rule");?></td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    <tr class="hidden-xs hidden-sm">
                      <td><i class="fa fa-list fa-fw text-primary"></i></td>
                      <td colspan="12"><?=gettext("Alias (click to view/edit)");?></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
