<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2004 Dinesh Nair <dinesh@alphaque.com>
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
require_once("functions.inc");
require_once("filter.inc");
require_once("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
        $cpzone = $_POST['zone'];
}

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

if (!is_array($config['captiveportal'])) {
        $config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"),gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if ($_GET['act'] == "del") {
    $a_allowedips =& $config['captiveportal'][$cpzone]['allowedip'];
    if ($a_allowedips[$_GET['id']]) {
        $ipent = $a_allowedips[$_GET['id']];
        unset($a_allowedips[$_GET['id']]);
        write_config();
        header("Location: services_captiveportal_ip.php?zone={$cpzone}");
        exit;
    }
}


include("head.inc");

$main_buttons = array(
    array('label'=>'Add IP address', 'href'=>'services_captiveportal_ip_edit.php?zone='.$cpzone),
);


?>


<body>
	<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if ($savemsg) {
                    print_info_box($savemsg);
} ?>

			    <section class="col-xs-12">

				<?php
                        $tab_array = array();
                        $tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Allowed IP addresses"), true, "services_captiveportal_ip.php?zone={$cpzone}");
                        // Hide Allowed Hostnames as this feature is currently not supported
                        // $tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
                        display_top_tabs($tab_array, true);
                    ?>

					<div class="tab-content content-box col-xs-12">

					<div class="container-fluid">

		                    <form action="services_captiveportal_ip.php" method="post" name="iform" id="iform">
		                        <input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />

		                        <div class="table-responsive">
			                        <table class="table table-striped table-sort">
										<tr>
										  <td width="40%" class="listhdrr"><?=gettext("IP address"); ?></td>
										  <td width="50%" class="listhdr"><?=gettext("Description"); ?></td>
										  <td width="10%" class="list">

										  </td>
										</tr>
									<?php	if (is_array($a_cp[$cpzone]['allowedip'])) :
                                            $i = 0; foreach ($a_cp[$cpzone]['allowedip'] as $ip) :
?>
										<tr ondblclick="document.location='services_captiveportal_ip_edit.php?zone=<?=$cpzone;
?>&amp;id=<?=$i;?>'">
										  <td class="listlr">
											<?php
                                            if ($ip['dir'] == "to") {
                                                echo "any <span class=\"glyphicon glyphicon-arrow-right\" aria-hidden=\"true\" alt=\"in\"></span>  ";
                                            }
                                            if ($ip['dir'] == "both") {
                                                echo "<span class=\"glyphicon glyphicon-resize-horizontal\" aria-hidden=\"true\" alt=\"pass\"></span>   ";
                                            }
                                            echo strtolower($ip['ip']);
                                            if ($ip['sn'] != "32" && is_numeric($ip['sn'])) {
                                                $sn = $ip['sn'];
                                                echo "/$sn";
                                            }
                                            if ($ip['dir'] == "from") {
                                                echo "<span class=\"glyphicon glyphicon-arrow-right\" aria-hidden=\"true\" alt=\"any\"></span> any";
                                            }

                                            ?>
										  </td>
										  <td class="listbg">
											<?=htmlspecialchars($ip['descr']);?>&nbsp;
										  </td>
										  <td valign="middle" class="list nowrap"><a href="services_captiveportal_ip_edit.php?zone=<?=$cpzone;
?>&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
											<a href="services_captiveportal_ip.php?zone=<?=$cpzone;
?>&amp;act=del&amp;id=<?=$i;
?>" onclick="return confirm('<?=gettext("Do you really want to delete this address?"); ?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a></td>
										</tr>
                                        <?php $i++;

                                            endforeach;

endif; ?>

										<tr>
										<td colspan="2" class="list"><p class="vexpl"><span class="red"><strong>
                                            <?=gettext("Note:"); ?><br />
										  </strong></span>
                                            <?=gettext("Adding allowed IP addresses will allow IP access to/from these addresses through the captive portal without being taken to the portal page. This can be used for a web server serving images for the portal page or a DNS server on another network, for example."); ?></p>
										</td>
										<td class="list">&nbsp;</td>
										</tr>
									  </table>
		                        </div>
		                    </form>
					</div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc");
