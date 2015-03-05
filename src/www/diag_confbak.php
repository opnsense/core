<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2005 Colin Smith
    Copyright (C) 2010 Jim Pingle
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

if (isset($_POST['backupcount'])) {
	if (is_numeric($_POST['backupcount']) && ($_POST['backupcount'] >= 0)) {
		$config['system']['backupcount'] = $_POST['backupcount'];
		$changedescr = $config['system']['backupcount'];
	} else {
		unset($config['system']['backupcount']);
		$changedescr = "(platform default)";
	}
	write_config("Changed backup revision count to {$changedescr}");
} elseif ($_POST) {
	if (!isset($_POST['confirm']) || ($_POST['confirm'] != gettext("Confirm")) || (!isset($_POST['newver']) && !isset($_POST['rmver']))) {
		header("Location: diag_confbak.php");
		return;
	}

	conf_mount_rw();
	$confvers = unserialize(file_get_contents('/conf/backup/backup.cache'));
	if($_POST['newver'] != "") {
		if(config_restore('/conf/backup/config-' . $_POST['newver'] . '.xml') == 0)
		$savemsg = sprintf(gettext('Successfully reverted to timestamp %1$s with description "%2$s".'), date(gettext("n/j/y H:i:s"), $_POST['newver']), $confvers[$_POST['newver']]['description']);
		else
			$savemsg = gettext("Unable to revert to the selected configuration.");
	}
	if($_POST['rmver'] != "") {
		unlink_if_exists('/conf/backup/config-' . $_POST['rmver'] . '.xml');
		$savemsg = sprintf(gettext('Deleted backup with timestamp %1$s and description "%2$s".'), date(gettext("n/j/y H:i:s"), $_POST['rmver']),$confvers[$_POST['rmver']]['description']);
	}
	conf_mount_ro();
}

if($_GET['getcfg'] != "") {
	$file = '/conf/backup/config-' . $_GET['getcfg'] . '.xml';

	$exp_name = urlencode("config-{$config['system']['hostname']}.{$config['system']['domain']}-{$_GET['getcfg']}.xml");
	$exp_data = file_get_contents($file);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

if (($_GET['diff'] == 'Diff') && isset($_GET['oldtime']) && isset($_GET['newtime'])
      && is_numeric($_GET['oldtime']) && (is_numeric($_GET['newtime']) || ($_GET['newtime'] == 'current'))) {
	$diff = "";
	$oldfile = '/conf/backup/config-' . $_GET['oldtime'] . '.xml';
	$oldtime = $_GET['oldtime'];
	if ($_GET['newtime'] == 'current') {
		$newfile = '/conf/config.xml';
		$newtime = $config['revision']['time'];
	} else {
		$newfile = '/conf/backup/config-' . $_GET['newtime'] . '.xml';
		$newtime = $_GET['newtime'];
	}
	if (file_exists($oldfile) && file_exists($newfile)) {
		exec("/usr/bin/diff -u " . escapeshellarg($oldfile) . " " . escapeshellarg($newfile), $diff);
	}
}

cleanup_backupcache(false);
$confvers = get_backups();
unset($confvers['versions']);

$pgtitle = array(gettext("Diagnostics"),gettext("Configuration History"));
include("head.inc");

?>

<body>
	<?php
		include("fbegin.inc");
		if($savemsg)
			print_info_box($savemsg);
	?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

			    <section class="col-xs-12">

					<?php
							$tab_array = array();
							$tab_array[0] = array(gettext("Config History"), true, "diag_confbak.php");
							$tab_array[1] = array(gettext("Backup/Restore"), false, "diag_backup.php");
							display_top_tabs($tab_array);
					?>


						<div class="tab-content content-box col-xs-12">

					    <div class="container-fluid tab-content">

							<div class="tab-pane active" id="system">


									<?php if ($diff): ?>
									<section class="__mb">
										<div classs="content-box">
											<header class="content-box-head container-fluid">
									        <h3><?=gettext("Configuration diff from");?> <?php echo date(gettext("n/j/y H:i:s"), $oldtime); ?> <?=gettext("to");?> <?php echo date(gettext("n/j/y H:i:s"), $newtime); ?></h3>
									    </header>

									     <div class="content-box-main">

									    <div class="table-responsive">

													<table summary="diag confbak">
														<tr><td></td></tr>
														<?php foreach ($diff as $line) {
															switch (substr($line, 0, 1)) {
																case "+":
																	$color = "#caffd3";
																	break;
																case "-":
																	$color = "#ffe8e8";
																	break;
																case "@":
																	$color = "#a0a0a0";
																	break;
																default:
																	$color = "#ffffff";
															}
															?>
														<tr>
															<td valign="middle" bgcolor="<?php echo $color; ?>" style="white-space: pre-wrap;"><?php echo htmlentities($line);?></td>
														</tr>
														<?php } ?>
													</table>
									    </div>
									     </div>
										</div>
									</section>
									<?php endif; ?>


									<?PHP if ($_GET["newver"] || $_GET["rmver"]): ?>
									<form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
									<section>
				                        <div class="content-box">

				                            <header class="content-box-head container-fluid">
									        <h3><?PHP echo gettext("Confirm Action"); ?></h3>
									    </header>

									    <div class="content-box-main col-xs-12">

										    <strong><?PHP echo gettext("Please confirm the selected action"); ?></strong>:
												<br />
												<br /><strong><?PHP echo gettext("Action"); ?>:</strong>
											<?PHP	if (!empty($_GET["newver"])) {
												echo gettext("Restore from Configuration Backup");
												$target_config = $_GET["newver"]; ?>
												<input type="hidden" name="newver" value="<?PHP echo htmlspecialchars($_GET["newver"]); ?>" />
											<?PHP	} elseif (!empty($_GET["rmver"])) {
												echo gettext("Remove Configuration Backup");
												$target_config = $_GET["rmver"]; ?>
												<input type="hidden" name="rmver" value="<?PHP echo htmlspecialchars($_GET["rmver"]); ?>" />
											<?PHP	} ?>
												<br /><strong><?PHP echo gettext("Target Configuration"); ?>:</strong>
												<?PHP echo sprintf(gettext('Timestamp %1$s'), date(gettext("n/j/y H:i:s"), $target_config)); ?>
												<br /><input type="submit" name="confirm" class="btn btn-primary" value="<?PHP echo gettext("Confirm"); ?>" />

									    </div>
				                        </div>
									</section>
									</form>

									<? else: ?>

									<form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
									<section style="margin-bottom:15px;">
				                        <div class="content-box">

				                            <header class="content-box-head container-fluid">
									        <h3>Config history</h3>
									    </header>

									    <div class="content-box-main">

									    <div class="table-responsive">
									        <table class="table table-striped">
										        <tbody>
										        <tr>
										          <td><?=gettext("Backup Count");?></td>
										          <td><input name="backupcount" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($config['system']['backupcount']);?>"/></td>
										          <td><?= gettext("Enter the number of older configurations to keep in the local backup cache. By default this is 30 for a full install or 5 on NanoBSD."); ?></td>
										          <td><input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" /></td>
										        </tr>
										        </tbody>
										    </table>

										    <div class="container-fluid">
										    <?= gettext("NOTE: Be aware of how much space is consumed by backups before adjusting this value. Current space used by backups: "); ?> <?= exec("/usr/bin/du -sh /conf/backup | /usr/bin/awk '{print $1;}'") ?>
										    </div>
									    </div>
									    </div>
				                        </div>
									</section>
									</form>


									<?php if (is_array($confvers)): ?>
									<form action="<?=$_SERVER['REQUEST_URI'];?>" method="get">
									<section>
										<div class="content-box">
											<div class="content-box-main">

												<div class="container-fluid __mb">
												<?= gettext("To view the differences between an older configuration and a newer configuration, select the older configuration using the left column of radio options and select the newer configuration in the right column, then press the Diff button."); ?>
												</div>


                                            <table class="table table-striped table-sort" summary="difference">

											<thead>
											<tr>
												<td colspan="2" valign="middle" class="list nowrap"><?=gettext("Diff");?></td>
												<td class="listhdrr"><?=gettext("Date");?></td>
												<td class="listhdrr"><?=gettext("Version");?></td>
												<td class="listhdrr"><?=gettext("Size");?></td>
												<td class="listhdrr"><?=gettext("Configuration Change");?></td>
												<td class="list">&nbsp;</td>
											</tr>
											</thead>
											<tbody>
											<tr valign="top">
												<td valign="middle" class="list nowrap"></td>
												<td class="list">
													<input type="radio" name="newtime" value="current" />
												</td>
												<td class="listlr"> <?= date(gettext("n/j/y H:i:s"), $config['revision']['time']) ?></td>
												<td class="listr"> <?= $config['version'] ?></td>
												<td class="listr"> <?= format_bytes(filesize("/conf/config.xml")) ?></td>
												<td class="listr"> <?= $config['revision']['description'] ?></td>
												<td valign="middle" class="list nowrap"><b><?=gettext("Current");?></b></td>
											</tr>
											<?php
												$c = 0;
												foreach($confvers as $version):
													if($version['time'] != 0)
														$date = date(gettext("n/j/y H:i:s"), $version['time']);
													else
														$date = gettext("Unknown");
											?>
											<tr valign="top">
												<td class="list">
													<input type="radio" name="oldtime" value="<?php echo $version['time'];?>" />
												</td>
												<td class="list">
													<?php if ($c < (count($confvers) - 1)) { ?>
													<input type="radio" name="newtime" value="<?php echo $version['time'];?>" />
													<?php } else { ?>
													&nbsp;
													<?php }
													$c++; ?>
												</td>
												<td class="listlr"> <?= $date ?></td>
												<td class="listr"> <?= $version['version'] ?></td>
												<td class="listr"> <?= format_bytes($version['filesize']) ?></td>
												<td class="listr"> <?= substr($version['description'],0,50) ?></td>

												 <td class="btn-group-table">
		                                            <a href="diag_confbak.php?newver=<?=$version['time'];?>" class="btn btn-default btn-xs" title="<?=gettext("Revert to this configuration");?>"><span class="glyphicon glyphicon-log-in"></span></a>
		                                            <a href="diag_confbak.php?rmver=<?=$version['time'];?>" class="btn btn-default btn-xs" title="<?=gettext("Remove this backup");?>" ><span class="glyphicon glyphicon-remove"></span></a>
		                                            <a href="diag_confbak.php?getcfg=<?=$version['time'];?>" class="btn btn-default btn-xs" title="<?=gettext("Download this backup");?>"><span class="glyphicon glyphicon-download"></span></a>
		                                        </td>

											</tr>
											<?php endforeach; ?>
											</tbody>
										</table>

										<div class="container-fluid">
										<input type="submit" name="diff" class="btn btn-primary" value="<?=gettext("Diff"); ?>" />
										</div>

										</div>
									</section>
									</form>
									<? endif; endif; ?>



					    </div>

					</div>
				</section>



			</div>
		</div>
	</section>


<?php include("foot.inc"); ?>
