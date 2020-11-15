#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016-2020 Deciso B.V.
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
require_once("interfaces.inc");
require_once("config.inc");

$result = array("interfaces" => array());
$interfaces = legacy_interface_stats();
$temp = gettimeofday();
$result['time'] = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;
// collect user friendly interface names
foreach (legacy_config_get_interfaces(array("virtual" => false)) as $interfaceKey => $itf) {
    if (array_key_exists($itf['if'], $interfaces)) {
        $result['interfaces'][$interfaceKey] = $interfaces[$itf['if']];
        $result['interfaces'][$interfaceKey]['name'] = !empty($itf['descr']) ? $itf['descr'] : $interfaceKey;
    }
}

echo json_encode($result);
