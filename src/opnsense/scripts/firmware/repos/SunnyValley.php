#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2024 Hasan Ucak <hasan@sunnyvalley.io>
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

require_once 'util.inc';

$conf = '/usr/local/etc/pkg/repos/SunnyValley.conf';
$uuidPattern = '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/';

if (file_exists($conf . '.shadow') && file_exists('/usr/local/zenarmor/bin/eastpect')) {
    $node_uuid = shell_safe('/usr/local/zenarmor/bin/eastpect -s');
    if ($node_uuid != '') {
        $fileContents = file_get_contents($conf . '.shadow');
        if (strpos($fileContents, '/latest"') !== false) {
            $fileContents = str_replace('/latest"', '/' . $node_uuid . '"', $fileContents);
        } else {
            $fileContents = preg_replace("$uuidPattern", $node_uuid, $fileContents);
        }
        file_put_contents($conf, $fileContents);
    }
}
