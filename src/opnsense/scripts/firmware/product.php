#!/usr/local/bin/php
<?php

/*
 * Copyright (c) 2021 Franco Fichtner <franco@opnsense.org>
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

$metafile = '/usr/local/opnsense/version/core';

$ret = json_decode(@file_get_contents($metafile), true);
if ($ret != null) {
    $ret['product_crypto'] = trim(shell_exec('opnsense-version -f'));
    $ret['product_mirror'] = preg_replace('/\/[a-z0-9]{8}(-[a-z0-9]{4}){3}-[a-z0-9]{12}\//i', '/subscription-key/', trim(shell_exec('opnsense-update -M')));
    $ret['product_time'] = date('D M j G:i:s T Y', filemtime('/usr/local/opnsense/www/index.php'));
    $repos = explode("\n", trim(shell_exec('opnsense-verify -l')));
    sort($repos);
    $ret['product_repos'] = implode(', ', $repos);
    $ret['product_check'] = json_decode(@file_get_contents('/tmp/pkg_upgrade.json'), true);
    ksort($ret);
} else {
    $ret = [];
}

echo json_encode($ret, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
