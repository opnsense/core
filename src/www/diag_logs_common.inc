<?php

/*
    Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

if (!isset($logsplit)) {
    $logsplit = 3;
}

function print_dump($logarr)
{
    global $config, $logsplit;

    if (!is_array($logarr)) {
        echo "<tr>\n";
        echo "<td colspan=\"2\">" . htmlspecialchars($logarr) . "</td>\n";
        echo "</tr>\n";
        return;
    }

    foreach ($logarr as $logent) {
        $logent = preg_split('/\s+/', $logent);
        if (count($logent) < $logsplit + 1) {
            continue;
        }
        echo "<tr>\n";
        $entry_date_time = html_safe(join(' ', array_slice($logent, 0, $logsplit)));
        $entry_text = '';
        $hostname = $logent[$logsplit];
        if ($hostname != $config['system']['hostname']) {
            $entry_text = html_safe($hostname) . ' ';
        }
        $entry_text .= html_safe(join(' ', array_slice($logent, $logsplit + 1)));
        echo "<td class=\"text-nowrap\">{$entry_date_time}</td>\n";
        echo "<td>{$entry_text}</td>\n";
        echo "</tr>\n";
    }
}

function dump_clog($logfile, $tail, $grepfor = '')
{
    global $config;

    $sor = isset($config['syslog']['reverse']) ? '-r' : '';
    $logarr = array();
    $grepline = '';

    $grepfor = preg_split('/\s+/', trim($grepfor));

    foreach ($grepfor as $pattern) {
        $grepline .= ' | /usr/bin/egrep -i ' . escapeshellarg($pattern);
    }

    if (is_dir($logfile)) {
        $logarr = sprintf(gettext('File %s is a directory.'), $logfile);
    } elseif (!file_exists($logfile)) {
        $logarr = sprintf(gettext('File %s doesn\'t exist.'), $logfile);
    } else {
        exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " {$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
    }

    if (is_array($logarr) && !count($logarr)) {
        $logarr = sprintf(gettext('File %s yielded no results.'), $logfile);
    }

    print_dump($logarr);
}

function dump_log($logfile, $tail, $grepfor = '')
{
    global $config;

    $sor = isset($config['syslog']['reverse']) ? '-r' : '';
    $logarr = array();
    $grepline = '';

    $grepfor = preg_split('/\s+/', trim($grepfor));

    foreach ($grepfor as $pattern) {
        $grepline .= ' | /usr/bin/egrep -i ' . escapeshellarg($pattern);
    }

    if (is_dir($logfile)) {
        $logarr = sprintf(gettext('File %s is a directory.'), $logfile);
    } elseif (!file_exists($logfile)) {
        $logarr = sprintf(gettext('File %s doesn\'t exist.'), $logfile);
    } else {
        exec("cat " . escapeshellarg($logfile) . " {$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
    }

    if (is_array($logarr) && !count($logarr)) {
        $logarr = sprintf(gettext('File %s yielded no results.'), $logfile);
    }

    print_dump($logarr);
}
