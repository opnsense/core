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

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("filter.inc");

if (!is_array($config['ipsec'])) {
        $config['ipsec'] = array();
}

if (!is_array($config['ipsec']['mobilekey'])) {
    $config['ipsec']['mobilekey'] = array();
}
ipsec_mobilekey_sort();
$a_secret = &$config['ipsec']['mobilekey'];

$userkeys = array();
foreach ($config['system']['user'] as $id => $user) {
    if (!empty($user['ipsecpsk'])) {
        $userkeys[] = array('ident' => $user['name'], 'pre-shared-key' => $user['ipsecpsk'], 'id' => $id);
        ;
    }
}

if (isset($_POST['apply'])) {
    $retval = vpn_ipsec_configure();
    /* reload the filter in the background */
    filter_configure();
    $savemsg = get_std_save_message($retval);
    if (is_subsystem_dirty('ipsec')) {
        clear_subsystem_dirty('ipsec');
    }
}

if ($_GET['act'] == "del") {
    if ($a_secret[$_GET['id']]) {
        unset($a_secret[$_GET['id']]);
        write_config(gettext("Deleted IPsec Pre-Shared Key"));
        mark_subsystem_dirty('ipsec');
        header("Location: vpn_ipsec_keys.php");
        exit;
    }
}

$pgtitle = gettext("VPN: IPsec: Keys");
$shortcut_section = "ipsec";

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">


				<?php
                if ($savemsg) {
                    print_info_box($savemsg);
                }
                if (is_subsystem_dirty('ipsec')) {
                    print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
                }

                ?>

			    <section class="col-xs-12">

				<? $active_tab = "/vpn_ipsec_settings.php";
                include('vpn_ipsec_tabs.inc'); ?>

					<div class="tab-content content-box col-xs-12">

							<form action="vpn_ipsec_keys.php" method="post">

								<div class="table-responsive">
									<table class="table table-striped table-sort">


						                <tr>
						                  <td class="listhdrr"><?=gettext("Identifier"); ?></td>
						                  <td class="listhdr"><?=gettext("Pre-Shared Key"); ?></td>
						                  <td class="list">
											<table border="0" cellspacing="0" cellpadding="1" summary="add key">
											    <tr>
											        <td width="20" height="17"></td>
												<td><a href="vpn_ipsec_keys_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a></td>
											    </tr>
											</table>
								  </td>
								</tr>
                                        <?php $i = 0; foreach ($userkeys as $secretent) :
?>
								<tr>
								<td class="listlr gray">
									<?php
                                    if ($secretent['ident'] == 'allusers') {
                                        echo gettext("ANY USER");
                                    } else {
                                        echo htmlspecialchars($secretent['ident']);
                                    }
                                    ?>
								</td>
								<td class="listr gray">
									<?=htmlspecialchars($secretent['pre-shared-key']);?>
								</td>
								<td class="list nowrap">
									<form action="system_usermanager.php" method="post" name="form_edit_key">
										<input type="hidden" name="act" value="edit" />
										<input type="hidden" name="userid" value="<?=$secretent['id'];?>" />
										<input type="image" name="edituser[]" width="17" height="17" border="0"
											src="/themes/<?=$g['theme'];?>/images/icons/icon_e.gif"
											title="<?=gettext("edit");?>" />
									</form>
								&nbsp;</td>
										</tr>
                                        <?php $i++;

endforeach; ?>

                                        <?php $i = 0; foreach ($a_secret as $secretent) :
?>
						                <tr>
						                  <td class="listlr">
						                    <?=htmlspecialchars($secretent['ident']);?>
						                  </td>
						                  <td class="listr">
						                    <?=htmlspecialchars($secretent['pre-shared-key']);?>
						                  </td>
						                  <td class="list nowrap"><a href="vpn_ipsec_keys_edit.php?id=<?=$i;
?>"><img src="./themes/<?= $g['theme'];
?>/images/icons/icon_e.gif" title="<?=gettext("edit key"); ?>" width="17" height="17" border="0" alt="edit" /></a>
						                     &nbsp;<a href="vpn_ipsec_keys.php?act=del&amp;id=<?=$i;
?>" onclick="return confirm('<?=gettext("Do you really want to delete this Pre-Shared Key?");
?>')"><img src="./themes/<?= $g['theme'];
?>/images/icons/icon_x.gif" title="<?=gettext("delete key"); ?>" width="17" height="17" border="0" alt="delete" /></a></td>
										</tr>
                                        <?php $i++;

endforeach; ?>
						                <tr>
						                  <td class="list" colspan="2"></td>
						                  <td class="list">
									<table border="0" cellspacing="0" cellpadding="1" summary="add key">
									    <tr>
									        <td width="20" height="17"></td>
										<td><a href="vpn_ipsec_keys_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a></td>
									    </tr>
									</table>
								  </td>
								</tr>
						              </table>
							</div>
							</form>

							<div class="container-fluid">
							<p>
							<span class="vexpl">
							<span class="text-danger">
								<strong><?=gettext("Note"); ?>:<br /></strong>
							</span>
							<?=gettext("PSK for any user can be set by using an identifier of any/ANY");?>
							</span>
							</p>
				        </div>
					</div>
			    </section>
			</div>
		</div>
</section>

<?php include("foot.inc");
