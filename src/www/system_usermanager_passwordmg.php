<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2011 Ermal Luçi
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

require_once("certs.inc");
require_once("guiconfig.inc");

$pgtitle = array(gettext("System"),gettext("User Password"));

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['Username'];

if (isset($_POST['save'])) {
    unset($input_errors);
    /* input validation */

    $reqdfields = explode(" ", "passwordfld0 passwordfld1 passwordfld2");
    $reqdfieldsn = array(gettext("Password"));
    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if ($_POST['passwordfld1'] != $_POST['passwordfld2'] ||
        $config['system']['user'][$userindex[$username]]['password'] != crypt($_POST['passwordfld0'], '$6$')) {
        $input_errors[] = gettext("The passwords do not match.");
    }

    if (!$input_errors) {
        // all values are okay --> saving changes
        $config['system']['user'][$userindex[$username]]['password'] = crypt($_POST['passwordfld1'], '$6$');
        local_user_set($config['system']['user'][$userindex[$username]]);

        write_config();

        $savemsg = gettext("Password successfully changed") . "<br />";
    }
}

session_write_close();

/* determine if user is not local to system */
$islocal = false;
foreach ($config['system']['user'] as $user) {
    if ($user['name'] == $username) {
        $islocal = true;
    }
}


include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">

		<div class="container-fluid">

			<div class="row">
				<?

                if (isset($input_errors)) {
                    print_input_errors($input_errors);
                }
                if (isset($savemsg)) {
                    print_info_box($savemsg);
                }

                if ($islocal == false) {
                    echo gettext("Sorry, you cannot change the password for a non-local user.");
                                        include("foot.inc");
                    exit;
                }

                ?>
			    <section class="col-xs-12">

				<div class="content-box">

		                        <form action="system_usermanager_passwordmg.php" method="post" name="iform" id="iform">

						<div class="table-responsive">
							<table class="table table-striped table-sort">
			                                <tr>
			                                        <td colspan="2" valign="top" class="listtopic"><?=$username?>'s <?=gettext("Password"); ?></td>
			                                </tr>
			                                <tr>
			                                        <td width="22%" valign="top" class="vncell"><?=gettext("Old password"); ?></td>
			                                        <td width="78%" class="vtable">
			                                                <input name="passwordfld0" type="password" class="formfld pwd" id="passwordfld0" size="20" />
			                                        </td>
			                                </tr>
			                                <tr>
			                                        <td width="22%" valign="top" class="vncell"><?=gettext("New password"); ?></td>
			                                        <td width="78%" class="vtable">
			                                                <input name="passwordfld1" type="password" class="formfld pwd" id="passwordfld1" size="20" />
			                                        </td>
			                                </tr>
			                                <tr>
									<td width="22%" valign="top" class="vncell"><?=gettext("Confirmation");?></td>
			                                        <td width="78%" class="vtable">
			                                                <input name="passwordfld2" type="password" class="formfld pwd" id="passwordfld2" size="20" />
			                                        </td>
			                                </tr>
			                                <tr>
			                                        <td width="22%" valign="top">&nbsp;</td>
			                                        <td width="78%">
			                                                <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
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
<?php include("foot.inc");
