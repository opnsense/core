<?php
/*
		Copyright (C) 2014 Deciso B.V.
        Copyright 2007 Scott Dale
        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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

require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');
include_once("includes/functions.inc.php");
require_once("script/load_phalcon.php");

$file_pkg_status="/tmp/pkg_status.json";

if($_POST['action'] == 'pkg_update') {
	/* Setup Shell variables */
	$shell_output = array();
	$shell = new Core\Shell();
	// execute shell command and collect (only valid) info into named array
	$shell->exec("/usr/local/opnsense/scripts/pkg_updatecheck.sh",false,false,$shell_output);
}

if (file_exists($file_pkg_status)) {

		$json = file_get_contents($file_pkg_status);
		$pkg_status = json_decode($json,true);
}

if($_REQUEST['getupdatestatus']) {
	if (file_exists($file_pkg_status)) {
		if ($pkg_status["connection"]=="error") {
			echo "<span class='text-danger'>".gettext("Connection Error")."</span><br/><span class='btn-link' onclick='checkupdate()'>".gettext("Click to retry now")."</span>";
		} elseif ($pkg_status["repository"]=="error") {
			echo "<span class='text-danger'>".gettext("Repository Problem")."</span><br/><span class='btn-link' onclick='checkupdate()'>".gettext("Click to retry now")."</span>";
		} elseif  ($pkg_status["updates"]=="0") {
			echo "<span class='text-info'>".gettext("At")." <small>".$pkg_status["last_check"]."</small>".gettext(" no updates found.")."<br/><span class='btn-link' onclick='checkupdate()'>Click to check now</span>";
		} else {
			echo "<span class='text-danger'>".gettext("A total of ").$pkg_status["updates"].gettext(" update(s) are available.")."<br/><span class='text-info'><small>(When last checked at: ".$pkg_status["last_check"]." )</small></span>"."</span><br/><a href='/system_firmware_check.php'>".gettext("Click to upgrade")."</a> | <span class='btn-link' onclick='checkupdate()'>Re-check now</span>";
		}
	} else {
		echo "<span class='text-danger'>".gettext("Unknown")."</span><br/><span class='btn-link' onclick='checkupdate()'>".gettext("Click to check now")."</span>";
	}
	exit;
}

$curcfg = $config['system']['firmware'];

$filesystems = get_mounted_filesystems();

?>
<script type="text/javascript">
//<![CDATA[
	jQuery(function() {
		jQuery("#statePB").css( { width: '<?php echo get_pfstate(true); ?>%' } );
		jQuery("#mbufPB").css( { width: '<?php echo get_mbuf(true); ?>%' } );
		jQuery("#cpuPB").css( { width:0 } );
		jQuery("#memUsagePB").css( { width: '<?php echo mem_usage(); ?>%' } );

<?PHP $d = 0; ?>
<?PHP foreach ($filesystems as $fs): ?>
		jQuery("#diskUsagePB<?php echo $d++; ?>").css( { width: '<?php echo $fs['percent_used']; ?>%' } );
<?PHP endforeach; ?>

		<?php if($showswap == true): ?>
			jQuery("#swapUsagePB").css( { width: '<?php echo swap_usage(); ?>%' } );
		<?php endif; ?>
		<?php if (get_temp() != ""): ?>
			jQuery("#tempPB").css( { width: '<?php echo get_temp(); ?>%' } );
		<?php endif; ?>
	});
//]]>
</script>

<table class="table table-striped">
	<tbody>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Name");?></td>
			<td width="75%" class="listr"><?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
		</tr>
		<tr>
			<td width="25%" valign="top" class="vncellt"><?=gettext("Version");?></td>
			<td width="75%" class="listr">
				<strong><?php readfile("/usr/local/etc/version"); ?><span id="version"></span></strong>
				(<?php echo php_uname("m"); ?>)
		<?php if(!$g['hideuname']): ?>
		<br />
		<div id="uname"><a href="#" onclick='swapuname(); return false;'><?php echo php_uname("s") . " " . php_uname("r"); ?></a></div>
		<?php endif; ?>
			</td>

		</tr>
					<?php if(!isset($config['system']['firmware']['disablecheck'])): ?>
			<tr>
				<td>
					Updates
				</td>
					<td>
						<div id='updatestatus'><span class="text-info">Fetching status</span></div>
					</td>
				</tr>
				<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Platform");?></td>
			<td width="75%" class="listr">
				<?=htmlspecialchars($g['platform'] == 'pfSense' ? 'OPNsense' : $g['platform']); /* Platform should not be used as product name */?>
				<?php if (($g['platform'] == "nanobsd") && (file_exists("/etc/nanosize.txt"))) {
					echo " (" . htmlspecialchars(trim(file_get_contents("/etc/nanosize.txt"))) . ")";
				} ?>
			</td>
		</tr>
		<?php if ($g['platform'] == "nanobsd"): ?>
			<?
			global $SLICE, $OLDSLICE, $TOFLASH, $COMPLETE_PATH, $COMPLETE_BOOT_PATH;
			global $GLABEL_SLICE, $UFS_ID, $OLD_UFS_ID, $BOOTFLASH;
			global $BOOT_DEVICE, $REAL_BOOT_DEVICE, $BOOT_DRIVE, $ACTIVE_SLICE;
			nanobsd_detect_slice_info();
			$rw = is_writable("/") ? "(rw)" : "(ro)";
			?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("NanoBSD Boot Slice");?></td>
			<td width="75%" class="listr">
				<?=htmlspecialchars(nanobsd_friendly_slice_name($BOOT_DEVICE));?> / <?=htmlspecialchars($BOOTFLASH);?> <?php echo $rw; ?>
				<?php if ($BOOTFLASH != $ACTIVE_SLICE): ?>
				<br /><br />Next Boot:<br />
				<?=htmlspecialchars(nanobsd_friendly_slice_name($GLABEL_SLICE));?> / <?=htmlspecialchars($ACTIVE_SLICE);?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("CPU Type");?></td>
			<td width="75%" class="listr">
			<?php
				echo (htmlspecialchars(get_single_sysctl("hw.model")));
			?>
			<div id="cpufreq"><?= get_cpufreq(); ?></div>
		<?php	$cpucount = get_cpu_count();
			if ($cpucount > 1): ?>
			<div id="cpucount">
				<?= htmlspecialchars($cpucount) ?> CPUs: <?= htmlspecialchars(get_cpu_count(true)); ?></div>
		<?php	endif; ?>
			</td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Hardware crypto");?></td>
			<td width="75%" class="listr"><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Uptime");?></td>
			<td width="75%" class="listr" id="uptime"><?= htmlspecialchars(get_uptime()); ?></td>
		</tr>
        <tr>
            <td width="25%" class="vncellt"><?=gettext("Current date/time");?></td>
            <td width="75%" class="listr">
                <div id="datetime"><?= date("D M j G:i:s T Y"); ?></div>
            </td>
        </tr>
		 <tr>
             <td width="30%" class="vncellt"><?=gettext("DNS server(s)");?></td>
             <td width="70%" class="listr">
					<?php
						$dns_servers = get_dns_servers();
						foreach($dns_servers as $dns) {
							echo "{$dns}<br />";
						}
					?>
			</td>
		</tr>
		<?php if ($config['revision']): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Last config change");?></td>
			<td width="75%" class="listr"><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("State table size");?></td>
			<td width="75%" class="listr">
				<?php	$pfstatetext = get_pfstate();
					$pfstateusage = get_pfstate(true);
				?>
				<div class="progress">
				  <div id="statePB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>

				<span id="pfstateusagemeter"><?= $pfstateusage.'%'; ?></span> (<span id="pfstate"><?= htmlspecialchars($pfstatetext); ?></span>)
			<br />
			<a href="diag_dump_states.php"><?=gettext("Show states");?></a>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("MBUF Usage");?></td>
			<td width="75%" class="listr">
				<?php
					$mbufstext = get_mbuf();
					$mbufusage = get_mbuf(true);
				?>

				<div class="progress">
				  <div id="mbufPB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<span id="mbufusagemeter"><?= $mbufusage.'%'; ?></span> (<span id="mbuf"><?= $mbufstext ?></span>)
			</td>
		</tr>
                <?php if (get_temp() != ""): ?>
                <tr>
                        <td width="25%" class="vncellt"><?=gettext("Temperature");?></td>
			<td width="75%" class="listr">
				<?php $TempMeter = $temp = get_temp(); ?>

				<div class="progress">
				  <div id="tempPB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<span id="tempmeter"><?= $temp."&#176;C"; ?></span>
			</td>
                </tr>
                <?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Load average");?></td>
			<td width="75%" class="listr">
			<div id="load_average" title="Last 1, 5 and 15 minutes"><?= get_load_average(); ?></div>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("CPU usage");?></td>
			<td width="75%" class="listr">

				<div class="progress">
				  <div id="cpuPB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<span id="cpumeter">(Updating in 10 seconds)</span>
			</td>
		</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Memory usage");?></td>
			<td width="75%" class="listr">
				<?php $memUsage = mem_usage(); ?>
				<div class="progress">
				  <div id="memUsagePB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<span id="memusagemeter"><?= $memUsage.'%'; ?></span> of <?= sprintf("%.0f", get_single_sysctl('hw.physmem') / (1024*1024)) ?> MB
			</td>
		</tr>
		<?php if($showswap == true): ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("SWAP usage");?></td>
			<td width="75%" class="listr">
				<?php $swapusage = swap_usage(); ?>
				<div class="progress">
				  <div id="swapUsagePB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<span id="swapusagemeter"><?= $swapusage.'%'; ?></span> of <?= sprintf("%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MB
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Disk usage");?></td>
			<td width="75%" class="listr">
<?PHP $d = 0; ?>
<?PHP foreach ($filesystems as $fs): ?>
				<div class="progress">
				  <div id="diskUsagePB<?php echo $d; ?>" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<?PHP if (substr(basename($fs['device']), 0, 2) == "md") $fs['type'] .= " in RAM"; ?>
				<?PHP echo "{$fs['mountpoint']} ({$fs['type']})";?>: <span id="diskusagemeter<?php echo $d++ ?>"><?= $fs['percent_used'].'%'; ?></span> of <?PHP echo $fs['total_size'];?>
				<br />
<?PHP endforeach; ?>
			</td>
		</tr>
	</tbody>
</table>
<script type="text/javascript">
//<![CDATA[
	function swapuname() {
		jQuery('#uname').html("<?php echo php_uname("a"); ?>");
	}
	function checkupdate() {
		jQuery('#updatestatus').html('<span class="text-info">Updating.... (takes upto 30 seconds) </span>');
		jQuery.ajax({
			type: "POST",
			url: '/widgets/widgets/system_information.widget.php',
			data:{action:'pkg_update'},
			success:function(html) {
				//alert(html);
				getstatus();

			}
		});
	}
	window.onload = function(){
		getstatus();
	}

	<?php if(!isset($config['system']['firmware']['disablecheck'])): ?>
		function getstatus() {
			scroll(0,0);
			var url = "/widgets/widgets/system_information.widget.php";
			var pars = 'getupdatestatus=yes';
			jQuery.ajax(
			url,
			{
			type: 'get',
			data: pars,
			complete: activitycallback
			});
		}
		function activitycallback(transport) {
			// .html() method process all script tags contained in responseText,
			// to avoid this we set the innerHTML property
			jQuery('#updatestatus').prop('innerHTML',transport.responseText);
		}
	<?php endif; ?>
//]]>
</script>
