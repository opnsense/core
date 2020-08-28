<?php

/*
 * Copyright (C) 2015-2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2011 Scott Ullrich <sullrich@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");

function upload_crash_report($files, $agent)
{
    $post = array();
    $counter = 0;

    foreach ($files as $filename) {
        if (is_link($filename) || $filename == '/var/crash/minfree.gz' || $filename == '/var/crash/bounds.gz'
            || filesize($filename) > 5 * 1024 * 1024) {
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

include('head.inc');

$plugins = implode(' ',  explode("\n", shell_exec('pkg info -g "os-*"')));

$crash_report_header = sprintf(
    "%s %s\n%s %s %s\n%sTime %s\n%s\nPHP %s\n",
    php_uname('v'),
    $g['product_arch'],
    $g['product_name'],
    $g['product_version'],
    $g['product_hash'],
    empty($plugins) ? '' : "Plugins $plugins\n",
    date('r'),
    trim(shell_exec('/usr/local/bin/openssl version')),
    PHP_VERSION
);

if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $crash_report_header = "User-Agent {$_SERVER['HTTP_USER_AGENT']}\n{$crash_report_header}";
}

$user_agent = "{$g['product_name']}/{$g['product_version']}";
$crash_reports = array();
$has_crashed = false;

$pconfig = array();
$pconfig['Email'] = isset($config['system']['contact_email']) ? $config['system']['contact_email'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if ($pconfig['Submit'] == 'yes') {
        if (!is_dir('/var/crash')) {
            mkdir('/var/crash', 0750, true);
        }
        $email = trim($pconfig['Email']);
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
        $desc = trim($pconfig['Desc']);
        if (!empty($desc)) {
            $crash_report_header .= "Description\n\n{$desc}";
        }
        $skip_files = array('.', '..', 'minfree', 'bounds', '');
        $crashes = glob('/var/crash/*');
        foreach ($crashes as $crash) {
            if (!in_array(basename($crash), $skip_files)) {
                $count++;
            }
        }
        if ($count || (!empty($desc) && !empty($email))) {
            file_put_contents('/var/crash/crashreport_header.txt', $crash_report_header);
            if (file_exists('/tmp/PHP_errors.log')) {
                // limit PHP_errors to send to 1MB
                exec('/usr/bin/tail -c 1048576 /tmp/PHP_errors.log > /var/crash/PHP_errors.log');
                @unlink('/tmp/PHP_errors.log');
            }
            @copy('/var/run/dmesg.boot', '/var/crash/dmesg.boot');
            exec('/usr/bin/gzip /var/crash/*');
            $files_to_upload = glob('/var/crash/*');
            upload_crash_report($files_to_upload, $user_agent);
            foreach ($files_to_upload as $file_to_upload) {
                @unlink($file_to_upload);
            }
        } else {
            /* still crashing ;) */
            $has_crashed = true;
        }
    } elseif ($pconfig['Submit'] == 'no') {
        $files_to_upload = glob('/var/crash/*');
        foreach ($files_to_upload as $file_to_upload) {
            @unlink($file_to_upload);
        }
        @unlink('/tmp/PHP_errors.log');
    } elseif ($pconfig['Submit'] == 'new') {
          /* force a crash report generation */
          $has_crashed = true;
    }
} else {
    /* if there is no user activity probe for a crash report */
    $has_crashed = get_crash_report(true) != '';
}

if ($has_crashed) {
    $crash_files = glob("/var/crash/*");
    $crash_reports['System Information'] = trim($crash_report_header);
    if (file_exists('/tmp/PHP_errors.log')) {
        $php_errors_size = @filesize('/tmp/PHP_errors.log');
        $max_php_errors_size = 1 * 1024 * 1024;
        // limit reporting for PHP_errors.log to $max_php_errors_size characters
        if ($php_errors_size > $max_php_errors_size) {
            // if file is to large, only display last $max_php_errors_size characters
            $php_errors .= @file_get_contents(
                          '/tmp/PHP_errors.log',
                          NULL,
                          NULL,
                          ($php_errors_size - $max_php_errors_size),
                          $max_php_errors_size
            );
        } else {
            $php_errors = @file_get_contents('/tmp/PHP_errors.log');
        }
        if (!empty($php_errors)) {
            $crash_reports['PHP Errors'] = trim($php_errors);
        }
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

$message = gettext('No issues were detected.');
if ($has_crashed) {
    $message = gettext('An issue was detected.');
}

if (isset($pconfig['Submit'])) {
    if ($pconfig['Submit'] == 'yes') {
        if (!$has_crashed) {
            $message = gettext('Thank you for submitting this crash report.');
        } else {
            $message = gettext('This crash report contains no actual crash information. If you want to submit a problem please fill out your e-mail and description below.');
        }
    } elseif ($pconfig['Submit'] == 'no') {
        $message = gettext('Please consider submitting a crash report if the error persists.');
    }
}

// escape form output before processing
legacy_html_escape_form_data($pconfig);

?>
<body>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box">
          <form method="post">
            <div class="col-xs-12">
<?php
            if ($has_crashed):?>
              <br/><button name="Submit" type="submit" class="btn btn-default pull-right" value="no"><?=gettext('Dismiss this report');?></button>
              <button name="Submit" type="submit" class="btn btn-primary pull-right" style="margin-right: 8px;" value="yes"><?=gettext('Submit this report');?></button>
              <p><strong><?= $message ?></strong></p>
              <p><?=gettext("Would you like to submit this crash report to the developers?");?></p>
              <hr><p><?=gettext('You can help us further by adding your contact information and a problem description. ' .
                  'Please note that providing your contact information greatly improves the chances of bugs being fixed.');?></p>
              <p><input type="text" placeholder="<?= html_safe(gettext('your@email.com')) ?>" name="Email" value="<?= $pconfig['Email'] ?>"></p>
              <p><textarea rows="5" placeholder="<?= html_safe(gettext('A short problem description or steps to reproduce.')) ?>" name="Desc"><?= $pconfig['Desc'] ?></textarea></p>
              <hr><p><?=gettext("Please double-check the following contents to ensure you are comfortable submitting the following information.");?></p>
<?php
              foreach ($crash_reports as $report => $content):?>
                  <p>
                    <?=$report;?>:<br/>
                    <pre><?=$content;?></pre>
                  </p>
<?php
              endforeach;
            else:?>

              <input type="hidden" name="Email" value="<?= $pconfig['Email'] ?>">
              <br/><button name="Submit" type="submit" class="btn btn-primary pull-right" value="new"><?=gettext('Report an issue');?></button>
              <p><strong><?=$message;?></strong></p><br/>
<?php
            endif;?>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
