#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
 * Copyright (C) 2023 Deciso B.V.
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

try {
    require_once("config.inc");
    require_once("util.inc");

    $filename = $argv[1] ?? null;
    $include_rrd = ($argv[2] ?? '') === 'rrd';
    if (empty($filename)) {
        echo json_encode(["status" => "failed", "message" => "No target filename provided"]);
        exit(1);
    }

    $data = file_get_contents('/conf/config.xml');

    if ($include_rrd) {
        require_once("rrd.inc");
        global $config;
        $rrd_data_xml = \rrd_export();
        $config = \parse_config();
        $data = str_replace("</opnsense>", $rrd_data_xml . "</opnsense>", $data);
    }

    if (file_put_contents($filename, $data) !== false) {
        chmod($filename, 0600);
        echo json_encode(["status" => "success", "filename" => $filename]);
    } else {
        echo json_encode(["status" => "failed", "message" => "Could not write to ".$filename]);
    }
} catch (\Throwable $e) {
    echo json_encode(["status" => "failed", "message" => "Fatal Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine()]);
    exit(0);
}
