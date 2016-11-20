<?php

/*
    Copyright (C) 2014-2016 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2010 Ermal Luci
    Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

function igmpproxy_enabled()
{
    global $config;

    return isset($config['igmpproxy']['igmpentry']);
}

function igmpproxy_services()
{
    $services = array();

    if (!igmpproxy_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'igmpproxy';
    $pconfig['description'] = gettext('IGMP Proxy');
    $pconfig['php']['restart'] = array('igmpproxy_configure_do');
    $pconfig['php']['start'] = array('igmpproxy_configure_do');
    $services[] = $pconfig;

    return $services;
}

function igmpproxy_configure()
{
    return array(
        'bootup' => array('igmpproxy_configure_do'),
        'interface' => array('igmpproxy_configure_do'),
    );
}

function igmpproxy_configure_do($verbose = false)
{
    global $config;

    killbyname('igmpproxy');

    if (!igmpproxy_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting IGMP Proxy...';
        flush();
    }

    $iflist = get_configured_interface_list();

    $igmpconf = <<<EOD

##------------------------------------------------------
## Enable Quickleave mode (Sends Leave instantly)
##------------------------------------------------------
quickleave

EOD;

    foreach ($config['igmpproxy']['igmpentry'] as $igmpcf) {
        unset($iflist[$igmpcf['ifname']]);
        $realif = get_real_interface($igmpcf['ifname']);
        if (empty($igmpcf['threshold'])) {
            $threshld = 1;
        } else {
            $threshld = $igmpcf['threshold'];
        }
        $igmpconf .= "phyint {$realif} {$igmpcf['type']} ratelimit 0 threshold {$threshld}\n";

        if ($igmpcf['address'] <> "") {
            $item = explode(" ", $igmpcf['address']);
            foreach($item as $iww) {
                $igmpconf .= "altnet {$iww}\n";
            }
        }
        $igmpconf .= "\n";
    }
    foreach ($iflist as $ifn) {
        $realif = get_real_interface($ifn);
        $igmpconf .= "phyint {$realif} disabled\n";
    }
    $igmpconf .= "\n";

    file_put_contents('/usr/local/etc/igmpproxy.conf', $igmpconf);
    mwexec('/usr/local/etc/rc.d/igmpproxy onestart');

    if ($verbose) {
        print "done.\n";
    }
}

