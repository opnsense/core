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
require_once("interfaces.inc");

$pconfig['enable'] = isset($config['rrd']['enable']);

if ($_POST) {
	$pconfig = $_POST;

	if (isset($pconfig['ResetRRD'])) {
		$savemsg = gettext('RRD data has been cleared.');
		mwexec('/bin/rm /var/db/rrd/*');
	} else {
		$config['rrd']['enable'] = $_POST['enable'] ? true : false;
		$savemsg = get_std_save_message();
		write_config();
	}

	enable_rrd_graphing();
	setup_gateways_monitor();
}

$pgtitle = array(gettext('System'), gettext('Settings'), gettext('RRD Graphs'));
include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($savemsg)) print_info_box($savemsg); ?>

			    <section class="col-xs-12">

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
