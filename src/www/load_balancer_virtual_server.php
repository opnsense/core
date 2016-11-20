<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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
require_once("plugins.inc.d/relayd.inc");
require_once("services.inc");
require_once("interfaces.inc");

if (empty($config['load_balancer']['virtual_server']) || !is_array($config['load_balancer']['virtual_server'])) {
    $config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del") {
        if (isset($_POST['id']) && !empty($a_vs[$_POST['id']])){
            relayd_cleanup_lb_mark_anchor($a_vs[$_POST['id']]['name']);
            unset($a_vs[$_POST['id']]);
            write_config();
            mark_subsystem_dirty('loadbalancer');
        }
        exit;
    } elseif (!empty($_POST['apply'])) {
        relayd_configure_do();
        filter_configure();
        /* Wipe out old relayd anchors no longer in use. */
        relayd_cleanup_lb_marked();
        clear_subsystem_dirty('loadbalancer');
        header(url_safe('Location: /load_balancer_virtual_server.php'));
        exit;
    }
}

/* Index lbpool array for easy hyperlinking */
$poodex = array();
for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
    $poodex[$config['load_balancer']['lbpool'][$i]['name']] = $i;
}


$service_hook = 'relayd';

include("head.inc");
legacy_html_escape_form_data($a_vs);
$main_buttons = array(
    array('label'=>gettext('Add'), 'href'=>'load_balancer_virtual_server_edit.php'),
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
        title: "<?= gettext("Load Balancer: Virtual Server Setup");?>",
        message: "<?=gettext("Do you really want to delete this entry?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        location.reload();
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
          <?php print_info_box_apply(gettext("The virtual server configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
          <?php endif; ?>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th><?=gettext("Name");?></th>
                        <th><?=gettext("Protocol");?></th>
                        <th><?=gettext("IP Address");?></th>
                        <th><?=gettext('Port');?></th>
                        <th><?=gettext('Pool');?></th>
                        <th><?=gettext('Fall Back Pool');?></th>
                        <th><?=gettext("Description");?></th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
<?php
                    $i=0;
                    foreach ($a_vs as $vs):?>
                    <tr>
                      <td><?=$vs['name'];?></td>
                      <td><?=$vs['relay_protocol'];?></td>
                      <td><?=$vs['ipaddr'];?></td>
                      <td><?=$vs['port'];?></td>
                      <td>
                        <a href="load_balancer_pool_edit.php?id=<?=$poodex[$vs['poolname']];?>">
                          <?=$vs['poolname'];?>
                        </a>
                      <td>
<?php
                        if(!empty($vs['sitedown'])):?>
                        <a href="load_balancer_pool_edit.php?id=<?=$poodex[$vs['sitedown']];?>">
                          <?=$vs['sitedown'];?>
                        </a>
<?php
                        else:?>
                        <?=gettext("none");?>
<?php
                        endif;?>
                      <td><?=$vs['descr'];?></td>
                      <td>
                        <a href="load_balancer_virtual_server_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        <a data-id="<?=$i;?>"  class="act_delete btn btn-default btn-xs">
                          <span class="fa fa-trash text-muted"></span>
                        </a>
                        <a href="load_balancer_virtual_server_edit.php?act=dup&id=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?=gettext("clone rule");?>">
                          <span class="fa fa-clone text-muted"></span>
                        </a>
                      </td>
                    </tr>
<?php
                    ++$i;
                    endforeach;?>
                    </tbody>
                  </table>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </section>

<?php include("foot.inc"); ?>
