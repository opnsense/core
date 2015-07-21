<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2007 Seth Mos <seth.mos@dds.nl>
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
require_once("rrd.inc");

$pconfig['enable'] = isset($config['rrd']['enable']);
$pconfig['category'] = $config['rrd']['category'];
$pconfig['style'] = $config['rrd']['style'];
$pconfig['period'] = $config['rrd']['period'];

$curcat = "settings";
$categories = array('system' => gettext("System"),
		'traffic' => gettext("Traffic"),
		'packets' => gettext("Packets"),
		'quality' => gettext("Quality"),
		'captiveportal' => gettext("Captive Portal"));

if(isset($config['ntpd']['statsgraph'])) {
	$categories['ntpd'] = gettext("NTP");
}

$styles = array('inverse' => gettext("Inverse"),
		'absolute' => gettext("Absolute"));
$periods = array("absolute" => gettext("Absolute Timespans"),
		"current" => gettext("Current Period"),
		"previous" => gettext("Previous Period"));

if ($_POST['ResetRRD']) {
	mwexec('/bin/rm /var/db/rrd/*');
	enable_rrd_graphing();
	setup_gateways_monitor();
	$savemsg = "RRD data has been cleared. New RRD files have been generated.";
} elseif ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	/* none */

        if (!$input_errors) {
                $config['rrd']['enable'] = $_POST['enable'] ? true : false;
                $config['rrd']['category'] = $_POST['category'];
                $config['rrd']['style'] = $_POST['style'];
                $config['rrd']['period'] = $_POST['period'];
                write_config();

                $retval = 0;
                $retval = enable_rrd_graphing();
                $savemsg = get_std_save_message($retval);
	}
}



$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");

foreach($databases as $database) {
	if(stristr($database, "wireless")) {
		$wireless = true;
	}
	if(stristr($database, "-cellular") && !empty($config['ppps'])) {
		$cellular = true;
	}
	if(stristr($database, "-vpnusers")) {
		$vpnusers = true;
	}
	if(stristr($database, "captiveportal-") && is_array($config['captiveportal'])) {
		$captiveportal = true;
	}
}

$pgtitle = array(gettext("Status"),gettext("RRD Graphs"));
include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
				<?php if (isset($savemsg)) print_info_box($savemsg); ?>

			    <section class="col-xs-12">

				<? include("status_rrd_graph_tabs.inc"); ?>

					<div class="tab-content content-box col-xs-12">


							<form action="status_rrd_graph_settings.php" method="post" name="iform" id="iform">



							<div class="table-responsive">
								<table class="table table-striped __nomb">
									<tr>
										<td width="22%" valign="top" class="vtable"><?=gettext("RRD Graphs");?></td>
										<td width="78%" class="vtable">
											<label>
												<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\"" ?> onclick="enable_change(false)" />
												&nbsp;<?=gettext("Enables the RRD graphing backend.");?>
											</label>
										</td>
									</tr>
									<tr>
							<td width="22%" valign="top" class="vtable"><?=gettext("Default category");?></td>
				                        <td width="78%" class="vtable">
											<select name="category" id="category" class="form-control" style="z-index: -10;" >
											<?php
											foreach ($categories as $category => $categoryd) {
												echo "<option value=\"$category\"";
												if ($category == $pconfig['category']) echo " selected=\"selected\"";
												echo ">" . htmlspecialchars($categoryd) . "</option>\n";
											}
											?>
											</select>
											<p class="text-muted"><em><small><?=gettext("This selects default category.");?></small></em></p>
										</td>
									</tr>
									<tr>
							<td width="22%" valign="top" class="vtable"><?=gettext("Default style");?></td>
				                        <td width="78%" class="vtable">
											<select name="style" class="form-control" style="z-index: -10;" >
											<?php
											foreach ($styles as $style => $styled) {
												echo "<option value=\"$style\"";
												if ($style == $pconfig['style']) echo " selected=\"selected\"";
												echo ">" . htmlspecialchars($styled) . "</option>\n";
											}
											?>
											</select>
											<p class="text-muted"><em><small><?=gettext("This selects the default style.");?></small></em></p>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vtable"><?=gettext("Default period");?></td>
										<td width="78%" class="vtable">
											<select name="period" class="form-control" style="z-index: -10;" >
											<?php
											foreach ($periods as $period => $periodd) {
												echo "<option value=\"$period\"";
												if ($period == $pconfig['period']) echo " selected=\"selected\"";
												echo ">" . htmlspecialchars($periodd) . "</option>\n";
											}
											?>
											</select>
											<p class="text-muted"><em><small><?=gettext("This selects the default period.");?></small></em></p>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top">&nbsp;</td>
										<td width="78%">
											<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" onclick="enable_change(true)" />
											<input name="ResetRRD" type="submit" class="btn btn-default" value="<?=gettext("Reset RRD Data");?>" onclick="return confirm('<?=gettext('Do you really want to reset the RRD graphs? This will erase all graph data.');?>')" />
										</td>
									</tr>
								</table>
							 </div>
							 <div class="container-fluid">
							 <p><strong><span class="text-danger"><?=gettext("Note:");?></span></strong><br />
											<?=gettext("Graphs will not be allowed to be recreated within a 1 minute interval, please " .
											"take this into account after changing the style.");?>

							 </p>
						</div>
				    </div>
			</section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
