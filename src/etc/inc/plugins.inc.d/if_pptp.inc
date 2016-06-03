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

function if_pptp_configure()
{
    return array('if_pptp_configure_do');
}

function if_pptp_services()
{
    global $config;

    $services = array();

    if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == 'server') {
        $services[] = array(
            'description' => gettext('PPTP Server'),
            'pidfile' => '/var/run/pptp-vpn.pid',
            'php' => array(
                'restart' => array('if_pptp_configure_do'),
                'start' => array('if_pptp_configure_do'),
            ),
            'name' => 'pptpd',
        );
    }

    return $services;
}

/**
 * request syslog facilities for this plugin
 * @return array
 */
function if_pptp_syslog()
{
    $logfacilities = array();

    $logfacilities['pptps'] = array('facility' => array('pptps'), 'remote' => null);

    return $logfacilities;
}

function if_pptp_link_scripts($rootdir, $logtype = 'pptp')
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

function if_pptp_configure_do()
{
    global $config;

    $syscfg = $config['system'];
    $pptpdcfg = $config['pptpd'];

    killbypid('/var/run/pptp-vpn.pid', 'TERM', true);
    mwexec('rm -rf /var/etc/pptp-vpn');

    if (!isset($pptpdcfg['mode']) || $pptpdcfg['mode'] != 'server') {
        return 0;
    }

    if (file_exists('/var/run/booting')) {
        echo gettext("Configuring PPTP VPN service...");
    }

    switch ($pptpdcfg['mode']) {
        case 'server':
            mkdir('/var/etc/pptp-vpn');
            if_pptp_link_scripts('/var/etc/pptp-vpn');

            $fd = fopen('/var/etc/pptp-vpn/mpd.conf', 'w');
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.conf in if_pptp_configure().") . "\n");
                return 1;
            }

            $iprange = $pptpdcfg['remoteip'] . ' ';
            $iprange .= long2ip32(ip2long($pptpdcfg['remoteip']) + $pptpdcfg['n_pptp_units'] - 1);

            $mpdconf = <<<EOD
startup:

pptps:
  set ippool add pool1 {$iprange}

  create bundle template B
  set iface disable on-demand
  set iface enable proxy-arp
  set iface enable tcpmssfix
  set iface idle 1800
  set iface up-script /var/etc/pptp-vpn/linkup
  set iface down-script /var/etc/pptp-vpn/linkdown
  set ipcp ranges {$pptpdcfg['localip']}/32 ippool pool1
  set ipcp yes vjcomp

EOD;

            if (isset($pptpdcfg["wins"]) && $pptpdcfg['wins'] != "") {
                $mpdconf  .=  "  set ipcp nbns {$pptpdcfg['wins']}\n";
            }
            if (!empty($pptpdcfg['dns1'])) {
                $mpdconf .= "  set ipcp dns " . $pptpdcfg['dns1'];
                if (!empty($pptpdcfg['dns2'])) {
                    $mpdconf .= " " . $pptpdcfg['dns2'];
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

  set bundle enable crypt-reqd
  set bundle enable compression
  set ccp yes mppc
  set mppc yes e128
  set mppc yes stateless

EOD;

            if (!isset($pptpdcfg['req128'])) {
                $mpdconf .=<<<EOD
  set mppc yes e40
  set mppc yes e56

EOD;
            }

            $mpdconf .= <<<EOD

  create link template L pptp
  set link action bundle B
  set link enable multilink
  set link yes acfcomp protocomp
  set link no pap chap eap
  set link enable chap-msv2
  set link mtu 1460
  set link keep-alive 10 60
  set pptp self {$pptpdcfg['localip']}
  set link enable incoming

EOD;

            if (isset($pptpdcfg['radius']['server']['enable'])) {
                $authport = (isset($pptpdcfg['radius']['server']['port']) && strlen($pptpdcfg['radius']['server']['port']) > 1) ? $pptpdcfg['radius']['server']['port'] : 1812;
                $acctport = $authport + 1;
                $mpdconf .=<<<EOD
  set radius server {$pptpdcfg['radius']['server']['ip']} "{$pptpdcfg['radius']['server']['secret']}" {$authport} {$acctport}

EOD;
                if (isset($pptpdcfg['radius']['server2']['enable'])) {
                    $authport = (isset($pptpdcfg['radius']['server2']['port']) && strlen($pptpdcfg['radius']['server2']['port']) > 1) ? $pptpdcfg['radius']['server2']['port'] : 1812;
                    $acctport = $authport + 1;
                    $mpdconf .=<<<EOD
  set radius server {$pptpdcfg['radius']['server2']['ip']} "{$pptpdcfg['radius']['server2']['secret2']}" {$authport} {$acctport}

EOD;
                }
                $mpdconf .=<<<EOD
  set radius retries 3
  set radius timeout 10
  set auth enable radius-auth

EOD;

                if (isset($pptpdcfg['radius']['accounting'])) {
                    $mpdconf .=<<<EOD
  set auth enable radius-acct
  set radius acct-update 300

EOD;
                }
            }

            fwrite($fd, $mpdconf);
            fclose($fd);
            unset($mpdconf);

            $fd = fopen('/var/etc/pptp-vpn/mpd.secret', 'w');
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.secret in if_pptp_configure().") . "\n");
                return 1;
            }

            $mpdsecret = "";

            if (is_array($pptpdcfg['user'])) {
                foreach ($pptpdcfg['user'] as $user) {
                    $pass = str_replace('\\', '\\\\', $user['password']);
                    $pass = str_replace('"', '\"', $pass);
                    $mpdsecret .= "{$user['name']} \"{$pass}\" {$user['ip']}\n";
                }
            }

            fwrite($fd, $mpdsecret);
            fclose($fd);
            unset($mpdsecret);
            chmod('/var/etc/pptp-vpn/mpd.secret', 0600);

            /* fixed to WAN elsewhere, no need to extend, but at least make it work */
            legacy_netgraph_attach(get_real_interface('wan'));

            mwexec('/usr/local/sbin/mpd5 -b -d /var/etc/pptp-vpn -p /var/run/pptp-vpn.pid -s pptps pptps');

            break;
    }

    if (file_exists('/var/run/booting')) {
        echo gettext("done") . "\n";
    }

    return 0;
}

function if_pptp_interfaces()
{
    global $config;

    $interfaces = array();

    if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == 'server') {
        $oic = array("enable" => true);
        $oic['networks'] =  array();
        $oic['virtual'] = true;
        $oic['if'] = 'pptp';
        $oic['descr'] = 'pptp';
        $mask = !empty($config['pptpd']['pptp_subnet']) ? $config['pptpd']['pptp_subnet'] : 32;
        if (isset($config['pptpd']['n_pptp_units']) && is_numeric($config['pptpd']['n_pptp_units'])) {
            $pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'],
              long2ip32(ip2long($config['pptpd']['remoteip'])+($config['pptpd']['n_pptp_units']-1)));
        } else {
            $pptp_subnets = ip_range_to_subnet_array($config['pptpd']['remoteip'],
              long2ip32(ip2long($config['pptpd']['remoteip'])));
        }
        foreach ($pptp_subnets as $pptp_subnet) {
            $snparts = explode("/", $pptp_subnet);
            $oic['networks'][] = array("network" => $snparts[0], "mask" => $snparts[1]);
        }
        $interfaces['pptp'] = $oic;
    }

    return $interfaces;
}
