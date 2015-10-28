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

function upload_crash_report($files, $agent)
{
    global $g;

    $post = array();
    $counter = 0;

    foreach ($files as $filename) {
        if (is_link($filename) || $filename == '/var/crash/minfree.gz' || $filename == '/var/crash/bounds.gz') {
            continue;
        }
        $post["file{$counter}"] = curl_file_create($filename, "application/x-gzip", basename($filename));
        $counter++;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://crash.opnsense.org/');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data;' ));
    $response = curl_exec($ch);
    curl_close($ch);

    return !$response;
}

$pgtitle = array(gettext('System'), gettext('Crash Reporter'));
include('head.inc');

$last_version = '/usr/local/opnsense/version/opnsense.last';
$crash_report_header = sprintf(
    "%s\n%s %s%s %s (%s)\nUUID %s\n",
    php_uname('v'),
    $g['product_name'],
    trim(file_get_contents('/usr/local/opnsense/version/opnsense')),
    file_exists($last_version) ? sprintf(' [%s]', trim(file_get_contents($last_version))) : '',
    trim(shell_exec('/usr/local/bin/openssl version')),
    php_uname('m'),
    shell_exec('/sbin/sysctl -b kern.hostuuid')
);

if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $crash_report_header .= "User Agent {$_SERVER['HTTP_USER_AGENT']}\n";
}

$pkgver = explode('-', trim(file_get_contents('/usr/local/opnsense/version/opnsense')));
$user_agent = $g['product_name'] . '/' . $pkgver[0];
$crash_reports = array();
$has_crashed = false;

if (isset($_POST['Submit'])) {
    if ($_POST['Submit'] == 'yes') {
        if (!is_dir('/var/crash')) {
            mkdir('/var/crash', 0750, true);
        }
        $email = trim($_POST['Email']);
        if (!empty($email)) {
            $crash_report_header .= "Email {$email}\n";
            if (!isset($config['system']['contact_email']) ||
                $config['system']['contact_email'] !== $email) {
                $config['system']['contact_email'] = $email;
                write_config('Updated crash reporter contact email.');
            }
        } elseif (isset($config['system']['contact_email'])) {
            unset($config['system']['contact_email']);
            write_config('Removed crash reporter contact email.');
        }
        $desc = trim($_POST['Desc']);
        if (!empty($desc)) {
            $crash_report_header .= "Description\n\n{$desc}";
        }
        file_put_contents('/var/crash/crashreport_header.txt', $crash_report_header);
        @rename('/tmp/PHP_errors.log', '/var/crash/PHP_errors.log');
        @copy('/var/run/dmesg.boot', '/var/crash/dmesg.boot');
        exec('/usr/bin/gzip /var/crash/*');
        $files_to_upload = glob('/var/crash/*');
        upload_crash_report($files_to_upload, $user_agent);
        foreach ($files_to_upload as $file_to_upload) {
            @unlink($file_to_upload);
	}
    } elseif ($_POST['Submit'] == 'no') {
        $files_to_upload = glob('/var/crash/*');
        foreach ($files_to_upload as $file_to_upload) {
            @unlink($file_to_upload);
        }
        @unlink('/tmp/PHP_errors.log');
    } elseif ($_POST['Submit'] == 'new') {
        /* force a crash report generation */
        $has_crashed = true;
    }
} else {
    /* if there is no user activity probe for a crash report */
    $has_crashed = get_crash_report(true) != '';
}

$email = isset($config['system']['contact_email']) ? $config['system']['contact_email'] : '';

if ($has_crashed) {
    $crash_files = glob("/var/crash/*");
    $crash_reports['System Information'] = trim($crash_report_header);
    $php_errors = @file_get_contents('/tmp/PHP_errors.log');
    if (!empty($php_errors)) {
        $crash_reports['PHP Errors'] = trim($php_errors);
    }
    $dmesg_boot = @file_get_contents('/var/run/dmesg.boot');
    if (!empty($dmesg_boot)) {
        $crash_reports['dmesg.boot'] = trim($dmesg_boot);
    }
    foreach ($crash_files as $cf) {
        if (!is_link($cf) && $cf != '/var/crash/minfree' && $cf != '/var/crash/bounds' && filesize($cf) < 450000) {
            $crash_reports[$cf] = trim(file_get_contents($cf));
        }
    }
}

?>

<body>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <div class="content-box">
                    <form action="/crash_reporter.php" method="post">
                        <div class="col-xs-12">

<?php

if ($has_crashed) {
    echo "<br/><button name=\"Submit\" type=\"submit\" class=\"btn btn-default pull-right\" value=\"no\">" . gettext('Dismiss this report') . "</button>";
    echo "<button name=\"Submit\" type=\"submit\" class=\"btn btn-primary pull-right\" style=\"margin-right: 8px;\" value=\"yes\">" . gettext('Submit this report') . "</button>";
    echo "<p><strong>" . gettext("Unfortunately we have detected at least one programming bug.") . "</strong></p>";
    echo "<p>" . gettext("Would you like to submit this crash report to the developers?") . "</p>";
    echo '<hr><p>' . gettext('You can help us further by adding your contact information and a problem description. ' .
        'Please note that providing your contact information greatly improves the chances of bugs being fixed.') . '</p>';
    echo sprintf('<p><input type="text" placeholder="%s" name="Email" value="%s"></p>', gettext('your@email.com'), $email);
    echo sprintf('<p><textarea rows="5" placeholder="%s" name="Desc"></textarea></p>', gettext('A short problem description or steps to reproduce.'));
    echo "<hr><p>" . gettext("Please double-check the following contents to ensure you are comfortable submitting the following information.") . "</p>";
    foreach ($crash_reports as $report => $content) {
        echo "<p>{$report}:<br/><pre>{$content}</pre></p>";
    }
} else {
    $message = gettext('Luckily we have not detected a programming bug.');
    if (isset($_POST['Submit'])) {
        if ($_POST['Submit'] == 'yes') {
            $message = gettext('Thank you for submitting this crash report.');
        } elseif ($_POST['Submit'] == 'no') {
            $message = gettext('Please consider submitting a crash report if the error persists.');
        }
    }

    echo '<br/><button name="Submit" type="submit" class="btn btn-primary pull-right" value="new">' . gettext('Report an issue') . '</button>';
    echo '<p><strong>' . $message . '</strong></p><br/>';
}

?>

                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include("foot.inc");
