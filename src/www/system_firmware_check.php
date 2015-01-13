<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
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
require_once("pfsense-utils.inc");
require_once("script/load_phalcon.php");

//$d_isfwfile = 1;

$file_pkg_status="/tmp/pkg_status.json";
$file_upgrade_progress="/tmp/pkg_upgrade.progress";
$pkg_status = array();

if($_POST['action'] == 'pkg_update') {
	/* Setup Shell variables */
	$shell_output = array();
	$shell = new OPNsense\Core\Shell();
	// execute shell command and collect (only valid) info into named array
	$shell->exec("/usr/local/opnsense/scripts/pkg_updatecheck.sh",false,false,$shell_output);
}

if($_POST['action'] == 'pkg_upgrade' ) {
	/* Setup Shell variables */
	$shell_output = array();
	$shell = new OPNsense\Core\Shell();
	// execute shell command and collect (only valid) info into named array
	$shell->exec("/usr/local/opnsense/scripts/pkg_upgrade.sh ".$_POST['packages']." > /dev/null 2 > /dev/null < /dev/null &",false,false,$shell_output);
}

if($_POST['action'] == 'update_status' ) {
	if (file_exists($file_upgrade_progress)) {
		$content = file_get_contents($file_upgrade_progress);
		echo $content;
	}
	exit;

}

if (file_exists($file_pkg_status)) {

		$json = file_get_contents($file_pkg_status);
		$pkg_status = json_decode($json,true);
}

if($_REQUEST['getupdatestatus']) {
	if (file_exists($file_pkg_status)) {
		if ($pkg_status["connection"]=="error") {
			echo "<span class='text-danger'>".gettext("Connection Error")."</span><br/><span class='btn btn-primary' onclick='checkupdate()'>".gettext("Click to retry now")."</span>";
		} elseif ($pkg_status["repository"]=="error") {
			echo "<span class='text-danger'>".gettext("Repository Problem")."</span><br/><span class='btn btn-primary' onclick='checkupdate()'>".gettext("Click to retry now")."</span>";
		} elseif  ($pkg_status["updates"]=="0") {
			echo "<span class='text-info'>".gettext("At")." <small>".$pkg_status["last_check"]."</small>".gettext(" no updates found.")."<br/><span class='btn btn-primary' onclick='checkupdate()'>".gettext("Click to check now")."</span>";
		} elseif ( $pkg_status["updates"] == "1" && $pkg_status["upgrade_packages"][0]["name"] == "pkg" ) {
			echo "<span class='text-danger'>".gettext("There is a mandatory update for the package manager.").
					"</span><span class='text-info'><small>(When last checked at: ".$pkg_status["last_check"]." )</small></span><br />".
					"<span class='text-danger'>".gettext("Upgrade pkg and recheck, there maybe other updates available.").
					"</span><br/><span class='btn btn-primary' onclick='upgradenow(this)' pkgs='pkg'>".gettext("Upgrade Now").
					"</span>&nbsp;<span class='btn btn-primary' onclick='checkupdate()'>".gettext("Re-Check Now")."</span>";
		} else {
			echo "<span class='text-danger'>".gettext("A total of ").$pkg_status["updates"].gettext(" update(s) are available.")."<span class='text-info'><small>(When last checked at: ".$pkg_status["last_check"]." )</small></span>"."</span><br/><span class='btn btn-primary' onclick='upgradenow(this)' pkgs='all'>".gettext("Upgrade Now")."</span>&nbsp;<span class='btn btn-primary' onclick='checkupdate()'>".gettext("Re-Check Now")."</span>";
		}
	} else {
		echo "<span class='text-danger'>".gettext("Current status is unknown")."</span><br/><span class='btn btn-primary' onclick='checkupdate()'>".gettext("Click to check now")."</span>";
	}
	exit;
}

//$curcfg = $config['system']['firmware'];
$pgtitle=array(gettext("System"), gettext("Firmware"), gettext("Auto Update"));
include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

<!-- row -->
<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">
            <section class="col-xs-12">

                <? include('system_firmware_tabs.php'); ?>

                <div class="content-box tab-content">

                    <form action="system_firmware_auto.php" method="post">

                        <div class="table-responsive table-striped">

					<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="" class="table table-striped">
						<tr>
							<th colspan="2">Current Firmware Status</th>
						</tr>
						<tr>
							<td align="left" colspan="2">
								<div id="updatestatus">
								</div>
								<div class="progress" style="display:none">
											<div class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width:0%;">
												 <span class="text-info">0% Complete</span>
											</div>


										</div>
										<div>
												<textarea name="output" id="output" class="form-control" rows="10" wrap="hard" readonly style="max-width:100%;display:none"></textarea>
										</div>
							</td>
						</tr>
						<tr>
						</tr>
							<tr>
								<th>Available Upgrades</th>
							</tr>
							<tr>
								<td>
									<div id="upgrades">
										<?php
											echo '<table>';
												echo '<tr>';
												echo '<th>Package Name</th>';
												echo '<th>Current Version</th>';
												echo '<th>New Version</th>';
												echo '</tr>';
											foreach ($pkg_status["upgrade_packages"] as $upgrade_new) {
												echo '<tr>';
												echo '<td>';
												echo '<span class="text-info"><b>'.$upgrade_new["name"].'</b></span><br/>';
												echo '</td>';
												echo '<td>';
														echo '<span class="text-info"><b>'.$upgrade_new["current_version"].'</b></span><br/>';
												echo '</td>';
														echo '<td>';
														echo '<span class="text-info"><b>'.$upgrade_new["new_version"].'</b></span><br/>';
														echo '</td>';
												echo '</tr>';

											}
											echo '</table>';
										?>
									</div>
								</td>
							</tr>
							<tr>
								<th>Required new Installs</th>
							</tr>
							<tr>
								<td>
									<div id="new">
										<?php
											echo '<table>';
										echo '<tr>';
											echo '<th>Package Name</th>';
											echo '</tr>';
											echo '<tr>';
											foreach ($pkg_status["new_packages"] as $upgrade_new) {
												echo '<td>';
												echo '<span class="text-info"><b>'.$upgrade_new["name"].'</b></span><br/>';
												echo '</td>';
												echo '</tr>';
											}
											echo '</table>';
										?>
									</div>

								</td>
							</tr>
					</table>

		            </div>

                    </form>

                </div>
            </section>

        </div>

	</div>
</section>

<script type="text/javascript">
//<![CDATA[
	function checkupdate() {
		jQuery('#updatestatus').html('<span class="text-info">Updating.... (may take up to 30 seconds) </span>');
		jQuery.ajax({
			type: "POST",
			url: '/system_firmware_check.php',
			data:{action:'pkg_update'},
			success:function(html) {
				getstatus();
				location.reload(); // Reload Page to show update status

			}
		});
	}

    function upgradenow(data) {
		jQuery('#updatestatus').html('<span class="text-info">Starting Upgrade.. Please do not leave this page while upgrade is in progress.</span>');
		jQuery('#output').show();
		jQuery.ajax({
			type: "POST",
			url: '/system_firmware_check.php',
			data:{action: 'pkg_upgrade', packages: data.getAttribute("pkgs") },
			success:function(html) {
				setTimeout(function() { updatestatus(); }, 100);
			}
		});

	}

	function updatestatus() {

		jQuery.ajax({
			type: "POST",
			url: '/system_firmware_check.php',
			data:{action:'update_status'},
			success:function(data, textStatus, jqXHR) {
				jQuery('#output').prop('innerHTML',data);
				document.getElementById("output").scrollTop = document.getElementById("output").scrollHeight ;
				if ( data.indexOf('***DONE***') < 0 )  {
					setTimeout(function() { updatestatus(); }, 500);
				} else {
					jQuery('#updatestatus').html('<span class="text-info">Upgrade Done!</span>');
				}
			}
		});
	}

	function getstatus() {
		scroll(0,0);
		var url = "/system_firmware_check.php";
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

	window.onload = function(){
		getstatus();
	}

//]]>
</script>

<?php include("foot.inc"); ?>
