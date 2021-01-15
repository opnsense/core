<?php

/*
    Copyright (C) 2014 Deciso B.V.
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

$a_1to1 = &config_read_array('nat', 'onetoone');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_1to1[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }

    if (isset($pconfig['apply'])) {
        filter_configure();
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('natconf');
        clear_subsystem_dirty('filter');
    } elseif (isset($pconfig['action']) && $pconfig['action'] == 'del' && isset($id)) {
        // delete single entry
        unset($a_1to1[$id]);
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat_1to1.php'));
        exit;
    } elseif (isset($pconfig['action']) && $pconfig['action'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // delete selected
        foreach ($pconfig['rule'] as $rulei) {
            unset($a_1to1[$rulei]);
        }
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat_1to1.php'));
        exit;
    } elseif (isset($pconfig['action']) && in_array($pconfig['action'], array('toggle_enable', 'toggle_disable')) && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        foreach ($pconfig['rule'] as $rulei) {
            $a_1to1[$rulei]['disabled'] = $pconfig['action'] == 'toggle_disable';
        }
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat_1to1.php'));
        exit;
    } elseif (isset($pconfig['action']) && $pconfig['action'] == 'move') {
        // move selected
        if (isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
            // if rule not set/found, move to end
            if (!isset($id)) {
                $id = count($a_1to1);
            }
            $a_1to1 = legacy_move_config_list_items($a_1to1, $id,  $pconfig['rule']);

            write_config();
            mark_subsystem_dirty('natconf');
            header(url_safe('Location: /firewall_nat_1to1.php'));
            exit;
        }
    } elseif (isset($pconfig['action']) && $pconfig['action'] == 'toggle' && isset($id)) {
        // toggle item
        if(isset($a_1to1[$id]['disabled'])) {
            unset($a_1to1[$id]['disabled']);
        } else {
            $a_1to1[$id]['disabled'] = true;
        }
        write_config('Toggled NAT 1:1 rule');
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat_1to1.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_1to1);

include("head.inc");

$main_buttons = array(
    array('label' => gettext('Add'), 'href' => 'firewall_nat_1to1_edit.php'),
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
            title: "<?= gettext("1:1");?>",
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
            title: "<?= gettext("1:1");?>",
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

    // watch scroll position and set to last known on page load
    watchScrollPosition();

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
<?php
        print_service_banner('firewall');
        if (isset($savemsg))
          print_info_box($savemsg);
        if (is_subsystem_dirty('natconf'))
          print_info_box_apply(gettext("The NAT configuration has been changed.") .
            "<br />" .
            gettext("You must apply the changes in order for them to take effect."));
?>
          <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="action" value="" />
              <table class="table table-striped table-condensed opnsense-rules">
                <thead>
                  <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>&nbsp;</th>
                    <th><?=gettext("Interface"); ?></th>
                    <th><?=gettext("External IP"); ?></th>
                    <th><?=gettext("Internal IP"); ?></th>
                    <th><?=gettext("Destination IP"); ?></th>
                    <th><?=gettext("Description"); ?></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
<?php
                $i = 0;
                foreach ($a_1to1 as $natent):
?>
                  <tr class="rule <?=isset($natent['disabled'])?"text-muted":"";?>" data-category="<?=!empty($natent['category']) ? $natent['category'] : "";?>" ondblclick="document.location='firewall_nat_1to1_edit.php?id=<?=$i;?>';">
                    <td>
                      <input class="rule_select" type="checkbox" name="rule[]" value="<?=$i;?>" />
                    </td>
                    <td>
                      <a href="#" type="submit" id="toggle_<?=$i;?>" data-toggle="tooltip" title="<?=(!isset($natent['disabled'])) ? gettext("Disable") : gettext("Enable");?>" class="act_toggle">
<?php                   if(isset($natent['disabled'])):?>
                          <span class="fa fa-play text-muted"></span>
<?php                   else:?>
                          <span class="fa fa-play text-success"></span>
<?php                   endif; ?>
                      </a>
                    </td>
                    <td>
                      <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr(isset($natent['interface']) ? $natent['interface'] : "wan"));?>
                    </td>
                    <td>
                      <?=isset($natent['external']) ? $natent['external'] : "";?><?=isset($natent['source']) && strpos($natent['external'], '/') === false ? strstr(pprint_address($natent['source']), '/') : "";?>
<?php                 if (isset($natent['external']['address']) && is_alias($natent['external']['address'])): ?>
                      &nbsp;<a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['external']['address']);?>"><i class="fa fa-list"></i> </a>
<?php                 endif; ?>
                    </td>
                    <td>
<?php                 if (isset($natent['source']['address']) && is_alias($natent['source']['address'])): ?>
                        <span title="<?=htmlspecialchars(get_alias_description($natent['source']['address']));?>" data-toggle="tooltip" data-html="true">
                          <?=htmlspecialchars(pprint_address($natent['source']));?>&nbsp;
                        </span>
                        <a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['source']['address']);?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list"></i>
                        </a>
<?php                 else: ?>
                        <?=htmlspecialchars(pprint_address($natent['source']));?>
<?php                 endif; ?>
                    </td>
                    <td>
<?php                 if (isset($natent['destination']['address']) && is_alias($natent['destination']['address'])): ?>
                        <span title="<?=htmlspecialchars(get_alias_description($natent['destination']['address']));?>" data-toggle="tooltip" data-html="true">
                          <?=htmlspecialchars(pprint_address($natent['destination']));?>&nbsp;
                        </span>
                        <a href="/ui/firewall/alias/index/<?=htmlspecialchars($natent['destination']['address']);?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list"></i>
                        </a>
<?php                 else: ?>
                        <?=htmlspecialchars(pprint_address($natent['destination']));?>
<?php                 endif; ?>
                    </td>
                    <td>
                      <?=$natent['descr'];?> &nbsp;
                    </td>
                    <td>
                      <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?= html_safe(gettext("Move selected rules before this rule")) ?>" class="act_move btn btn-default btn-xs">
                        <span class="fa fa-arrow-left fa-fw"></span>
                      </a>
                      <a href="firewall_nat_1to1_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                        <span class="fa fa-pencil fa-fw"></span>
                      </a>
                      <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" class="act_delete btn btn-default btn-xs">
                        <span class="fa fa-trash fa-fw"></span>
                      </a>
                      <a href="firewall_nat_1to1_edit.php?dup=<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>" class="btn btn-default btn-xs">
                        <span class="fa fa-clone fa-fw"></span>
                      </a>
                    </td>
                  </tr>
<?php
                  $i++;
                endforeach;
?>
<?php if ($i != 0): ?>
                  <tr>
                    <td colspan="7"></td>
                    <td>
                      <button id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?= html_safe(gettext('Move selected rules to end')) ?>" class="act_move btn btn-default btn-xs">
                        <i class="fa fa-arrow-left fa-fw"></i>
                      </button>
                      <button id="del_x" title="<?= html_safe(gettext('Delete selected')) ?>" data-toggle="tooltip" class="act_delete btn btn-default btn-xs">
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
                  <tr>
                    <td style="width:16px"><span class="fa fa-play text-success"></span></td>
                    <td colspan="8"><?=gettext("Enabled rule"); ?></td>
                  </tr>
                  <tr>
                    <td><span class="fa fa-play text-muted"></span></td>
                    <td colspan="8"><?=gettext("Disabled rule"); ?></td>
                  </tr>
                  <tr>
                    <td><a><i class="fa fa-list"></i></a></td>
                    <td colspan="8"><?=gettext("Alias (click to view/edit)");?></td>
                  </tr>
                  <tr>
                    <td colspan="9">
                      <?=gettext("If you add a 1:1 NAT entry for any of the interface IPs on this system, " .
                        "it will make this system inaccessible on that IP address. i.e. if " .
                        "you use your WAN IP address, any services on this system (IPsec, OpenVPN server, etc.) " .
                        "using the WAN IP address will no longer function."); ?>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
