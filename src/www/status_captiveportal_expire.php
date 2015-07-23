<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>.
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
require_once("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
    $cpzone = $_POST['zone'];
}

if (empty($cpzone)) {
    header("Location: status_captiveportal.php");
    exit;
}

if (!is_array($config['captiveportal'])) {
    $config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Status"), gettext("Captive portal"), gettext("Expire Vouchers"), $a_cp[$cpzone]['zone']);

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
                        $tab_array[] = array(gettext("Active Users"), false, "status_captiveportal.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Active Vouchers"), false, "status_captiveportal_vouchers.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Voucher Rolls"), false, "status_captiveportal_voucher_rolls.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Test Vouchers"), false, "status_captiveportal_test.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Expire Vouchers"), true, "status_captiveportal_expire.php?zone={$cpzone}");
                        display_top_tabs($tab_array);
                    ?>

					<div class="tab-content content-box col-xs-12">

				    <div class="container-fluid">

	                        <form action="status_captiveportal_expire.php" method="post" enctype="multipart/form-data" name="iform" id="iform">

					<div class="table-responsive">
						<table class="table table-striped table-sort">

									  <tr>
									    <td valign="top" class="vncellreq"><?=gettext("Voucher(s)"); ?></td>
									    <td class="vtable">
									    <textarea name="vouchers" cols="65" rows="3" id="vouchers" class="formpre"><?=htmlspecialchars($_POST['vouchers']);?></textarea>
									    <br />
									<?=gettext("Enter multiple vouchers separated by space or newline. All valid vouchers will be marked as expired"); ?>.</td>
									  </tr>
									  <tr>
									    <td width="22%" valign="top">&nbsp;</td>
									    <td width="78%">
									    <input name="zone" type="hidden" value="<?=htmlspecialchars($cpzone);?>" />
									    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Submit"); ?>" />
									    </td>
									  </tr>
									</table>

									<?php
                                    if ($_POST) {
                                        if ($_POST['vouchers']) {
                                            $result = voucher_expire($_POST['vouchers']);
                                            echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\" width=\"100%\" summary=\"results\">\n";
                                            if ($result) {
                                                echo "<tr><td bgcolor=\"#D9DEE8\"><img src=\"/themes/{$g['theme']}/images/icons/icon_pass.gif\" alt=\"pass\" /></td>";
                                                echo "<td bgcolor=\"#D9DEE8\">Success</td></tr>";
                                            } else {
                                                echo "<tr><td bgcolor=\"#FFD9D1\"><img src=\"/themes/{$g['theme']}/images/icons/icon_block.gif\" alt=\"block\" /></td>";
                                                echo "<td bgcolor=\"#FFD9D1\">Error</td></tr>";
                                            }
                                            echo "</table>";
                                        }
                                    }

?>
					</div>
	                        </form>
				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>


<?php include("foot.inc");
