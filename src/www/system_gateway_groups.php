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
require_once("system.inc");

$a_gateway_groups = &config_read_array('gateways', 'gateway_group');
$gateways_status = return_gateways_status();
$a_gateways = (new \OPNsense\Routing\Gateways(legacy_interfaces_details()))->gatewaysIndexedByName();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del" ) {
        if (!empty($a_gateway_groups[$_POST['id']])) {
            foreach ($config['filter']['rule'] as $idx => $rule) {
                if ($rule['gateway'] == $a_gateway_groups[$_POST['id']]['name']) {
                    unset($config['filter']['rule'][$idx]['gateway']);
                }
            }
            unset($a_gateway_groups[$_POST['id']]);
            mark_subsystem_dirty('gwgroups');
            write_config();
            header(url_safe('Location: /system_gateway_groups.php'));
            exit;
        }
    } elseif (isset($_POST['apply'])) {
        plugins_configure('monitor');
        configd_run('dyndns reload');
        configd_run('filter reload');

        clear_subsystem_dirty('gwgroups');

        header(url_safe('Location: /system_gateway_groups.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_gateway_groups);
legacy_html_escape_form_data($a_gateways);

$service_hook = 'dpinger';

include("head.inc");

?>
<script>
$( document ).ready(function() {
    // remove group
    $(".act-del-group").click(function(event){
      var id = $(this).data('id');
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("Gateway-group");?>",
          message: '<?=gettext("Do you really want to delete this gateway group?");?>',
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val(id);
                      $("#act").val("del");
                      $("#iform").submit();
                  }
          }]
      });
    });
});
</script>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
      if (is_subsystem_dirty('gwgroups')) {
         print_info_box_apply(sprintf(gettext("The gateway configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br />"));
      }
?>
      <section class="col-xs-12">
        <div class="container-fluid">
          <div class="tab-content content-box">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="act" name="act" value="" />
              <input type="hidden" id="id" name="id" value="" />
              <div class="table-responsive">
                <table class="table table-striped table-condensed">
                  <thead>
                    <tr>
                      <td><?= gettext('Name') ?></td>
                      <td><?= gettext('Gateways') ?></td>
                      <td class="hidden-xs"><?= gettext('Description') ?></td>
                      <td class="text-nowrap">
                        <a href="system_gateway_groups_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                          <i class="fa fa-plus fa-fw"></i>
                        </a>
                      </td>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_gateway_groups as $gateway_group):
                    $priorities = array();
                    foreach($gateway_group['item'] as $item) {
                      $itemsplit = explode("|", $item);
                      if (!isset($priorities[$itemsplit[1]])) {
                          $priorities[$itemsplit[1]] = array();
                      }
                      if (!empty($a_gateways[$itemsplit[0]])) {
                          $priorities[$itemsplit[1]][$itemsplit[0]] = $a_gateways[$itemsplit[0]];
                      }
                    }
                    ksort($priorities);
?>
                    <tr>
                        <td><?= $gateway_group['name'] ?></td>
                        <td>
                          <table class="table table-condensed">
<?php
                          foreach ($priorities as $priority => $gateways):?>
                          <tr>
                            <td class="text-nowrap"><?=sprintf(gettext("Tier %s"), $priority);?></td>
                            <td>
<?php
                            foreach ($gateways as $gname => $gateway):
                              $online = gettext('Pending');
                              $gateway_label_class = 'default';
                              if ($gateways_status[$gname]) {
                                  $status = $gateways_status[$gname]['status'];
                                      if (stristr($status, 'force_down')) {
                                          $online = gettext('Offline (forced)');
                                          $gateway_label_class = 'danger';
                                      } elseif (stristr($status, 'down')) {
                                          $online = gettext('Offline');
                                          $gateway_label_class = 'danger';
                                      } elseif (stristr($status, 'loss')) {
                                          $online = gettext('Warning (packetloss)');
                                          $gateway_label_class = 'warning';
                                      } elseif (stristr($status, 'delay')) {
                                          $online = gettext('Warning (latency)');
                                          $gateway_label_class = 'warning';
                                      } elseif ($status == 'none') {
                                          $online = gettext('Online');
                                          $gateway_label_class = 'success';
                                  } elseif (!empty($gateway['monitor_disable']))  {
                                      $online = gettext('Online');
                                      $gateway_label_class = 'success';
                                  }
                              }
?>
                                <div class="label label-<?= $gateway_label_class ?>" style="margin-right:4px">
                                  <?=$gateway['name'];?>, <?=$online;?>
                                </div>
<?php
                          endforeach;?>
                          </td>
                        </tr>
<?php
                        endforeach; ?>
                        </table>
                      </td>
                      <td class="hidden-xs"><?=$gateway_group['descr'];?></td>
                      <td class="text-nowrap">
                        <a href="system_gateway_groups_edit.php?id=<?= $i ?>" class="btn btn-default btn-xs"
                            title="<?= html_safe(gettext('Edit')) ?>" data-toggle="tooltip">
                          <i class="fa fa-pencil fa-fw"></i>
                        </a>
                        <button type="button" class="btn btn-default btn-xs act-del-group"
                            data-id="<?= $i ?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip">
                          <i class="fa fa-trash fa-fw"></i>
                        </button>
                        <a href="system_gateway_groups_edit.php?dup=<?= $i ?>" class="btn btn-default btn-xs"
                            title="<?= html_safe(gettext('Clone')) ?>" data-toggle="tooltip">
                          <i class="fa fa-clone fa-fw"></i>
                        </a>
                      </td>
                    </tr>
<?php $i++;
                    endforeach; ?>
                    <tr>
                      <td colspan="3">
                        <?= gettext("Remember to use these Gateway Groups in firewall rules in order to enable load balancing, failover, or policy-based routing. Without rules directing traffic into the Gateway Groups, they will not be used.") ?>
                      </td>
                      <td class="text-nowrap"></td>
                    </tr>
                  </tbody>
                </table>
                </div>
              </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc");
