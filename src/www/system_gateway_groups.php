<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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
require_once("system.inc");
require_once("services.inc");
require_once("rrd.inc");

$a_gateway_groups = &config_read_array('gateways', 'gateway_group');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del" ) {
        if (!empty($a_gateway_groups[$_POST['id']])) {
            foreach ($config['filter']['rule'] as $idx => $rule) {
                if ($rule['gateway'] == $a_gateway_groups[$_POST['id']]['name']) {
                    unset($config['filter']['rule'][$idx]['gateway']);
                }
            }
            unset($a_gateway_groups[$_POST['id']]);
            write_config();
            mark_subsystem_dirty('staticroutes');
            header(url_safe('Location: /system_gateway_groups.php'));
            exit;
        }
    } elseif (isset($_POST['apply'])) {
        $retval = 0;
        $retval = system_routing_configure();

        configd_run('dyndns reload');
        configd_run('rfc2136 reload');
        configd_run('filter reload');

        /* reconfigure our gateway monitor */
        setup_gateways_monitor();

        if ($retval == 0) {
            clear_subsystem_dirty('staticroutes');
        }

        foreach ($a_gateway_groups as $gateway_group) {
            $gw_subsystem = 'gwgroup.' . $gateway_group['name'];
            if (is_subsystem_dirty($gw_subsystem)) {
                openvpn_configure_gwgroup($gateway_group['name']);
                clear_subsystem_dirty($gw_subsystem);
            }
        }
        header(url_safe('Location: /system_gateway_groups.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_gateway_groups);

$service_hook = 'apinger';

include("head.inc");

$main_buttons = array(
    array('label'=> gettext('Add group'), 'href'=>'system_gateway_groups_edit.php'),
);

?>
<script type="text/javascript">
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
      if (is_subsystem_dirty('staticroutes')) {
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
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <td><?= gettext("Group Name") ?></td>
                      <td class="hidden-xs"><?= gettext("Gateways") ?></td>
                      <td class="hidden-xs"><?= gettext("Priority") ?></td>
                      <td><?=gettext("Description");?></td>
                      <td></td>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_gateway_groups as $gateway_group) :
?>
                    <tr>
                      <td> <?=$gateway_group['name'];?> </td>
                      <td class="hidden-xs">
<?php
                      foreach ($gateway_group['item'] as $item):?>
                           <?=strtoupper(explode("|", $item)[0]);?> <br/>
<?php
                      endforeach;?>
                      </td>
                      <td class="hidden-xs">
<?php
                        foreach ($gateway_group['item'] as $item):?>
                             <?= sprintf(gettext('Tier %s'),explode("|", $item)[1]) ?> <br/>
<?php
                        endforeach;?>
                      </td>
                      <td><?=$gateway_group['descr'];?></td>
                      <td>
                        <a href="system_gateway_groups_edit.php?id=<?= $i ?>" class="btn btn-default btn-xs"
                            title="edit group" data-toggle="tooltip">
                          <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        <button type="button" class="btn btn-default btn-xs act-del-group"
                            data-id="<?= $i ?>" title="<?= gettext("delete group") ?>" data-toggle="tooltip">
                          <span class="fa fa-trash text-muted"></span>
                        </button>
                        <a href="system_gateway_groups_edit.php?dup=<?= $i ?>" class="btn btn-default btn-xs"
                            title="clone group" data-toggle="tooltip">
                          <span class="fa fa-clone text-muted"></span>
                        </a>
                      </td>
                    </tr>
<?php $i++;
                    endforeach; ?>
                    <tr class="hidden-xs">
                      <td colspan="5">
                        <?= gettext("Remember to use these Gateway Groups in firewall rules in order to enable load balancing, failover, or policy-based routing. Without rules directing traffic into the Gateway Groups, they will not be used.") ?>
                      </td>
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
