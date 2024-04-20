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

function map_ifs($ifs, $data)
{
    $result = ["interfaces" => []];
    $temp = gettimeofday();
    $result['time'] = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;

    foreach ($ifs as $interfaceKey => $itf) {
        if (array_key_exists($itf['if'], $data)) {
            $result['interfaces'][$interfaceKey] = [
                "inbytes" => $data[$itf['if']]['bytes received'],
                "outbytes" => $data[$itf['if']]['bytes transmitted'],
                "inpkts" => $data[$itf['if']]['packets received'],
                "outpkts" => $data[$itf['if']]['packets transmitted'],
                "inerrs" => $data[$itf['if']]['input errors'],
                "outerrs" => $data[$itf['if']]['output errors'],
                "collisions" => $data[$itf['if']]['collisions'],
                "name" => !empty($itf['descr']) ? $itf['descr'] : $interfaceKey
            ];
        }
    }

    return $result;
}

if (isset($argv[1])) {
    $intfs = legacy_config_get_interfaces(["virtual" => false]);
    $prev = legacy_interface_stats();

    while (1) {
        $interfaces = $tmp = legacy_interface_stats();

        $keys = [
            'bytes received',
            'bytes transmitted',
            'packets received',
            'packets transmitted',
            'input errors',
            'output errors',
            'collisions'
        ];

        foreach ($intfs as $interfaceKey => $itf) {
            if (array_key_exists($itf['if'], $interfaces) && array_key_exists($itf['if'], $prev)) {
                foreach ($keys as $key) {
                    $tmp[$itf['if']][$key] -= $prev[$itf['if']][$key];
                }
            }
        }

        $result = map_ifs($intfs, $tmp);
        $prev = $interfaces;
        echo 'event: message' . PHP_EOL;
        echo 'data: ' . json_encode($result) . PHP_EOL . PHP_EOL;
        flush();
        sleep($argv[1] <= 1 ? 1 : $argv[1]);
    }
} else {
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
}
