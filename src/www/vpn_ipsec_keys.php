<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
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
require_once("filter.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/ipsec.inc");

config_read_array('ipsec', 'mobilekey');
ipsec_mobilekey_sort();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && isset($_POST['id']) && is_numericint($_POST['id']) && $_POST['act'] == "del") {
        // delete entry
        if (isset($config['ipsec']['mobilekey'][$_POST['id']])) {
            unset($config['ipsec']['mobilekey'][$_POST['id']]);
            write_config('Deleted pre-shared IPsec key');
            mark_subsystem_dirty('ipsec');
            header(url_safe('Location: /vpn_ipsec_keys.php'));
            exit;
        }
    } elseif (isset($_POST['apply'])) {
        // apply changes
        ipsec_configure_do();
        filter_configure();
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('ipsec');
    } else {
        // nothing to post, redirect
        header(url_safe('Location: /vpn_ipsec_keys.php'));
        exit;
    }
}

$service_hook = 'strongswan';

include("head.inc");

?>
<body>

<script>
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(){
    var id = $(this).attr("id").split('_').pop(-1);
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
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
    print_info_box_apply(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}

?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post">
            <div class="table-responsive">
              <table class="table table-striped">
                <tr>
                  <td><?=gettext("Identifier"); ?></td>
                  <td><?=gettext("Pre-Shared Key"); ?></td>
                  <td><?=gettext("Type"); ?></td>
                  <td class="text-nowrap">
                    <a href="vpn_ipsec_keys_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                      <i class="fa fa-plus fa-fw"></i>
                    </a>
                  </td>
                </tr>
<?php           $i = 0;
                $userkeys = array();
                foreach ($config['system']['user'] as $id => $user) {
                    if (!empty($user['ipsecpsk'])) {
                        $userkeys[] = array('ident' => $user['name'], 'pre-shared-key' => $user['ipsecpsk'], 'id' => $id);
                    }
                }
                foreach ($userkeys as $secretent):?>
                <tr>
                  <td><?=htmlspecialchars($secretent['ident']) ;?></td>
                  <td><?=htmlspecialchars($secretent['pre-shared-key']);?></td>
                  <td>PSK</td>
                  <td class="text-nowrap">
                    <a href="system_usermanager.php?userid=<?=$secretent['id'];?>&act=edit" title="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                  </td>
                </tr>
<?php
                $i++;
                endforeach;
                $i = 0;
                foreach ($config['ipsec']['mobilekey'] as $secretent) :?>
                <tr>
                  <td><?=htmlspecialchars($secretent['ident']);?></td>
                  <td><?=htmlspecialchars($secretent['pre-shared-key']);?></td>
                  <td><?=!empty($secretent['type']) ? htmlspecialchars($secretent['type']) : "PSK"?> </td>
                  <td class="text-nowrap">
                    <a href="vpn_ipsec_keys_edit.php?id=<?=$i;?>" title="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                    <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" class="act_delete btn btn-default btn-xs"><i class="fa fa-trash fa-fw"></i></a>
                  </td>
                </tr>
<?php
                $i++;
                endforeach; ?>
                <tr>
                  <td colspan="4">
                    <?=gettext("PSK for any user can be set by using an identifier of any/ANY") ?>
                  </td>
                </tr>
              </table>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
