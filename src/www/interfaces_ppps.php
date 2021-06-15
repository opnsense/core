<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

function ppp_inuse($ppp_intf)
{
    global $config;

    if (isset($config['ppps']['ppp']) && !is_array($config['ppps']['ppp'])) {
        foreach (legacy_config_get_interfaces(array("virtual" => false)) as $intf) {
            if ($intf['if'] == $ppp_intf) {
                return true;
            }
        }
    }

    return false;
}

$a_ppps = &config_read_array('ppps', 'ppp');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    if (!empty($a_ppps[$_POST['id']])) {
        $id = $_POST['id'];
    }

    if (!empty($_POST['action']) && $_POST['action'] == "del" && isset($id)) {
        if (ppp_inuse($a_ppps[$id]['if'])) {
            $input_errors[] = gettext("This point-to-point link cannot be deleted because it is still being used as an interface.");
        } else {
            unset($a_ppps[$id]);
            write_config();
            header(url_safe('Location: /interfaces_ppps.php'));
            exit;
        }
    }
}

include("head.inc");

legacy_html_escape_form_data($a_ppps);

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
        title: "<?= gettext("Point-to-Point");?>",
        message: "<?=gettext("Do you really want to delete this PPP interface?");?>",
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
                      <th><?=gettext("Interface(s)/Port(s)");?></th>
                      <th><?=gettext("Description");?></th>
                      <th class="text-nowrap">
                        <a href="interfaces_ppps_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                          <i class="fa fa-plus fa-fw"></i>
                        </a>
                      </th>
                    </tr>
                  </thead>
                  <tbody>

<?php
                  $i = 0;
                  foreach ($a_ppps as $id => $ppp): ?>
                  <tr>
                    <td><?=$ppp['if'];?></td>
                    <td>
<?php
                    $portlist = explode(",", $ppp['ports']);
                    foreach ($portlist as $portid => $port) {
                      if ($port != get_real_interface($port) && $ppp['type'] != "ppp")
                        $portlist[$portid] = convert_friendly_interface_to_friendly_descr($port);
                    }?>
                      <?=htmlspecialchars(implode(",", $portlist));?>
                    </td>
                    <td><?=$ppp['descr'];?></td>
                    <td class="text-nowrap">
                      <a href="interfaces_ppps_edit.php?id=<?=$i;?>" class="btn btn-xs btn-default" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>">
                        <i class="fa fa-pencil fa-fw"></i>
                      </a>
                      <button title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" data-id="<?=$i;?>" class="btn btn-default btn-xs act_delete" type="submit">
                        <i class="fa fa-trash fa-fw"></i>
                      </button>
                    </td>
                  </tr>
<?php
                  $i++;
                  endforeach; ?>
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
