<?php

/*
 * Copyright (C) 2016-2021 Franco Fichtner <franco@opnsense.org>
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

function ntpd_enabled()
{
    global $config;

    return isset($config['system']['timeservers']);
}

function ntpd_services()
{
    global $config;

    $services = array();

    if (!ntpd_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'ntpd';
    $pconfig['description'] = gettext('Network Time Daemon');
    $pconfig['php']['restart'] = array('ntpd_configure_do');
    $pconfig['php']['start'] = array('ntpd_configure_do');
    $pconfig['pidfile'] = '/var/run/ntpd.pid';

    if (!empty($config['ntpd']['clientmode'])) {
        $pconfig['nocheck'] = true;
    }

    $services[] = $pconfig;

    return $services;
}

function ntpd_syslog()
{
    $logfacilities = array();

    $logfacilities['ntpd'] = array('facility' => array('ntp', 'ntpd', 'ntpdate'));

    return $logfacilities;
}

function ntpd_cron()
{
    global $config;

    $jobs = array();

    if (ntpd_enabled() && !empty($config['ntpd']['clientmode'])) {
        $jobs[]['autocron'] = array('pluginctl -s ntpd restart', '0', '2');
    }

    return $jobs;
}
function ntpd_configure()
{
    return array(
        'bootup' => array('ntpd_configure_do'),
        'local' => array('ntpd_configure_do'),
        'newwanip' => array('ntpd_configure_do'),
    );
}

function ntpd_configure_gps($serialport)
{
    global $config;
    $gps_device = '/dev/gps0';
    $serialport = '/dev/' . $serialport;

    if (!file_exists($serialport)) {
        return false;
    }

    // Create symlink that ntpd requires
    @unlink($gps_device);
    symlink($serialport, $gps_device);

    /* Send the following to the GPS port to initialize the GPS */
    if (isset($config['ntpd']['gps'])) {
        $gps_init = base64_decode($config['ntpd']['gps']['initcmd']);
    } else {
        $gps_init = base64_decode(
            'JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMC' .
            'wwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRH' .
            'LDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWC' .
            'w0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQ' .
            'VUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng' .
            '0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEs' .
            'MSwxLDEsMQ=='
        );
    }

    /* XXX: Why not file_put_contents to the device */
    @file_put_contents('/tmp/gps.init', $gps_init);
    `cat /tmp/gps.init > $serialport`;

    /* Add /etc/remote entry in case we need to read from the GPS with tip */
    if (intval(`grep -c '^gps0' /etc/remote`) == 0) {
        $gpsbaud = '4800';
        if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['speed'])) {
            switch ($config['ntpd']['gps']['speed']) {
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
            }
        }
        @file_put_contents("/etc/remote", "gps0:dv={$serialport}:br#{$gpsbaud}:pa=none:", FILE_APPEND);
    }

    return true;
}

function ntpd_configure_pps($serialport)
{
    $pps_device = '/dev/pps0';
    $serialport = "/dev/{$serialport}";

    if (!file_exists($serialport)) {
        return false;
    }

    // Create symlink that ntpd requires
    @unlink($pps_device);
    @symlink($serialport, $pps_device);

    return true;
}

function ntpd_configure_do($verbose = false)
{
    global $config;

    killbypid('/var/run/ntpd.pid', 'TERM', true);

    if (!ntpd_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting NTP service...';
        flush();
    }

    $driftfile = '/var/db/ntpd.drift';
    $statsdir = '/var/log/ntp';

    @mkdir($statsdir, 0755);

    config_read_array('ntpd');

    $ntpcfg = "#\n";
    $ntpcfg .= "# Autogenerated configuration file\n";
    $ntpcfg .= "#\n\n";
    $ntpcfg .= "tinker panic 0\n";

    /* Add Orphan mode */
    $ntpcfg .= "# Orphan mode stratum\n";
    $ntpcfg .= 'tos orphan ';
    if (!empty($config['ntpd']['orphan'])) {
        $ntpcfg .= $config['ntpd']['orphan'];
    } else {
        $ntpcfg .= '12';
    }
    $ntpcfg .= "\n";

    /* Add PPS configuration */
    if (
        !empty($config['ntpd']['pps'])
        && file_exists('/dev/' . $config['ntpd']['pps']['port'])
        && ntpd_configure_pps($config['ntpd']['pps']['port'])
    ) {
        $ntpcfg .= "\n";
        $ntpcfg .= "# PPS Setup\n";
        $ntpcfg .= 'server 127.127.22.0';
        $ntpcfg .= ' minpoll 4 maxpoll 4';
        if (empty($config['ntpd']['pps']['prefer'])) { /*note: this one works backwards */
            $ntpcfg .= ' prefer';
        }
        if (!empty($config['ntpd']['pps']['noselect'])) {
            $ntpcfg .= ' noselect ';
        }
        $ntpcfg .= "\n";
        $ntpcfg .= 'fudge 127.127.22.0';
        if (!empty($config['ntpd']['pps']['fudge1'])) {
            $ntpcfg .= ' time1 ';
            $ntpcfg .= $config['ntpd']['pps']['fudge1'];
        }
        if (!empty($config['ntpd']['pps']['flag2'])) {
            $ntpcfg .= ' flag2 1';
        }
        if (!empty($config['ntpd']['pps']['flag3'])) {
            $ntpcfg .= ' flag3 1';
        } else {
            $ntpcfg .= ' flag3 0';
        }
        if (!empty($config['ntpd']['pps']['flag4'])) {
            $ntpcfg .= ' flag4 1';
        }
        if (!empty($config['ntpd']['pps']['refid'])) {
            $ntpcfg .= ' refid ';
            $ntpcfg .= $config['ntpd']['pps']['refid'];
        }
        if (!empty($config['ntpd']['pps']['stratum'])) {
            $ntpcfg .= ' stratum ' . $config['ntpd']['pps']['stratum'];
        }
        $ntpcfg .= "\n";
    }
    /* End PPS configuration */

    /* Add GPS configuration */
    if (
        isset($config['ntpd']['gps']['port'])
        && file_exists('/dev/' . $config['ntpd']['gps']['port'])
        && ntpd_configure_gps($config['ntpd']['gps']['port'])
    ) {
        $ntpcfg .= "\n";
        $ntpcfg .= "# GPS Setup\n";
        $ntpcfg .= 'server 127.127.20.0 mode ';
        if (
            !empty($config['ntpd']['gps']['nmea']) ||
            !empty($config['ntpd']['gps']['speed']) ||
            !empty($config['ntpd']['gps']['subsec'])
        ) {
            if (!empty($config['ntpd']['gps']['nmea'])) {
                $ntpmode = (int) $config['ntpd']['gps']['nmea'];
            }
            if (!empty($config['ntpd']['gps']['speed'])) {
                $ntpmode += (int) $config['ntpd']['gps']['speed'];
            }
            if (!empty($config['ntpd']['gps']['subsec'])) {
                $ntpmode += 128;
            }
            $ntpcfg .= (string) $ntpmode;
        } else {
            $ntpcfg .= '0';
        }
        $ntpcfg .= ' minpoll 4 maxpoll 4';
        if (empty($config['ntpd']['gps']['prefer'])) { /*note: this one works backwards */
            $ntpcfg .= ' prefer';
        }
        if (!empty($config['ntpd']['gps']['noselect'])) {
            $ntpcfg .= ' noselect ';
        }
        $ntpcfg .= "\n";
        $ntpcfg .= 'fudge 127.127.20.0';
        if (!empty($config['ntpd']['gps']['fudge1'])) {
            $ntpcfg .= ' time1 ';
            $ntpcfg .= $config['ntpd']['gps']['fudge1'];
        }
        if (!empty($config['ntpd']['gps']['fudge2'])) {
            $ntpcfg .= ' time2 ';
            $ntpcfg .= $config['ntpd']['gps']['fudge2'];
        }
        if (!empty($config['ntpd']['gps']['flag1'])) {
            $ntpcfg .= ' flag1 1';
        } else {
            $ntpcfg .= ' flag1 0';
        }
        if (!empty($config['ntpd']['gps']['flag2'])) {
            $ntpcfg .= ' flag2 1';
        }
        if (!empty($config['ntpd']['gps']['flag3'])) {
            $ntpcfg .= ' flag3 1';
        } else {
            $ntpcfg .= ' flag3 0';
        }
        if (!empty($config['ntpd']['gps']['flag4'])) {
            $ntpcfg .= ' flag4 1';
        }
        if (!empty($config['ntpd']['gps']['refid'])) {
            $ntpcfg .= ' refid ';
            $ntpcfg .= $config['ntpd']['gps']['refid'];
        }
        if (!empty($config['ntpd']['gps']['stratum'])) {
            $ntpcfg .= ' stratum ' . $config['ntpd']['gps']['stratum'];
        }
        $ntpcfg .= "\n";
    }
    /* End GPS configuration */

    $noselect = isset($config['ntpd']['noselect']) ? explode(' ', $config['ntpd']['noselect']) : array();
    $prefer = isset($config['ntpd']['prefer']) ? explode(' ', $config['ntpd']['prefer']) : array();

    $ntpcfg .= "\n\n# Upstream Servers\n";
    /* foreach through ntp servers and write out to ntpd.conf */
    foreach (explode(' ', $config['system']['timeservers']) as $ts) {
        $ntpcfg .= "server {$ts} iburst maxpoll 9";
        if (in_array($ts, $prefer)) {
            $ntpcfg .= ' prefer';
        }
        if (in_array($ts, $noselect)) {
            $ntpcfg .= ' noselect';
        }
        $ntpcfg .= "\n";
    }
    unset($ts);

    $ntpcfg .= "\n\n";

    /* prevent NTP reflection attack, see https://ics-cert.us-cert.gov/advisories/ICSA-14-051-04 */
    $ntpcfg .= "disable monitor\n";

    if (
        !empty($config['ntpd']['clockstats']) ||
        !empty($config['ntpd']['loopstats']) ||
        !empty($config['ntpd']['peerstats'])
    ) {
        $ntpcfg .= "enable stats\n";
        $ntpcfg .= 'statistics';
        if (!empty($config['ntpd']['clockstats'])) {
            $ntpcfg .= ' clockstats';
        }
        if (!empty($config['ntpd']['loopstats'])) {
            $ntpcfg .= ' loopstats';
        }
        if (!empty($config['ntpd']['peerstats'])) {
            $ntpcfg .= ' peerstats';
        }
        $ntpcfg .= "\n";
    }
    $ntpcfg .= "statsdir {$statsdir}\n";
    $ntpcfg .= 'logconfig =syncall +clockall';
    if (!empty($config['ntpd']['logpeer'])) {
        $ntpcfg .= ' +peerall';
    }
    if (!empty($config['ntpd']['logsys'])) {
        $ntpcfg .= ' +sysall';
    }
    $ntpcfg .= "\n";
    $ntpcfg .= "driftfile {$driftfile}\n";
    /* Access restrictions */
    $ntpcfg .= 'restrict default';
    if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
        $ntpcfg .= ' kod limited';
    }
    if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
        $ntpcfg .= ' nomodify';
    }
    if (!empty($config['ntpd']['noquery'])) {
        $ntpcfg .= ' noquery';
    }
    if (empty($config['ntpd']['nopeer'])) { /*note: this one works backwards */
        $ntpcfg .= ' nopeer';
    }
    if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
        $ntpcfg .= ' notrap';
    }
    if (!empty($config['ntpd']['noserve'])) {
        $ntpcfg .= ' noserve';
    }
    $ntpcfg .= "\nrestrict -6 default";
    if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
        $ntpcfg .= ' kod limited';
    }
    if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
        $ntpcfg .= ' nomodify';
    }
    if (!empty($config['ntpd']['noquery'])) {
        $ntpcfg .= ' noquery';
    }
    if (empty($config['ntpd']['nopeer'])) { /*note: this one works backwards */
        $ntpcfg .= ' nopeer';
    }
    if (!empty($config['ntpd']['noserve'])) {
        $ntpcfg .= ' noserve';
    }
    if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
        $ntpcfg .= ' notrap';
    }
    $ntpcfg .= "\n";

    /* A leapseconds file is really only useful if this clock is stratum 1 */
    $ntpcfg .= "\n";
    if (!empty($config['ntpd']['leapsec'])) {
        $leapsec .= base64_decode($config['ntpd']['leapsec']);
        file_put_contents('/var/db/leap-seconds', $leapsec);
        $ntpcfg .= "leapfile /var/db/leap-seconds\n";
    }


    $interfaces = array();
    if (isset($config['ntpd']['interface'])) {
        $interfaces = explode(',', $config['ntpd']['interface']);
    }

    if (is_array($interfaces) && count($interfaces)) {
        $ntpcfg .= "interface ignore all\n";
        foreach ($interfaces as $interface) {
            if (!is_ipaddr($interface)) {
                $interface = get_real_interface($interface);
            }
            if (!empty($interface)) {
                $ntpcfg .= "interface listen {$interface}\n";
            }
        }
    }

    if (!empty($config['ntpd']['custom_options'])) {
        $ntpcfg .= "\n# custom options\n{$config['ntpd']['custom_options']}\n";
    }

    file_put_contents('/var/etc/ntpd.conf', $ntpcfg);

    /* if /var/empty does not exist, create it */
    @mkdir('/var/empty', 0775, true);

    if (empty($config['ntpd']['clientmode'])) {
        mwexecf(
            '/usr/local/sbin/ntpd -g -c %s -p %s',
            array('/var/etc/ntpd.conf', '/var/run/ntpd.pid')
        );
    } else {
        mwexecf_bg(
            '/usr/local/sbin/ntpd -q -g -c %s -p %s',
            array('/var/etc/ntpd.conf', '/var/run/ntpd.pid')
        );
    }

    if ($verbose) {
        echo "done.\n";
    }
}
