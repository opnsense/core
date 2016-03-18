<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

require_once('guiconfig.inc');
require_once('services.inc');
require_once("system.inc");
require_once("plugins.inc");
require_once('plugins.inc.d/vpn.inc');

if (!is_array($config['pptpd']['user'])) {
    $config['pptpd']['user'] = array();
}
$a_secret = &$config['pptpd']['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // delete entry
    if (isset($_POST['act']) && $_POST['act'] == "del" && isset($_POST['id'])) {
        if (!empty($a_secret[$_POST['id']])) {
            unset($a_secret[$_POST['id']]);
            mark_subsystem_dirty('pptpusers');
            write_config();
        }
        exit;
    } elseif (!empty($_POST['apply'])) {
        vpn_pptpd_configure();
        clear_subsystem_dirty('pptpusers');
        header("Location: vpn_pptp_users.php");
        exit;

    }
}

$service_hook = 'pptpd';
include("head.inc");
$main_buttons = array(
    array('label'=>gettext("add user"), 'href'=>'vpn_pptp_users_edit.php'),
);

?>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete host action
    $(".act_delete_user").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?=gettext("delete user"); ?>",
        message: "<?=gettext("Do you really want to delete this user?");?>",
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
<?php
      if (isset($config['pptpd']['radius']['enable'])) {
          print_info_box(gettext("Warning: RADIUS is enabled. The local user database will not be used."));
      }
      if (is_subsystem_dirty('pptpusers')) :?><br/>
        <?php print_info_box_apply(gettext("The PPTP user list has been modified").".<br />".gettext("You must apply the changes in order for them to take effect").".<br /></b><b>".gettext("Warning: this will terminate all current PPTP sessions")."!");?></b><br />
<?php
      endif; ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped table-sort">
                    <tr>
                      <td><?=gettext("Username");?></td>
                      <td><?=gettext("IP address");?></td>
                      <td></td>
                    </tr>
<?php
                    $i = 0;
                    foreach ($a_secret as $secretent) :?>
                    <tr>
                      <td><?=htmlspecialchars($secretent['name']);?></td>
                      <td><?=htmlspecialchars($secretent['ip']);?></td>
                      <td>
                         <a href="vpn_pptp_users_edit.php?id=<?=$i;?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-edit"></span></a>
                         <button data-id="<?=$i;?>" type="button" class="act_delete_user btn btn-xs btn-default"><span class="fa fa-trash text-muted"></span></button>
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
