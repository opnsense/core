<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2009 Janne Enberg <janne.enberg@lietu.net>
    Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

$main_buttons = array(
  array('label'=>gettext('Add'), 'href'=>'firewall_nat_edit.php'),
);

?>

<body>
<script>
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(){
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

  // watch scroll position and set to last known on page load
  watchScrollPosition();
});
</script>
<?php include("fbegin.inc"); ?>
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
                <table class="table table-striped">
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
                      <th><?=gettext("If");?></th>
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
<?php           if (isset($config['interfaces']['lan'])) {
                    $lockout_intf_name = empty($config['interfaces']['lan']['descr']) ? "LAN" :$config['interfaces']['lan']['descr'];
                } elseif (count($config['interfaces']) == 1 && isset($config['interfaces']['wan'])) {
                    $lockout_intf_name = empty($config['interfaces']['wan']['descr']) ? "WAN" :$config['interfaces']['wan']['descr'];
                } else {
                    $lockout_intf_name = null;
                }

                // show anti-lockout when enabled
                if ($lockout_intf_name !== null && !isset($config['system']['webgui']['noantilockout'])):
?>
                    <tr>
                      <td></td>
                      <td><i class="fa fa-exclamation text-success"></i></td>
                      <td></td>
                      <td><?=$lockout_intf_name?></td>
                      <td>TCP</td>
                      <td class="hidden-xs hidden-sm">*</td>
                      <td class="hidden-xs hidden-sm">*</td>
                      <td class="hidden-xs hidden-sm"><?=$lockout_intf_name?> <?=gettext("address");?></td>
                      <td class="hidden-xs hidden-sm"><?=implode('<br />', filter_core_antilockout_ports());?></td>
                      <td>*</td>
                      <td>*</td>
                      <td><?=gettext("Anti-Lockout Rule");?></td>
                      <td></td>
                    </tr>
<?php               endif; ?>
<?php               $nnats = 0;
                    foreach ($a_nat as $natent):
?>
                    <tr <?=isset($natent['disabled'])?"class=\"text-muted\"":"";?> ondblclick="document.location='firewall_nat_edit.php?id=<?=$nnats;?>';">
                      <td>
                        <input class="rule_select" type="checkbox" name="rule[]" value="<?=$nnats;?>"  />
                      </td>
                      <td>
<?php                 if (isset($natent['nordr'])): ?>
                        <i class="fa fa-exclamation <?=isset($natent['disabled']) ? "text-muted" : "text-success" ;?>"></i>
<?php                 endif; ?>
                      </td>
                      <td>
                        <a href="#" class="act_toggle" id="toggle_<?=$nnats;?>" data-toggle="tooltip" title="<?=(!isset($natent['disabled'])) ? gettext("Disable") : gettext("Enable");?>">
<?php                     if (!empty($natent['associated-rule-id'])): ?>
<?php                     if(isset($natent['disabled'])):?>
                          <span class="glyphicon glyphicon-resize-horizontal text-muted"></span>
<?php                        else:?>
                          <span class="glyphicon glyphicon-resize-horizontal text-success"></span>
<?php                     endif; ?>
<?php                        elseif(isset($natent['disabled'])):?>
                          <span class="fa fa-play text-muted"></span>
<?php                        else:?>
                          <span class="fa fa-play text-success"></span>
<?php                     endif; ?>
                        </a>
                      </td>
                      <td>
                        <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr(isset($natent['interface']) ? $natent['interface'] : "wan"));?>
                      </td>
                      <td>
                        <?=strtoupper($natent['protocol']);?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['source']['address']) && is_alias($natent['source']['address'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['source']['address']));?>" data-toggle="tooltip">
                            <?=htmlspecialchars(pprint_address($natent['source'])); ?>
                          </span>
                          <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['source']['address']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_address($natent['source'])); ?>
<?php                   endif; ?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['source']['port']) && is_alias($natent['source']['port'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['source']['port']));?>" data-toggle="tooltip">
                            <?=htmlspecialchars(pprint_port($natent['source']['port'])); ?>&nbsp;
                          </span>
                          <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['source']['port']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_port(isset($natent['source']['port']) ? $natent['source']['port'] : null)); ?>
<?php                   endif; ?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['destination']['address']) && is_alias($natent['destination']['address'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['destination']['address']));?>" data-toggle="tooltip">
                            <?=htmlspecialchars(pprint_address($natent['destination'])); ?>
                          </span>
                          <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['destination']['address']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_address($natent['destination'])); ?>
<?php                   endif; ?>
                      </td>

                      <td class="hidden-xs hidden-sm">
<?php                   if (isset($natent['destination']['port']) && is_alias($natent['destination']['port'])): ?>
                          <span title="<?=htmlspecialchars(get_alias_description($natent['destination']['port']));?>" data-toggle="tooltip">
                            <?=htmlspecialchars(pprint_port($natent['destination']['port'])); ?>&nbsp;
                          </span>
                          <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['destination']['port']);?>"
                              title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                            <i class="fa fa-list"></i>
                          </a>
<?php                   else: ?>
                          <?=htmlspecialchars(pprint_port(isset($natent['destination']['port']) ? $natent['destination']['port'] : null)); ?>
<?php                   endif; ?>
                      </td>

                      <td>
                        <?=$natent['target'];?>
<?php                   if (is_alias($natent['target'])): ?>
                        &nbsp;<a href="/firewall_aliases_edit.php?name=<?=$natent['target'];?>"><i class="fa fa-list"></i> </a>
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
                          <span title="<?=htmlspecialchars(get_alias_description($localport));?>" data-toggle="tooltip">
                            <?=htmlspecialchars(pprint_port($localport));?>&nbsp;
                          </span>
                          <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($localport);?>"
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
                        <a type="submit" id="move_<?=$nnats;?>" name="move_<?=$nnats;?>_x" data-toggle="tooltip" title="<?=gettext("move selected rules before this rule");?>" class="act_move btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>
                        <a href="firewall_nat_edit.php?id=<?=$nnats;?>" data-toggle="tooltip" title="<?=gettext("edit rule");?>" class="btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        <a id="del_<?=$nnats;?>" title="<?=gettext("delete rule"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="fa fa-trash text-muted"></span>
                        </a>
                        <a href="firewall_nat_edit.php?dup=<?=$nnats;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("clone rule");?>">
                          <span class="fa fa-clone text-muted"></span>
                        </a>
                      </td>
                     </tr>
<?php $nnats++; endforeach; ?>
                    <tr>

                    <td colspan="8"></td>
                    <td class="hidden-xs hidden-sm" colspan="4"> </td>
                    <td>
<?php               if ($nnats == 0): ?>
                        <span class="btn btn-default btn-xs text-muted">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </span>
<?php               else: ?>
                        <a type="submit" id="move_<?=$nnats;?>" name="move_<?=$nnats;?>_x" data-toggle="tooltip" title="<?=gettext("move selected rules to end");?>" class="act_move btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>
<?php                   endif; ?>
<?php                   if (count($a_nat) == 0): ?>
                      <span class="btn btn-default btn-xs text-muted"  data-toggle="tooltip" title="<?=gettext("delete selected rules");?>"><span class="fa fa-trash text-muted" ></span></span>
<?php                   else: ?>
                        <a id="del_x" title="<?=gettext("delete selected rules"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="fa fa-trash text-muted"></span>
                        </a>
<?php                   endif; ?>
                        <a href="firewall_nat_edit.php" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("add new rule");?>">
                          <span class="glyphicon glyphicon-plus"></span>
                        </a>
                    </td>
                  </tr>
                </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="13">&nbsp;</td>
                    </tr>
                    <tr>
                      <td style="width:16px"><span class="fa fa-play text-success"></span></td>
                      <td colspan="12"><?=gettext("Enabled rule"); ?></td>
                    </tr>
                    <tr>
                      <td><span class="fa fa-play text-muted"></span></td>
                      <td colspan="12"><?=gettext("Disabled rule"); ?></td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-exclamation text-success"></i></td>
                      <td colspan="12"><?=gettext("No redirect"); ?></td>
                    </tr>
                    <tr>
                      <td><span class="glyphicon glyphicon-resize-horizontal text-success"></span></td>
                      <td colspan="12"><?=gettext("linked rule");?></td>
                    </tr>
                    <tr>
                      <td><a><i class="fa fa-list"></i></a></td>
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
