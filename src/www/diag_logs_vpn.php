#!/usr/local/bin/php
<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array(gettext("Status"), gettext("System logs"), gettext("VPN"));
require("guiconfig.inc");
require_once("vpn.inc");

$nentries = $config['syslog']['nentries'];
if (!$nentries)
	$nentries = 50;

if (htmlspecialchars($_POST['vpntype']))
	$vpntype = htmlspecialchars($_POST['vpntype']);
elseif (htmlspecialchars($_GET['vpntype']))
	$vpntype = htmlspecialchars($_GET['vpntype']);
else
	$vpntype = "pptp";

if (htmlspecialchars($_POST['mode']))
	$mode = htmlspecialchars($_POST['mode']);
elseif (htmlspecialchars($_GET['mode']))
	$mode = htmlspecialchars($_GET['mode']);
else
	$mode = "login";

switch ($vpntype) {
	case 'pptp':
		$logname = "pptps";
		break;
	case 'poes':
		$logname = "poes";
		break;
	case 'l2tp':
		$logname = "l2tps";
		break;
}

if ($_POST['clear']) {
	if ($mode != "raw")
		clear_log_file("/var/log/vpn.log");
	else
		clear_log_file("/var/log/{$logname}.log");
}

function dump_clog_vpn($logfile, $tail) {
	global $g, $config, $vpntype;

	$sor = isset($config['syslog']['reverse']) ? "-r" : "";

	$logarr = "";

	if(isset($config['system']['usefifolog']))
		exec("/usr/sbin/fifolog_reader " . escapeshellarg($logfile) . " | tail {$sor} -n " . $tail, $logarr);
	else
		exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | tail {$sor} -n " . $tail, $logarr);

	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		$llent = explode(",", $logent[5]);
		$iftype = substr($llent[1], 0, 4);
		if ($iftype != $vpntype)
			continue;
		echo "<tr>\n";
		echo "<td class=\"listlr nowrap\">" . htmlspecialchars(join(" ", array_slice($logent, 0, 3))) . "</td>\n";

		if ($llent[0] == "login")
			echo "<td class=\"listr\"><img src=\"/themes/{$g['theme']}/images/icons/icon_in.gif\" width=\"11\" height=\"11\" title=\"login\" alt=\"in\" /></td>\n";
		else
			echo "<td class=\"listr\"><img src=\"/themes/{$g['theme']}/images/icons/icon_out.gif\" width=\"11\" height=\"11\" title=\"logout\" alt=\"out\" /></td>\n";

		echo "<td class=\"listr\">" . htmlspecialchars($llent[3]) . "</td>\n";
		echo "<td class=\"listr\">" . htmlspecialchars($llent[2]) . "&nbsp;</td>\n";
		echo "</tr>\n";
	}
}

include("head.inc");

?>


<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if ($input_errors) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<? $active_tab = "/diag_logs_vpn.php"; include('diag_logs_tabs.php'); ?>

					<div class="tab-content content-box col-xs-12">
				    <div class="container-fluid">


							<? $tab_group = 'vpn'; include('diag_logs_pills.php'); ?>


							 <div class="table-responsive">
								<table class="table table-striped table-sort">
									 <?php if ($mode != "raw"): ?>
										<tr>
											<td class="listhdrr"><?=gettext("Time");?></td>
											<td class="listhdrr"><?=gettext("Action");?></td>
											<td class="listhdrr"><?=gettext("User");?></td>
											<td class="listhdrr"><?=gettext("IP address");?></td>
										</tr>
											<?php dump_clog_vpn("/var/log/vpn.log", $nentries); ?>
										<?php else:
											dump_clog("/var/log/{$logname}.log", $nentries);
									  endif; ?>
								</table>
							 </div>

							<form action="diag_logs_vpn.php" method="post">
								<input type="hidden" name="vpntype" id="vpntype" value="<?=$vpntype;?>" />
								<input type="hidden" name="mode" id="mode" value="<?=$mode;?>" />
								<input name="clear" type="submit" class="btn" value="<?= gettext("Clear log");?>" />
							</form>


						</div>
				    </div>
			</section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
