<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2010 Ermal Luci
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
require_once("plugins.inc.d/vpn.inc");
require_once("interfaces.inc");

if (empty($config['pppoes']['pppoe']) || !is_array($config['pppoes']['pppoe'])) {
    $config['pppoes'] = array();
    $config['pppoes']['pppoe'] = array();
}
$a_pppoes = &$config['pppoes']['pppoe'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['apply'])) {
        if (file_exists('/tmp/.vpn_pppoe.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.vpn_pppoe.apply'));
            foreach ($toapplylist as $pppoeid) {
                if (!is_numeric($pppoeid)) {
                    continue;
                }
                if (isset($config['pppoes']['pppoe'])) {
                    foreach ($config['pppoes']['pppoe'] as $pppoe) {
                        if ($pppoe['pppoeid'] == $pppoeid) {
                            vpn_pppoe_configure($pppoe);
                            break;
                        }
                    }
                }
            }
            @unlink('/tmp/.vpn_pppoe.apply');
        }

        filter_configure();
        clear_subsystem_dirty('vpnpppoe');
        header("Location: vpn_pppoe.php");
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "del") {
        if (!empty($a_pppoes[$_POST['id']])) {
            killbypid("/var/run/pppoe{$a_pppoes[$_POST['id']]['pppoeid']}-vpn.pid");
            mwexecf('/bin/rm -r %s', "/var/etc/pppoe{$a_pppoes[$_POST['id']]['pppoeid']}");
            unset($a_pppoes[$_POST['id']]);
            write_config();
            exit;
        }
    }


}

include("head.inc");
legacy_html_escape_form_data($a_pppoes);
$main_buttons = array(
    array('label'=>gettext("add a new pppoe instance"), 'href'=>'vpn_pppoe_edit.php'),
);

?>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete pppoe action
    $(".act_delete_pppoe").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("PPPoE");?>",
        message: "<?=gettext("Do you really want to delete this entry? All elements that still use it will become invalid (e.g. filter rules)!");?>",
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
  </script>
  <?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (is_subsystem_dirty('vpnpppoe')) : ?><br/>
        <?php print_info_box_apply(gettext("The PPPoE entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?>
        <?php endif; ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td><?=gettext("Interface");?></td>
                    <td><?=gettext("Local IP");?></td>
                    <td><?=gettext("Number of users");?></td>
                    <td><?=gettext("Description");?></td>
                    <td>
                    </td>
                  </tr>
<?php
                  $i = 0;
                  foreach ($a_pppoes as $pppoe) :?>
                  <tr>
                    <td><?=strtoupper($pppoe['interface']);?></td>
                    <td><?=$pppoe['localip'];?></td>
                    <td><?=$pppoe['n_pppoe_units'];?></td>
                    <td><?=$pppoe['descr'];?></td>
                    <td>
                      <a href="vpn_pppoe_edit.php?id=<?=$i;?>" title="<?=gettext("edit pppoe instance"); ?>" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-pencil"></span>
                      </a>
                      <button data-id="<?=$i;?>" type="button" class="act_delete_pppoe btn btn-xs btn-default"><span class="fa fa-trash text-muted"></span></button>
                    </td>
                  </tr>
<?php
                  $i++;
                  endforeach; ?>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc");
