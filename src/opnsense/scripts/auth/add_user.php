#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2021 Deciso B.V.
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
require_once('legacy_bindings.inc');
use OPNsense\Core\Config;
use OPNsense\Auth\User;

$opts = getopt('hu:o', array(), $optind);
$args = array_slice($argv, $optind);

if (isset($opts['h']) || empty($opts['u'])) {
    echo "Usage: add_user.php [-h] \n";
    echo "\t-h show this help text and exit\n";
    echo "\t-u [required] username\n";
    echo "\t-o origin (default=automation)";
    exit(-1);
} else {
    Config::getInstance()->lock();
    $input_errors = [];
    $usermdl = new User();
    $user = $usermdl->user->Add();
    $user->name = $opts['u'];
    $user->scope = !empty($opts['o']) ? $opts['o'] : 'automation';

    /* generate a random password */
    $password = random_bytes(50);
    while (($i = strpos($password, "\0")) !== false) {
        $password[$i] = random_bytes(1);
    }
    $hash = $usermdl->generatePasswordHash($password);
    if ($hash !== false && strpos($hash, '$') === 0) {
        /* model validation won't pass when no password is offered */
        $user->password = $hash;
    }

    $valMsgs = $usermdl->performValidation();
    foreach ($valMsgs as $field => $msg) {
        if (strpos($msg->getField(), $user->__reference) !== false) {
            $input_errors[] = $msg->getMessage();
        }
    }
    if (empty($input_errors)) {
        if ($usermdl->serializeToConfig(false, true)) {
            Config::getInstance()->save();
        }
        configdp_run('auth user changed', [$userent['name']]);
        echo json_encode(["status" => "ok", "uid" => (string)$user->uid, "name" => (string)$user->name]);
    } else {
        echo json_encode(["status" => "failed", "messages" => $input_errors]);
        Config::getInstance()->unlock();
    }
}
