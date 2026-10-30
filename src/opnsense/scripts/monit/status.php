#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

require_once('script/load_phalcon.php');

use OPNsense\Monit\Monit;

$result = [
    'response' => null,
    'http_code' => 0,
    'error' => null,
];
$format = $argv[1] ?? '';

if (!in_array($format, ['xml', 'text'], true)) {
    $result['error'] = gettext('Unsupported Monit status format.');
    echo json_encode($result) . PHP_EOL;
    exit(1);
}

$curl = curl_init('http://127.0.0.1/_status?format=' . $format);
curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, '/var/run/monit.sock');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$model = new Monit(false);
$username = $model->general->httpdUsername->getValue();
$password = $model->general->httpdPassword->getValue();
if (trim($username) !== '' && trim($password) !== '') {
    curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);
}

$response = curl_exec($curl);
$result['http_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
if ($response === false) {
    $result['error'] = curl_error($curl);
} else {
    $result['response'] = $response;
}

echo json_encode($result) . PHP_EOL;
