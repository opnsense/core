<?php

/*
 * Coypright (C) 2016 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2008 Shrew Soft Inc
 * Copyright (C) 2008 Ermal Luçi
 * Copyright (C) 2004 Scott Ullrich
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

function if_l2tp_configure()
{
    return array('if_l2tp_configure_do');
}

function if_l2tp_services()
{
    global $config;

    $services = array();

    if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == 'server') {
        $services[] = array(
            'description' => gettext('L2TP Server'),
            'pidfile' => '/var/run/l2tp-vpn.pid',
            'php' => array(
                'restart' => array('if_l2tp_configure_do'),
                'start' => array('if_l2tp_configure_do'),
            ),
            'name' => 'l2tpd',
        );
    }

    return $services;
}

/**
 * request syslog facilities for this plugin
 * @return array
 */
function if_l2tp_syslog()
{
    $logfacilities = array();

    $logfacilities['l2tps'] = array('facility' => array('l2tps'), 'remote' => null);

    return $logfacilities;
}

function if_l2tp_link_scripts($rootdir, $logtype = 'l2tp')
{
    $up = <<<'EOD'
#!/bin/sh

/usr/bin/logger -p local3.info "login,%s,$4,$5"

EOD;
    $down = <<<'EOD'
#!/bin/sh

/usr/bin/logger -p local3.info "logout,%s,$4,$5"

/sbin/pfctl -i $1 -Fs
/sbin/pfctl -K $4/32

EOD;

    file_put_contents($rootdir . '/linkup', sprintf($up, $logtype));
    file_put_contents($rootdir . '/linkdown', sprintf($down, $logtype));

    chmod($rootdir . '/linkup', 0755);
    chmod($rootdir . '/linkdown', 0755);
}

function if_l2tp_configure_do()
{
    global $config;

    killbypid('/var/run/l2tp-vpn.pid', 'TERM', true);
    mwexec('rm -rf /var/etc/l2tp-vpn');

    $syscfg = $config['system'];
    if (isset($config['l2tp'])) {
        $l2tpcfg = $config['l2tp'];
    } else {
        return 0;
    }

    if (!isset($l2tpcfg['mode']) || $l2tpcfg['mode'] != 'server') {
        return 0;
    }

    if (file_exists('/var/run/booting')) {
        echo gettext('Configuring L2TP VPN service...');
    }

    switch ($l2tpcfg['mode']) {
        case 'server':

            mkdir('/var/etc/l2tp-vpn');
            if_l2tp_link_scripts('/var/etc/l2tp-vpn');

            $fd = fopen("/var/etc/l2tp-vpn/mpd.conf", "w");
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.conf in if_l2tp_configure().") . "\n");
                return 1;
            }

            $iprange = $l2tpcfg['remoteip'] . ' ';
            $iprange .= long2ip32(ip2long($l2tpcfg['remoteip']) + $l2tpcfg['n_l2tp_units'] - 1);

            $iptype = "ippool pool1";
            if (isset($l2tpcfg['radius']['enable']) && isset($l2tpcfg['radius']['radiusissueips'])) {
                $iptype = "0.0.0.0/0";
            }

            $mpdconf = <<<EOD
startup:

l2tps:
  set ippool add pool1 {$iprange}

  create bundle template B
  set iface disable on-demand
  set iface enable proxy-arp
  set iface up-script /var/etc/l2tp-vpn/linkup
  set iface down-script /var/etc/l2tp-vpn/linkdown
  set ipcp ranges {$l2tpcfg['localip']}/32 {$iptype}
  set ipcp yes vjcomp

EOD;

            if (is_ipaddr($l2tpcfg['wins'])) {
                $mpdconf .= "  set ipcp nbns {$l2tpcfg['wins']}\n";
            }
            if (is_ipaddr($l2tpcfg['dns1'])) {
                $mpdconf .= "  set ipcp dns " . $l2tpcfg['dns1'];
                if (is_ipaddr($l2tpcfg['dns2'])) {
                    $mpdconf .= " " . $l2tpcfg['dns2'];
                }
                $mpdconf .= "\n";
            } elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
                $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
                if (isset($syscfg['dnsserver'][0])) {
                    $mpdconf .= " " . $syscfg['dnsserver'][0];
                }
                $mpdconf .= "\n";
            } elseif (isset($syscfg['dnsserver'][0])) {
                $mpdconf .= "  set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
            }

            if ($l2tpcfg['paporchap'] == "chap") {
                $paporchap = "set link enable chap";
            } else {
                $paporchap = "set link enable pap";
            }

            $mpdconf .= <<<EOD

  set bundle enable crypt-reqd
  set bundle enable compression
  set ccp yes mppc

  create link template L l2tp
  set link action bundle B
  set link enable multilink
  set link yes acfcomp protocomp
  set link no pap chap eap
  {$paporchap}
  set link keep-alive 10 60
  set link mtu 1460
  set l2tp self ${l2tpcfg['localip']}
  set link enable incoming

EOD;

            if (!empty($l2tpcfg['secret'])) {
                $mpdconf .= "  set l2tp secret {$l2tpcfg['secret']}\n";
            }

            if (isset($l2tpcfg['radius']['enable'])) {
                $mpdconf .=<<<EOD
  set radius server {$l2tpcfg['radius']['server']} "{$l2tpcfg['radius']['secret']}"
  set radius retries 3
  set radius timeout 10
  set auth enable radius-auth

EOD;

                if (isset($l2tpcfg['radius']['accounting'])) {
                    $mpdconf .=<<<EOD
  set auth enable radius-acct

EOD;
                }
            }

            fwrite($fd, $mpdconf);
            fclose($fd);
            unset($mpdconf);

            $fd = fopen("/var/etc/l2tp-vpn/mpd.secret", "w");
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.secret in if_l2tp_configure().") . "\n");
                return 1;
            }

            $mpdsecret = "\n\n";

            if (is_array($l2tpcfg['user'])) {
                foreach ($l2tpcfg['user'] as $user) {
                    $mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
                }
            }

            fwrite($fd, $mpdsecret);
            fclose($fd);
            unset($mpdsecret);
            chmod('/var/etc/l2tp-vpn/mpd.secret', 0600);

            legacy_netgraph_attach(get_real_interface($l2tpcfg['interface']));

            mwexec('/usr/local/sbin/mpd5 -b -d /var/etc/l2tp-vpn -p /var/run/l2tp-vpn.pid -s l2tps l2tps');

            break;
    }

    if (file_exists('/var/run/booting')) {
        echo gettext("done") . "\n";
    }

    return 0;
}

function l2tp_interfaces()
{
    global $config;

    $interfaces = array();

    if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == 'server') {
        $oic = array("enable" => true);
        $oic['virtual'] = true;
        $oic['networks'] =  array();
        $oic['if'] = 'l2tp';
        $oic['descr'] = 'L2TP';
        $mask = !empty($config['l2tp']['l2tp_subnet']) ? $config['l2tp']['l2tp_subnet'] : 32;
        $oic['networks'][] = array("network" => gen_subnet($config['l2tp']['remoteip'], $mask), "mask" => $mask);
        $interfaces['l2tp'] = $oic;
    }

    return $interfaces;
}
