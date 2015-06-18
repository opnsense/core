<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
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

global $cpzone;
global $cpzoneid;

if (!is_array($config['captiveportal'])) {
    $config['captiveportal'] = array();
}
$a_cp = &$config['captiveportal'];

if ($_GET['act'] == "del" && !empty($_GET['zone'])) {
    $cpzone = $_GET['zone'];
    if ($a_cp[$cpzone]) {
        $cpzoneid = $a_cp[$cpzone]['zoneid'];
        unset($a_cp[$cpzone]['enable']);
        captiveportal_configure();
        unset($a_cp[$cpzone]);
        if (isset($config['voucher'][$cpzone])) {
            unset($config['voucher'][$cpzone]);
        }
        write_config();
        header("Location: services_captiveportal_zones.php");
        exit;
    }
}

$pgtitle = array(gettext("Captiveportal"),gettext("Zones"));
$shortcut_section = "captiveportal";
include("head.inc");

$main_buttons = array(
    array('href'=>'services_captiveportal_zones_edit.php', 'label'=>gettext("add a new captiveportal instance")),
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
				<?php if (is_subsystem_dirty('captiveportal')) :
?><p>
				<?php print_info_box_np(gettext("The CaptivePortal entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?>
				<?php
endif; ?>

			    <section class="col-xs-12">

					<div class="content-box">

				    <form action="services_captiveportal_zones.php" method="post" name="iform" id="iform">

							<div class="table-responsive">
								<table class="table table-striped table-sort">

									<tr>
									  <td width="15%" class="listhdrr"><?=gettext("Zone");?></td>
									  <td width="30%" class="listhdrr"><?=gettext("Interfaces");?></td>
									  <td width="10%" class="listhdrr"><?=gettext("Users");?></td>
									  <td width="35%" class="listhdrr"><?=gettext("Description");?></td>
									  <td width="10%" class="list">

									  </td>
									</tr>
                                            <?php foreach ($a_cp as $cpzone => $cpitem) :
                                                if (!is_array($cpitem)) {
                                                    continue;
                                                }
                                            ?>
									<tr>
									  <td class="listlr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									    <?=htmlspecialchars($cpitem['zone']);?>
									  </td>
									  <td class="listlr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									    <?php $cpifaces = explode(",", $cpitem['interface']);
                                        foreach ($cpifaces as $cpiface) {
                                            echo convert_friendly_interface_to_friendly_descr($cpiface) . " ";
                                        }
                                        ?>
									  </td>
									  <td class="listr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
                                            <?php
                                            $cpdb = new OPNsense\CaptivePortal\DB($cpzone) ;
                                            echo $cpdb->countClients() ;
                                            ?>
									  </td>
									  <td class="listbg" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									    <?=htmlspecialchars($cpitem['descr']);?>&nbsp;
									  </td>
									  <td valign="middle" class="list nowrap">
									    <a href="services_captiveportal.php?zone=<?=$cpzone?>" title="<?=gettext("edit captiveportal instance"); ?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
									    <a href="services_captiveportal_zones.php?act=del&amp;zone=<?=$cpzone;
?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");
?>')" title="<?=gettext("delete captiveportal instance");?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
									  </td>
									</tr>
                                            <?php
endforeach; ?>
									</table>
							</div>
				    </form>
					</div>
			    </section>
			</div>
		</div>
	</section>
<?php include("foot.inc");
