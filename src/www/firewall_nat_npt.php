<?php
/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
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
require_once("filter.inc");
require_once("interfaces.inc");

if (!isset($config['nat']['npt'])) {
  $config['nat']['npt'] = array();
}
$a_npt = &$config['nat']['npt'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_npt[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }
    if (isset($pconfig['apply'])) {
        filter_configure();
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('natconf');
        clear_subsystem_dirty('filter');
    }  elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        // delete single record
        unset($a_npt[$id]);
        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_npt.php");
        exit;
      } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
          /* delete selected rules */
          foreach ($pconfig['rule'] as $rulei) {
              if (isset($a_npt[$rulei])) {
                  unset($a_npt[$rulei]);
              }
          }
          if (write_config()) {
              mark_subsystem_dirty('natconf');
          }
          header("Location: firewall_nat_npt.php");
          exit;
        } elseif ( isset($pconfig['act']) && $pconfig['act'] == 'move') {
            // move records
            if (isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
                // if rule not set/found, move to end
                if (!isset($id)) {
                    $id = count($a_npt);
                }
                $a_npt = legacy_move_config_list_items($a_npt, $id,  $pconfig['rule']);
            }
            if (write_config()) {
                mark_subsystem_dirty('natconf');
            }
            header("Location: firewall_nat_npt.php");
            exit;
        } elseif (isset($pconfig['act']) && $pconfig['act'] == 'toggle' && isset($id)) {
            // toggle item
            if(isset($a_npt[$id]['disabled'])) {
                unset($a_npt[$id]['disabled']);
            } else {
                $a_npt[$id]['disabled'] = true;
            }
            if (write_config("Firewall: NAT: NPt, enable/disable NAT rule")) {
                mark_subsystem_dirty('natconf');
            }
            header("Location: firewall_nat_npt.php");
            exit;
    }
}



legacy_html_escape_form_data($a_npt);
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("NPt"));
include("head.inc");

$main_buttons = array(
  array('label'=>'Add rule', 'href'=>'firewall_nat_npt_edit.php'),
);
?>


<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      if (id != 'x') {
        // delete single
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
          title: "<?=gettext("NPT");?>",
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
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("NPT");?>",
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
    $(".act_move").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      $("#id").val(id);
      $("#action").val("move");
      $("#iform").submit();
    });

    // link toggle buttons
    $(".act_toggle").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      $("#id").val(id);
      $("#action").val("toggle");
      $("#iform").submit();
    });

  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <?php if (is_subsystem_dirty('natconf')): ?>
        <?php print_info_box_np(gettext("The NAT configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
        <?php endif; ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form action="firewall_nat_npt.php" method="post" name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <div class="table-responsive">
                <table class="table table-striped table-sort">
                  <thead>
                    <tr>
                      <th width="2%">&nbsp;</th>
                      <th width="2%">&nbsp;</th>
                      <th width="2%">&nbsp;</th>
                      <th><?=gettext("Interface"); ?></th>
                      <th><?=gettext("External Prefix"); ?></th>
                      <th><?=gettext("Internal prefix"); ?></th>
                      <th><?=gettext("Description"); ?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $i = 0; foreach ($a_npt as $natent):
?>
                    <tr <?=isset($natent['disabled'])?"class=\"text-muted\"":"";?>  ondblclick="document.location='firewall_nat_npt_edit.php?id=<?=$i;?>';">
                      <td> </td>
                      <td>
                          <input type="checkbox" name="rule[]" value="<?=$i;?>"  />
                      </td>
                      <td>
                        <a href="#" class="act_toggle" id="toggle_<?=$i;?>" data-toggle="tooltip" data-placement="left" title="<?=gettext("click to toggle enabled/disabled status");?>">
<?php                     if(isset($natent['disabled'])):?>
                          <span class="glyphicon glyphicon-play text-muted"></span>
<?                        else:?>
                          <span class="glyphicon glyphicon-play text-success"></span>
<?php                     endif; ?>
                        </a>
                      </td>
                      <td>
                          <?= htmlspecialchars(convert_friendly_interface_to_friendly_descr(!empty($natent['interface']) ? $natent['interface'] : "wan"));?>
                      </td>
                      <td>
                          <?= pprint_address($natent['destination']);?>
                      </td>
                      <td>
                          <?= pprint_address($natent['source']) ;?>
                      </td>
                      <td>
                          <?=$natent['descr'];?>
                      </td>
                      <td>
                        <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" data-placement="left" title="<?=gettext("move selected rules before this rule");?>" class="act_move btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>
                        <a href="firewall_nat_npt_edit.php?id=<?=$i;?>" data-toggle="tooltip" data-placement="left" title="<?=gettext("edit this rule");?>" class="btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        <a id="del_<?=$i;?>" title="<?=gettext("delete this rule"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-remove"></span>
                        </a>
                        <a href="firewall_nat_npt_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("add new rule based on this one");?>">
                          <span class="glyphicon glyphicon-plus"></span>
                        </a>
                      </td>
                    </tr>
                    <?php $i++; endforeach; ?>
                    <tr>
                        <td colspan="7"> </td>
                        <td>
<?php               if ($i == 0): ?>
                        <span class="btn btn-default btn-xs text-muted">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </span>
<?php               else: ?>
                        <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" data-placement="left" title="<?=gettext("move selected rules to end");?>" class="act_move btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>
<?php                   endif; ?>
<?php                   if (count($a_npt) == 0): ?>
                      <span class="btn btn-default btn-xs text-muted"  data-toggle="tooltip" data-placement="left" title="<?=gettext("delete selected rules");?>"><span class="glyphicon glyphicon-remove" ></span></span>
<?php                   else: ?>
                        <a id="del_x" title="<?=gettext("delete selected rules"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-remove"></span>
                        </a>
<?php                   endif; ?>
                        <a href="firewall_nat_npt_edit.php?after=-1" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("add new rule");?>">
                          <span class="glyphicon glyphicon-plus"></span>
                        </a>
                        </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="8">&nbsp;</td>
                    </tr>
                    <tr>
                      <td><span class="glyphicon glyphicon-play text-success"></span></td>
                      <td colspan="7"><?=gettext("Enabled rule"); ?></td>
                    </tr>
                    <tr>
                      <td><span class="glyphicon glyphicon-play text-muted"></span></td>
                      <td colspan="7"><?=gettext("Disabled rule"); ?></td>
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
