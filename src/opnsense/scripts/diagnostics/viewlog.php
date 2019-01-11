#!/usr/local/bin/php
<?php
/**
 *    Copyright (C) 2019 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

require_once 'config.inc';

if ($argc < 3) {
    echo 'Too few arguments!' . PHP_EOL;
    exit(1);
}

$logfile = $argv[1];
$type = $argv[2];
$filter = trim($argv[3]) ?? '';

doFileChecks($logfile);

$numlines = intval($config['syslog']['nentries'] ?? 50);
$tailParam = isset($config['syslog']['reverse']) ? '-r' : '';
$logarr = [];
$grepline = getGrepLine($filter);

if ($type == 'clog') {
    exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " {$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$tailParam} -n " . escapeshellarg($numlines), $logarr);
} else {
    exec("cat " . escapeshellarg($logfile) . " {$grepline} | /usr/bin/tail {$tailParam} -n " . escapeshellarg($numlines), $logarr);
}

$result = checkResult($logarr, $logfile);

echo json_encode($result) . PHP_EOL;
exit(0);

function doFileChecks($logfile)
{
    if (is_dir($logfile)) {
        echo json_encode(['status' => sprintf(gettext('File %s is a directory.'), $logfile)]) . PHP_EOL;
        exit(1);
    }
    if (!file_exists($logfile)) {
        echo json_encode(['status' => sprintf(gettext('File %s does not exist.'), $logfile)]) . PHP_EOL;
        exit(1);
    }
}

function getGrepLine(string $filter): string
{
    $grepline = '';
    $grepfor = preg_split('/\s+/', $filter);

    foreach ($grepfor as $pattern) {
        $grepline .= ' | /usr/bin/egrep -i ' . escapeshellarg($pattern);
    }
    return $grepline;
}

function checkResult($logarr, string $logfile): array
{
    if (!is_array($logarr)) {
        return [
            'status' => sprintf(gettext('Unknown error retrieving file %s.'), $logfile),
        ];
    }
    elseif (count($logarr) === 0) {
        return [
            'status' => sprintf(gettext('File %s yielded no results.'), $logfile),
        ];
    }
    else
    {
        return [
            'status' => 'ok',
            'logLines' => $logarr,
        ];
    }
}
