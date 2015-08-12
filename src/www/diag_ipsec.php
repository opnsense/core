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

require_once("guiconfig.inc");
require_once("vpn.inc");
require_once("services.inc");
require_once("interfaces.inc");


function ipsec_fixup_network($network) {
	if (substr($network, -3) == '|/0')
		$result = substr($network, 0, -3);
	else {
		$tmp = explode('|', $network);
		if (isset($tmp[1]))
			$result = $tmp[1];
		else
			$result = $tmp[0];
		unset($tmp);
	}

	return $result;
}

if (!is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}

if (!is_array($config['ipsec']['phase1'])) {
    $config['ipsec']['phase1'] = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// check if post can be valid
		if (!empty($_POST['ikeid']) && ctype_digit($_POST['ikeid']) && !empty($_POST['action'])) {
			$act = $_POST['action'];
			$ikeid = $_POST['ikeid'];
			// check if a valid ikesaid is provided
			if (!empty($_POST['ikesaid']) && ctype_digit($_POST['ikesaid'])) {
				$ikesaid = $_POST['ikesaid'];
			} else {
				$ikesaid = null;
			}
			// todo: move to configctl calls
			switch ($act) {
				case 'connect':
					mwexec("/usr/local/sbin/ipsec down con" . $ikeid);
					mwexec("/usr/local/sbin/ipsec up con" . $ikeid);
					break;
				case 'ikedisconnect':
					mwexec("/usr/local/sbin/ipsec down con" . $ikeid);
					break;
				case 'ikedisconnectconn':
					if ($ikesaid !== null) {
						mwexec("/usr/local/sbin/ipsec down con" . $ikeid . "[" . $ikesaid . "]");
					} else {

					}
				case 'childdisconnect':
					mwexec("/usr/local/sbin/ipsec down con" . $ikeid . "{" . $ikesaid . "}");
					break;

			}
		}
}

$status = ipsec_smp_dump_status();
$pconfig = $config['ipsec']['phase1'];
legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($status);
$pgtitle = array(gettext("Status"),gettext("IPsec"));
$shortcut_section = "ipsec";

include("head.inc");
?>
<script type="text/javascript">
//<![CDATA[
	function show_childsa(id, buttonid) {
		document.getElementById(buttonid).innerHTML='';
		aodiv = document.getElementById(id);
		aodiv.style.display = "";
	}
//]]>
</script>
<body>

<?php include("fbegin.inc"); ?>
	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">
				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
			    <section class="col-xs-12">
<? 				$active_tab = "/diag_ipsec.php";
					include('diag_ipsec_tabs.inc');
?>
						<div class="tab-content content-box col-xs-12">
							<div class="table-responsive">
								<table class="table table-striped">
									<thead>
									<tr>
										<th><?= gettext("Description");?></th>
										<th><?= gettext("Local ID");?></th>
										<th><?= gettext("Local IP");?></th>
										<th><?= gettext("Remote ID");?></th>
										<th><?= gettext("Remote IP");?></th>
										<th><?= gettext("Role");?></th>
										<th><?= gettext("Status");?></th>
										<th></th>
									</tr>
									</thead>
									<tbody>
									<?php
										$ipsecconnected = array();
										if (isset($status['query']['ikesalist']['ikesa'])):
											foreach ($status['query']['ikesalist']['ikesa'] as $ikeid => $ikesa):
												// first do formatting
												$con_id = substr($ikesa['peerconfig'], 3);
												$ipsecconnected[$con_id] = $con_id;
												$ipsec_get_descr = '';
												foreach ($pconfig as $p1) {
														if ($p1['ikeid'] == $con_id) {
																$ipsec_get_descr = $p1['descr'];
																break;
														}
												}
												$ipsec_local_identification = 'Unknown';
												if (!empty($ikesa['local']['identification'])) {
													if ($ikesa['local']['identification'] = "%any" ){
															$ipsec_local_identification = 'Any identifier';
													} else {
															$ipsec_local_identification = $ikesa['local']['identification'];
													}
												}
												$ipsec_local_address = 'Unknown';
												if (!empty($ikesa['local']['address'])) {
														$ipsec_local_address = $ikesa['local']['address'] . '<br/>Port:' . $ikesa['local']['port'];
												}
												if (isset($ikesa['local']['nat']) && $ikesa['local']['nat'] != 'false') {
														$ipsec_local_address .= ' NAT-T';
												}

												$ipsec_remote_identification = 'Unknown';
												if (!empty($ikesa['remote']['identification'])) {
														if ($ikesa['remote']['identification'] == '%any') {
																$ipsec_remote_identification = 'Any identifier';
														} else {
																$ipsec_remote_identification = $ikesa['remote']['identification'];
														}
												}
												if (!empty($ikesa['remote']['auth'][0]['identity'])) {
													$ipsec_remote_identification = $ikesa['remote']['auth'][0]['identity'] . '<br/>'. $ipsec_remote_identification;
												}

												$ipsec_remote_address = 'Unknown';
												if (!empty($ikesa['remote']['address'])) {
														$ipsec_remote_address = $ikesa['remote']['address'] . '<br/>Port:';
												}
												if (isset($ikesa['remote']['nat']) && $ikesa['remote']['nat'] != 'false') {
														$ipsec_remote_address .= ' NAT-T';
												}

												$connected = false;
												if (ipsec_phase1_status($status['query']['ikesalist']['ikesa'], $ikesa['id'])) {
													$icon = "glyphicon glyphicon-play text-success";
													$connected = true;
												} elseif(!isset($config['ipsec']['enable'])) {
													$icon = "glyphicon glyphicon-remove text-danger";
												} else {
													$icon = "glyphicon glyphicon-remove text-warning";
												}
									?>
												<tr>
													<td><?= $ipsec_get_descr;?></td>
													<td><?= $ipsec_local_identification ?></td>
													<td><?= $ipsec_local_address ?> </td>
													<td><?= $ipsec_remote_identification ?> </td>
													<td><?= $ipsec_remote_address ?> </td>
													<td><?= $ikesa['role'];?></td>
													<td>
															<span class="<?= $icon; ?>" title="<?= $ikesa['status']; ?>" alt=""></span>
															<small><?= $ikesa['status'];?></small>
													</td>
													<td>
														<form method="post">
															<input type="hidden" value="<?=$con_id?>" name="ikeid"/>
															<input type="hidden" value="<?=isset($ikesa['id']) ? $ikesa['id'] :""?>" name="ikesaid" />
<?php 												if (!$connected): ?>
															<button type="submit" class="btn btn-xs" name="action" value="connect"  title="<?=gettext("Connect VPN");?>">
																<span class="glyphicon glyphicon-play"/>
															</button>
<?php 												else: ?>
															<button type="submit" class="btn btn-xs" name="action" value="ikedisconnect" title="<?=gettext("Disconnect VPN");?>">
																<span class="glyphicon glyphicon-stop"/>
															</button>
															<button type="submit" class="btn btn-xs" name="action" value="ikedisconnectconn"  title="<?=gettext("Disconnect VPN Connection");?>">
																<span class="glyphicon glyphicon-remove"/>
															</button>
<?php 												endif; ?>
														</form>
													</td>
												</tr>
<?php 									if (isset($ikesa['childsalist']) && is_array($ikesa['childsalist']) ): ?>
												<tr>
													<td colspan="8">
														<div id="btnchildsa-<?=$ikeid;?>">
															<button class="btn btn-xs" type="button" onclick="show_childsa('childsa-<?=$ikeid;?>','btnchildsa-<?=$ikeid;?>');" >
																<i class="fa fa-plus"></i> - <?=gettext("Show child SA entries");?>
															</button>
														</div>
														<table class="table table-condensed" id="childsa-<?=$ikeid;?>" style="display:none">
															<thead>
																<tr>
																	<th> </th>
																	<th><?php echo gettext("Local subnets");?></th>
																	<th><?php echo gettext("Local SPI(s)");?></th>
																	<th><?php echo gettext("Remote subnets");?></th>
																</tr>
															</thead>
															<tbody>
<?php
														if (is_array($ikesa['childsalist']['childsa'])) {
															foreach ($ikesa['childsalist']['childsa'] as $childsa) {
?>
																<tr>
																	<td>
																		<form method="post">
																			<input type="hidden" value="<?=$con_id?>" name="ikeid"/>
																			<input type="hidden" value="<?=$childsa['reqid'];?>" name="ikesaid"/>
																			<button type="submit" class="btn btn-xs" name="action" value="childdisconnect">
																				<span class="glyphicon glyphicon-remove text-default"/>
																			</button>
																		</form>
																	</td>
																	<td>
<?php																if (isset($childsa['local']['networks']['network'])):
																			foreach ($childsa['local']['networks']['network'] as $lnets):
?>
																			<?=htmlspecialchars(ipsec_fixup_network($lnets));?> <br/>
<?php 																endforeach;
 																		else:
?>
																			Unknown <br/>
<?php																endif;
?>
																	</td>
																	<td>
<?php																if (isset($childsa['local']['spi'])):
?>
																			Local : <?=htmlspecialchars($childsa['local']['spi']);?>
<?php 															endif;
?>
<?php																if (isset($childsa['remote']['spi'])):
?>
																			<br/>Remote : <?=htmlspecialchars($childsa['remote']['spi']);?>
<?php 															endif;
?>
																	</td>
																	<td>
<?php																if (isset($childsa['remote']['networks']['network'])):
																			foreach ($childsa['remote']['networks']['network'] as $rnets):
?>
																			<?=htmlspecialchars(ipsec_fixup_network($rnets));?> <br/>
<?php 																endforeach;
																		else:
?>
																			Unknown <br/>
<?php																endif;
?>
																</td>
														</tr>
													<?php } } ?>
														<tr style="display:none;"><td></td></tr>
													</tbody>
													</table>
													</td>
												</tr>
<?php 								endif;
											unset($con_id);
												// close outer loop {foreach ($status['query']['ikesalist']['ikesa'] as $ikeid => $ikesa)}
												endforeach;
											endif;

											$rgmap = array();
											foreach ($pconfig as $ph1ent):
												if (isset($ph1ent['remote-gateway'])) {
														$rgmap[$ph1ent['remote-gateway']] = $ph1ent['remote-gateway'];
												}
												if (isset($ipsecconnected[$ph1ent['ikeid']])) {
														continue;
												}
												$ph1src = ipsec_get_phase1_src($ph1ent);
												$ph1dst = ipsec_get_phase1_dst($ph1ent);
												list ($myid_type, $myid_data) = ipsec_find_id($ph1ent, "local");
												list ($peerid_type, $peerid_data) = ipsec_find_id($ph1ent, "peer", $rgmap);

?>
												<tr>
													<td><?php echo htmlspecialchars($ph1ent['descr']);?></td>
													<td><?=!empty($myid_data) ? htmlspecialchars($myid_data) : "Unknown"?></td>
													<td><?=!empty($ph1src) ? htmlspecialchars($ph1src) : "Unknown"?></td>
													<td><?=!empty($peerid_data) ? htmlspecialchars($peerid_data) : "Unknown"?></td>
													<td><?=!empty($ph1dst) ? htmlspecialchars($ph1dst) : "Unknown"?></td>
													<td></td>
													<td>
														<span class="glyphicon glyphicon-remove text-warning" title="Disconnected" alt=""></span>
														<small>Disconnected</small>
													</td>
													<td >
														<form method="post">
															<input type="hidden" value="<?=$ph1ent['ikeid']?>" name="ikeid"/>
															<button type="submit" class="btn btn-xs" name="action" value="connect">
																<span class="glyphicon glyphicon-play"/>
															</button>
														</form>
													</td>
											</tr>
<?php
										endforeach;
?>
											<tr>
												<td colspan="8">
													<span class="text-danger">
														<strong><?php echo gettext("Note:");?><br /></strong>
													</span>
													<?php echo gettext("You can configure IPsec");?>
													<a href="vpn_ipsec.php">here</a>.</p>
												</td>
											</tr>
										</tbody>
									</table>
				    		</div>
							</div>
			    	</section>
					</div>
				</div>
			</section>




<?php
include("foot.inc"); ?>
