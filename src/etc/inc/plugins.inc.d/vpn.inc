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

function vpn_configure()
{
    return array(
        'vpn_pptpd_configure',
        'vpn_pppoes_configure',
        'vpn_l2tp_configure',
    );
}

function vpn_services()
{
    global $config;

    $services = array();

    if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == 'server') {
        $services[] = array(
            'description' => gettext('PPTP Server'),
            'pidfile' => '/var/run/pptp-vpn.pid',
            'php' => array(
                'restart' => array('vpn_pptpd_configure'),
                'start' => array('vpn_pptpd_configure'),
            ),
            'name' => 'pptpd',
        );
    }

    if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == 'server') {
        $services[] = array(
            'description' => gettext('L2TP Server'),
            'pidfile' => '/var/run/l2tp-vpn.pid',
            'php' => array(
                'restart' => array('vpn_l2tp_configure'),
                'start' => array('vpn_l2tp_configure'),
            ),
            'name' => 'l2tpd',
        );
    }

    if (isset($config['pppoes']['pppoe'])) {
        foreach ($config['pppoes']['pppoe'] as $pppoecfg) {
            if (isset($pppoecfg['mode']) && $pppoecfg['mode'] == 'server') {
                $services[] = array(
                    'description' => gettext('PPPoE Server') . ': ' . htmlspecialchars($pppoecfg['descr']),
                    'php' => array(
                        'restart' => array('vpn_pppoe_configure_by_id'),
                        'start' => array('vpn_pppoe_configure_by_id'),
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
function vpn_syslog()
{
    $logfacilities = array();
    $logfacilities['pptps'] = array("facility" => array('pptps'), "remote" => null);
    $logfacilities['poes'] = array("facility" => array('poes'), "remote" => null);
    $logfacilities['l2tps'] = array("facility" => array('l2tps'), "remote" => null);
    return $logfacilities;
}

function vpn_link_scripts($rootdir, $logtype)
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

function vpn_pptpd_configure()
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
            vpn_link_scripts('/var/etc/pptp-vpn', 'pptp');

            $fd = fopen('/var/etc/pptp-vpn/mpd.conf', 'w');
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.conf in vpn_pptpd_configure().") . "\n");
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
                printf(gettext("Error: cannot open mpd.secret in vpn_pptpd_configure().") . "\n");
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

function vpn_pppoes_configure()
{
    global $config;

    if (isset($config['pppoes']['pppoe'])) {
        foreach ($config['pppoes']['pppoe'] as $pppoe) {
            vpn_pppoe_configure($pppoe);
        }
    }
}

function vpn_pppoe_configure_by_id($id)
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

    vpn_pppoe_configure($found);
}

function vpn_pppoe_configure(&$pppoecfg)
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
            vpn_link_scripts("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn", 'poes');

            $pppoe_interface = get_real_interface($pppoecfg['interface']);

            $fd = fopen("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.conf", "w");
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.conf in vpn_pppoe_configure().") . "\n");
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
                    printf(gettext("Error: cannot open mpd.secret in vpn_pppoe_configure().") . "\n");
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

function vpn_l2tp_configure()
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
            vpn_link_scripts('/var/etc/l2tp-vpn', 'l2tp');

            $fd = fopen("/var/etc/l2tp-vpn/mpd.conf", "w");
            if (!$fd) {
                printf(gettext("Error: cannot open mpd.conf in vpn_l2tp_configure().") . "\n");
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
                printf(gettext("Error: cannot open mpd.secret in vpn_l2tp_configure().") . "\n");
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

function vpn_interface()
{
    global $config;
    $interfaces = array();

    if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == 'server') {
        $oic = array("enable" => true);
        $oic['if'] = 'pptp';
        $oic['descr'] = 'pptp';
        $oic['ip'] = $config['pptpd']['localip'];
        $oic['sa'] = $config['pptpd']['remoteip'];
        if (!empty($config['pptpd']['pptp_subnet'])) {
            $oic['sn'] = $config['pptpd']['pptp_subnet'];
        } else {
            $oic['sn'] = "32";
        }
        $oic['mode'] = $config['pptpd']['mode'];
        $oic['virtual'] = true;
        $interfaces['pptp'] = $oic;
    }

    if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == 'server') {
        $oic = array("enable" => true);
        $oic['if'] = 'l2tp';
        $oic['descr'] = 'L2TP';
        $oic['ip'] = $config['l2tp']['localip'];
        $oic['sa'] = $config['l2tp']['remoteip'];
        if (!empty($config['l2tp']['l2tp_subnet'])) {
            $oic['sn'] = $config['l2tp']['l2tp_subnet'];
        } else {
            $oic['sn'] = "32";
        }
        $oic['mode'] = $config['l2tp']['mode'];
        $oic['virtual'] = true;
        $interfaces['l2tp'] = $oic;
    }

    if (isset($config['pppoes']['pppoe'])) {
        $pppoeifs = array('networks' => array());
        foreach($config['pppoes']['pppoe'] as $pppoe) {
            if ($pppoe['mode'] == "server") {
                $oic = array();
                $oic['sa'] = $pppoe['remoteip'];
                if ($pppoe['pppoe_subnet'] <> "") {
                    $oic['sn'] = $pppoe['pppoe_subnet'];
                } else {
                    $oic['sn'] = "32";
                }
                $oic['ip'] = $pppoe['localip'];
                $pppoeifs['networks'][] = $oic;
            }
        }
        if (count($pppoeifs['networks'])) {
            $pppoeifs['enable'] = true;
            $pppoeifs['if'] = 'pppoe';
            $pppoeifs['descr'] = 'pppoe';
            $pppoeifs['ip'] = "";
            $pppoeifs['sa'] = "";
            $pppoeifs['sn'] = "32";
            $pppoeifs['mode'] = "";
            $pppoeifs['virtual'] = true;
            $interfaces['pppoe'] = $pppoeifs;
        }
    }

    return $interfaces;
}
