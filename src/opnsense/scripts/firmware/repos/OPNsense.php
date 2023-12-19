#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Franco Fichtner <franco@opnsense.org>
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

require_once('util.inc');
require_once('script/load_phalcon.php');

use OPNsense\Core\Config;

$config = Config::getInstance()->object();

/* calculate the effective ABI */
$args = [ exec_safe('-A %s', shell_safe('opnsense-version -x')) ];
$url_sub = '';

if (!empty($config->system->firmware->subscription)) {
    /*
     * Append the url now that it is not in the mirror anymore.
     * This only ever works if the mirror is set to a non-default.
     */
    $url_sub = '/' . $config->system->firmware->subscription;
} else {
    /* clear the license file when no subscription key is set */
    @unlink('/usr/local/opnsense/version/core.license');
}

if (!empty($config->system->firmware->mirror)) {
    $args[] = exec_safe('-m %s', str_replace('/', '\/', $config->system->firmware->mirror . $url_sub));
}

if (!empty($config->system->firmware->flavour)) {
    $args[] = exec_safe('-n %s', str_replace('/', '\/', (string)$config->system->firmware->flavour));
}

/* rewrite the config via the defaults and possible arguments */
shell_safe('/usr/local/sbin/opnsense-update -sd ' . join(' ', $args));
