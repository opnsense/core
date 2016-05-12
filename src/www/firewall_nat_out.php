<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

/**
 *   quite nasty, content provided by filter_generate_gateways (in filter.inc).
 *  Not going to solve this now, because filter_generate_gateways is not a propper function
 *  it returns both rules for the firewall and is kind of responsible for updating this global.
 */
global $GatewaysList;

if (!isset($config['nat']['outbound']))
    $config['nat']['outbound'] = array();

if (!isset($config['nat']['outbound']['rule']))
    $config['nat']['outbound']['rule'] = array();

if (!isset($config['nat']['outbound']['mode']))
    $config['nat']['outbound']['mode'] = "automatic";

$a_out = &$config['nat']['outbound']['rule'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_out[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }
    if (isset($pconfig['apply'])) {
        write_config();
        filter_configure();
        clear_subsystem_dirty('natconf');
        clear_subsystem_dirty('filter');
    } elseif (isset($pconfig['save']) && $pconfig['save'] == "Save") {
        $mode = $config['nat']['outbound']['mode'];
        /* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
        if ($pconfig['mode'] == "advanced" && ($mode == "automatic" || $mode == "hybrid")) {
            /*
             *    user has enabled advanced outbound NAT and doesn't have rules
             *    lets automatically create entries
             *    for all of the interfaces to make life easier on the pip-o-chap
             */
            if(empty($GatewaysList)) {
                filter_generate_gateways();
            }

            /* XXX cranky low-level call, please refactor */
            $FilterIflist = filter_generate_optcfg_array();
            $tonathosts = filter_nat_rules_automatic_tonathosts($FilterIflist, true);
            $automatic_rules = filter_nat_rules_outbound_automatic($FilterIflist, '');

            foreach ($tonathosts as $tonathost) {
                foreach ($automatic_rules as $natent) {
                    $natent['source']['network'] = $tonathost['subnet'];
                    $natent['descr'] .= ' - ' . $tonathost['descr'] . ' -> ' . convert_real_interface_to_friendly_descr($natent['interface']);
                    $natent['created'] = make_config_revision_entry();

                    /* Try to detect already auto created rules and avoid duplicate them */
                    $found = false;
                    foreach ($a_out as $rule) {
                      // initialize optional values
                      if (!isset($rule['dstport'])) {
                          $rule['dstport'] = "";
                      }
                      if (!isset($natent['dstport'])) {
                          $natent['dstport'] = "";
                      }
                      //
                      if ($rule['interface'] == $natent['interface'] &&
                          $rule['source']['network'] == $natent['source']['network'] &&
                          $rule['dstport'] == $natent['dstport'] &&
                          $rule['target'] == $natent['target'] &&
                          $rule['descr'] == $natent['descr']) {
                          $found = true;
                          break;
                      }
                    }

                    if (!$found) {
                        $a_out[] = $natent;
                    }
                }
            }
            $savemsg = gettext("Default rules for each interface have been created.");
            unset($GatewaysList);
        }

        $config['nat']['outbound']['mode'] = $pconfig['mode'];

        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_out.php");
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        // delete single record
        unset($a_out[$id]);
        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_out.php");
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        /* delete selected rules */
        foreach ($pconfig['rule'] as $rulei) {
            if (isset($a_out[$rulei])) {
                unset($a_out[$rulei]);
            }
        }
        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_out.php");
        exit;
    } elseif ( isset($pconfig['act']) && $pconfig['act'] == 'move' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // if rule not set/found, move to end
        if (!isset($id)) {
            $id = count($a_out);
        }
        $a_out = legacy_move_config_list_items($a_out, $id,  $pconfig['rule']);
        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_out.php");
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'toggle' && isset($id)) {
        // toggle item disabled / enabled
        if(isset($a_out[$id]['disabled'])) {
            unset($a_out[$id]['disabled']);
        } else {
            $a_out[$id]['disabled'] = true;
        }
        if (write_config("Firewall: NAT: Outbound, enable/disable NAT rule")) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_out.php");
        exit;
    }
}

$mode = $config['nat']['outbound']['mode'];

include("head.inc");

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
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("Nat")." ".gettext("Outbound");?>",
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
                      event.preventDefault();
                  }
                }]
        });
      } else {
        // delete selected
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?=gettext("Nat")." ".gettext("Outbound");?>",
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
                      event.preventDefault();
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
    // watch scroll position and set to last known on page load
    watchScrollPosition();
  });
  </script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
        print_service_banner('firewall');
        if (isset($savemsg))
            print_info_box($savemsg);
        if (is_subsystem_dirty('natconf'))
            print_info_box_apply(gettext("The NAT configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
?>
        <form method="post" name="iform" id="iform">
          <input type="hidden" id="id" name="id" value="" />
          <input type="hidden" id="action" name="act" value="" />
          <section class="col-xs-12">
            <div class="content-box">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th colspan="4"><?=gettext("Mode:"); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <input name="mode" type="radio" id="mode_automatic"
                          value="automatic" <?= $mode == "automatic" ? "checked=\"checked\"" : "";?> />
                    </td>
                    <td>
                      <label for="mode_automatic">
                        <strong>
                          <?=gettext("Automatic outbound NAT rule generation"); ?><br />
                          <?=gettext("(no manual rules can be used)");?>
                        </strong>
                      </label>
                    </td>
                    <td>
                      <input name="mode" type="radio" id="mode_hybrid"
                          value="hybrid" <?= $mode == "hybrid" ? "checked=\"checked\"" : "";?> />
                    </td>
                    <td>
                      <label for="mode_hybrid">
                        <strong>
                          <?=gettext("Hybrid outbound NAT rule generation"); ?><br />
                          <?=gettext("(automatically generated rules are applied after manual rules)");?>
                        </strong>
                      </label>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <input name="mode" type="radio" id="mode_advanced"
                          value="advanced" <?= $mode == "advanced" ? "checked=\"checked\"" : "";?> />
                    </td>
                    <td>
                      <label for="mode_advanced">
                        <strong>
                          <?=gettext("Manual outbound NAT rule generation"); ?><br />
                          <?=gettext("(no automatic rules are being generated)");?>
                        </strong>
                      </label>
                    </td>
                    <td>
                      <input name="mode" type="radio" id="mode_disabled"
                          value="disabled" <?= $mode == "disabled" ? "checked=\"checked\"" : "";?> />
                    </td>
                    <td>
                      <label for="mode_disabled">
                        <strong>
                          <?=gettext("Disable outbound NAT rule generation"); ?><br />
                          <?=gettext("(outbound NAT is disabled)");?>
                        </strong>
                      </label>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="4">
                      <button name="save" type="submit" class="btn btn-primary" value="Save"><?= gettext('Save') ?></button>
                    </td>
                  </tr>
                </tbody>
              </table>
          </div>
        </section>
<?php if ($mode == 'advanced' || $mode == 'hybrid'): ?>
        <section class="col-xs-12">
          <div class="table-responsive content-box ">
            <table class="table table-striped table-sort">
              <thead>
                <tr><th colspan="12"><?=gettext("Manual rules:"); ?></th></tr>
                <tr>
                    <th>&nbsp;</th>
                    <th>&nbsp;</th>
                    <th><?=gettext("Interface");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Source");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Source Port");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Destination");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Destination Port");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("NAT Address");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("NAT Port");?></th>
                    <th><?=gettext("Static Port");?></th>
                    <th><?=gettext("Description");?></th>
                    <th>&nbsp;</th>
                  </tr>
                </thead>
                <tbody>
<?php
                $i = 0;
                foreach ($a_out as $natent):
?>
                  <tr <?=$mode == "disabled" || $mode == "automatic" || isset($natent['disabled'])?"class=\"text-muted\"":"";?> ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
                    <td>
                      <input type="checkbox" name="rule[]" value="<?=$i;?>"  />
                    </td>
                    <td>
<?php
                    if ($mode == "disabled" || $mode == "automatic"):
?>
                      <span data-toggle="tooltip" title="<?=gettext("All manual rules are being ignored");?>" class="glyphicon glyphicon-play <?=$mode == "disabled" || $mode == "automatic" || isset($natent['disabled']) ? "text-muted" : "text-success";?>"></span>
<?php
                    else:
?>
                      <a href="#" class="act_toggle" id="toggle_<?=$i;?>" data-toggle="tooltip" title="<?=(!isset($natent['disabled'])) ? gettext("disable rule") : gettext("enable rule");?>" class="btn btn-default btn-xs <?=isset($natent['disabled']) ? "text-muted" : "text-success";?>">
                        <span class="glyphicon glyphicon-play <?=isset($natent['disabled']) ? "text-muted" : "text-success";?>  "></span>
                      </a>
<?php
                    endif;
?>
                    </td>
                    <td>
                      <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])); ?>
                    </td>
                    <td class="hidden-xs hidden-sm">
<?php                 if (isset($natent['source']['network']) && is_alias($natent['source']['network'])): ?>
                        <span title="<?=htmlspecialchars(get_alias_description($natent['source']['network']));?>" data-toggle="tooltip">
                          <?=htmlspecialchars($natent['source']['network']);?>&nbsp;
                        </span>
                        <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['source']['network']);?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list"></i>
                        </a>
<?php                 else: ?>
                        <?=$natent['source']['network'] == "(self)" ? gettext("This Firewall") : htmlspecialchars($natent['source']['network']); ?>&nbsp;
<?php                 endif; ?>
                    </td>
                    <td class="hidden-xs hidden-sm">
                      <?=!empty($natent['protocol']) ? $natent['protocol'] . '/' : "" ;?>
                      <?=!empty($natent['sourceport']) ? $natent['sourceport'] : "*"; ?>
                    </td>
                    <td class="hidden-xs hidden-sm">
                      <?=isset($natent['destination']['not']) ? "!&nbsp;" :"";?>
<?php                 if (isset($natent['destination']['address']) && is_alias($natent['destination']['address'])): ?>
                        <span title="<?=htmlspecialchars(get_alias_description($natent['destination']['address']));?>" data-toggle="tooltip">
                          <?=htmlspecialchars($natent['destination']['address']);?>&nbsp;
                        </span>
                        <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['destination']['address']);?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list"></i>
                        </a>
<?php                 else: ?>
                        <?=isset($natent['destination']['any']) ? "*" : htmlspecialchars($natent['destination']['address']);?>
<?php                 endif; ?>
                    </td>
                    <td class="hidden-xs hidden-sm">
                      <?=!empty($natent['protocol']) ? $natent['protocol'] . '/' : "" ;?>
                      <?=empty($natent['dstport']) ? "*" : $natent['dstport'] ;?>
                    </td>
                    <td class="hidden-xs hidden-sm">
<?php

                      if (isset($natent['nonat']))
                        $nat_address = '<I>NO NAT</I>';
                      elseif (!$natent['target'])
                        $nat_address = htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
                      elseif ($natent['target'] == "other-subnet")
                        $nat_address = $natent['targetip'] . '/' . $natent['targetip_subnet'];
                      else
                        $nat_address = $natent['target'];
?>
<?php                 if (isset($natent['target']) && is_alias($natent['target'])): ?>
                        <span title="<?=htmlspecialchars(get_alias_description($natent['target']));?>" data-toggle="tooltip">
                          <?=htmlspecialchars($nat_address);?>&nbsp;
                        </span>
                        <a href="/firewall_aliases_edit.php?name=<?=htmlspecialchars($natent['target']);?>"
                            title="<?=gettext("edit alias");?>" data-toggle="tooltip">
                          <i class="fa fa-list"></i>
                        </a>
<?php                 else: ?>
                        <?=htmlspecialchars($nat_address);?>
<?php                 endif; ?>
                    </td>
                    <td class="hidden-xs hidden-sm">
                      <?=empty($natent['natport']) ? "*" : htmlspecialchars($natent['natport']);?>
                    </td>
                    <td>
                      <?=isset($natent['staticnatport']) ? gettext("YES") : gettext("NO");?>
                    </td>
                    <td>
                      <?=htmlspecialchars($natent['descr']);?>&nbsp;
                    </td>
                    <td>
                      <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?=gettext("move selected rules before this rule");?>" class="act_move btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-arrow-left"></span>
                      </a>
                      <a href="firewall_nat_out_edit.php?id=<?=$i;?>" data-toggle="tooltip" title="<?=gettext("edit rule");?>" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-pencil"></span>
                      </a>
                      <a id="del_<?=$i;?>" title="<?=gettext("delete rule"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                        <span class="fa fa-trash text-muted"></span>
                      </a>
                      <a href="firewall_nat_out_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("clone rule");?>">
                        <span class="fa fa-clone text-muted"></span>
                      </a>
                    </td>
                  </tr>
<?php
                  $i++;
                endforeach;
?>
        <tr>
          <td colspan="6" class="hidden-xs hidden-sm"></td>
          <td colspan="5"></td>
          <td>

<?php
                if ($i == 0):
?>
                  <span class="btn btn-default btn-xs"><span class="glyphicon glyphicon-arrow-left"></span></span>
<?php
                else:
?>
                  <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?=gettext("move selected rules to end");?>" class="act_move btn btn-default btn-xs">
                    <span class="glyphicon glyphicon-arrow-left"></span>
                  </a>
<?php
                endif;
?>
<?php
                if ($i == 0):
?>
                  <span title="<?=gettext("delete selected rules");?>"  class="btn btn-default btn-xs"><span class="fa fa-trash text-muted"></span></span>
<?php
                else:
?>
                  <a id="del_x" title="<?=gettext("delete selected rules"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                    <span class="fa fa-trash text-muted"></span>
                  </a>
<?php
                endif;
?>
                  <a href="firewall_nat_out_edit.php" title="<?=gettext("add new rule");?>" alt="add"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="12">&nbsp;</td>
                </tr>
                <tr>
                  <td width="16"><span class="glyphicon glyphicon-play text-success"></span></td>
                  <td colspan="11"><?=gettext("Enabled rule"); ?></td>
                </tr>
                <tr>
                  <td><span class="glyphicon glyphicon-play text-muted"></span></td>
                  <td colspan="11"><?=gettext("Disabled rule"); ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>
<?php   endif; ?>
<?php
      // when automatic or hybrid, display "auto" table.
      if ($mode == "automatic" || $mode == "hybrid"):
        if(empty($GatewaysList))
          filter_generate_gateways();
        /* XXX cranky low-level call, please refactor */
        $FilterIflist = filter_generate_optcfg_array();
        $automatic_rules = filter_nat_rules_outbound_automatic(
          $FilterIflist, implode(' ', filter_nat_rules_automatic_tonathosts($FilterIflist))
        );
        unset($GatewaysList);
?>
        <section class="col-xs-12">
          <div class="table-responsive content-box ">
            <table class="table table-striped table-sort">
              <thead>
                  <tr>
                    <th colspan="11"><?=gettext("Automatic rules:"); ?></th>
                  </tr>
                  <tr>
                    <th>&nbsp;</th>
                    <th>&nbsp;</th>
                    <th><?=gettext("Interface");?></th>
                    <th><?=gettext("Source");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Source Port");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Destination");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Destination Port");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("NAT Address");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("NAT Port");?></th>
                    <th class="hidden-xs hidden-sm"><?=gettext("Static Port");?></th>
                    <th><?=gettext("Description");?></th>
                  </tr>
              </thead>
              <tbody>
<?php
              foreach ($automatic_rules as $natent):
?>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <span class="glyphicon glyphicon-play text-success" data-toggle="tooltip" title="<?=gettext("automatic outbound nat");?>"></span>
                  </td>
                  <td>
                    <?= htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])); ?>
                  </td>
                  <td>
                    <?=$natent['source']['network'];?>
                  </td>
                  <td class="hidden-xs hidden-sm">
                    <?=(!empty($natent['protocol'])) ? $natent['protocol'] . '/' : "" ;?>
                    <?=empty($natent['sourceport']) ? "*" : $natent['sourceport'] ;?>
                  </td>
                  <td class="hidden-xs hidden-sm">
                    <?=isset($natent['destination']['not']) ? "!&nbsp;" : "";?>
                    <?=isset($natent['destination']['any']) ? "*" : $natent['destination']['address'] ;?>
                  </td>
                  <td class="hidden-xs hidden-sm">
                    <?=!empty($natent['protocol']) ? $natent['protocol'] . '/' : "" ;?>
                    <?=empty($natent['dstport']) ? "*" : $natent['dstport'] ;?>
                  </td>
                  <td class="hidden-xs hidden-sm">
<?php
                    if (isset($natent['nonat'])) {
                        $nat_address = '<I>NO NAT</I>';
                    } elseif (empty($natent['target'])) {
                        $nat_address = htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
                    } elseif ($natent['target'] == "other-subnet") {
                        $nat_address = $natent['targetip'] . '/' . $natent['targetip_subnet'];
                    } else  {
                        $nat_address = $natent['target'];
                    }
?>
                    <?=$nat_address;?>
                  </td>
                  <td class="hidden-xs hidden-sm">
                    <?= empty($natent['natport']) ? "*" : $natent['natport'];?>
                  </td>
                  <td class="hidden-xs hidden-sm">
                    <?= isset($natent['staticnatport']) ? gettext("YES") : gettext("NO") ;?>
                  </td>
                  <td>
                    <?=htmlspecialchars($natent['descr']);?>
                  </td>
                </tr>
<?php
        endforeach;
?>
              </table>
            </div>
          </section>
<?php
      endif;
?>
        </form>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
