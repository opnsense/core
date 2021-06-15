<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Erik Fonnesbeck
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

function clone_inuse($cloneif)
{
    global $config;

    foreach (array_keys(legacy_config_get_interfaces(['virtual' => false])) as $if) {
        if ($config['interfaces'][$if]['if'] == $cloneif) {
            return true;
        }
    }

    return false;
}

$a_clones = &config_read_array('wireless', 'clone');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    if (!empty($_POST['action']) && $_POST['action'] == "del" && !empty($a_clones[$_POST['id']])) {
        if (clone_inuse($a_clones[$_POST['id']]['cloneif'])) {
            /* check if still in use */
            $input_errors[] = gettext("This wireless clone cannot be deleted because it is assigned as an interface.");
        } else {
            mwexec("/sbin/ifconfig " . escapeshellarg($a_clones[$_POST['id']]['cloneif']) . " destroy");
            unset($a_clones[$_POST['id']]);
            write_config();

            header(url_safe('Location: /interfaces_wireless.php'));
            exit;
        }
    }
}

include("head.inc");

legacy_html_escape_form_data($a_clones);

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
        title: "<?= gettext("Wireless");?>",
        message: "<?=gettext("Do you really want to delete this wireless clone?");?>",
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
          <div class="tab-content content-box">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="action" name="action" value="">
              <input type="hidden" id="id" name="id" value="">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?=gettext("Interface");?></th>
                      <th><?=gettext("Mode");?></th>
                      <th><?=gettext("Description");?></th>
                      <th class="text-nowrap">
                        <a href="interfaces_wireless_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                          <i class="fa fa-plus fa-fw"></i>
                        </a>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_clones as $clone):?>
                    <tr>
                      <td><?=$clone['cloneif'];?></td>
                      <td><?=$wlan_modes[$clone['mode']];?></td>
                      <td><?=$clone['descr'];?></td>
                      <td class="text-nowrap">
                        <a href="interfaces_wireless_edit.php?id=<?=$i;?>" class="btn btn-xs btn-default" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                          <i class="fa fa-pencil fa-fw"></i>
                        </a>
                        <button title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" data-id="<?=$i;?>" class="btn btn-default btn-xs act_delete" type="submit">
                          <i class="fa fa-trash fa-fw"></i>
                        </button>
                      </td>
                    </tr>
<?php
                    $i++;
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
