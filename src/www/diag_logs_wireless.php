<?php

/*
	Copyright (C) Deciso B.V.
	Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>.
	Copyright (C) 2008 Seth Mos <seth.mos@dds.nl>.
	Copyright (C) 2011 Jim Pingle <jimp@pfsense.org>.
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

$wireless_logfile = '/var/log/wireless.log';

$nentries = $config['syslog']['nentries'];
if (!$nentries) {
	$nentries = 50;
}

if ($_POST['clear']) {
	clear_log_file($wireless_logfile);
}

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Wireless"));
$shortcut_section = "wireless";
include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors)) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<? $active_tab = "/diag_logs.php"; include('diag_logs_tabs.inc'); ?>

					<div class="tab-content content-box col-xs-12">
				    <div class="container-fluid">


							<? include('diag_logs_pills.php'); ?>


							 <div class="table-responsive">
								<table class="table table-striped table-sort">
									 <?php dump_clog($wireless_logfile, $nentries); ?>
								</table>
							 </div>

							<form action="diag_logs_wireless.php" method="post">
								<input name="clear" type="submit" class="btn" value="<?= gettext("Clear log");?>" />
							</form>


						</div>
				    </div>
			</section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
