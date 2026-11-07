<?php

/*
 * Copyright (C) 2026 Greelan
 * Copyright (C) 2016-2024 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

function ntpd_services()
{
    $services = [];
    $mdl = new \OPNsense\Ntpd\Ntpd(true);

    if (!$mdl->isEnabled()) {
        return $services;
    }

    $pconfig = [];
    $pconfig['name'] = 'ntpd';
    $pconfig['description'] = gettext('Ntpd');
    $pconfig['php']['restart'] = ['ntpd_configure_do'];
    $pconfig['php']['start'] = ['ntpd_configure_do'];
    /* the pidfile for ntpd is unreliable - DO NOT USE IT */

    if ($mdl->clientmode->isEqual('1')) {
        $pconfig['nocheck'] = true;
    }

    $services[] = $pconfig;

    return $services;
}

function ntpd_syslog()
{
    $logfacilities = [];

    $logfacilities['ntpd'] = ['facility' => ['ntp', 'ntpd', 'ntpdate']];

    return $logfacilities;
}

function ntpd_cron()
{
    $jobs = [];
    $mdl = new \OPNsense\Ntpd\Ntpd(true);

    /* client mode exits after the sync, restart it daily */
    if ($mdl->isEnabled() && $mdl->clientmode->isEqual('1')) {
        $jobs[]['autocron'] = ['/usr/local/sbin/pluginctl -s ntpd restart', '0', '2'];
    }

    return $jobs;
}

function ntpd_configure()
{
    return [
        'bootup' => ['ntpd_configure_do'],
        'local' => ['ntpd_configure_do'],
        'newwanip' => ['ntpd_configure_do'],
        'ntpd' => ['ntpd_configure_do'],
    ];
}

function ntpd_configure_gps($gps)
{
    $port = (string)$gps->port;
    $gps_device = '/dev/gps0';
    $serialport = "/dev/{$port}";

    @unlink($gps_device);

    if (empty($port) || !preg_match('/^cua[a-z0-9.,{}]+$/i', $port)) {
        return false;
    } elseif (!file_exists($serialport) || filetype($serialport) != 'char') {
        return false;
    }

    symlink($serialport, $gps_device);

    $gps_init = (string)$gps->initcmd;
    if ($gps_init === '') {
        $gps_init = "\$PUBX,40,GSV,0,0,0,0*59\r\n\$PUBX,40,GLL,0,0,0,0*5C\r\n" .
                    "\$PUBX,40,ZDA,0,0,0,0*44\r\n\$PUBX,40,VTG,0,0,0,0*5E\r\n" .
                    "\$PUBX,40,GSV,0,0,0,0*59\r\n\$PUBX,40,GSA,0,0,0,0*4E\r\n" .
                    "\$PUBX,40,GGA,0,0,0,0\r\n\$PUBX,40,TXT,0,0,0,0\r\n" .
                    "\$PUBX,40,RMC,0,0,0,0*46\r\n\$PUBX,41,1,0007,0003,4800,0\r\n" .
                    "\$PUBX,40,ZDA,1,1,1,1";
    }

    /* NMEA sentences end in CR LF, a browser textarea only keeps LF */
    file_put_contents($serialport, preg_replace('/\r?\n/', "\r\n", rtrim($gps_init, "\r\n")) . "\r\n");

    /* XXX this is wrong as it only ever appends once with the given speed */
    if (shell_safe('grep -c "^gps0" /etc/remote') == '0') {
        switch ((string)$gps->speed) {
            case '16':
                $gpsbaud = '9600';
                break;
            case '32':
                $gpsbaud = '19200';
                break;
            case '48':
                $gpsbaud = '38400';
                break;
            case '64':
                $gpsbaud = '57600';
                break;
            case '80':
                $gpsbaud = '115200';
                break;
            default:
                $gpsbaud = '4800';
                break;
        }

        /* add /etc/remote entry in case we need to read from the GPS with tip */
        file_put_contents('/etc/remote', "gps0:dv={$serialport}:br#{$gpsbaud}:pa=none:", FILE_APPEND);
    }

    return true;
}

function ntpd_configure_pps($port)
{
    $pps_device = '/dev/pps0';
    $serialport = "/dev/{$port}";

    @unlink($pps_device);

    if (empty($port) || !preg_match('/^cua[a-z0-9.,{}]+$/i', $port)) {
        return false;
    } elseif (!file_exists($serialport) || filetype($serialport) != 'char') {
        return false;
    }

    @symlink($serialport, $pps_device);

    return true;
}

function ntpd_configure_do($verbose = false)
{
    killbyname('ntpd');

    $mdl = new \OPNsense\Ntpd\Ntpd(true);

    if (!$mdl->isEnabled()) {
        return;
    }

    service_log('Starting NTP service...', $verbose);

    $gps = $mdl->gps;
    $pps = $mdl->pps;

    $driftfile = '/var/db/ntpd.drift';
    $statsdir = '/var/log/ntp';

    @mkdir($statsdir, 0755);

    $ntpcfg = "#\n";
    $ntpcfg .= "# Autogenerated configuration file\n";
    $ntpcfg .= "#\n\n";
    $ntpcfg .= "tinker panic 0\n";

    /* Add Orphan mode */
    if ($mdl->orphan->isSet()) {
        $ntpcfg .= "# Orphan mode stratum\n";
        $ntpcfg .= 'tos orphan ' . $mdl->orphan->asInt() . "\n";
    }

    /* Add Max Clock Count */
    if ($mdl->maxclock->isSet()) {
        $ntpcfg .= "# Max number of associations\n";
        $ntpcfg .= 'tos maxclock ' . $mdl->maxclock->asInt() . "\n";
    }

    /* PPS configuration */
    if (ntpd_configure_pps((string)$pps->port)) {
        $ntpcfg .= "\n# PPS Setup\n";
        $ntpcfg .= 'server 127.127.22.0 minpoll 4 maxpoll 4 prefer';
        $ntpcfg .= "\nfudge 127.127.22.0";
        if ($pps->fudge1->isSet()) {
            $ntpcfg .= ' time1 ' . $pps->fudge1;
        }
        $ntpcfg .= $pps->flag2->isEqual('1') ? ' flag2 1' : '';
        $ntpcfg .= ' flag3 ' . ($pps->flag3->isEqual('1') ? '1' : '0');
        $ntpcfg .= $pps->flag4->isEqual('1') ? ' flag4 1' : '';
        if ($pps->refid->isSet()) {
            $ntpcfg .= ' refid ' . $pps->refid;
        }
        if ($pps->stratum->isSet()) {
            $ntpcfg .= ' stratum ' . $pps->stratum->asInt();
        }
        $ntpcfg .= "\n";
    }

    /* GPS configuration */
    if (ntpd_configure_gps($gps)) {
        $ntpcfg .= "\n# GPS Setup\n";
        /* mode combines the baud rate bits, the NMEA sentence bits and the subsecond bit */
        $ntpmode = $gps->speed->asInt();
        foreach ($gps->nmea->getValues() as $sentence) {
            $ntpmode |= (int)$sentence;
        }
        if ($gps->subsec->isEqual('1')) {
            $ntpmode |= 128;
        }
        $ntpcfg .= 'server 127.127.20.0 mode ' . $ntpmode . ' minpoll 4 maxpoll 4';
        if ($gps->prefer->isEqual('1')) {
            $ntpcfg .= ' prefer';
        }
        if ($gps->noselect->isEqual('1')) {
            $ntpcfg .= ' noselect';
        }
        $ntpcfg .= "\nfudge 127.127.20.0";
        if ($gps->fudge1->isSet()) {
            $ntpcfg .= ' time1 ' . $gps->fudge1;
        }
        if ($gps->fudge2->isSet()) {
            $ntpcfg .= ' time2 ' . $gps->fudge2;
        }
        $ntpcfg .= ' flag1 ' . ($gps->flag1->isEqual('1') ? '1' : '0');
        $ntpcfg .= $gps->flag2->isEqual('1') ? ' flag2 1' : '';
        $ntpcfg .= ' flag3 ' . ($gps->flag3->isEqual('1') ? '1' : '0');
        $ntpcfg .= $gps->flag4->isEqual('1') ? ' flag4 1' : '';
        if ($gps->refid->isSet()) {
            $ntpcfg .= ' refid ' . $gps->refid;
        }
        if ($gps->stratum->isSet()) {
            $ntpcfg .= ' stratum ' . $gps->stratum->asInt();
        }
        $ntpcfg .= "\n";
    }

    $ntpcfg .= "\n\n# Upstream Servers\n";
    foreach ($mdl->timeservers->server->iterateItems() as $srv) {
        /* do not allow newline injection */
        $host = str_replace(["\n", "\r"], '', (string)$srv->hostname);
        $is_pool = $srv->pool->isEqual('1') || preg_match('/\.pool\.ntp\.org$/', $host);
        $ntpcfg .= ($is_pool ? 'pool ' : 'server ') . $host;
        if ($srv->iburst->isEqual('1')) {
            $ntpcfg .= ' iburst';
        }
        $ntpcfg .= ' maxpoll 9';
        if ($srv->prefer->isEqual('1')) {
            $ntpcfg .= ' prefer';
        }
        if (!$srv->enabled->isEqual('1')) {
            $ntpcfg .= ' noselect';
        }
        $ntpcfg .= "\n";
    }

    $ntpcfg .= "\n\n";

    $statistics = [];
    foreach (['clockstats', 'loopstats', 'peerstats'] as $statistic) {
        if ($mdl->$statistic->isEqual('1')) {
            $statistics[] = $statistic;
        }
    }
    if (!empty($statistics)) {
        $ntpcfg .= "enable stats\n";
        $ntpcfg .= 'statistics ' . implode(' ', $statistics) . "\n";
    }
    $ntpcfg .= "statsdir {$statsdir}\n";
    $ntpcfg .= 'logconfig =syncall +clockall';
    if ($mdl->logpeer->isEqual('1')) {
        $ntpcfg .= ' +peerall';
    }
    if ($mdl->logsys->isEqual('1')) {
        $ntpcfg .= ' +sysall';
    }

    $ntpcfg .= "\ndriftfile {$driftfile}";

    /* Access restrictions, the model fields are named after the ntpd restrict flags */
    $ntpaccess = '';
    foreach (['kod', 'limited', 'nomodify', 'noquery', 'notrap', 'noserve'] as $restriction) {
        if ($mdl->$restriction->isEqual('1')) {
            $ntpaccess .= " {$restriction}";
        }
    }
    $ntpcfg .= "\nrestrict source {$ntpaccess}";
    if ($mdl->nopeer->isEqual('1')) {
        $ntpaccess .= ' nopeer';
    }
    $ntpcfg .= "\nrestrict default {$ntpaccess}";
    $ntpcfg .= "\nrestrict -6 default {$ntpaccess}";
    /* Do not break the status page with restrict noquery */
    $ntplocal = str_replace(' noquery', '', $ntpaccess);
    $ntpcfg .= "\nrestrict 127.0.0.1 {$ntplocal}";
    $ntpcfg .= "\nrestrict ::1 {$ntplocal}";
    $ntpcfg .= "\n";

    /* A leapseconds file is really only useful if this clock is stratum 1 */
    if ($mdl->leapsec->isSet()) {
        file_put_contents('/var/db/leap-seconds', (string)$mdl->leapsec);
        $ntpcfg .= "leapfile /var/db/leap-seconds\n";
    }

    /* only bind to given interfaces or IP aliases */
    if ($mdl->interface->isSet()) {
        $ntpifs = ['lo0'];
        $ntpaddrs = [];

        foreach ($mdl->interface->getValues() as $interface) {
            /* discard bad values here: literal addresses and _vip constructs */
            if (!is_ipaddr($interface) && strstr($interface, '_vip') === false) {
                $ntpifs[] = $interface;
            }
        }

        foreach (interfaces_addresses($ntpifs) as $tmpaddr => $info) {
            if (!$info['bind'] || ($info['family'] == 'inet6' && $info['tentative'])) {
                continue;
            }

            $ntpaddrs[] = $tmpaddr;
        }

        $ntpcfg .= "interface ignore all\n";
        $ntpcfg .= "interface ignore wildcard\n";

        foreach ($ntpaddrs as $addr) {
            $ntpcfg .= "interface listen {$addr}\n";
        }
    }

    file_put_contents('/var/etc/ntpd.conf', $ntpcfg);

    /* if /var/empty does not exist, create it */
    @mkdir('/var/empty', 0775, true);

    if (!$mdl->clientmode->isEqual('1')) {
        mwexecf('/usr/local/sbin/ntpd -g -c %s', ['/var/etc/ntpd.conf']);
    } else {
        mwexecfb('/usr/local/sbin/ntpd -q -g -c %s', ['/var/etc/ntpd.conf']);
    }

    service_log("done.\n", $verbose);
}

function ntpd_xmlrpc_sync()
{
    return [[
        'description' => gettext('Network Time'),
        'section' => 'ntpd,system.timeservers,OPNsense.Ntpd',
        'services' => ['ntpd'],
        'id' => 'ntpd',
    ]];
}
