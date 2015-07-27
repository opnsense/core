<?php

/*
	Copyright (C) 2015 Franco Fichtner <franco@opnsense.org>
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2011 Scott Ullrich
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

define("FILE_SIZE", 450000);

function upload_crash_report($files)
{
	global $g;

	$post = array();
	$counter = 0;

	foreach($files as $filename) {
		$post["file{$counter}"] = curl_file_create($filename, "application/x-gzip", basename($filename));
		$counter++;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://crash.opnsense.org/');
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_VERBOSE, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible;)');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data;' ) );
	$response = curl_exec($ch);
	curl_close($ch);

	return !$response;
}

$pgtitle = array(gettext("Diagnostics"),gettext("Crash Reporter"));
include('head.inc');

$last_version = '/usr/local/opnsense/version/opnsense.last';
$crash_report_header = sprintf(
	"System Information:\n%s\n%s %s%s (%s)\n%s\n",
	php_uname('v'),
	$g['product_name'],
	trim(file_get_contents('/usr/local/opnsense/version/opnsense')),
	file_exists($last_version) ? sprintf(' [%s]', trim(file_get_contents($last_version))) : '',
	php_uname('m'),
	exec('/usr/local/bin/openssl version')
);

?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">
                <div class="content-box">
					 <form action="crash_reporter.php" method="post">
						 <div class="col-xs-12">


<?php
	if (isset($_POST['Submit']) && $_POST['Submit'] == 'yes') {
		echo '<p>' . gettext('Processing...');
		flush();
		if (!is_dir('/var/crash')) {
			mkdir('/var/crash', 0750, true);
		}
		file_put_contents('/var/crash/crashreport_header.txt', $crash_report_header);
		@rename('/tmp/PHP_errors.log', '/var/crash/PHP_errors.log');
		exec('/usr/bin/gzip /var/crash/*');
		$files_to_upload = glob('/var/crash/*');
		echo gettext('ok') . '<br/>' . gettext('Uploading...');
		flush();
		$resp = upload_crash_report($files_to_upload);
		echo ($resp ? gettext('ok') : gettext('failed')) . '</p>';
		array_map('unlink', $files_to_upload);
	} elseif (isset($_POST['Submit']) && $_POST['Submit'] == 'no') {
		array_map('unlink', glob('/var/crash/*'));
		@unlink('/tmp/PHP_errors.log');
	}

	if (get_crash_report(true) == '') {
		echo '<p><strong>';
		if (isset($_POST['Submit']) && $_POST['Submit'] == 'yes') {
			echo gettext('Thank you for submitting this crash report.');
		} elseif ($_POST['Submit'] == 'no') {
			echo gettext('Please consider submitting a crash report if the error persists.');
		} else {
			echo gettext('Luckily we have not detected a programming bug.');
		}
		echo '</strong></p>';
	} else {
		$crash_files = glob("/var/crash/*");
		$crash_reports = $crash_report_header;
		$php_errors = @file_get_contents('/tmp/PHP_errors.log');
		if (!empty($php_errors)) {
			$crash_reports .= "\nPHP Errors:\n";
			$crash_reports .= $php_errors;
		}
		foreach ($crash_files as $cf) {
			if (filesize($cf) < FILE_SIZE) {
				$crash_reports .= "\nFilename: {$cf}\n";
				$crash_reports .= file_get_contents($cf);
			}
		}
		echo "<p><strong>" . gettext("Unfortunately we have detected at least one programming bug.") . "</strong></p>";
		echo "<p><br/>" . sprintf(gettext("Would you like to submit this crash report to the %s developers?"), $g['product_name']) . "</p>";
		echo "<p><button name=\"Submit\" type=\"submit\" class=\"btn btn-primary\" value=\"yes\">" . gettext('Yes') . "</button> ";
		echo "<button name=\"Submit\" type=\"submit\" class=\"btn btn-primary\" value=\"no\">" . gettext('No') . "</button></p>";
		echo "<p><br/><i>" . gettext("Please-double check the contents to ensure you are comfortable submitting the following information:") . "</i></p>";
		echo "<textarea readonly=\"readonly\" style=\"max-width: none;\" rows=\"24\" cols=\"80\" name=\"crashreports\">{$crash_reports}</textarea></p>";
	}
?>
						 </div>
					</form>
                </div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
