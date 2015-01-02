<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2005-2009 Scott Ullrich
	Copyright (C) 2005 Colin Smith
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

/* handle AJAX operations */
if(isset($_POST['action']) && $_POST['action'] == "remove") {
	if (isset($_POST['srcip']) && isset($_POST['dstip']) && is_ipaddr($_POST['srcip']) && is_ipaddr($_POST['dstip'])) {
		$retval = mwexec("/sbin/pfctl -k " . escapeshellarg($_POST['srcip']) . " -k " . escapeshellarg($_POST['dstip']));
		echo htmlentities("|{$_POST['srcip']}|{$_POST['dstip']}|{$retval}|");
	} else {
		echo gettext("invalid input");
	}
	return;
}

if (isset($_POST['filter']) && isset($_POST['killfilter'])) {
	if (is_ipaddr($_POST['filter'])) {
		$tokill = escapeshellarg($_POST['filter'] . "/32");
	} elseif (is_subnet($_POST['filter'])) {
		$tokill = escapeshellarg($_POST['filter']);
	} else {
		// Invalid filter
		$tokill = "";
	}
	if (!empty($tokill)) {
		$retval = mwexec("/sbin/pfctl -k {$tokill} -k 0/0");
		$retval = mwexec("/sbin/pfctl -k 0.0.0.0/0 -k {$tokill}");
	}
}

$pgtitle = array(gettext("Diagnostics"),gettext("Show States"));
include("head.inc");

?>

<body onload="<?=$jsevents["body"]["onload"];?>">
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
	function removeState(srcip, dstip) {
		var busy = function(index,icon) {
			jQuery(icon).bind("onclick","");
			jQuery(icon).attr('src',jQuery(icon).attr('src').replace("\.gif", "_d.gif"));
			jQuery(icon).css("cursor","wait");
		}

		jQuery('span[name="i:' + srcip + ":" + dstip + '"]').each(busy);

		jQuery.ajax(
			"<?=$_SERVER['SCRIPT_NAME'];?>",
			{
				type: "post",
				data: {
					action: "remove",
					srcip: srcip,
					dstip: dstip
				},
				complete: removeComplete
			}
		);
	}

	function removeComplete(req) {
		var values = req.responseText.split("|");
		if(values[3] != "0") {
			alert('<?=gettext("An error occurred.");?>');
			return;
		}

		jQuery('tr[id="r:' + values[1] + ":" + values[2] + '"]').each(
			function(index,row) { jQuery(row).fadeOut(1000); }
		);
	}
//]]>
</script>




<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

		      <section class="col-xs-12">

					<?php
							$tab_array = array();
							$tab_array[0] = array(gettext("States"), true, "diag_dump_states.php");
							$tab_array[1] = array(gettext("Reset states"), false, "diag_resetstate.php");
							display_top_tabs($tab_array);
					?>


						<div class="tab-content content-box col-xs-12">

						<form action="<?=$_SERVER['SCRIPT_NAME'];?>" method="post" name="iform">
							                <?php
												$current_statecount=`pfctl -si | grep "current entries" | awk '{ print $3 }'`;
											?>

							               <table class="table table-striped">
									        <tbody>
										        <tr>
											      <td><?=gettext("Current total state count");?>: <?= $current_statecount ?></td>
										          <td><?=gettext("Filter expression:");?></td>
										          <td><input type="text" name="filter" class="form-control search" value="<?=htmlspecialchars($_POST['filter']);?>" size="30" /></td>
										          <td>	<input type="submit" class="btn btn-primary" value="<?=gettext("Filter");?>" />
															<?php if (isset($_POST['filter']) && (is_ipaddr($_POST['filter']) || is_subnet($_POST['filter']))): ?>
																<input type="submit" class="btn" name="killfilter" value="<?=gettext("Kill");?>" />
															<?php endif; ?>
										          </td>
										        </tr>

									        </tbody>
								        </table>


										</form>

					    <div class="container-fluid tab-content">

							<div class="tab-pane active" id="system">

										<div class="content-box">

						                    <div class="table-responsive">

						                        <table class="table table-striped table-sort sortable __nomb">
						                            <tr class="content-box-head">
						                                <th>
						                                    <table>
						                                        <tr>
						                                            <td><?=gettext("Int");?></td>
						                                            <td>
						                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
						                                            </td>
						                                        </tr>
						                                    </table>
						                                </th>
						                                <th>
						                                    <table>
						                                        <tr>
						                                            <td><?=gettext("Proto");?></td>
						                                            <td>
						                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
						                                            </td>
						                                        </tr>
						                                    </table>
						                                </th>
						                                <th>
						                                    <table>
						                                        <tr>
						                                            <td><?=gettext("Source -> Router -> Destination");?></td>
						                                            <td>
						                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
						                                            </td>
						                                        </tr>
						                                    </table>
						                                </th>
						                                <th>
						                                    <table>
						                                        <tr>
						                                            <td><?=gettext("State");?></td>
						                                            <td>
						                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
						                                            </td>
						                                        </tr>
						                                    </table>
						                                </th>
						                                <th></th>
						                            </tr>
													<?php
													$row = 0;
													/* get our states */
													$grepline = (isset($_POST['filter'])) ? "| /usr/bin/egrep " . escapeshellarg(htmlspecialchars($_POST['filter'])) : "";
													$fd = popen("/sbin/pfctl -s state {$grepline}", "r" );
													while ($line = chop(fgets($fd))) {
														if($row >= 10000)
															break;

														$line_split = preg_split("/\s+/", $line);

														$iface  = array_shift($line_split);
														$proto = array_shift($line_split);
														$state = array_pop($line_split);
														$info  = implode(" ", $line_split);

														// We may want to make this optional, with a large state table, this could get to be expensive.
														$iface = convert_real_interface_to_friendly_descr($iface);

														/* break up info and extract $srcip and $dstip */
														$ends = preg_split("/\<?-\>?/", $info);
														$parts = explode(":", $ends[0]);
														$srcip = trim($parts[0]);
														$parts = explode(":", $ends[count($ends) - 1]);
														$dstip = trim($parts[0]);

													?>
														<tr id="r:<?= $srcip ?>:<?= $dstip ?>">
																<td class="listlr"><?= $iface ?></td>
																<td class="listr"><?= $proto ?></td>
																<td class="listr"><?= $info ?></td>
																<td class="listr"><?= $state ?></td>
																<td class="list">
																	<a href="#" onclick="removeState('<?= $srcip ?>', '<?= $dstip ?>');" name="i:<?= $srcip ?>:<?= $dstip ?>" class="btn btn-default" title="<?= gettext('Remove all state entries from') ?> <?= $srcip ?> <?= gettext('to') ?> <?= $dstip ?>"><span class="glyphicon glyphicon-remove"></span></a>


																</td>
														</tr>
													<?php
														$row++;
														ob_flush();
													}

													if ($row == 0): ?>
														<tr>
															<td class="list" colspan="5" align="center" valign="top">
															<?= gettext("No states were found.") ?>
															</td>
														</tr>
													<?php endif;
													pclose($fd);
													?>

												</table>

												<?php if (isset($_POST['filter']) && !empty($_POST['filter'])): ?>
													<div class="col-xs-12"><p><?=gettext("States matching current filter")?>: <?= $row ?></p></div>
												<?php endif; ?>

						                    </div>
										</div>

							</div>
					    </div>
						</div>
		      </section>
		</div>
	</div>
</section>


<?php include('foot.inc');?>
