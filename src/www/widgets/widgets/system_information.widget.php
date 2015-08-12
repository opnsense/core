<?php

/*
        Copyright (C) 2014 Deciso B.V.
        Copyright (C) 2007 Scott Dale
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

require_once("guiconfig.inc");
include_once("includes/functions.inc.php");
require_once("pfsense-utils.inc");
require_once("system.inc");

if (isset($_REQUEST['getupdatestatus'])) {
    $pkg_json = trim(configd_run('firmware pkgstatus'));
    if ($pkg_json != '') {
        $pkg_status = json_decode($pkg_json, true);
    }

    if (!isset($pkg_status) || $pkg_status["connection"]=="error") {
        echo "<span class='text-danger'>".gettext("Connection Error")."</span><br/><span class='btn-link' onclick='checkupdate()'>".gettext("Click to retry")."</span>";
    } elseif ($pkg_status["repository"]=="error") {
        echo "<span class='text-danger'>".gettext("Repository Problem")."</span><br/><span class='btn-link' onclick='checkupdate()'>".gettext("Click to retry")."</span>";
    } elseif ($pkg_status["updates"]=="0") {
        echo "<span class='text-info'>".gettext("Your system is up to date.")."</span><br/><span class='btn-link' onclick='checkupdate()'>".gettext('Click to check for updates')."</span>";
    } else {
        echo "<span class='text-info'>".gettext("There are ").$pkg_status["updates"].gettext(" update(s) available.")."</span><br/><a href='/ui/core/firmware/#checkupdate'>".gettext("Click to upgrade")."</a> | <span class='btn-link' onclick='checkupdate()'>".gettext('Re-check now')."</span>";
    }

    exit;
}

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
<?PHP foreach ($filesystems as $fs) :
?>
		jQuery("#diskUsagePB<?php echo $d++; ?>").css( { width: '<?php echo $fs['percent_used']; ?>%' } );
<?PHP
endforeach; ?>

		<?php if ($showswap == true) :
?>
			jQuery("#swapUsagePB").css( { width: '<?php echo swap_usage(); ?>%' } );
		<?php
endif; ?>
		<?php if (get_temp() != "") :
?>
			jQuery("#tempPB").css( { width: '<?php echo get_temp(); ?>%' } );
		<?php
endif; ?>
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
			<td width="25%" valign="top" class="vncellt"><?=gettext("Versions");?></td>
			<td width="75%" class="listr">
				<?php
                    $pkgver = explode('-', trim(file_get_contents('/usr/local/opnsense/version/opnsense')));
                    echo sprintf('%s %s-%s', $g['product_name'], $pkgver[0], php_uname('m'));
                ?>
				<br /><?php echo php_uname('s') . ' ' . php_uname('r'); ?>
				<br /><?php echo exec('/usr/local/bin/openssl version'); ?>
			</td>

		</tr>
			<tr>
				<td>
					<?= gettext('Updates') ?>
				</td>
					<td>
						<div id='updatestatus'><span class='btn-link' onclick='checkupdate()'><?=gettext("Click to check for updates");?></span></div>
					</td>
				</tr>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("CPU Type");?></td>
			<td width="75%" class="listr">
			<?php
                echo (htmlspecialchars(get_single_sysctl("hw.model")));
            ?>
			<div id="cpufreq"><?= get_cpufreq(); ?></div>
		<?php	$cpucount = get_cpu_count();
        if ($cpucount > 1) :
?>
			<div id="cpucount">
				<?= htmlspecialchars($cpucount) ?> CPUs: <?= htmlspecialchars(get_cpu_count(true)); ?></div>
		<?php
        endif; ?>
			</td>
		</tr>
		<?php if (isset($hwcrypto)) :
?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Hardware crypto");?></td>
			<td width="75%" class="listr"><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php
endif; ?>
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
                    foreach ($dns_servers as $dns) {
                        echo "{$dns}<br />";
                    }
                    ?>
			</td>
		</tr>
		<?php if ($config['revision']) :
?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Last config change");?></td>
			<td width="75%" class="listr"><?= htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));?></td>
		</tr>
		<?php
endif; ?>
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

				<span id="pfstateusagemeter"><?= $pfstateusage.'%';
?></span> (<span id="pfstate"><?= htmlspecialchars($pfstatetext); ?></span>)
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
                <?php if (get_temp() != "") :
?>
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
                <?php
endif; ?>
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
				<span id="cpumeter">(<?= gettext('Updating in 10 seconds') ?>)</span>
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
				<span id="memusagemeter"><?= $memUsage.'%'; ?></span> used <?= sprintf("%.0f/%.0f", $memUsage/100.0 * get_single_sysctl('hw.physmem') / (1024*1024), get_single_sysctl('hw.physmem') / (1024*1024)) ?> MB
			</td>
		</tr>
		<?php if ($showswap == true) :
?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("SWAP usage");?></td>
			<td width="75%" class="listr">
				<?php $swapusage = swap_usage(); ?>
				<div class="progress">
				  <div id="swapUsagePB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<span id="swapusagemeter"><?= $swapusage.'%'; ?></span> used <?= sprintf("%.0f/%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $3;}'`, `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MB
			</td>
		</tr>
		<?php
endif; ?>
		<tr>
			<td width="25%" class="vncellt"><?=gettext("Disk usage");?></td>
			<td width="75%" class="listr">
<?PHP $d = 0; ?>
<?PHP foreach ($filesystems as $fs) :
?>
				<div class="progress">
				  <div id="diskUsagePB<?php echo $d; ?>" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
				    <span class="sr-only"></span>
				  </div>
				</div>
				<?PHP if (substr(basename($fs['device']), 0, 5) == "tmpfs") {
                    $fs['type'] .= " in RAM";
} ?>
				<?PHP echo "{$fs['mountpoint']} ({$fs['type']})";?>: <span id="diskusagemeter<?php echo $d++ ?>"><?= $fs['percent_used'].'%'; ?></span> used <?PHP echo $fs['used_size'] ."/". $fs['total_size'];
				if ($d != count($filesystems)) {
					echo '<br/><br/>';
				}
endforeach; ?>
			</td>
		</tr>
	</tbody>
</table>
<script type="text/javascript">
//<![CDATA[
	function checkupdate() {
		jQuery('#updatestatus').html('<span class="text-info">Fetching... (may take up to 30 seconds)</span>');
		jQuery.ajax({
			type: "POST",
			url: '/widgets/widgets/system_information.widget.php',
			data:{action:'pkg_update'},
			success:function(html) {
				getstatus();
			}
		});
	}

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
//]]>
</script>
