<?php

/*
    Copyright (C) 2014-2017 Franco Fichtner <franco@opnsense.org>
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

require_once('plugins.inc.d/dyndns/phpdns.inc');
require_once('plugins.inc.d/dyndns/r53.inc');

function dyndns_configure()
{
    return array(
        'bootup' => array('dyndns_configure_do'),
        'local' => array('dyndns_configure_do'),
        'interface' => array('dyndns_configure_do:2'),
    );
}

function dyndns_list()
{
    return array(
      'citynetwork' => 'City Network',
      'cloudflare' => 'CloudFlare',
      'custom' => 'Custom',
      'custom-v6' => 'Custom (v6)',
      'dhs' => 'DHS',
      'dnsexit' => 'DNSexit',
      'dnsomatic' => 'DNS-O-Matic',
      'duckdns' => 'Duck DNS',
      'dyndns' => 'DynDNS (dynamic)',
      'dyndns-static' => 'DynDNS (static)',
      'dyndns-custom' => 'DynDNS (custom)',
      'dyns' => 'DyNS',
      'easydns' => 'easyDNS',
      'eurodns' => 'EuroDNS',
      'freedns' => 'freeDNS',
      'gratisdns' => 'GratisDNS',
      'googledomains' => 'Google Domains',
      'he-net' => 'HE.net',
      'he-net-v6' => 'HE.net (v6)',
      'he-net-tunnelbroker' => 'HE.net Tunnelbroker',
      'loopia' => 'Loopia',
      'namecheap' => 'Namecheap',
      'noip' => 'No-IP',
      'noip-free' => 'No-IP (free)',
      'ods' => 'ODS.org',
      'opendns' => 'OpenDNS',
      'ovh-dynhost' => 'OVH DynHOST',
      'route53' => 'Route 53',
      'selfhost' => 'SelfHost',
      'zoneedit' => 'ZoneEdit',
    );
}

function dyndns_configure_client($conf)
{
    if (!isset($conf['enable'])) {
        return;
    }

    $dns = new updatedns($dnsService = $conf['type'],
        $dnsHost = $conf['host'],
        $dnsUser = $conf['username'],
        $dnsPass = $conf['password'],
        $dnsWilcard = $conf['wildcard'],
        $dnsMX = $conf['mx'],
        $dnsIf = "{$conf['interface']}",
        $dnsBackMX = NULL,
        $dnsServer = NULL,
        $dnsPort = NULL,
        $dnsUpdateURL = "{$conf['updateurl']}",
        $forceUpdate = $conf['force'],
        $dnsZoneID=$conf['zoneid'],
        $dnsTTL=$conf['ttl'],
        $dnsResultMatch = "{$conf['resultmatch']}",
        $dnsRequestIf = "{$conf['requestif']}",
        $dnsID = "{$conf['id']}",
        $dnsVerboseLog = $conf['verboselog'],
        $curlIpresolveV4 = $conf['curl_ipresolve_v4'],
        $curlSslVerifypeer = $conf['curl_ssl_verifypeer']
    );
}

function dyndns_configure_do($verbose = false, $int = '')
{
    global $config;

    if (!isset($config['dyndnses']['dyndns'])) {
        return;
    }

    $dyndnscfg = $config['dyndnses']['dyndns'];
    $gwgroups = return_gateway_groups_array();
    if (!is_array($dyndnscfg)) {
        return;
    }

    if ($verbose) {
        echo 'Configuring dynamic DNS clients...';
        flush();
    }

    foreach ($dyndnscfg as $dyndns) {
        if ((empty($int)) || ($int == $dyndns['interface']) || (is_array($gwgroups[$dyndns['interface']]))) {
            $dyndns['verboselog'] = isset($dyndns['verboselog']);
            $dyndns['curl_ipresolve_v4'] = isset($dyndns['curl_ipresolve_v4']);
            $dyndns['curl_ssl_verifypeer'] = isset($dyndns['curl_ssl_verifypeer']);
            dyndns_configure_client($dyndns);
            sleep(1);
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}
