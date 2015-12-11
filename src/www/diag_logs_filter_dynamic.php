<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004-2009 Scott Ullrich
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

require_once("guiconfig.inc");
require_once("filter_log.inc");
require_once("system.inc");
require_once("interfaces.inc");

$filter_logfile = '/var/log/filter.log';

/* Hardcode this. AJAX doesn't do so well with large numbers */
$nentries = 50;

/* AJAX related routines */
handle_ajax($nentries, $nentries + 20);

if (isset($_POST['clear'])) {
	clear_clog($filter_logfile);
}

$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100);

$pgtitle = array(gettext('Firewall'), gettext('Log Files'), gettext('Dynamic View'));
$shortcut_section = "firewall";
include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
	lastsawtime = '<?php echo time(); ?>;';
	var lines = Array();
	var timer;
	var updateDelay = 25500;
	var isBusy = false;
	var isPaused = false;
	var nentries = <?php echo $nentries; ?>;
<?php
	if(isset($config['syslog']['reverse']))
		echo "var isReverse = true;\n";
	else
		echo "var isReverse = false;\n";
?>
	/* Called by the AJAX updater */
	function format_log_line(row) {
		var i = 0;
		var line = '<td class="listMRlr nowrap" align="center">' + row[i++] + '<\/td>';
		while (i < 6) {
			line += '<td class="listMRr nowrap">' + row[i++] + '<\/td>';
		}
		return line;
	}
//]]>
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>


	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php print_service_disabled_banner('firewall'); ?>
				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

					<div class="tab-content content-box col-xs-12">

							<div class="table-responsive">
								<table class="table table-striped table-sort">
									<thead>
									<tr>
										<td colspan="6" class="listtopic">
										<strong><?php printf(gettext("Showing last %s records."),$nentries);?></strong>
										</td>
									</tr>
									<tr>
										<td colspan="6" class="listtopic">
										<input type="checkbox" onclick="javascript:toggle_pause();" />&nbsp;<?=gettext("Pause");?>
										</td>
									</tr>
									<tr>
										<td class="listhdrr" width="60"><?=gettext("Act");?></td>
										<td class="listhdrr"><?=gettext("Time");?></td>
										<td class="listhdrr"><?=gettext("If");?></td>
										<td class="listhdrr"><?=gettext("Source");?></td>
										<td class="listhdrr"><?=gettext("Destination");?></td>
										<td class="listhdrr"><?=gettext("Proto");?></td>
									</tr>
									</thead>
									<tbody id="filter-log-entries">
									<?php
									$rowIndex = 0;
									foreach ($filterlog as $filterent):
									$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
									$rowIndex++;?>
									<tr class="<?=$evenRowClass?>">
										<td class="listMRlr nowrap" align="center">
										<a href="#" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);"  title="<?php echo $filterent['act'];?>"><span class="glyphicon glyphicon-<?php switch ($filterent['act']) {
									    case 'pass':
                                                                                echo "play";  /* icon triangle */
                                                                                break;
                                                                            case 'match':
                                                                                echo "random";
                                                                                break;
                                                                            case 'reject':
									    case 'block':
									    default:
                                                                            echo 'remove'; /* a x*/
                                                                            break;
									  }
									  ?>"></span>
										</a>
										</td>
										<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['time']);?></td>
										<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['interface']);?></td>
										<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['src']);?></td>
										<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['dst']);?></td>
										<?php
											if ($filterent['proto'] == "TCP")
												$filterent['proto'] .= ":{$filterent['tcpflags']}";
										?>
										<td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['proto']);?></td>
									</tr>
									<?php endforeach; ?>
									<tr style="display:none;"><td></td></tr>
									</tbody>
								</table>
				    </div>
			</section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
