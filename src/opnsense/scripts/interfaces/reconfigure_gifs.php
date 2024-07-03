#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

require_once 'config.inc';
require_once 'interfaces.inc';
require_once 'util.inc';
require_once 'system.inc';

/* gather all relevant gif interfaces*/
$gifs_todo = [];
$vfilename = '/tmp/.gif.todo';
if (file_exists($vfilename) && filesize($vfilename) > 0) {
    $handle = fopen($vfilename, 'r+');
    if (flock($handle, LOCK_EX)) {
        fseek($handle, 0);
        foreach (explode("\n", fread($handle, filesize($vfilename))) as $line) {
            if (!isset($gifs_todo[$line]) && trim($line) != '') {
                $gifs_todo[$line] = [];
            }
        }
        fseek($handle, 0);
        ftruncate($handle, 0);
        flock($handle, LOCK_UN);
    }
}

/* collect changed gifs to reconfigure */
$gif_configure = [];
foreach ((new OPNsense\Interfaces\Gif())->gif->iterateItems() as $item) {
    $gif = [];
    foreach ($item->iterateItems() as $node) {
        $gif[$node->getInternalXMLTagName()] = (string)$node;
    }
    if (isset($gifs_todo[$gif['gifif']])) {
        $gif_configure[] = $gif;
        unset($gifs_todo[$gif['gifif']]);
    }
}

/* remove non existing interfaces */
foreach (array_keys($gifs_todo) as $gifif) {
    legacy_interface_destroy($gifif);
}

/*
    reconfigure still existing gifs,
    removal should happen first as it may free parent interfaces.
*/
$ifdetails = legacy_interfaces_details();
foreach ($gif_configure as $gif) {
    $reconfigure = false;
    if (isset($ifdetails[$gre['gifif']])) {
        /* when reconfiguring, we need to remove addresses (at least for IPv6) to prevent old ones left behind */
        $reconfigure = true;
        interfaces_addresses_flush($gre['gifif'], 4, $ifdetails);
        interfaces_addresses_flush($gre['gifif'], 6, $ifdetails);
    }
    _interfaces_gif_configure($gif);
    if ($reconfigure) {
        /* re-apply additional addresses and hook routing */
        interfaces_restart_by_device(false, [$gre['gifif']]);
    }
}
