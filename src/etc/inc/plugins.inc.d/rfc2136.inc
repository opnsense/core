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

function rfc2136_configure()
{
    return array(
        'interface' => array('rfc2136_configure_do:2'),
    );
}

function rfc2136_configure_do($verbose = false, $int = '', $updatehost = '', $forced = false)
{
    global $config;

    if (!isset($config['dnsupdates']['dnsupdate'])) {
        return;
    }

    $notify_text = '';

    foreach ($config['dnsupdates']['dnsupdate'] as $i => $dnsupdate) {
        if (!isset($dnsupdate['enable'])) {
            continue;
        } elseif (!empty($int) && $int != $dnsupdate['interface']) {
            continue;
        } elseif (!empty($updatehost) && ($updatehost != $dnsupdate['host'])) {
            continue;
        }

        $if = get_real_interface($dnsupdate['interface']);

        if (isset($dnsupdate['usepublicip'])) {
            $wanip = dyndnsCheckIP($dnsupdate['interface']);
        } else {
            $wanip = get_interface_ip($dnsupdate['interface']);
        }

        $wanipv6 = get_interface_ipv6($dnsupdate['interface']);
        $cacheFile = "/conf/dyndns_{$dnsupdate['interface']}_rfc2136_" . escapeshellarg($dnsupdate['host']) . "_{$dnsupdate['server']}.cache";
        $currentTime = time();

        if ($wanip || $wanipv6) {
            $keyname = $dnsupdate['keyname'];
            /* trailing dot */
            if (substr($keyname, -1) != ".") {
                $keyname .= ".";
            }

            $hostname = $dnsupdate['host'];
            /* trailing dot */
            if (substr($hostname, -1) != ".") {
                $hostname .= ".";
            }

            /* write private key file
               this is dumb - public and private keys are the same for HMAC-MD5,
               but nsupdate insists on having both */
            $fd = fopen("/var/etc/K{$i}{$keyname}+157+00000.private", "w");
            $privkey = <<<EOD
Private-key-format: v1.2
Algorithm: 157 (HMAC)
Key: {$dnsupdate['keydata']}

EOD;
            fwrite($fd, $privkey);
            fclose($fd);

            /* write public key file */
            if ($dnsupdate['keytype'] == "zone") {
                $flags = 257;
                $proto = 3;
            } elseif ($dnsupdate['keytype'] == "host") {
                $flags = 513;
                $proto = 3;
            } elseif ($dnsupdate['keytype'] == "user") {
                $flags = 0;
                $proto = 2;
            }

            $fd = fopen("/var/etc/K{$i}{$keyname}+157+00000.key", "w");
            fwrite($fd, "{$keyname} IN KEY {$flags} {$proto} 157 {$dnsupdate['keydata']}\n");
            fclose($fd);

            /* generate update instructions */
            $upinst = "";
            if (!empty($dnsupdate['server'])) {
                $upinst .= "server {$dnsupdate['server']}\n";
            }

            if (file_exists($cacheFile)) {
                list($cachedipv4, $cacheTimev4) = explode("|", file_get_contents($cacheFile));
            }
            if (file_exists("{$cacheFile}.ipv6")) {
                list($cachedipv6, $cacheTimev6) = explode("|", file_get_contents("{$cacheFile}.ipv6"));
            }

            // 25 Days
            $maxCacheAgeSecs = 25 * 24 * 60 * 60;
            $need_update = false;

            /* Update IPv4 if we have it. */
            if (is_ipaddrv4($wanip) && (empty($dnsupdate['recordtype']) || $dnsupdate['recordtype'] == 'A')) {
                if (($wanip != $cachedipv4) || (($currentTime - $cacheTimev4) > $maxCacheAgeSecs) || $forced) {
                    $upinst .= "update delete {$dnsupdate['host']}. A\n";
                    $upinst .= "update add {$dnsupdate['host']}. {$dnsupdate['ttl']} A {$wanip}\n";
                    $notify_text .= sprintf(gettext('Dynamic DNS updated IP Address (A) for %s on %s (%s) to %s'), $dnsupdate['host'], convert_real_interface_to_friendly_descr($if), $if, $wanip) . "\n";
                    @file_put_contents($cacheFile, "{$wanip}|{$currentTime}");
                    log_error("Dynamic DNS: updating cache file {$cacheFile}: {$wanip}");
                    $need_update = true;
                } else {
                    log_error("Dynamic DNS: Not updating {$dnsupdate['host']} A record because the IP address has not changed.");
                }
            } else {
                @unlink($cacheFile);
            }

            /* Update IPv6 if we have it. */
            if (is_ipaddrv6($wanipv6) && (empty($dnsupdate['recordtype']) || $dnsupdate['recordtype'] == 'AAAA')) {
                if (($wanipv6 != $cachedipv6) || (($currentTime - $cacheTimev6) > $maxCacheAgeSecs) || $forced) {
                    $upinst .= "update delete {$dnsupdate['host']}. AAAA\n";
                    $upinst .= "update add {$dnsupdate['host']}. {$dnsupdate['ttl']} AAAA {$wanipv6}\n";
                    $notify_text .= sprintf(gettext('Dynamic DNS updated IPv6 Address (AAAA) for %s on %s (%s) to %s'), $dnsupdate['host'], convert_real_interface_to_friendly_descr($if), $if, $wanipv6) . "\n";
                    @file_put_contents("{$cacheFile}.ipv6", "{$wanipv6}|{$currentTime}");
                    log_error("Dynamic DNS: updating cache file {$cacheFile}.ipv6: {$wanipv6}");
                    $need_update = true;
                } else {
                    log_error("Dynamic DNS: Not updating {$dnsupdate['host']} AAAA record because the IPv6 address has not changed.");
                }
            } else {
                @unlink("{$cacheFile}.ipv6");
            }

            $upinst .= "\n";  /* mind that trailing newline! */

            if ($need_update) {
                @file_put_contents("/var/etc/nsupdatecmds{$i}", $upinst);
                unset($upinst);
                /* invoke nsupdate */
                $cmd = "/usr/local/bin/nsupdate -k /var/etc/K{$i}{$keyname}+157+00000.key";
                if (isset($dnsupdate['usetcp']))
                  $cmd .= " -v";
                $cmd .= " /var/etc/nsupdatecmds{$i}";
                mwexec_bg($cmd);
                unset($cmd);
            }
        }
    }

    if (!empty($notify_text)) {
        notify_all_remote($notify_text);
    }
}
