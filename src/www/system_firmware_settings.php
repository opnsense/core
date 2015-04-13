<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2005 Colin Smith
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

require_once('guiconfig.inc');

if ($_POST) {
	if (!$input_errors) {
		if($_POST['disablecheck'] == "yes")
			$config['system']['firmware']['disablecheck'] = true;
		else
			unset($config['system']['firmware']['disablecheck']);

		write_config();
	}
}

$curcfg = $config['system']['firmware'];

$pgtitle = array(gettext("System"),gettext("Firmware"),gettext("Settings"));
include("head.inc");

?>


<body>

<?php include("fbegin.inc");?>

<!-- row -->
<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">
            <?php
		if ($input_errors) print_input_errors($input_errors);
		if ($savemsg) print_info_box($savemsg);
            ?>
            <section class="col-xs-12">

                <? include('system_firmware_tabs.inc'); ?>

                <div class="content-box tab-content">

                    <form action="system_firmware_settings.php" method="post" name="iform" id="iform">

                        <table class="table table-striped" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
                            <thead>
				    <tr>
					<th colspan="2" valign="top" class="listtopic"><?=gettext("Updates"); ?></th>
				</tr>
                            </thead>

                            <tbody>
				<tr>
					<td width="22%" valign="top" class="vncell"><?=gettext("Dashboard check"); ?></td>
					<td width="78%" class="vtable">
						<input name="disablecheck" type="checkbox" id="disablecheck" value="yes" <?php if (isset($curcfg['disablecheck'])) echo "checked=\"checked\""; ?> />
						<br />
						<?=gettext("Disable the automatic dashboard auto-update check."); ?>
					</td>
				</tr>
                            </tbody>
                        </table>

                        <table class="table table-striped __nomb" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
                           <tr>
                                <td width="22%" valign="top">&nbsp;</td>
                                <td width="78%">
                                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                                </td>
                            </tr>
                        </table>

			</form>

                </div>
            </section>
        </div>
	</div>
</section>


<?php include("foot.inc"); ?>
