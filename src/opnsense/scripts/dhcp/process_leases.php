#!/usr/local/bin/php
<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

require_once 'config.inc';
require_once 'interfaces.inc';
require_once 'services.inc';
require_once 'util.inc';

if ($argc < 2) {
    echo 'Too few parameters!';
    exit(1);
}

$type = $argv[1];
$showAll = $argv[2] ?? false;

$leasesfile = services_dhcpd_leasesfile();
$leases = [];
$pools = [];
$arpdata_ip = [];
$arpdata_mac = [];

if ($leasesHandle = @fopen($leasesfile, 'r')) {

    $arpData = null;
    exec('/usr/sbin/arp -an', $arpData);

    foreach ($arpData as $line) {
        $words = explode(' ', $line);
        if ($words[3] != '(incomplete)') {
            $arpdata_ip[] = trim(str_replace(['(', ')'], '', $words[1]));
            $arpdata_mac[] = strtolower(trim($words[3]));
        }
    }

    $currentLease = '';
    $currentPool = '';
    while (($line = fgets($leasesHandle, 4096)) !== false) {
        // Remove leading and trailing whitespace, and trailing semicolons.
        $words = explode(' ', trim($line, " \t\n\r\0\x0B;"));
        if ($words[0] === 'lease') {
            $currentLease = $words[1];
            $leases[$currentLease] = ['ip' => $currentLease, 'type' => 'dynamic'];
        } elseif ($words[0] === 'failover') {
            $currentPool = trim($words[2], '"');
            $pools[$currentPool] = [
                'name' => "$currentPool (" . convert_friendly_interface_to_friendly_descr($currentPool) . ")",
            ];
        } elseif ($words[0] === '}') {
            $currentLease = '';
            $currentPool = '';
        }

        if ($currentLease !== '') {
            switch ($words[0]) {
                case 'starts':
                    // Ignore number after 'starts', copy rest of line.
                    $leases[$currentLease]['start'] = adjust_utc(implode(' ', array_slice($words, 2)));
                    break;
                case 'ends':
                    // Ignore number after 'ends', copy rest of line.
                    $leases[$currentLease]['end'] = adjust_utc(implode(' ', array_slice($words, 2)));
                    break;
                case 'binding':
                    switch ($words[2]) {
                        case 'active':
                            $leases[$currentLease]['act'] = 'active';
                            break;
                        case 'free':
                            $leases[$currentLease]['act'] = 'expired';
                            $leases[$currentLease]['online'] = 'offline';
                            break;
                        case 'backup':
                            $leases[$currentLease]['act'] = 'reserved';
                            $leases[$currentLease]['online'] = 'offline';
                            break;
                    }
                    break;
                case "hardware":
                    $leases[$currentLease]['mac'] = $words[2];
                    /* check if it's online and the lease is active */
                    if (in_array($currentLease, $arpdata_ip)) {
                        $leases[$currentLease]['online'] = 'online';
                    } else {
                        $leases[$currentLease]['online'] = 'offline';
                    }
                    break;
                case "client-hostname":
                    if ($words[1] !== '') {
                        $leases[$currentLease]['hostname'] = str_replace('"', '', $words[1]);
                    } else {
                        $leases[$currentLease]['hostname'] = gethostbyaddr($currentLease);
                    }
                    break;
                case 'tstp':
                case 'tsfp':
                case 'atsfp':
                case 'cltt':
                case 'next':
                case 'rewind':
                case 'uid':
                default:
                    // Ignore
            }
        }
        if ($currentPool !== '') {
            switch ($words[0]) {
                case 'my':
                    $pools[$currentPool]['mystate'] = $words[2];
                    $pools[$currentPool]['mydate'] = adjust_utc(implode(' ', array_slice($words, 4)));
                    break;
                case 'peer':
                    $pools[$currentPool]['peerstate'] = $words[2];
                    $pools[$currentPool]['peerdate'] = adjust_utc(implode(' ', array_slice($words, 4)));
                    break;
            }
        }
    }
    if ($type === 'leases' && !$showAll) {
        $leases = array_filter($leases, function ($lease) {
            return ($lease['act'] == 'active' || $lease['act'] == 'static');
        });
    }

    fclose($leasesHandle);
}

if ($type === 'pools') {
    echo json_encode($pools);
    exit (0);
}

foreach (array_keys(legacy_config_get_interfaces(['virtual' => false])) as $ifname) {
    if (isset($config['dhcpd'][$ifname]['staticmap'])) {
        foreach ($config['dhcpd'][$ifname]['staticmap'] as $static) {
            $slease = [];
            $slease['ip'] = $static['ipaddr'];
            $slease['encodedIp'] = $static['ipaddr'];
            $slease['type'] = 'static';
            $slease['mac'] = $static['mac'];
            $slease['start'] = '';
            $slease['end'] = '';
            $slease['hostname'] = htmlentities($static['hostname']);
            $slease['descr'] = htmlentities($static['descr']);
            $slease['act'] = 'static';
            $slease['online'] = in_array(strtolower($slease['mac']), $arpdata_mac) ? 'online' : 'offline';
            $leases[] = $slease;
        }
    }
}

$interfaces = legacy_config_get_interfaces();
$staticEntries = [];
$ranges = [];
if (isset($config['dhcpd'])) {
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (isset($dhcpifconf['staticmap']) && is_array($dhcpifconf['staticmap'])) {
            foreach ($dhcpifconf['staticmap'] as $staticent) {
                $staticEntries[$staticent['ipaddr']] = [
                    'if' => $dhcpif,
                    'if_friendly' => htmlspecialchars($interfaces[$dhcpif]['descr']),
                ];
            }
        }

        if (!empty($dhcpifconf['range']) && !empty($dhcpifconf["enable"])) {
            $ranges[] = [
                'from' => ip2ulong($dhcpifconf['range']['from']),
                'to' => ip2ulong($dhcpifconf['range']['to']),
                'data' => [
                    'if' => $dhcpif,
                    'if_friendly' => htmlspecialchars($interfaces[$dhcpif]['descr']),
                ]
            ];
        }
    }
}

$backend = new OPNsense\Core\Backend();
$mac_man = json_decode($backend->configdRun("interface list macdb json"), true);
array_walk($leases, function (&$lease) use ($mac_man, $ranges, $staticEntries) {
    if ($lease['act'] == 'static') {
        if (array_key_exists($lease['ip'], $staticEntries)) {
            $lease = array_merge($lease, $staticEntries[$lease['ip']]);
        }
    } else {
        $lip = ip2ulong($lease['ip']);
        foreach ($ranges as $range) {
            if ($lip >= $range['from'] && $lip <= $range['to']) {
                $lease = array_merge($lease, $range['data']);
                break;
            }

        }
    }

    $lease['encodedIp'] = urlencode($lease['ip']);
    $lease['manu'] = getManufacturer($lease['mac'], $mac_man);
});

function getManufacturer($macAddress, $macTable)
{
    $macHighBytes = substr(strtoupper(str_replace(':', '', $macAddress)), 0, 6);
    return $macTable[$macHighBytes] ?? '';
}

function adjust_utc($dt)
{
    if (trim($dt) === '') {
        return $dt;
    }

    foreach (config_read_array('dhcpd') as $dhcpd) {
        if (!empty($dhcpd['dhcpleaseinlocaltime'])) {
            /* we want local time, so specify this is actually UTC */
            return strftime('%Y/%m/%d %H:%M:%S', strtotime("{$dt} UTC"));
        }
    }

    /* lease time is in UTC, here just pretend it's the correct time */
    return strftime('%Y/%m/%d %H:%M:%S UTC', strtotime($dt));
}

echo json_encode($leases);
exit(0);
