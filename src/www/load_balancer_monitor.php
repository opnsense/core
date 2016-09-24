<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>.
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
require_once("services.inc");
require_once("vslb.inc");
require_once("interfaces.inc");

if (empty($config['load_balancer']['monitor_type']) || !is_array($config['load_balancer']['monitor_type'])) {
    $config['load_balancer']['monitor_type'] = array();
}
$a_monitor = &$config['load_balancer']['monitor_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del") {
        if (isset($_POST['id']) && !empty($a_monitor[$_POST['id']])){
            $input_errors = array();
            /* make sure no pools reference this entry */
            if (is_array($config['load_balancer']['lbpool'])) {
              foreach ($config['load_balancer']['lbpool'] as $pool) {
                if ($pool['monitor'] == $a_monitor[$_GET['id']]['name']) {
                  $input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one pool.");
                  break;
                }
              }
            }
            if (count($input_errors) == 0) {
                unset($a_monitor[$_POST['id']]);
                write_config();
                mark_subsystem_dirty('loadbalancer');
            } else {
                echo implode('\n', $input_errors);
            }
        }
        exit;
    } elseif (!empty($_POST['apply'])) {
        /* reload all components that use igmpproxy */
        relayd_configure();
        filter_configure();
        clear_subsystem_dirty('loadbalancer');
        header(url_safe('Location: /load_balancer_monitor.php'));
        exit;
    }
}

$service_hook = 'relayd';

include("head.inc");
legacy_html_escape_form_data($a_monitor);
$main_buttons = array(
    array('label'=>gettext('Add'), 'href'=>'load_balancer_monitor_edit.php'),
);

?>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete host action
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Load Balancer: Monitors");?>",
        message: "<?=gettext("Do you really want to delete this entry?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        if (data == "") {
                            // no errors
                            location.reload();
                        } else {
                            dialogRef.close();
                            BootstrapDialog.show({
                              type:BootstrapDialog.TYPE_DANGER,
                              title: "<?= gettext("Load Balancer: Monitors");?>",
                              message: data
                            });
                        }
                    });
                }
              }]
      });
    });
  });
  //]]>
  </script>
<?php include("fbegin.inc"); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (is_subsystem_dirty('loadbalancer')): ?><br/>
        <?php print_info_box_apply(gettext("The load balancer configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
        <?php endif; ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("Name");?></th>
                    <th><?=gettext("Type");?></th>
                    <th><?=gettext("Description");?></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
<?php
                $i = 0;
                foreach ($a_monitor as $monitor): ?>
                <tr>
                  <td><?=$monitor['name'];?></td>
                  <td><?=$monitor['type'];?></td>
                  <td><?=$monitor['descr'];?></td>
                  <td>
                    <a href="load_balancer_monitor_edit.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs">
                      <span class="glyphicon glyphicon-pencil"></span>
                    </a>
                    <a data-id="<?=$i;?>"  class="act_delete btn btn-default btn-xs">
                      <span class="fa fa-trash text-muted"></span>
                    </a>
                  </td>
                </tr>
<?php
                ++$i;
                endforeach;?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
