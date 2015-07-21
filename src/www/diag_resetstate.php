<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004-2009 Scott Ullrich
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

function filter_flush_state_table()
{
	return mwexec('/sbin/pfctl -F state');
}


if ($_POST) {
	$savemsg = "";
	if ($_POST['statetable']) {
		filter_flush_state_table();
		if (isset($savemsg))
			$savemsg .= " ";
		$savemsg .= gettext("The state table has been flushed successfully.");
	}
	if ($_POST['sourcetracking']) {
		mwexec("/sbin/pfctl -F Sources");
		if (isset($savemsg))
			$savemsg .= " <br />";
		$savemsg .= gettext("The source tracking table has been flushed successfully.");
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("Reset state"));
include("head.inc");

?>
<body>

<?php include("fbegin.inc"); ?>


<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			 <section class="col-xs-12">

				<?php
						$tab_array = array();
						$tab_array[0] = array(gettext("States"), false, "diag_dump_states.php");
						$tab_array[1] = array(gettext("Reset states"), true, "diag_resetstate.php");
						display_top_tabs($tab_array);
				?>


				<div class="tab-content content-box col-xs-12">

				<div class="container-fluid tab-content">

					<div class="tab-pane active" id="system">


								<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
								<?php if (isset($savemsg)) print_info_box($savemsg); ?>


								<form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">


			                      <input name="statetable" type="checkbox" id="statetable" value="yes" checked="checked" />
			                      <strong><?= gettext("Firewall state table"); ?></strong><br />
			                      <span class="vexpl"><br />
			                      <?=gettext("Resetting the state tables will remove all entries from " .
			                      "the corresponding tables. This means that all open connections " .
			                      "will be broken and will have to be re-established. This " .
			                      "may be necessary after making substantial changes to the " .
			                      "firewall and/or NAT rules, especially if there are IP protocol " .
			                      "mappings (e.g. for PPTP or IPv6) with open connections."); ?><br />
			                      <br />
			                      </span><span class="vexpl"><?=gettext("The firewall will normally leave " .
			                      "the state tables intact when changing rules."); ?><br />
			                      <br />
			                      <?=gettext("NOTE: If you reset the firewall state table, the browser " .
			                      "session may appear to be hung after clicking &quot;Reset&quot;. " .
			                      "Simply refresh the page to continue."); ?></span></p>

									<?php if (isset($config['system']['lb_use_sticky'])): ?>
									<p>
									<input name="sourcetracking" type="checkbox" id="sourcetracking" value="yes" checked="checked" />
									<strong><?= gettext("Firewall Source Tracking"); ?></strong><br />
									<span class="vexpl"><br />
									<?=gettext("Resetting the source tracking table will remove all source/destination associations. " .
									"This means that the \"sticky\" source/destination association " .
									"will be cleared for all clients."); ?><br />
									<br />
									</span><span class="vexpl"><?=gettext("This does not clear active connection states, only source tracking."); ?><br />
									</p>
									<?php endif; ?>
						            <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Reset"); ?>" />


								</form>

						</div>
					</div>
				</div>
		    </section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
