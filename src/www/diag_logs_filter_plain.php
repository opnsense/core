<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2009-2010 Jim Pingle <jpingle@gmail.com>
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2003-2009 Manuel Kasper <mk@neon1.net>
	Originally Sponsored By Anathematic @ pfSense Forums
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
require_once("filter.inc");
require_once("filter_log.inc");
require_once("system.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

$filter_logfile = '/var/log/filter.log';

if (isset($config['syslog']['nentries'])) {
	$nentries = $config['syslog']['nentries'];
}  else {
	$nentries = 50;
}

if (isset($_POST['clear'])) {
	clear_clog($filter_logfile);
}

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			     <section class="col-xs-12">

					<div class="tab-content content-box col-xs-12">

							<div class="table-responsive">
								<table class="table table-striped table-sort">


								  <tr>
									<td colspan="2" class="listtopic">
									  <strong><?php printf(gettext("Last %s firewall log entries"),$nentries);?></strong></td>
								  </tr>
								  <?php dump_clog($filter_logfile, $nentries); ?>
								<tr><td colspan="2">
								<form id="clearform" name="clearform" action="diag_logs_filter.php" method="post" style="margin-top: 14px;">
									<input id="submit" name="clear" type="submit" class="btn btn-primary" value="<?=gettext("Clear log");?>" />
								</form>
								</td></tr>

								</table>
								</div>
							</td>
						  </tr>
						</table>
				    </div>
			</section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
