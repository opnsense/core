<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2009 Ermal Luçi
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

function qinq_inuse($qinq_intf) {
    global $config;
    foreach (legacy_config_get_interfaces(array("virtual" => false)) as $if => $intf) {
        if ($intf['if'] == $qinq_intf) {
            return true;
        }
    }
    return false;
}


$a_qinqs = &config_read_array('qinqs', 'qinqentry');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($a_qinqs[$_POST['id']])) {
        $id = $_POST['id'];
    }

    if (!empty($_POST['action']) && $_POST['action'] == "del" && isset($id)) {
        /* check if still in use */
        if (qinq_inuse($a_qinqs[$id]['qinqif'])) {
            $input_errors[] = gettext("This QinQ cannot be deleted because it is still being used as an interface.");
        } elseif (empty($a_qinqs[$id]['vlanif']) || !does_interface_exist($a_qinqs[$id]['vlanif'])) {
            $input_errors[] = gettext("QinQ interface does not exist");
        } else {
            $qinq =& $a_qinqs[$id];

            $delmembers = explode(" ", $qinq['members']);
            if (count($delmembers) > 0) {
              foreach ($delmembers as $tag) {
                  mwexec("/usr/sbin/ngctl shutdown {$qinq['vlanif']}h{$tag}:");
              }
            }
            mwexec("/usr/sbin/ngctl shutdown {$qinq['vlanif']}qinq:");
            mwexec("/usr/sbin/ngctl shutdown {$qinq['vlanif']}:");
            mwexec("/sbin/ifconfig {$qinq['vlanif']} destroy");
            unset($a_qinqs[$id]);

            write_config();

            header(url_safe('Location: /interfaces_qinq.php'));
            exit;
        }
    }
}



include("head.inc");
legacy_html_escape_form_data($a_qinqs);
$main_buttons = array(
  array('href'=>'interfaces_qinq_edit.php', 'label'=>gettext('Add')),
);

?>

<body>
  <script>
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("QinQ");?>",
        message: "<?=gettext("Do you really want to delete this QinQ?");?>",
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

  });
  </script>
<?php include("fbegin.inc"); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="action" name="action" value="">
              <input type="hidden" id="id" name="id" value="">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?=gettext("Interface");?></th>
                      <th><?=gettext("Tag");?></th>
                      <th><?=gettext("QinQ members");?></th>
                      <th><?=gettext("Description");?></th>
                      <th>&nbsp;</th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_qinqs as $qinq): ?>
                    <tr>
                      <td><?=$qinq['if'];?></td>
                      <td><?=$qinq['tag'];?></td>
                      <td><?=strlen($qinq['members']) > 20 ? substr($qinq['members'], 0, 20) . "..." : $qinq['members'] ;?></td>
                      <td><?=$qinq['descr'];?></td>
                      <td>
                        <a href="interfaces_qinq_edit.php?id=<?=$i;?>" class="btn btn-xs btn-default" data-toggle="tooltip" title="<?=gettext("edit group");?>">
                          <span class="glyphicon glyphicon-edit"></span>
                        </a>
                        <button title="<?=gettext("delete interface");?>" data-toggle="tooltip" data-id="<?=$i;?>" class="btn btn-default btn-xs act_delete" type="submit">
                          <span class="fa fa-trash text-muted"></span>
                        </button>
                      </td>
                    </tr>
<?php
                    $i++;
                    endforeach; ?>
                    <tr>
                      <td colspan="5">
                        <?= gettext("Not all drivers/NICs support 802.1Q QinQ tagging properly. On cards that do not explicitly support it, QinQ tagging will still work, but the reduced MTU may cause problems.");?>
                      </td>
                    </tr>
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
