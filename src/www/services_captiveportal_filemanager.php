<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2005-2006 Jonathan De Graeve (jonathan.de.graeve@imelda.be)
	and Paul Taylor (paultaylor@winn-dixie.com).
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

function cpelementscmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function cpelements_sort() {
        global $config, $cpzone;

        usort($config['captiveportal'][$cpzone]['element'],"cpelementscmp");
}

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

$cpzone = $_GET['zone'];
if (isset($_POST['zone']))
        $cpzone = $_POST['zone'];

if (empty($cpzone)) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

if (!is_array($config['captiveportal']))
        $config['captiveportal'] = array();
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"),gettext("Captive portal"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal";

if (!is_array($a_cp[$cpzone]['element']))
	$a_cp[$cpzone]['element'] = array();
$a_element =& $a_cp[$cpzone]['element'];

// Calculate total size of all files
$total_size = 0;
foreach ($a_element as $element) {
	$total_size += $element['size'];
}

if ($_POST) {
    unset($input_errors);

    if (is_uploaded_file($_FILES['new']['tmp_name'])) {

	if(!stristr($_FILES['new']['name'], "captiveportal-"))
		$name = "captiveportal-" . $_FILES['new']['name'];
	else
		$name = $_FILES['new']['name'];
	$size = filesize($_FILES['new']['tmp_name']);

	// is there already a file with that name?
	foreach ($a_element as $element) {
			if ($element['name'] == $name) {
				$input_errors[] = sprintf(gettext("A file with the name '%s' already exists."), $name);
				break;
			}
		}

		// check total file size
		if (($total_size + $size) > $g['captiveportal_element_sizelimit']) {
			$input_errors[] = gettext("The total size of all files uploaded may not exceed ") .
				format_bytes($g['captiveportal_element_sizelimit']) . ".";
		}

		if (!$input_errors) {
			$element = array();
			$element['name'] = $name;
			$element['size'] = $size;
			$element['content'] = base64_encode(file_get_contents($_FILES['new']['tmp_name']));

			$a_element[] = $element;
			cpelements_sort();

			write_config();
			captiveportal_write_elements();
			header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
			exit;
		}
    }
} else if (($_GET['act'] == "del") && !empty($cpzone) && $a_element[$_GET['id']]) {
	@unlink("{$g['captiveportal_element_path']}/" . $a_element[$_GET['id']]['name']);
	@unlink("{$g['captiveportal_path']}/" . $a_element[$_GET['id']]['name']);
	unset($a_element[$_GET['id']]);
	write_config();
	header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
	exit;
}

include("head.inc");

$main_buttons = array(
	array('label'=>gettext('add file'), 'href'=>'services_captiveportal_filemanager.php?zone='.$cpzone.'&act=add'),
);


?>


<body>
	<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if ($input_errors) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<?php
						$tab_array = array();
						$tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
						$tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
						$tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
						// Hide Allowed Hostnames as this feature is currently not supported
						// $tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
						$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
						$tab_array[] = array(gettext("File Manager"), true, "services_captiveportal_filemanager.php?zone={$cpzone}");
						display_top_tabs($tab_array, true);
					?>

					<div class="tab-content content-box col-xs-12">

					<div class="container-fluid">

		                    <form action="services_captiveportal_filemanager.php" method="post" name="iform" id="iform" enctype="multipart/form-data">
		                        <input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />


								  <?php if ($_GET['act'] == 'add'): ?>
								   <div class="table-responsive">
			                        <table class="table table-striped table-sort">
									  <tr>
											<td width="10%">Upload file</td>
											<td class="listlr" colspan="2"><input type="file" name="new" class="formfld file" size="40" id="new" /></td>
									  </tr>
									  <tr>
										  <td></td>
										  <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Upload"); ?>" />
											<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone;?>" class="btn btn-default">Cancel</a>

										  </td>
									  </tr>
			                        </table>
								   </div>
								   <br/>

								  <?php endif; ?>



		                        <div class="table-responsive">
			                        <table class="table table-striped table-sort">
								      <tr>
								        <td width="70%" class="listhdrr"><?=gettext("Name"); ?></td>
								        <td width="20%" class="listhdr"><?=gettext("Size"); ?></td>
								        <td width="10%" class="list">

									</td>
								      </tr>
								<?php if (is_array($a_cp[$cpzone]['element'])):
									$i = 0; foreach ($a_cp[$cpzone]['element'] as $element): ?>
									  <tr>
										<td class="listlr"><?=htmlspecialchars($element['name']);?></td>
										<td class="listr" align="right"><?=format_bytes($element['size']);?></td>
										<td valign="middle" class="list nowrap">
										<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone;?>&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this file?"); ?>')">
											<span class="glyphicon glyphicon-remove" title="<?=gettext("delete file"); ?>"></span></a>
										</td>
									  </tr>
								  <?php $i++; endforeach; endif; ?>

								  <?php if ($total_size > 0): ?>
									  <tr>
										<td class="listlr" style="background-color: #eee"><strong><?=gettext("TOTAL"); ?></strong></td>
										<td class="listr" style="background-color: #eee" align="right"><strong><?=format_bytes($total_size);?></strong></td>
										<td valign="middle" style="background-color: #eee" class="list nowrap"></td>
									  </tr>
								  <?php endif; ?>

								</table>
		                        </div>

								<span class="vexpl"><span class="text-danger"><strong>
								<?=gettext("Note:"); ?><br />
								</strong></span>
								<?=gettext("Any files that you upload here with the filename prefix of captiveportal- will " .
								"be made available in the root directory of the captive portal HTTP(S) server. " .
								"You may reference them directly from your portal page HTML code using relative paths. " .
								"Example: you've uploaded an image with the name 'captiveportal-test.jpg' using the " .
								"file manager. Then you can include it in your portal page like this:"); ?><br /><br />
								<tt>&lt;img src=&quot;captiveportal-test.jpg&quot; width=... height=...&gt;</tt>
								<br /><br />
								<?=gettext("In addition, you can also upload .php files for execution.  You can pass the filename " .
								"to your custom page from the initial page by using text similar to:"); ?>
								<br /><br />
								<tt>&lt;a href="/captiveportal-aup.php?zone=$PORTAL_ZONE$&amp;redirurl=$PORTAL_REDIRURL$"&gt;<?=gettext("Acceptable usage policy"); ?>&lt;/a&gt;</tt>
								<br /><br />
								<?php printf(gettext("The total size limit for all files is %s."), format_bytes($g['captiveportal_element_sizelimit']));?></span>

		                    </form>
					</div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
