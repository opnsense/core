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

function if_pppoe_configure()
{
    return array('if_pppoe_configure_do');
}

function if_pppoe_services()
{
    global $config;

    $services = array();

    if (isset($config['pppoes']['pppoe'])) {
        foreach ($config['pppoes']['pppoe'] as $pppoecfg) {
            if (isset($pppoecfg['mode']) && $pppoecfg['mode'] == 'server') {
                $services[] = array(
                    /* XXX clean up name printing */
                    'description' => gettext('PPPoE Server') . ': ' . htmlspecialchars($pppoecfg['descr']),
                    'php' => array(
                        'restart' => array('if_pppoe_configure_by_id'),
                        'start' => array('if_pppoe_configure_by_id'),
                        'args' => array('id'),
                    ),
                    'pidfile' => "/var/run/pppoe{$pppoecfg['pppoeid']}-vpn.pid",
                    'id' => $pppoecfg['pppoeid'],
                    'name' => 'pppoed',
                );
            }
        }
    }

    return $services;
}

/**
 * request syslog facilities for this plugin
 * @return array
 */
function if_pppoe_syslog()
{
    $logfacilities = array();

    $logfacilities['poes'] = array('facility' => array('poes'), 'remote' => null);

    return $logfacilities;
}

function if_pppoe_link_scripts($rootdir, $logtype = 'poes')
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

function if_pppoe_configure_do()
{
    global $config;

    if (isset($config['pppoes']['pppoe'])) {
        foreach ($config['pppoes']['pppoe'] as $pppoe) {
            if_pppoe_configure_single($pppoe);
        }
    }
}

function if_pppoe_configure_by_id($id)
{
    global $config;

    $found = null;

    if (isset($config['pppoes']['pppoe'])) {
        foreach ($config['pppoes']['pppoe'] as $pppoe) {
            if ($id != 0 && $id == $pppoe['pppoeid']) {
                $found = $pppoe;
                break;
            }
        }
    }

    if ($found == null) {
        return;
    }

    if_pppoe_configure_single($found);
}

function if_pppoe_configure_single(&$pppoecfg)
{
    global $config;

    $syscfg = $config['system'];

    killbypid("/var/run/pppoe{$pppoecfg['pppoeid']}-vpn.pid", 'TERM', true);
    mwexec("rm -rf /var/etc/pppoe{$pppoecfg['pppoeid']}-vpn");

    if (!isset($pppoecfg['mode']) || $pppoecfg['mode'] != 'server') {
        return 0;
    }

    if (file_exists('/var/run/booting')) {
        echo gettext("Configuring PPPoE VPN service...");
    }

    switch ($pppoecfg['mode']) {
        case 'server':
            mkdir("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn");
            if_pppoe_link_scripts("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn");

            $pppoe_interface = get_real_interface($pppoecfg['interface']);

            $fd = fopen("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.conf", "w");
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.conf in if_pppoe_configure().") . "\n");
                return 1;
            }

            $iprange = $pppoecfg['remoteip'] . ' ';
            $iprange .= long2ip32(ip2long($pppoecfg['remoteip']) + $pppoecfg['n_pppoe_units'] - 1);

            $iptype = 'ippool pool1';
            if (isset($pppoecfg['radius']['server']['enable']) && isset($pppoecfg['radius']['radiusissueips'])) {
                $iptype = '0.0.0.0/0';
            }

            $mpdconf = <<<EOD
startup:

poes:
  set ippool add pool1 {$iprange}
  create bundle template B
  set iface up-script /var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/linkup
  set iface down-script /var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/linkdown
  set iface idle 0
  set iface disable on-demand
  set iface disable proxy-arp
  set iface enable tcpmssfix
  set iface mtu 1500
  set ipcp no vjcomp
  set ipcp ranges {$pppoecfg['localip']}/32 {$iptype}

EOD;

            if (!empty($pppoecfg['dns1'])) {
                $mpdconf .= "  set ipcp dns " . $pppoecfg['dns1'];
                if (!empty($pppoecfg['dns2'])) {
                    $mpdconf .= " " . $pppoecfg['dns2'];
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

            $mpdconf .= <<<EOD

  set bundle enable compression
  set ccp yes mppc
  set mppc yes e40
  set mppc yes e128
  set mppc yes stateless

  create link template L pppoe
  set link action bundle B
  set link no multilink
  set link disable pap
  set link disable eap
  set link enable chap
  set link keep-alive 10 60
  set link max-redial -1
  set link mtu 1492
  set link mru 1492
  set link latency 1
  set pppoe service pppoe{$pppoecfg['pppoeid']}
  set pppoe iface {$pppoe_interface}
  set link enable incoming
  set auth max-logins 1

EOD;

            if (isset($pppoecfg['radius']['server']['enable'])) {
                $radiusport = "";
                $radiusacctport = "";
                if (isset($pppoecfg['radius']['server']['port'])) {
                    $radiusport = $pppoecfg['radius']['server']['port'];
                }
                if (isset($pppoecfg['radius']['server']['acctport'])) {
                    $radiusacctport = $pppoecfg['radius']['server']['acctport'];
                }
                $mpdconf .=<<<EOD
  set radius server {$pppoecfg['radius']['server']['ip']} "{$pppoecfg['radius']['server']['secret']}" {$radiusport} {$radiusacctport}
  set radius retries 3
  set radius timeout 10
  set auth enable radius-auth

EOD;

                if (isset($pppoecfg['radius']['accounting'])) {
                    $mpdconf .=<<<EOD
  set auth enable radius-acct

EOD;
                }
            }

            fwrite($fd, $mpdconf);
            fclose($fd);
            unset($mpdconf);

            if ($pppoecfg['username']) {
                $fd = fopen("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", "w");
                if (!$fd) {
                    printf(gettext("Error: cannot open mpd.secret in if_pppoe_configure().") . "\n");
                    return 1;
                }

                $mpdsecret = "\n\n";

                if (!empty($pppoecfg['username'])) {
                    $item = explode(" ", $pppoecfg['username']);
                    foreach ($item as $userdata) {
                        $data = explode(":", $userdata);
                        $mpdsecret .= "{$data[0]} \"" . base64_decode($data[1]) . "\" {$data[2]}\n";
                    }
                }

                fwrite($fd, $mpdsecret);
                fclose($fd);
                unset($mpdsecret);
                chmod("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", 0600);
            }

            legacy_netgraph_attach($pppoe_interface);

            mwexec("/usr/local/sbin/mpd5 -b -d /var/etc/pppoe{$pppoecfg['pppoeid']}-vpn -p /var/run/pppoe{$pppoecfg['pppoeid']}-vpn.pid -s poes poes");

            break;
    }

    if (file_exists('/var/run/booting')) {
        echo gettext("done") . "\n";
    }

    return 0;
}

function if_pppoe_interfaces()
{
    global $config;

    $interfaces = array();

    if (isset($config['pppoes']['pppoe'])) {
        $pppoeifs = array('networks' => array());
        foreach($config['pppoes']['pppoe'] as $pppoe) {
            if ($pppoe['mode'] == "server") {
                $mask = !empty($pppoe['pppoe_subnet']) ?  $pppoe['pppoe_subnet'] : 32;
                $pppoeifs['networks'][] = array("network" => gen_subnet($pppoe['remoteip'], $mask), "mask" => $mask);
            }
        }
        if (count($pppoeifs['networks'])) {
            $pppoeifs['enable'] = true;
            $pppoeifs['virtual'] = true;
            $pppoeifs['if'] = 'pppoe';
            $pppoeifs['descr'] = 'pppoe';
            $interfaces['pppoe'] = $pppoeifs;
        }
    }

    return $interfaces;
}
