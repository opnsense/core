<?php
/*
  Copyright (C) 2014-2015 Deciso B.V.
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

require_once("guiconfig.inc");
require_once("vpn.inc");
require_once("filter.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}

if (!is_array($config['ipsec']['mobilekey'])) {
    $config['ipsec']['mobilekey'] = array();
} else {
    ipsec_mobilekey_sort();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && isset($_POST['id']) && is_numericint($_POST['id']) && $_POST['act'] == "del") {
        // delete entry
        if (isset($config['ipsec']['mobilekey'][$_POST['id']])) {
            unset($config['ipsec']['mobilekey'][$_POST['id']]);
            write_config(gettext("Deleted IPsec Pre-Shared Key"));
            mark_subsystem_dirty('ipsec');
            header("Location: vpn_ipsec_keys.php");
            exit;
        }
    } elseif (isset($_POST['apply'])) {
        // apply changes
        $retval = vpn_ipsec_configure();
        /* reload the filter in the background */
        filter_configure();
        $savemsg = get_std_save_message();
        if (is_subsystem_dirty('ipsec')) {
            clear_subsystem_dirty('ipsec');
        }
    } else {
      // nothing to post, redirect
      header("Location: vpn_ipsec_keys.php");
      exit;
    }
}

$pgtitle = gettext("VPN: IPsec: Keys");
$shortcut_section = "ipsec";

include("head.inc");
?>


<body>
<script type="text/javascript">
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(){
    var id = $(this).attr("id").split('_').pop(-1);
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_INFO,
        title: "<?= gettext("IPsec");?>",
        message: "<?= gettext("Do you really want to delete this Pre-Shared Key?");?>",
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
                    dialogRef.close();
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
      if (isset($savemsg)) {
          print_info_box($savemsg);
      }
      if (is_subsystem_dirty('ipsec')) {
          print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
      }

?>
      <section class="col-xs-12">
<?php
        $active_tab = "/vpn_ipsec_settings.php";
        include('vpn_ipsec_tabs.inc');
?>
        <div class="tab-content content-box col-xs-12">
          <form action="vpn_ipsec_keys.php" method="post">
            <div class="table-responsive">
              <table class="table table-striped">
                <tr>
                  <td><?=gettext("Identifier"); ?></td>
                  <td><?=gettext("Pre-Shared Key"); ?></td>
                  <td>
                    <a href="vpn_ipsec_keys_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
                  </td>
                </tr>
<?php           $i = 0;
                $userkeys = array();
                foreach ($config['system']['user'] as $id => $user) {
                    if (!empty($user['ipsecpsk'])) {
                        $userkeys[] = array('ident' => $user['name'], 'pre-shared-key' => $user['ipsecpsk'], 'id' => $id);
                        ;
                    }
                }
                foreach ($userkeys as $secretent) :
?>
                <tr>
                  <td>
                    <?=$secretent['ident'] == 'allusers' ? gettext("ANY USER") : htmlspecialchars($secretent['ident']) ;?>
                  </td>
                  <td>
                    <?=htmlspecialchars($secretent['pre-shared-key']);?>
                  </td>
                  <td>
                    <a href="system_usermanager.php?userid=<?=$secretent['id'];?>&act=edit" title="<?=gettext("edit"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                  </td>
                </tr>
<?php           $i++;
                endforeach; ?>
<?php
                $i = 0;
                foreach ($config['ipsec']['mobilekey'] as $secretent) :
?>
                <tr>
                  <td>
                    <?=htmlspecialchars($secretent['ident']);?>
                  </td>
                  <td>
                    <?=htmlspecialchars($secretent['pre-shared-key']);?>
                  </td>
                  <td>
                    <a href="vpn_ipsec_keys_edit.php?id=<?=$i;?>" title="<?=gettext("edit key"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                    <a id="del_<?=$i;?>" title="<?=gettext("delete key"); ?>" class="act_delete btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
                  </td>
                </tr>
<?php           $i++;
                endforeach; ?>
                <tr>
                  <td colspan="2"></td>
                  <td>
                    <a href="vpn_ipsec_keys_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
                  </td>
                </tr>
              </table>
            </div>
          </form>
          <div class="container-fluid">
            <span class="text-danger">
              <strong><?=gettext("Note"); ?>:<br /></strong>
            </span>
            <?=gettext("PSK for any user can be set by using an identifier of any/ANY");?>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
