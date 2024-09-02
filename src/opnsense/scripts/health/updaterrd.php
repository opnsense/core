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

/* loader : add library path in this directory to our normal path */
require_once('/usr/local/opnsense/mvc/app/library/OPNsense/Autoload/Loader.php');
$phalcon_config = include('/usr/local/opnsense/mvc/app/config/config.php');

(new OPNsense\Autoload\Loader([
    $phalcon_config->application->modelsDir,
    $phalcon_config->application->libraryDir,
    __DIR__ . '/library/'])
)->register();
/* end loader */

$opts = getopt('hd', [], $optind);
$args = array_slice($argv, $optind);

if (isset($opts['h'])) {
    echo "Usage: updaterrd.php [-h] [-d]\n\n";
    echo "\t-d debug mode, output errors to stdout\n";
    exit(0);
}

$rrdcnf = OPNsense\Core\Config::getInstance()->object()->rrd;
if ($rrdcnf === null || !isset($rrdcnf->enable)) {
    echo "RRD statistics disabled... exit\n";
    exit(0);
}

$start_time = microtime(true);

if (!is_dir('/var/db/rrd')) {
    @mkdir('/var/db/rrd', 0775);
    @chown('/var/db/rrd', 'nobody');
}

$rrd_factory = new \OPNsense\RRD\Factory();
$rrd_factory->collect()->updateAll(isset($opts['d']));

if (isset($opts['d'])) {
    $collect_time = 0.0;
    echo sprintf("total runtime [seconds] \t: %0.2f\n", microtime(true) - $start_time);
    foreach ($rrd_factory->getRawStats() as $name => $payload) {
        $collect_time += $payload['runtime'];
    }
    echo sprintf("total collection [seconds] \t: %0.2f\n", $collect_time);
}
