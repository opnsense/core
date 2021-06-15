<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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

/**
 * check if gateway_item can be deleted
 * @param int $id sequence item in $a_gateways
 * @param array $a_gateways gateway list
 * @param array $input_errors input errors
 * @return bool has errors
 */
function can_delete_gateway_item($id, $a_gateways, &$input_errors)
{
    global $config;

    if (!isset($a_gateways[$id])) {
        return false;
    }

    if (isset($config['gateways']['gateway_group'])) {
        foreach ($config['gateways']['gateway_group'] as $group) {
            foreach ($group['item'] as $item) {
                $items = explode("|", $item);
                if ($items[0] == $a_gateways[$id]['name']) {
                    $input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Gateway Group '%s'"), $a_gateways[$id]['name'], $group['name']);
                    break;
                }
            }
        }
    }

    if (isset($config['staticroutes']['route'])) {
        foreach ($config['staticroutes']['route'] as $route) {
            if ($route['gateway'] == $a_gateways[$id]['name']) {
                $input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Static Route '%s'"), $a_gateways[$id]['name'], $route['network']);
                break;
            }
        }
    }

    if (isset($input_errors) && count($input_errors) > 0) {
        return false;
    }

    return true;
}
/**
 * delete gateway
 * @param int $id sequence item in $a_gateways
 * @param array $a_gateways gateway list
 */
function delete_gateway_item($id, $a_gateways)
{
    global $config;

    if (!isset($a_gateways[$id])) {
        return;
    }

    /* NOTE: Cleanup static routes for the monitor ip if any */
    if (!empty($a_gateways[$id]['monitor']) &&
        $a_gateways[$id]['monitor'] != "dynamic" &&
        is_ipaddr($a_gateways[$id]['monitor']) &&
        $a_gateways[$id]['gateway'] != $a_gateways[$id]['monitor']) {
        if (is_ipaddrv4($a_gateways[$id]['monitor'])) {
            mwexec("/sbin/route delete " . escapeshellarg($a_gateways[$id]['monitor']));
        } else {
            mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateways[$id]['monitor']));
        }
    }
    if (!empty($config['interfaces'][$a_gateways[$id]['interface']])) {
        if ($config['interfaces'][$a_gateways[$id]['interface']]['gateway'] == $a_gateways[$id]['name']) {
            unset($config['interfaces'][$a_gateways[$id]['interface']]['gateway']);
        }
    }
    unset($config['gateways']['gateway_item'][$a_gateways[$id]['attribute']]);
    if (empty($config['gateways']['gateway_item'])) {
        // make sure we don't leave a stray gateway_item
        unset($config['gateways']['gateway_item']);
    }
}

// fetch gateway list including active default for IPv4/IPv6
$gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());
$default_gwv4 = $gateways->getDefaultGW(return_down_gateways(), "inet");
$default_gwv6 = $gateways->getDefaultGW(return_down_gateways(), "inet6");
$a_gateways = array_values($gateways->gatewaysIndexedByName(true, false, true));
$gateways_status = return_gateways_status();

// form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (isset($pconfig['id']) && isset($a_gateways[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }
    if (isset($pconfig['apply'])) {
        // apply changes, reconfigure
        system_routing_configure();
        clear_subsystem_dirty('staticroutes');
        plugins_configure('monitor');
        filter_configure();
        header(url_safe('Location: /system_gateways.php?displaysave=true'));
        exit;
    } elseif (isset($id) && isset($pconfig['act']) && $pconfig['act'] == "del") {
        // delete single entry
        $input_errors = array();
        if (can_delete_gateway_item($id, $a_gateways, $input_errors)) {
            $realid = $a_gateways[$id]['attribute'];
            delete_gateway_item($id, $a_gateways);
            write_config("Gateways: removed gateway {$realid}");
            mark_subsystem_dirty('staticroutes');
            header(url_safe('Location: /system_gateways.php'));
            exit;
        }
    } elseif (isset($id) && isset($pconfig['act']) && $pconfig['act'] == "toggle") {
        // Toggle active/in-active
        $realid = $a_gateways[$id]['attribute'];
        $a_gateway_item = &config_read_array('gateways', 'gateway_item');

        if (isset($a_gateway_item[$realid]['disabled'])) {
            unset($a_gateway_item[$realid]['disabled']);
        } else {
            $a_gateway_item[$realid]['disabled'] = true;
        }

        write_config('Gateways: toggled gateway');
        mark_subsystem_dirty('staticroutes');

        header(url_safe('Location: /system_gateways.php'));
        exit;
    } elseif (!empty($pconfig['rule']) && isset($pconfig['act']) && $pconfig['act'] == "del_x") {
        // delete selected items
        $input_errors = array();
        if (is_array($pconfig['rule']) && count($pconfig['rule'])) {
            foreach ($pconfig['rule'] as $rulei) {
                if (!can_delete_gateway_item($rulei, $a_gateways, $input_errors)) {
                    break;
                }
            }

            if (count($input_errors) == 0) {
                $items_deleted = "";
                foreach ($_POST['rule'] as $rulei) {
                    delete_gateway_item($rulei, $a_gateways);
                    $items_deleted .= "{$rulei} ";
                }
                if (!empty($items_deleted)) {
                    write_config("Gateways: removed gateways {$items_deleted}");
                    mark_subsystem_dirty('staticroutes');
                }
                header(url_safe('Location: /system_gateways.php'));
                exit;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // set save message
    if (!empty($_GET['displaysave'])) {
        $savemsg = get_std_save_message();
    }
}

legacy_html_escape_form_data($a_gateways);

$service_hook = 'dpinger';

include("head.inc");

?>
<script>
$( document ).ready(function() {
  // link delete single item buttons (by class)
  $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data('id');
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Gateways");?>",
        message: "<?=gettext("Do you really want to delete this gateway?");?>",
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

  // link toggle buttons (by class)
  $(".act_toggle").click(function(event){
    event.preventDefault();
    var id = $(this).data('id');
    $("#id").val(id);
    $("#action").val("toggle");
    $("#iform").submit();
  });

  // link delete selected
  $("#btn_delete").click(function(event){
    event.preventDefault();
    var id = $(this).data('id');
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext("Gateways");?>",
      message: "<?=gettext("Do you really want to delete the selected gateway items?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $("#action").val("del_x");
                  $("#iform").submit()
              }
            }]
    });
  });
  // watch scroll position and set to last known on page load
  watchScrollPosition();
});
</script>

<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
    if (is_subsystem_dirty('staticroutes')) {
        print_info_box_apply(gettext("The gateway configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
    }
?>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <form method="post"  name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <table class="table table-striped table-condensed">
                <thead>
                  <tr>
                    <th colspan="2">&nbsp;</th>
                    <th><?=gettext("Name"); ?></th>
                    <th class="hidden-xs hidden-sm hidden-md"><?=gettext("Interface"); ?></th>
                    <th class="hidden-xs hidden-sm hidden-md"><?=gettext("Protocol"); ?></th>
                    <th class="hidden-xs hidden-sm hidden-md"><?=gettext("Priority"); ?></th>
                    <th class="hidden-xs hidden-sm hidden-md"><?=gettext("Gateway"); ?></th>
                    <th class="hidden-xs hidden-sm hidden-md"><?=gettext("Monitor IP"); ?></th>
                    <th class="text-nowrap hidden-xs"><?= gettext('RTT') ?></th>
                    <th class="text-nowrap hidden-xs"><?= gettext('RTTd') ?></th>
                    <th class="text-nowrap hidden-xs"><?= gettext('Loss') ?></th>
                    <th><?=gettext("Status"); ?></th>
                    <th class="hidden-xs hidden-sm hidden-md"><?=gettext("Description"); ?></th>
                    <th class="text-nowrap">
                      <a href="system_gateways_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                        <i class="fa fa-plus fa-fw"></i>
                      </a>
<?php if (count($a_gateways)): ?>
                          <button type="submit" id="btn_delete" name="del_x" class="btn btn-default btn-xs" data-toggle="tooltip"
                                  title="<?= html_safe(gettext('Delete selected items')) ?>">
                              <i class="fa fa-trash fa-fw"></i>
                          </button>
<?php endif ?>
                    </th>
                  </tr>
                </thead>
                <tbody>
<?php
                  $i = 0;
                  foreach ($a_gateways as $gateway) :?>
                    <tr class="<?=isset($gateway['disabled']) || isset($gateway['inactive']) ? "text-muted" : "";?>">
                      <td>
<?php
                    if (is_numeric($gateway['attribute'])) :?>
                      <input type="checkbox" name="rule[]" value="<?=$i;?>"/>
<?php
                    else :?>
                      &nbsp;
<?php
                    endif;?>
                      </td>
                      <td>
<?php
                    if (isset($gateway['inactive'])) :?>
                        <span class="fa fa-trash text-muted" data-toggle="tooltip" title="<?=gettext("Gateway is inactive because interface is missing");?>"></span>
<?php
                    elseif (is_numeric($gateway['attribute'])) :?>
                        <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=(!isset($gateway['disabled'])) ? gettext("Disable") : gettext("Enable");?>">
                          <span class="fa fa-play <?=isset($gateway['disabled']) || isset($gateway['inactive']) ? "text-muted" : "text-success";?>"></span>
                        </a>
<?php
                    else :?>
                        <span class="fa fa-play <?=isset($gateway['disabled']) || isset($gateway['inactive']) ? "text-muted" : "text-success";?>" data-toggle="tooltip" title="<?=(!isset($filterent['disabled'])) ? gettext("Disable") : gettext("Enable");?>"></span>
<?php
                    endif;?>
                      </td>
                      <td>
                        <?=$gateway['name'];?>
                        <?=!empty($default_gwv4) && $gateway['name'] == $default_gwv4['name'] ? "<strong>(active)</strong>" : "";?>
                        <?=!empty($default_gwv6) && $gateway['name'] == $default_gwv6['name'] ? "<strong>(active)</strong>" : "";?>
                      </td>
                      <td class="hidden-xs hidden-sm hidden-md">
                        <?=convert_friendly_interface_to_friendly_descr($gateway['interface']);?>
                      </td>
                      <td class="hidden-xs hidden-sm hidden-md">
                        <?=$gateway['ipprotocol'] == "inet" ?  "IPv4 " :  "IPv6 ";?>
                      </td>
                      <td class="hidden-xs hidden-sm hidden-md">
                        <?=empty($gateway['defunct']) ? $gateway['priority'] : gettext("defunct");?>
                        <small><?=!empty($gateway['defaultgw']) ? gettext("(upstream)") : "";?></small>
                      </td>
                      <td class="hidden-xs hidden-sm hidden-md">
                        <?=$gateway['gateway'];?>
                      </td>
                      <td class="hidden-xs hidden-sm hidden-md">
                        <?=$gateway['monitor'];?>
                      </td>
                      <td class="text-nowrap hidden-xs">
                        <?= !empty($gateways_status[$gateway['name']]) ? $gateways_status[$gateway['name']]['delay'] : "~" ?>
                      </td>
                      <td class="text-nowrap hidden-xs">
                        <?= !empty($gateways_status[$gateway['name']]) ? $gateways_status[$gateway['name']]['stddev'] : "~" ?>
                      </td>
                      <td class="text-nowrap hidden-xs">
                        <?= !empty($gateways_status[$gateway['name']]) ? $gateways_status[$gateway['name']]['loss'] : "~" ?>
                      </td>
                      <td>
  <?php
                      $online = gettext('Pending');
                      $gateway_label_class = 'default';
                      if ($gateways_status[$gateway['name']]) {
                          $status = $gateways_status[$gateway['name']];
                          if (stristr($status['status'], 'force_down')) {
                              $online = gettext('Offline (forced)');
                              $gateway_label_class = 'danger';
                          } elseif (stristr($status['status'], 'down')) {
                              $online = gettext('Offline');
                              $gateway_label_class = 'danger';
                          } elseif (stristr($status['status'], 'loss')) {
                              $online = gettext('Warning (packetloss)');
                              $gateway_label_class = 'warning';
                          } elseif (stristr($status['status'], 'delay')) {
                              $online = gettext('Warning (latency)');
                              $gateway_label_class = 'warning';
                          } elseif ($status['status'] == 'none') {
                              $online = gettext('Online');
                              $gateway_label_class = 'success';
                          }
                      } elseif (!isset($gateway['disabled']) && isset($gateway['monitor_disable'])) {
                          $online = gettext('Online');
                          $gateway_label_class = 'success';
                      }
  ?>
                        <div class="label label-<?= $gateway_label_class ?>">
                          <?=$online;?>
                        </div>
                      </td>
                      <td class="hidden-xs hidden-sm hidden-md">
                        <?=$gateway['descr'];?>
                      </td>
                      <td class="text-nowrap">
                        <a href="system_gateways_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"
                          data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                          <i class="fa fa-pencil fa-fw"></i>
                        </a>
<?php
                        if (is_numeric($gateway['attribute'])) :?>
                          <button data-id="<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"
                                  class="act_delete btn btn-default btn-xs">
                            <i class="fa fa-trash fa-fw"></i>
                          </button>
<?php
                        endif;?>
                          <a href="system_gateways_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs"
                             data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>">
                            <i class="fa fa-clone fa-fw"></i>
                          </a>
                        </td>
                      </tr>
<?php
                    $i++;
                  endforeach;?>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
