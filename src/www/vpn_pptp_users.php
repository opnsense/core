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

require_once('guiconfig.inc');
require_once('vpn.inc');

if (!is_array($config['pptpd']['user'])) {
    $config['pptpd']['user'] = array();
}
$a_secret = &$config['pptpd']['user'];

if ($_POST) {
    $pconfig = $_POST;

    if ($_POST['apply']) {
        $retval = 0;
        $retval = vpn_setup();
        $savemsg = get_std_save_message();
        if ($retval == 0) {
            if (is_subsystem_dirty('pptpusers')) {
                clear_subsystem_dirty('pptpusers');
            }
        }
    }
}

if ($_GET['act'] == "del") {
    if ($a_secret[$_GET['id']]) {
        unset($a_secret[$_GET['id']]);
        write_config();
        mark_subsystem_dirty('pptpusers');
        header("Location: vpn_pptp_users.php");
        exit;
    }
}

$pgtitle = array(gettext('VPN'), gettext('PPTP'), gettext('Users'));
include("head.inc");

$main_buttons = array(
    array('label'=>gettext("add user"), 'href'=>'vpn_pptp_users_edit.php'),
);
?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">


				<?php if (isset($savemsg)) {
                    print_info_box($savemsg);
} ?>
				<?php if (isset($config['pptpd']['radius']['enable'])) {
                    print_info_box(gettext("Warning: RADIUS is enabled. The local user database will not be used."));
} ?>
				<?php if (is_subsystem_dirty('pptpusers')) :
?><br/>
				<?php print_info_box_apply(gettext("The PPTP user list has been modified").".<br />".gettext("You must apply the changes in order for them to take effect").".<br /></b><b>".gettext("Warning: this will terminate all current PPTP sessions")."!");?><br />
				<?php
endif; ?>

			    <section class="col-xs-12">

					<div class="tab-content content-box col-xs-12">

							<form action="vpn_pptp_users.php" method="post" name="iform" id="iform">

								<div class="table-responsive">
									<table class="table table-striped table-sort">
						                <tr>
						                  <td class="listhdrr"><?=gettext("Username");?></td>
						                  <td class="listhdr"><?=gettext("IP address");?></td>
						                  <td class="list">

										  </td>
										</tr>
                                        <?php $i = 0; foreach ($a_secret as $secretent) :
?>
						                <tr>
						                  <td class="listlr">
						                    <?=htmlspecialchars($secretent['name']);?>
						                  </td>
						                  <td class="listr">
						                    <?=htmlspecialchars($secretent['ip']);?>&nbsp;
						                  </td>
						                  <td class="list nowrap">
							                   <a href="vpn_pptp_users_edit.php?id=<?=$i;?>" class="btn btn-default"><span class="glyphicon glyphicon-edit"></span></a>

                                        <a href="vpn_pptp_users.php?act=del&amp;id=<?=$i;
?>" class="btn btn-default" onclick="return confirm('<?=gettext("Do you really want to delete this user?");
?>')"title="<?=gettext("delete user"); ?>"><span class="glyphicon glyphicon-remove"></span></a></td>
										</tr>
                                        <?php $i++;

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
