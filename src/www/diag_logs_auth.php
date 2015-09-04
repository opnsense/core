<?php

/*
	Copyright (C) 2014 Deciso B.V.
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
require_once("services.inc");
require_once("system.inc");
require_once("interfaces.inc");

$portal_logfile = '/var/log/portalauth.log';

if (empty($config['syslog']['nentries'])) {
        $nentries = 50;
} else {
        $nentries = $config['syslog']['nentries'];
}

if ($_POST['clear']) {
	clear_log_file($portal_logfile);
}

$pgtitle = array(gettext("Status"),gettext("System logs"),gettext("Portal Auth"));
$shortcut_section = "captiveportal";
include("head.inc");

?>


<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">


					<? include('diag_logs_tabs.inc'); ?>


						<div class="tab-content content-box col-xs-12">
						<div class="container-fluid">
							<?php printf(gettext('Last %s Portal Auth log entries'), $nentries); ?>
						</div>
						<div class="table-responsive">
							<table class="table table-striped table-sort">
								<?php dump_clog($portal_logfile, $nentries, true); ?>
							</table>
						</div>

					    <div class="container-fluid">
								<form action="diag_logs_auth.php" method="post">
									<input name="clear" type="submit" class="btn" value="<?= gettext("Clear log");?>" />
								</form>

						</div>
				    </div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
