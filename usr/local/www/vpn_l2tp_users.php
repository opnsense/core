<?php
/*
	vpn_l2tp_users.php
	part of pfSense

	Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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

##|+PRIV
##|*IDENT=page-vpn-vpnl2tp-users
##|*NAME=VPN: VPN L2TP : Users page
##|*DESCR=Allow access to the 'VPN: VPN L2TP : Users' page.
##|*MATCH=vpn_l2tp_users.php*
##|-PRIV

$pgtitle = array(gettext("VPN"),gettext("L2TP"),gettext("Users"));
$shortcut_section = "l2tps";

require("guiconfig.inc");
require_once("vpn.inc");

if (!is_array($config['l2tp']['user'])) {
	$config['l2tp']['user'] = array();
}
$a_secret = &$config['l2tp']['user'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		if (!is_subsystem_dirty('rebootreq')) {
			$retval = vpn_l2tp_configure();
		}
		$savemsg = get_std_save_message($retval);
		if ($retval == 0) {
			if (is_subsystem_dirty('l2tpusers'))
				clear_subsystem_dirty('l2tpusers');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_secret[$_GET['id']]) {
		unset($a_secret[$_GET['id']]);
		write_config();
		mark_subsystem_dirty('l2tpusers');
		pfSenseHeader("vpn_l2tp_users.php");
		exit;
	}
}

include("head.inc");


$main_buttons = array(
	array('label'=>gettext("add user"), 'href'=>'vpn_l2tp_users_edit.php'),
);

?>

<body onload="<?= $jsevents["body"]["onload"] ?>">
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">	
			<div class="row">
				
				<?php if ($savemsg) print_info_box($savemsg); ?>
				<?php if (isset($config['l2tp']['radius']['enable']))
					print_info_box(gettext("Warning: RADIUS is enabled. The local user database will not be used.")); ?>
				<?php if (is_subsystem_dirty('l2tpusers')): ?><br/>
				<?php print_info_box_np(gettext("The l2tp user list has been modified") . ".<br />" . gettext("You must apply the changes in order for them to take effect") . ".<br /><b>" . gettext("Warning: this will terminate all current l2tp sessions!") . "</b>");?>
				<?php endif; ?>
				
				<div id="inputerrors"></div>

				
			    <section class="col-xs-12">
    				
    				<?php
						$tab_array = array();
						$tab_array[0] = array(gettext("Configuration"), false, "vpn_l2tp.php");
						$tab_array[1] = array(gettext("Users"), true, "vpn_l2tp_users.php");
						display_top_tabs($tab_array);
					?>
					
					<div class="tab-content content-box col-xs-12">	    					
    				    <div class="container-fluid">	
							
							<form action="vpn_l2tp_users.php" method="post" name="iform" id="iform">

							 <div class="table-responsive">
							 	<table class="table table-striped table-sort">
								 <tr>
				                  	<td class="listhdrr"><?=gettext("Username");?></td>
								  	<td class="listhdr"><?=gettext("IP address");?></td>
								  	<td class="list"></td>
								</tr>
							  <?php $i = 0; foreach ($a_secret as $secretent): ?>
				                <tr>
				                  <td class="listlr">
				                    <?=htmlspecialchars($secretent['name']);?>
				                  </td>
				                  <td class="listr">
				              <?php if($secretent['ip'] == "") $secretent['ip'] = "Dynamic"; ?>
				                    <?=htmlspecialchars($secretent['ip']);?>&nbsp;
				                  </td>
				                  <td class="list nowrap" width="150">
					                    <a href="vpn_l2tp_users_edit.php?id=<?=$i;?>" class="btn btn-default"><span class="glyphicon glyphicon-edit"></span></a>
                                       
                                        <a href="vpn_l2tp_users.php?act=del&amp;id=<?=$i;?>" class="btn btn-default" onclick="return confirm('<?=gettext("Do you really want to delete this user?");?>')"title="<?=gettext("delete user"); ?>"><span class="glyphicon glyphicon-remove"></span></a>
                                            
					                 </td>
								</tr>
							  <?php $i++; endforeach; ?>
				                
				              </table>
							 </div>
														 
							</form>
    				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
