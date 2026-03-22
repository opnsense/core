#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2014-2025 Deciso B.V.
 * Copyright (C) 2013 Dagorlad
 * Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
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

use OPNsense\Core\Config;
use OPNsense\Core\Shell;

$ntpq_servers = [];
$result = [];
$server = [];

foreach (array_slice(Shell::shell_safe('/usr/local/sbin/ntpq -pnw', [], true), 2) as $line) {
    if (empty($server['status'])) {
        $server['status'] = substr($line, 0, 1);
    }
    $line = substr($line, 1);
    $peerinfo = preg_split('/\s+/', $line);
    if (empty($server['server'])) {
        $server['server'] = $peerinfo[0];
    }
    if (empty($peerinfo[1])) {
        // newline in ntpq output
        continue;
    }
    $server['refid'] = $peerinfo[1];
    $server['stratum'] = $peerinfo[2];
    $server['type'] = $peerinfo[3];
    $server['when'] = $peerinfo[4];
    $server['poll'] = $peerinfo[5];
    $server['reach'] = $peerinfo[6];
    $server['delay'] = $peerinfo[7];
    $server['offset'] = $peerinfo[8];
    $server['jitter'] = $peerinfo[9];

    if ($server['type'] === 'p') {
        $server['status'] = '__pool';
    }

    $ntpq_servers[] = $server;
    $server = [];
}

$result['ntpq_servers'] = $ntpq_servers;
$result['gps'] = [];

function nmeaGeoParts(?string $val, ?string $dir): ?array
{
    if ($val === null || $dir === null) {
        return null;
    }
    if (!preg_match('/^\d+(\.\d+)?$/', $val)) {
        return null;
    }

    [$int, $frac] = array_pad(explode('.', $val, 2), 2, '0');
    $degStr = substr($int, 0, max(strlen($int) - 2, 0));
    $minStr = substr($int, -2) . '.' . $frac;

    $deg  = ($degStr === '' ? 0 : (int)$degStr);
    $min  = (float)$minStr;
    $dir  = strtoupper($dir);
    $sign = ($dir === 'S' || $dir === 'W') ? -1 : 1;

    $dec  = $sign * ($deg + $min / 60.0);

    return ['dec' => $dec, 'deg' => $deg, 'min' => $min, 'dir' => $dir];
}

foreach (Shell::shell_safe('/usr/local/sbin/ntpq -c clockvar 2> /dev/null', [], true) as $line) {
    if (strncmp($line, "timecode=", 9) !== 0) {
        continue;
    }
    if (!preg_match('/"([^"]+)"/', $line, $m)) {
        continue;
    }

    $vars = explode(',', $m[1]);
    $type = $vars[0] ?? '';
    $gps  = ['sentence' => $type];

    switch ($type) {
        case '$GPRMC': {
            $gps['ok'] = (($vars[2] ?? '') === 'A');

            $lat = nmeaGeoParts($vars[3] ?? null, $vars[4] ?? null);
            $lon = nmeaGeoParts($vars[5] ?? null, $vars[6] ?? null);
            break;
        }
        case '$GPGGA': {
            $gps['ok']       = $vars[6]  ?? null;
            $gps['alt']      = $vars[9]  ?? null;
            $gps['alt_unit'] = $vars[10] ?? null;
            $gps['sat']      = $vars[7]  ?? null;

            $lat = nmeaGeoParts($vars[2] ?? null, $vars[3] ?? null);
            $lon = nmeaGeoParts($vars[4] ?? null, $vars[5] ?? null);
            break;
        }
        case '$GPGLL': {
            $gps['ok'] = (($vars[6] ?? '') === 'A');

            $lat = nmeaGeoParts($vars[1] ?? null, $vars[2] ?? null);
            $lon = nmeaGeoParts($vars[3] ?? null, $vars[4] ?? null);
            break;
        }
        default:
            $lat = $lon = null;
    }

    // Merge parsed lat/lon parts if present
    if ($lat) {
        $gps += [
            'lat'     => $lat['dec'],
            'lat_deg' => $lat['deg'],
            'lat_min' => $lat['min'],
            'lat_dir' => $lat['dir'],
        ];
    }
    if ($lon) {
        $gps += [
            'lon'     => $lon['dec'],
            'lon_deg' => $lon['deg'],
            'lon_min' => $lon['min'],
            'lon_dir' => $lon['dir'],
        ];
    }

    $gps = array_filter($gps, static fn($v) => $v !== null && $v !== '');
    $result['gps'] = array_replace($result['gps'], $gps);
}

$cfg = Config::getInstance()->object();

if (!empty($cfg->ntpd->gps->type) && (string)$cfg->ntpd->gps->type == 'SureGPS' && isset($result['gps']['ok'])) {
    // GSV message is only enabled by init commands in services_ntpd_gps.php for SureGPS board
    $gpsport = fopen("/dev/gps0", "r+");
    while ($gpsport) {
        $buffer = fgets($gpsport);
        if (substr($buffer, 0, 6) == '$GPGSV') {
            $gpgsv = explode(',', $buffer);
            $result['gps']['gps_satview'] = $gpgsv[3];
            break;
        }
    }
}

echo json_encode($result);
