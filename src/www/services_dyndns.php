<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2008 Ermal Luçi
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
require_once("interfaces.inc");
require_once("services.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");

if (!isset($config['dyndnses']['dyndns'])) {
	$config['dyndnses']['dyndns'] = array();
}

$a_dyndns = &$config['dyndnses']['dyndns'];

if ($_GET['act'] == "del") {
	$conf = $a_dyndns[$_GET['id']];
	@unlink("/conf/dyndns_{$conf['interface']}{$conf['type']}" . escapeshellarg($conf['host']) . "{$conf['id']}.cache");
	unset($a_dyndns[$_GET['id']]);

	write_config();
	services_dyndns_configure();

	header("Location: services_dyndns.php");
	exit;
}

$pgtitle = array(gettext("Services"), gettext("Dynamic DNS clients"));
include("head.inc");

$main_buttons = array(
	array('label'=>'Add', 'href'=>'services_dyndns_edit.php'),
);

?>


<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>


			    <section class="col-xs-12">

					<div class="tab-content content-box col-xs-12">

					  <form action="services_dyndns.php" method="post" name="iform" id="iform">

								<div class="table-responsive">
									<table class="table table-striped">


										<tr>
										  <td width="5%"  class="listhdrr"><?=gettext("Interface");?></td>
										  <td width="15%" class="listhdrr"><?=gettext("Service");?></td>
										  <td width="20%" class="listhdrr"><?=gettext("Hostname");?></td>
										  <td width="20%" class="listhdrr"><?=gettext("Cached IP");?></td>
										  <td width="50%" class="listhdr"><?=gettext("Description");?></td>
										  <td width="10%" class="list"></td>
										</tr>
										<?php $i = 0; foreach ($a_dyndns as $dyndns): ?>
										<tr ondblclick="document.location='services_dyndns_edit.php?id=<?=$i;?>'">
										  <td class="listlr">
										  <?php	$iflist = get_configured_interface_with_descr();
											foreach ($iflist as $if => $ifdesc) {
												if ($dyndns['interface'] == $if) {
													if (!isset($dyndns['enable']))
														echo "<span class=\"gray\">{$ifdesc}</span>";
													else
														echo "{$ifdesc}";
													break;
												}
											}
											$groupslist = return_gateway_groups_array();
											foreach ($groupslist as $if => $group) {
												if ($dyndns['interface'] == $if) {
													if (!isset($dyndns['enable']))
														echo "<span class=\"gray\">{$if}</span>";
													else
														echo "{$if}";
													break;
												}
											}
										  ?>
										  </td>
										  <td class="listr">
										  <?php
											$types = services_dyndns_list();
											if (isset($types[$dyndns['type']])) {
												if (!isset($dyndns['enable'])) {
													echo '<span class="gray">' . htmlspecialchars($types[$dyndns['type']]) . '</span>';
												} else {
													echo htmlspecialchars($types[$dyndns['type']]);
												}
											}
										  ?>
										  </td>
										  <td class="listr">
										  <?php
											if (!isset($dyndns['enable']))
												echo "<span class=\"gray\">" . htmlspecialchars($dyndns['host']) . "</span>";
											else
												echo htmlspecialchars($dyndns['host']);
										  ?>
										  </td>
										  <td class="listr">
										  <?php
											$filename = "/conf/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}.cache";
											$filename_v6 = "/conf/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}_v6.cache";
											if (file_exists($filename)) {
												$ipaddr = dyndnsCheckIP($dyndns['interface']);
												$cached_ip_s = explode(":", file_get_contents($filename));
												$cached_ip = $cached_ip_s[0];
												if ($ipaddr <> $cached_ip)
													echo "<font color='red'>";
												else
													echo "<font color='green'>";
												echo htmlspecialchars($cached_ip);
												echo "</font>";
											} else if (file_exists($filename_v6)) {
												$ipv6addr = get_interface_ipv6($dyndns['interface']);
												$cached_ipv6_s = explode("|", file_get_contents($filename_v6));
												$cached_ipv6 = $cached_ipv6_s[0];
												if ($ipv6addr <> $cached_ipv6)
													echo "<font color='red'>";
												else
													echo "<font color='green'>";
												echo htmlspecialchars($cached_ipv6);
												echo "</font>";
											} else {
												echo "N/A";
											}
										  ?>
										  </td>
										  <td class="listbg">
										  <?php
											if (!isset($dyndns['enable']))
												echo "<span class=\"gray\">".htmlspecialchars($dyndns['descr'])."</span>";
											else
												echo htmlspecialchars($dyndns['descr']);
										  ?>
										  </td>
										  <td valign="middle" class="list nowrap">
											<a href="services_dyndns_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
											&nbsp;<a href="services_dyndns.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
										  </td>
										</tr>
										<?php $i++; endforeach; ?>

										<tr>
										  <td colspan="5" class="list"><p class="vexpl"><span class="text-danger"><strong>
											<?=gettext("Note:");?><br />
											</strong></span>
											<?=gettext("IP addresses appearing in green are up to date with Dynamic DNS provider.");?><br />
											<?=gettext("You can force an update for an IP address on the edit page for that service.");?></p>
										  </td>
										  <td class="list">&nbsp;</td>
										</tr>
									  </table>
									  </div>
									</form>

					</div>
			    </section>
			</div>
		</div>
	</section>


<?php include("foot.inc"); ?>
