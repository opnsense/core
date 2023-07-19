#!/usr/local/bin/php
<?php

/*
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

require_once 'config.inc';
require_once 'filter.inc';
require_once 'interfaces.inc';
require_once 'util.inc';

/* gather all relevant lagg interfaces*/
$laggs_todo = [];
$vfilename = '/tmp/.lagg.todo';
if (file_exists($vfilename) && filesize($vfilename) > 0) {
    $handle = fopen($vfilename, 'r+');
    if (flock($handle, LOCK_EX)) {
        fseek($handle, 0);
        foreach (explode("\n", fread($handle, filesize($vfilename))) as $line) {
            if (!isset($laggs_todo[$line]) && trim($line) != '') {
                $laggs_todo[$line] = [];
            }
        }
        fseek($handle, 0);
        ftruncate($handle, 0);
        flock($handle, LOCK_UN);
    }
}

/* collect changed laggs to reconfigure */
$lagg_configure = [];
foreach ((new OPNsense\Interfaces\Lagg())->lagg->iterateItems() as $item) {
    $lagg = [];
    foreach ($item->iterateItems() as $node) {
        $lagg[$node->getInternalXMLTagName()] = (string)$node;
    }
    if (isset($laggs_todo[$lagg['laggif']])) {
        $lagg_configure[] = $lagg;
        unset($laggs_todo[$lagg['laggif']]);
    }
}

/* remove non existing interfaces */
foreach (array_keys($laggs_todo) as $laggif) {
    legacy_interface_destroy($laggif);
}

/*
    reconfigure still existing laggs,
    removal should happen first as it may free parent interfaces.
*/
foreach ($lagg_configure as $lagg) {
    _interfaces_lagg_configure($lagg);
}
