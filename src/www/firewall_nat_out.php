<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004 Scott Ullrich
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
require_once("filter.inc");
require_once("shaper.inc");

global $FilterIflist;
global $GatewaysList;

if (!is_array($config['nat']['outbound']))
	$config['nat']['outbound'] = array();

if (!is_array($config['nat']['outbound']['rule']))
	$config['nat']['outbound']['rule'] = array();

$a_out = &$config['nat']['outbound']['rule'];

if (!isset($config['nat']['outbound']['mode']))
	$config['nat']['outbound']['mode'] = "automatic";

$mode = $config['nat']['outbound']['mode'];

if ($_POST['apply']) {
	write_config();

	$retval = 0;
	$retval |= filter_configure();

	if(stristr($retval, "error") <> true)
	        $savemsg = get_std_save_message($retval);
	else
		$savemsg = $retval;

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}
}

if (isset($_POST['save']) && $_POST['save'] == "Save") {
	/* mutually exclusive settings - if user wants advanced NAT, we don't generate automatic rules */
	if ($_POST['mode'] == "advanced" && ($mode == "automatic" || $mode == "hybrid")) {
		/*
		 *    user has enabled advanced outbound NAT and doesn't have rules
		 *    lets automatically create entries
		 *    for all of the interfaces to make life easier on the pip-o-chap
		 */
		if(empty($FilterIflist))
			filter_generate_optcfg_array();
		if(empty($GatewaysList))
			filter_generate_gateways();
		$tonathosts = filter_nat_rules_automatic_tonathosts(true);
		$automatic_rules = filter_nat_rules_outbound_automatic("");

		foreach ($tonathosts as $tonathost) {
			foreach ($automatic_rules as $natent) {
				$natent['source']['network'] = $tonathost['subnet'];
				$natent['descr'] .= sprintf(gettext(' - %1$s to %2$s'),
					$tonathost['descr'],
					convert_real_interface_to_friendly_descr($natent['interface']));
				$natent['created'] = make_config_revision_entry(null, gettext("Manual Outbound NAT Switch"));

				/* Try to detect already auto created rules and avoid duplicate them */
				$found = false;
				foreach ($a_out as $rule) {
					if ($rule['interface'] == $natent['interface'] &&
					    $rule['source']['network'] == $natent['source']['network'] &&
					    $rule['dstport'] == $natent['dstport'] &&
					    $rule['target'] == $natent['target'] &&
					    $rule['descr'] == $natent['descr']) {
						$found = true;
						break;
					}
				}

				if ($found === false)
					$a_out[] = $natent;
			}
		}
		$savemsg = gettext("Default rules for each interface have been created.");
		unset($FilterIflist, $GatewaysList);
	}

	$config['nat']['outbound']['mode'] = $_POST['mode'];

	if (write_config())
		mark_subsystem_dirty('natconf');
	header("Location: firewall_nat_out.php");
	exit;
}

if ($_GET['act'] == "del") {
	if ($a_out[$_GET['id']]) {
		unset($a_out[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_out[$rulei]);
		}
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}

} else if ($_GET['act'] == "toggle") {
	if ($a_out[$_GET['id']]) {
		if(isset($a_out[$_GET['id']]['disabled']))
			unset($a_out[$_GET['id']]['disabled']);
		else
			$a_out[$_GET['id']]['disabled'] = true;
		if (write_config("Firewall: NAT: Outbound, enable/disable NAT rule"))
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
		}
	}
	/* move selected rules before this rule */
	if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_out_new = array();

		/* copy all rules < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}

		/* copy all selected rules */
		for ($i = 0; $i < count($a_out); $i++) {
			if ($i == $movebtn)
				continue;
			if (in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}

		/* copy $movebtn rule */
		if ($movebtn < count($a_out))
			$a_out_new[] = $a_out[$movebtn];

		/* copy all rules > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_out); $i++) {
			if (!in_array($i, $_POST['rule']))
				$a_out_new[] = $a_out[$i];
		}
		if (count($a_out_new) > 0)
			$a_out = $a_out_new;

		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_out.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Outbound"));
include("head.inc");

?>


<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php
				if ($savemsg)
					print_info_box($savemsg);
				if (is_subsystem_dirty('natconf'))
					print_info_box_np(gettext("The NAT configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
				?>

	            <form action="firewall_nat_out.php" method="post" name="iform" id="iform">

			    <section class="col-xs-12">

					<?php
							$tab_array = array();
							$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
							$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
							$tab_array[] = array(gettext("Outbound"), true, "firewall_nat_out.php");
							$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
							display_top_tabs($tab_array);
					?>

					<div class="tab-content content-box col-xs-12">

		                        <table class="table table-striped table-sort">
		                        <thead>
			                        <tr>
				                        <th colspan="4"><?=gettext("Mode:"); ?></th>
			                        </tr>
		                        </thead>
		                        <tbody>
									<tr>
										<td>
											<input name="mode" type="radio" id="automatic" value="automatic" <?php if ($mode == "automatic") echo "checked=\"checked\"";?> />
										</td>
										<td>
											<strong>
												<?=gettext("Automatic outbound NAT rule generation"); ?><br />
												<?=gettext("(IPsec passthrough included)");?>
											</strong>
										</td>
										<td>
											<input name="mode" type="radio" id="hybrid" value="hybrid" <?php if ($mode == "hybrid") echo "checked=\"checked\"";?> />
										</td>
										<td>
											<strong>
												<?=gettext("Hybrid Outbound NAT rule generation"); ?><br />
												<?=gettext("(Automatic Outbound NAT + rules below)");?>
											</strong>
										</td>
									</tr>

									<tr>
										<td>
											<input name="mode" type="radio" id="advanced" value="advanced" <?php if ($mode == "advanced") echo "checked=\"checked\"";?> />
										</td>
										<td>
											<strong>
												<?=gettext("Manual Outbound NAT rule generation"); ?><br />
												<?=gettext("(AON - Advanced Outbound NAT)");?>
											</strong>
										</td>
										<td>
											<input name="mode" type="radio" id="disabled" value="disabled" <?php if ($mode == "disabled") echo "checked=\"checked\"";?> />
										</td>
										<td>
											<strong>
												<?=gettext("Disable Outbound NAT rule generation"); ?><br />
												<?=gettext("(No Outbound NAT rules)");?>
											</strong>
										</td>
									</tr>
									<tr>
										<td colspan="4">

											<input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
										</td>
									</tr>
		                        </tbody>
								</table>

					</div>
				</section>

	            <section class="col-xs-12">

					<div class="table-responsive content-box ">

		                        <table class="table table-striped table-sort">
		                        <thead>
									<tr><th colspan="12"><?=gettext("Mappings:"); ?></th></tr>


									<tr id="frheader">
										<th width="2%" class="list">&nbsp;</th>
										<th width="3%" class="list">&nbsp;</th>
										<th width="10%" class="listhdrr"><?=gettext("Interface");?></th>
										<th width="10%" class="listhdrr"><?=gettext("Source");?></th>
										<th width="5%" class="listhdrr"><?=gettext("Source Port");?></th>
										<th width="10%" class="listhdrr"><?=gettext("Destination");?></th>
										<th width="10%" class="listhdrr"><?=gettext("Destination Port");?></th>
										<th width="10%" class="listhdrr"><?=gettext("NAT Address");?></th>
										<th width="10%" class="listhdrr"><?=gettext("NAT Port");?></th>
										<th width="10%" class="listhdrr"><?=gettext("Static Port");?></th>
										<th width="10%" class="listhdr"><?=gettext("Description");?></th>
										<th class="list">

											<a href="firewall_nat_out_edit.php?after=-1" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
										</th>
									</tr>
		                        </thead>
		                        <tbody>
					<?php
								$i = 0;
								foreach ($a_out as $natent):
									$iconfn = "glyphicon glyphicon-play";
									$textss = "text-success";
									if ($mode == "disabled" || $mode == "automatic" || isset($natent['disabled'])) {
										$textss = "text-muted";
									}

									//build Alias popup box
									$alias_src_span_begin = "";
									$alias_src_port_span_begin = "";
									$alias_dst_span_begin = "";
									$alias_dst_port_span_begin = "";

									$alias_popup = rule_popup($natent['source']['network'],pprint_port($natent['sourceport']),$natent['destination']['address'],pprint_port($natent['dstport']));

									$alias_src_span_begin = $alias_popup["src"];
									$alias_src_port_span_begin = $alias_popup["srcport"];
									$alias_dst_span_begin = $alias_popup["dst"];
									$alias_dst_port_span_begin = $alias_popup["dstport"];

									$alias_src_span_end = $alias_popup["src_end"];
									$alias_src_port_span_end = $alias_popup["srcport_end"];
									$alias_dst_span_end = $alias_popup["dst_end"];
									$alias_dst_port_span_end = $alias_popup["dstport_end"];
					?>
									<tr valign="top" id="fr<?=$i;?>">
										<td class="listt">
											<input type="checkbox" id="frc<?=$i;?>" name="rule[]" value="<?=$i;?>"  />
										</td>
										<td class="listt" align="center">
					<?php
										if ($mode == "disabled" || $mode == "automatic"):
					?>

											<span title="<?=gettext("This rule is being ignored");?>" class="<?=$iconfn;?> <?=$textss;?>"></span>

					<?php
										else:
					?>
											<a href="?act=toggle&amp;id=<?=$i;?>" title="<?=gettext("click to toggle enabled/disabled status");?>" class="btn btn-default btn-xs <?=$textss;?>"><span class="<?=$iconfn;?>"></span></a>
					<?php
											endif;
					?>
										</td>
										<td class="listlr"  id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
											<?php echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . $textse; ?>
											&nbsp;
										</td>
										<td class="listr" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
											<?PHP $natent['source']['network'] = ($natent['source']['network'] == "(self)") ? "This Firewall" : $natent['source']['network']; ?>
											<?php echo $alias_src_span_begin . $natent['source']['network'] . $alias_src_span_end . $textse;?>
										</td>
										<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
					<?php

											echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
											if (!$natent['sourceport'])
												echo "*";
											else
												echo $alias_src_port_span_begin . $natent['sourceport'] . $alias_src_port_span_end;
											echo $textse;
					?>
										</td>
										<td class="listr"  id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
					<?php

											if (isset($natent['destination']['any']))
												echo "*";
											else {
												if (isset($natent['destination']['not']))
													echo "!&nbsp;";
												echo $alias_dst_span_begin . $natent['destination']['address'] . $alias_dst_span_end;
											}
											echo $textse;
					?>
										</td>
										<td class="listr"  id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
					<?php

											echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
											if (!$natent['dstport'])
												echo "*";
											else
												echo $alias_dst_port_span_begin . $natent['dstport'] . $alias_dst_port_span_end;
											echo $textse;
					?>
										</td>
										<td class="listr"  id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
					<?php

											if (isset($natent['nonat']))
												echo '<I>NO NAT</I>';
											elseif (!$natent['target'])
												echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
											elseif ($natent['target'] == "other-subnet")
												echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
											else
												echo $natent['target'];
											echo $textse;
					?>
										</td>
										<td class="listr"  id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
					<?php

											if (!$natent['natport'])
												echo "*";
											else
												echo $natent['natport'];
											echo $textse;
					?>
										</td>
										<td class="listr"  id="frd<?=$i;?>" ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';" align="center">
					<?php

											if(isset($natent['staticnatport']))
												echo gettext("YES");
											else
												echo gettext("NO");
											echo $textse;
					?>
										</td>
										<td class="listbg"  ondblclick="document.location='firewall_nat_out_edit.php?id=<?=$i;?>';">
											<?=htmlspecialchars($natent['descr']);?>&nbsp;
										</td>
										<td class="list nowrap" valign="middle">
												<button onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>_x" title="<?=gettext("move selected rules before this rule");?>" type="submit" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-arrow-left"></span></button>

												<a href="firewall_nat_out_edit.php?id=<?=$i;?>" title="<?=gettext("edit mapping");?>" alt="edit"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
												<a href="firewall_nat_out.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this rule?");?>')"  title="<?=gettext("delete rule");?>" alt="delete"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
												<a href="firewall_nat_out_edit.php?dup=<?=$i;?>" title="<?=gettext("add a new NAT based on this one");?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
										</td>
									</tr>
					<?php
									$i++;
								endforeach;
					?>
				<tr valign="top" id="fr<?=$i;?>">
					<td class="list" colspan="11"></td>
					<td class="list nowrap" valign="middle">

<?php
								if ($i == 0):
?>

									<span class="btn btn-default btn-xs"><span class="glyphicon glyphicon-arrow-left"></span></span>


<?php
								else:
?>
									<button onmouseover="fr_insline(<?=$i;?>, true)" onmouseout="fr_insline(<?=$i;?>, false)" name="move_<?=$i;?>_x" type="submit"  title="<?=gettext("move selected mappings to end");?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-arrow-left"></span></button>
<?php
								endif;
?>

									<a href="firewall_nat_out_edit.php" title="<?=gettext("add new mapping");?>" alt="add"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>

<?php
								if ($i == 0):
?>
									<span title="<?=gettext("delete selected rules");?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></span>
<?php
								else:
?>
									<button name="del_x" type="submit" title="<?=gettext("delete selected mappings");?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected mappings?");?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></button>
<?php
								endif;
?>

					</td>
				</tr>
            </tbody>
<?php
			if ($mode == "automatic" || $mode == "hybrid"):
				if(empty($FilterIflist))
					filter_generate_optcfg_array();
				if(empty($GatewaysList))
					filter_generate_gateways();
				$automatic_rules = filter_nat_rules_outbound_automatic(implode(" ", filter_nat_rules_automatic_tonathosts()));
				unset($FilterIflist, $GatewaysList);
?>
            <thead>
				<tr><th colspan="12"><?=gettext("Automatic rules:"); ?></th></tr>
				<tr id="frheader">
					<th width="3%" class="list">&nbsp;</th>
					<th width="3%" class="list">&nbsp;</th>
					<th width="10%" class="listhdrr"><?=gettext("Interface");?></th>
					<th width="10%" class="listhdrr"><?=gettext("Source");?></th>
					<th width="10%" class="listhdrr"><?=gettext("Source Port");?></th>
					<th width="15%" class="listhdrr"><?=gettext("Destination");?></th>
					<th width="10%" class="listhdrr"><?=gettext("Destination Port");?></th>
					<th width="15%" class="listhdrr"><?=gettext("NAT Address");?></th>
					<th width="10%" class="listhdrr"><?=gettext("NAT Port");?></th>
					<th width="10%" class="listhdrr"><?=gettext("Static Port");?></th>
					<th width="25%" class="listhdr"><?=gettext("Description");?></th>
					<th class="list">&nbsp;</th>
				</tr>
            </thead>
            <tbody>
<?php
				foreach ($automatic_rules as $natent):
?>
					<tr valign="top">
						<td class="list">&nbsp;</td>
						<td class="listt" align="center">
							<span class="glyphicon glyphicon-play text-success" title="<?=gettext("automatic outbound nat");?>"></span>

						</td>
						<td class="listlr">
							<?php echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])); ?>
							&nbsp;
						</td>
						<td class="listr">
							<?=$natent['source']['network'];?>
						</td>
						<td class="listr">
<?php
							echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
							if (!$natent['sourceport'])
								echo "*";
							else
								echo $natent['sourceport'];
?>
						</td>
						<td class="listr">
<?php
							if (isset($natent['destination']['any']))
								echo "*";
							else {
								if (isset($natent['destination']['not']))
									echo "!&nbsp;";
								echo $natent['destination']['address'];
							}
?>
						</td>
						<td class="listr">
<?php
							echo ($natent['protocol']) ? $natent['protocol'] . '/' : "" ;
							if (!$natent['dstport'])
								echo "*";
							else
								echo $natent['dstport'];
?>
						</td>
						<td class="listr">
<?php
							if (isset($natent['nonat']))
								echo '<I>NO NAT</I>';
							elseif (!$natent['target'])
								echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface'])) . " address";
							elseif ($natent['target'] == "other-subnet")
								echo $natent['targetip'] . '/' . $natent['targetip_subnet'];
							else
								echo $natent['target'];
?>
						</td>
						<td class="listr">
<?php
							if (!$natent['natport'])
								echo "*";
							else
								echo $natent['natport'];
?>
						</td>
						<td class="listr">
<?php
							if(isset($natent['staticnatport']))
								echo gettext("YES");
							else
								echo gettext("NO");
?>
						</td>
						<td class="listbg">
							<?=htmlspecialchars($natent['descr']);?>&nbsp;
						</td>
						<td class="list">&nbsp;</td>
					</tr>
<?php
				endforeach;
			endif;
?>
				<tr>
					<td colspan="12">
						<p><span class="vexpl">
							<span class="red"><strong><?=gettext("Note:"); ?><br /></strong></span>
							<?=gettext("If automatic outbound NAT selected, a mapping is automatically created " .
								"for each interface's subnet (except WAN-type connections) and the rules " .
								"on \"Mappings\" section of this page are ignored.<br /><br /> " .
								"If manual outbound NAT is selected, outbound NAT rules will not be " .
								"automatically generated and only the mappings you specify on this page " .
								"will be used. <br /><br /> " .
								"If hybrid outbound NAT is selected, mappings you specify on this page will " .
								"be used, followed by the automatically generated ones. <br /><br />" .
								"If disable outbound NAT is selected, no rules will be used. <br /><br />" .
								"If a target address other than a WAN-type interface's IP address is used, " .
								"then depending on the way the WAN connection is setup, a "); ?>
								<a href="firewall_virtual_ip.php"><?=gettext("Virtual IP"); ?></a>
								<?= gettext(" may also be required.") ?>
						</span></p>
					</td>
				</tr>
            </tbody>
			</table>

					</div>
	            </section>
	            </form>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
