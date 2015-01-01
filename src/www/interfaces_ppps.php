<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require("guiconfig.inc");
require_once("functions.inc");

function ppp_inuse($num) {
	global $config, $g;
	$iflist = get_configured_interface_list(false, true);
	if (!is_array($config['ppps']['ppp']))
		return false;

	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $config['ppps']['ppp'][$num]['if'])
			return true;
	}
	return false;
}

if ($_GET['act'] == "del") {
	/* check if still in use */
	if (ppp_inuse($_GET['id'])) {
		$input_errors[] = gettext("This point-to-point link cannot be deleted because it is still being used as an interface.");
	} elseif (is_array($config['ppps']['ppp']) && is_array($config['ppps']['ppp'][$_GET['id']])) {

		unset($config['ppps']['ppp'][$_GET['id']]['pppoe-reset-type']);
		handle_pppoe_reset($config['ppps']['ppp'][$_GET['id']]);
		unset($config['ppps']['ppp'][$_GET['id']]);
		write_config();
		header("Location: interfaces_ppps.php");
		exit;
	}
}

if (!is_array($config['ppps']['ppp']))
	$config['ppps']['ppp'] = array();
$a_ppps = $config['ppps']['ppp'];

$pgtitle = gettext("Interfaces: PPPs");
$shortcut_section = "interfaces";
include("head.inc");
$main_buttons = array(
	array('href'=>'interfaces_ppps_edit.php', 'label'=>'Add'),
);

?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if ($input_errors) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">


					<?php
							$tab_array = array();
							$tab_array[0] = array(gettext("Interface assignments"), false, "interfaces_assign.php");
							$tab_array[1] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
							$tab_array[2] = array(gettext("Wireless"), false, "interfaces_wireless.php");
							$tab_array[3] = array(gettext("VLANs"), false, "interfaces_vlan.php");
							$tab_array[4] = array(gettext("QinQs"), false, "interfaces_qinq.php");
							$tab_array[5] = array(gettext("PPPs"), true, "interfaces_ppps.php");
							$tab_array[6] = array(gettext("GRE"), false, "interfaces_gre.php");
							$tab_array[7] = array(gettext("GIF"), false, "interfaces_gif.php");
							$tab_array[8] = array(gettext("Bridges"), false, "interfaces_bridge.php");
							$tab_array[9] = array(gettext("LAGG"), false, "interfaces_lagg.php");
							display_top_tabs($tab_array);
						?>


						<div class="tab-content content-box col-xs-12">

		                        <form action="interfaces_assign.php" method="post" name="iform" id="iform">

		                        <div class="table-responsive">
			                        <table class="table table-striped table-sort">

			                         <thead>
                                            <tr>
								<th width="20%" class="listtopic"><?=gettext("Interface");?></th>
								<th width="20%" class="listtopic"><?=gettext("Interface(s)/Port(s)");?></th>
								<th width="40%" class="listtopic"><?=gettext("Description");?></th>
								<th width="10%" class="listtopic">&nbsp;</th>
                                            </tr>
                                        </thead>

									<tbody>

									  <?php $i = 0; foreach ($a_ppps as $id => $ppp): ?>
						                <tr  ondblclick="document.location='interfaces_ppps_edit.php?id=<?=$i;?>'">
									<td class="listr">
											<?=htmlspecialchars($ppp['if']);?>
						                  </td>
						                  <td class="listr">
											<?php
												$portlist = explode(",", $ppp['ports']);
												foreach ($portlist as $portid => $port) {
													if ($port != get_real_interface($port) && $ppp['type'] != "ppp")
														$portlist[$portid] = convert_friendly_interface_to_friendly_descr($port);
												}
												echo htmlspecialchars(implode(",", $portlist));
											?>
						                  </td>
						                  <td class="listbg">
						                    <?=htmlspecialchars($ppp['descr']);?>&nbsp;
						                  </td>
						                  <td valign="middle" class="list nowrap">

							                   <a href="interfaces_ppps_edit.php?id=<?=$i;?>" class="btn btn-default"><span class="glyphicon glyphicon-edit" title="<?=gettext("edit group");?>"></span></a>

											   <a href="interfaces_ppps.php?act=del&amp;id=<?=$i;?>" class="btn btn-default"  onclick="return confirm('<?=gettext("Do you really want to delete this PPP interface?");?>')"><span class="glyphicon glyphicon-remove"></span></a>

							               </td>
										</tr>
									  <?php $i++; endforeach; ?>
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
