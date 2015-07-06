<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc <mgrooms@shrew.net>.
	Copyright (C) 2003-2004 Manuel Kasper
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

global $g;

$pgtitle = array(gettext("Status"),gettext("IPsec"));
$shortcut_section = "ipsec";

require_once("guiconfig.inc");
include("head.inc");
require_once("ipsec.inc");

if ($_GET['act'] == 'connect') {
	if (ctype_digit($_GET['ikeid'])) {
		mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
		mwexec("/usr/local/sbin/ipsec up con" . escapeshellarg($_GET['ikeid']));
	}
} else if ($_GET['act'] == 'ikedisconnect') {
	if (ctype_digit($_GET['ikeid'])) {
		if (!empty($_GET['ikesaid']) && ctype_digit($_GET['ikesaid']))
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']) . "[" . escapeshellarg($_GET['ikesaid']) . "]");
		else
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']));
	}
} else if ($_GET['act'] == 'childdisconnect') {
	if (ctype_digit($_GET['ikeid'])) {
		if (!empty($_GET['ikesaid']) && ctype_digit($_GET['ikesaid']))
			mwexec("/usr/local/sbin/ipsec down con" . escapeshellarg($_GET['ikeid']) . "{" . escapeshellarg($_GET['ikesaid']) . "}");
	}
}

if (!is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}

if (!is_array($config['ipsec']['phase1'])) {
    $config['ipsec']['phase1'] = array();
}

$a_phase1 = &$config['ipsec']['phase1'];

$status = ipsec_smp_dump_status();

?>


<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<? $active_tab = "/diag_ipsec.php"; include('diag_ipsec_tabs.inc'); ?>

					<div class="tab-content content-box col-xs-12">

							<div class="table-responsive">
								<table class="table table-striped table-sort">
									<thead>
									<tr>
										<th class="listhdrr nowrap"><?php echo gettext("Description");?></th>
										<th class="listhdrr nowrap"><?php echo gettext("Local ID");?></th>
										<th class="listhdrr nowrap"><?php echo gettext("Local IP");?></th>
										<th class="listhdrr nowrap"><?php echo gettext("Remote ID");?></th>
										<th class="listhdrr nowrap"><?php echo gettext("Remote IP");?></th>
										<th class="listhdrr nowrap"><?php echo gettext("Role");?></th>
										<th class="listhdrr nowrap"><?php echo gettext("Status");?></th>
									</tr>
									</thead>
									<tbody>
									<?php
										$ipsecconnected = array();
										if (is_array($status['query']) && is_array($status['query']['ikesalist']) && is_array($status['query']['ikesalist']['ikesa'])) {
											foreach ($status['query']['ikesalist']['ikesa'] as $ikeid => $ikesa) {
												$con_id = substr($ikesa['peerconfig'], 3);
												$ipsecconnected[$con_id] = $con_id;

												if (ipsec_phase1_status($status['query']['ikesalist']['ikesa'], $ikesa['id'])) {
													$icon = "glyphicon glyphicon-play text-success";
												} elseif(!isset($config['ipsec']['enable'])) {
													$icon = "glyphicon glyphicon-remove text-danger";
												} else {
													$icon = "glyphicon glyphicon-remove text-warning";
												}
									?>
												<tr>
													<td class="listr"><?php echo htmlspecialchars(ipsec_get_descr($con_id));?></td>
													<td class="listr">
													<?php   if (!is_array($ikesa['local'])) {
																	echo "Unknown";
														} else {
															if (!empty($ikesa['local']['identification'])) {
																if ($ikesa['local']['identification'] == '%any')
																	echo 'Any identifier';
																else
																	echo htmlspecialchars($ikesa['local']['identification']);
															} else {
																echo 'Unknown';
															}
														}
													?>
													</td>
													<td class="listr">
													<?php   if (!is_array($ikesa['local'])) {
															echo "Unknown";
														} else {
															if (!empty($ikesa['local']['address'])) {
																echo htmlspecialchars($ikesa['local']['address']) . '<br/>Port:' . htmlspecialchars($ikesa['local']['port']);
															} else {
																echo 'Unknown';
															}
															if ($ikesa['local']['nat'] != 'false') {
																echo " NAT-T";
															}
														}
													?>
													</td>
													<td class="listr">
													<?php   if (!is_array($ikesa['remote'])) {
															echo "Unknown";
														}
														else {
															$identity = "";
															if (!empty($ikesa['remote']['identification'])) {
																if ($ikesa['remote']['identification'] == '%any') {
																	$identity = 'Any identifier';
																} else {
																	$identity = htmlspecialchars($ikesa['remote']['identification']);
																}
															}

															if (is_array($ikesa['remote']['auth']) && !empty($ikesa['remote']['auth'][0]['identity'])) {
																echo htmlspecialchars($ikesa['remote']['auth'][0]['identity']);
																echo "<br/>{$identity}";
															} else {
																if (empty($identity)) {
																	echo "Unknown";
																} else {
																	echo $identity;
																}
															}
														}
													?>
													</td>
													<td class="listr">
													<?php   if (!is_array($ikesa['remote'])) {
															echo "Unknown";
														} else {
															if (!empty($ikesa['remote']['address'])) {
																echo htmlspecialchars($ikesa['remote']['address']) . '<br/>Port:' . htmlspecialchars($ikesa['remote']['port']);
															} else {
																echo 'Unknown';
															}
															if ($ikesa['remote']['nat'] != 'false') {
																echo " NAT-T";
															}
														}
													?>
													</td>
													<td class="listr">
														<?php echo htmlspecialchars($ikesa['role']);?>

													</td>
													<td class="listr">
															<span class="<?php echo $icon; ?>" title="<?php echo $ikesa['status']; ?>" alt=""></span>
															<small><?php echo htmlspecialchars($ikesa['status']);?></small>
													</td>
													<td >
													<?php if ($icon != "glyphicon glyphicon-play text-success"): ?>
														<a href="diag_ipsec.php?act=connect&amp;ikeid=<?php echo $con_id; ?>">
														<span class="glyphicon glyphicon-play text-default" alt="Connect VPN" title="Connect VPN"></span>
														</a>
													<?php else: ?>
														<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?php echo $con_id; ?>">
														<span class="glyphicon glyphicon-stop text-default" alt="Disconnect VPN" title="Disconnect VPN"></span>
														</a>
														<a href="diag_ipsec.php?act=ikedisconnect&amp;ikeid=<?php echo $con_id; ?>&amp;ikesaid=<?php echo $ikesa['id']; ?>">
														<span class="glyphicon glyphicon-remove text-default" alt="Disconnect VPN Connection" title="Disconnect VPN Connection" border="0"></span>
														</a>
													<?php endif; ?>
													</td>
												</tr>
												<?php if (is_array($ikesa['childsalist'])): ?>
												<tr>
													<td class="listrborder" colspan="10">
													<div id="btnchildsa-<?=$ikeid;?>">
														<input  type="button" onclick="show_childsa('childsa-<?=$ikeid;?>','btnchildsa-<?=$ikeid;?>');" value="+" /> - Show child SA entries
													</div>
													<table class="table table-sort" id="childsa-<?=$ikeid;?>" style="display:none">
													<thead>
														<tr>
															<th> </th>
															<th class="listhdrr nowrap"><?php echo gettext("Local subnets");?></th>
															<th class="listhdrr nowrap"><?php echo gettext("Local SPI(s)");?></th>
															<th class="listhdrr nowrap"><?php echo gettext("Remote subnets");?></th>
														</tr>
													</thead>
													<tbody>
													<?php
														if (is_array($ikesa['childsalist']['childsa'])) {
															foreach ($ikesa['childsalist']['childsa'] as $childsa) {
													?>
														<tr valign="top">
															<td>
																<a href="diag_ipsec.php?act=childdisconnect&amp;ikeid=<?php echo $con_id; ?>&amp;ikesaid=<?php echo $childsa['reqid']; ?>">
																<span class="glyphicon glyphicon-remove text-default" alt="Disconnect Child SA" title="Disconnect Child SA"></span>
																</a>
															</td>
															<td class="listlr nowrap">
													<?php	if (is_array($childsa['local']) && is_array($childsa['local']['networks']) && is_array($childsa['local']['networks']['network'])) {
															foreach ($childsa['local']['networks']['network'] as $lnets) {
																echo htmlspecialchars(ipsec_fixup_network($lnets)) . "<br />";
															}
														} else
															echo "Unknown";
													?>
															</td>
															<td class="listr nowrap">
													<?php	if (is_array($childsa['local']))
															echo "Local: " . htmlspecialchars($childsa['local']['spi']);
													?>
													<?php	if (is_array($childsa['remote']))
															echo "<br/>Remote: " . htmlspecialchars($childsa['remote']['spi']);
													?>
															</td>
															<td class="listr nowrap">
													<?php	if (is_array($childsa['remote']) && is_array($childsa['remote']['networks']) && is_array($childsa['remote']['networks']['network'])) {
															foreach ($childsa['remote']['networks']['network'] as $rnets) {
																echo htmlspecialchars(ipsec_fixup_network($rnets)) . "<br />";
															}
														} else
															echo "Unknown";
													?>
															</td>
														</tr>
													<?php } } ?>
														<tr style="display:none;"><td></td></tr>
													</tbody>
													</table>
													</td>
												</tr>
												<?php endif;

												unset($con_id);
											}
										}

										$rgmap = array();
										foreach ($a_phase1 as $ph1ent):
											$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];
											if ($ipsecconnected[$ph1ent['ikeid']])
												continue;
									?>
											<tr>
												<td class="listlr">
													<?php echo htmlspecialchars($ph1ent['descr']);?>
												</td>
												<td class="listr">
											<?php
												list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local");
												if (empty($myid_data))
													echo "Unknown";
												else
													echo htmlspecialchars($myid_data);
											?>
												</td>
												<td class="listr">
											<?php
												$ph1src = ipsec_get_phase1_src($ph1ent);
												if (empty($ph1src))
													echo "Unknown";
												else
													echo htmlspecialchars($ph1src);
											?>
												</td>
												<td class="listr">
											<?php
												list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);
												if (empty($peerid_data))
													echo "Unknown";
												else
													echo htmlspecialchars($peerid_data);
											?>
												</td>
												<td class="listr">
											<?php
												$ph1src = ipsec_get_phase1_dst($ph1ent);
												if (empty($ph1src))
													echo "Unknown";
												else
													echo htmlspecialchars($ph1src);
											?>
												</td>
												<td class="listr" >
												</td>
												<td class="listr">
													<span class="glyphicon glyphicon-remove text-warning" title="Disconnected" alt=""></span>
													<small>Disconnected</small>
												</td>
												<td >
													<a href="diag_ipsec.php?act=connect&amp;ikeid=<?php echo $ph1ent['ikeid']; ?>">
													<span class="glyphicon glyphicon-play text-default" alt="Connect VPN" title="Connect VPN"></span>
													</a>
												</td>
											</tr>
									<?php
										endforeach;
										unset($ipsecconnected, $phase1, $rgmap);
									?>
												<tr style="display:none;"><td></td></tr>
											</tbody>
											</table>

											<div class="container-fluid">
											<p class="vexpl">
	<span class="text-danger">
		<strong><?php echo gettext("Note:");?><br /></strong>
	</span>
	<?php echo gettext("You can configure IPsec");?>
	<a href="vpn_ipsec.php">here</a>.
</p>
											</div>

				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>




<script type="text/javascript">
//<![CDATA[
function show_childsa(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	aodiv = document.getElementById(id);
	aodiv.style.display = "";
}
//]]>
</script>
<?php unset($status); include("foot.inc"); ?>
