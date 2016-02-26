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
        'vpn_l2tp_configure'
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
                        'args' => array('pppoeid'),
                    ),
                    'pidfile' => "/var/run/pppoe{$pppoecfg['pppoeid']}-vpn.pid",
                    'pppoeid' => $pppoecfg['pppoeid'],
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

function vpn_pptpd_configure()
{
  global $config;

  $syscfg = $config['system'];
  $pptpdcfg = $config['pptpd'];

  killbypid('/var/run/pptp-vpn.pid', 'TERM', true);

  if (!isset($pptpdcfg['mode']) || $pptpdcfg['mode'] == 'off') {
      return 0;
  }

  if (file_exists('/var/run/booting')) {
    echo gettext("Configuring PPTP VPN service...");
  }

  /* remove mpd.conf, if it exists */
  @unlink('/var/etc/pptp-vpn/mpd.conf');
  @unlink('/var/etc/pptp-vpn/mpd.links');
  @unlink('/var/etc/pptp-vpn/mpd.secret');

  if (empty($pptpdcfg['n_pptp_units'])) {
    log_error("Something wrong in the PPTPd configuration. Preventing starting the daemon because issues would arise.");
    return;
  }

  /* make sure pptp-vpn directory exists */
  @mkdir('/var/etc/pptp-vpn');

  switch ($pptpdcfg['mode']) {
    case 'server' :
      /* write mpd.conf */
      $fd = fopen('/var/etc/pptp-vpn/mpd.conf', 'w');
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.conf in vpn_pptpd_configure().") . "\n");
        return 1;
      }

      $mpdconf = <<<EOD
pptps:

EOD;

      for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {
        $mpdconf .= "  load pt{$i}\n";
      }

      for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {

        $clientip = long2ip32(ip2long($pptpdcfg['remoteip']) + $i);

        $mpdconf .= <<<EOD

pt{$i}:
  new -i pptpd{$i} pt{$i} pt{$i}
  set ipcp ranges {$pptpdcfg['localip']}/32 {$clientip}/32
  load pts

EOD;
      }

      $mpdconf .=<<<EOD

pts:
  set iface disable on-demand
  set iface enable proxy-arp
  set iface enable tcpmssfix
  set iface idle 1800
  set iface up-script /usr/local/sbin/vpn-linkup
  set iface down-script /usr/local/sbin/vpn-linkdown
  set bundle enable multilink
  set bundle enable crypt-reqd
  set link yes acfcomp protocomp
  set link no pap chap
  set link enable chap-msv2
  set link mtu 1460
  set link keep-alive 10 60
  set ipcp yes vjcomp
  set bundle enable compression
  set ccp yes mppc
  set ccp yes mpp-e128
  set ccp yes mpp-stateless

EOD;

      if (!isset ($pptpdcfg['req128'])) {
        $mpdconf .=<<<EOD
  set ccp yes mpp-e40
  set ccp yes mpp-e56

EOD;
      }

      if  (isset($pptpdcfg["wins"]) && $pptpdcfg['wins'] != "")
        $mpdconf  .=  "  set ipcp nbns {$pptpdcfg['wins']}\n";

      if (!empty($pptpdcfg['dns1'])) {
        $mpdconf .= "  set ipcp dns " . $pptpdcfg['dns1'];
        if (!empty($pptpdcfg['dns2']))
          $mpdconf .= " " . $pptpdcfg['dns2'];
        $mpdconf .= "\n";
      } elseif (isset ($config['dnsmasq']['enable'])) {
        $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
        if ($syscfg['dnsserver'][0])
          $mpdconf .= " " . $syscfg['dnsserver'][0];
        $mpdconf .= "\n";
      } elseif (isset($config['unbound']['enable'])) {
        $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
        if ($syscfg['dnsserver'][0])
          $mpdconf .= " " . $syscfg['dnsserver'][0];
        $mpdconf .= "\n";
      } elseif (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
          $mpdconf .= "  set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
      }

      if (isset ($pptpdcfg['radius']['server']['enable'])) {
        $authport = (isset($pptpdcfg['radius']['server']['port']) && strlen($pptpdcfg['radius']['server']['port']) > 1) ? $pptpdcfg['radius']['server']['port'] : 1812;
        $acctport = $authport + 1;
        $mpdconf .=<<<EOD
  set radius server {$pptpdcfg['radius']['server']['ip']} "{$pptpdcfg['radius']['server']['secret']}" {$authport} {$acctport}

EOD;
      if (isset ($pptpdcfg['radius']['server2']['enable'])) {
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

        if (isset ($pptpdcfg['radius']['accounting'])) {
          $mpdconf .=<<<EOD
  set auth enable radius-acct
  set radius acct-update 300

EOD;
        }
      }

      fwrite($fd, $mpdconf);
      fclose($fd);
      unset($mpdconf);

      /* write mpd.links */
      $fd = fopen('/var/etc/pptp-vpn/mpd.links', 'w');
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.links in vpn_pptpd_configure().") . "\n");
        return 1;
      }

      $mpdlinks = "";

      for ($i = 0; $i < $pptpdcfg['n_pptp_units']; $i++) {
        $mpdlinks .=<<<EOD

pt{$i}:
  set link type pptp
  set pptp enable incoming
  set pptp disable originate
  set pptp disable windowing

EOD;
      }

      fwrite($fd, $mpdlinks);
      fclose($fd);
      unset($mpdlinks);

      /* write mpd.secret */
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

      mwexec('/usr/local/sbin/mpd4 -b -d /var/etc/pptp-vpn -p /var/run/pptp-vpn.pid -s pptps pptps');

      break;

    case 'redir' :
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

  if (!isset($pppoecfg['mode']) || $pppoecfg['mode'] == 'off') {
      return 0;
  }

  if (file_exists('/var/run/booting')) {
    echo gettext("Configuring PPPoE VPN service...");
  }

  switch ($pppoecfg['mode']) {

    case 'server' :

      /* create directory if it does not exist */
      @mkdir("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn");

      $pppoe_interface = get_real_interface($pppoecfg['interface']);

      if ($pppoecfg['paporchap'] == "chap")
        $paporchap = "set link enable chap";
      else
        $paporchap = "set link enable pap";

      /* write mpd.conf */
      $fd = fopen("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.conf", "w");
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.conf in vpn_pppoe_configure().") . "\n");
        return 1;
      }
      $mpdconf = "\n\n";
      $mpdconf .= "poes:\n";

      for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
        $mpdconf .= "  load poes{$pppoecfg['pppoeid']}{$i}\n";
      }

      for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {

        $clientip = long2ip32(ip2long($pppoecfg['remoteip']) + $i);

        if (isset($pppoecfg['radius']['radiusissueips']) && isset($pppoecfg['radius']['server']['enable'])) {
          $isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 0.0.0.0/0";
        } else {
          $isssue_ip_type = "set ipcp ranges {$pppoecfg['localip']}/32 {$clientip}/32";
        }

        $mpdconf .=<<<EOD

poes{$pppoecfg['pppoeid']}{$i}:
  new -i poes{$pppoecfg['pppoeid']}{$i} poes{$pppoecfg['pppoeid']}{$i} poes{$pppoecfg['pppoeid']}{$i}
  {$isssue_ip_type}
  load pppoe_standard

EOD;
      }

      $mpdconf .=<<<EOD

pppoe_standard:
  set bundle no multilink
  set bundle enable compression
  set auth max-logins 1
  set iface up-script /usr/local/sbin/vpn-linkup
  set iface down-script /usr/local/sbin/vpn-linkdown
  set iface idle 0
  set iface disable on-demand
  set iface disable proxy-arp
  set iface enable tcpmssfix
  set iface mtu 1500
  set link no pap chap
  {$paporchap}
  set link keep-alive 60 180
  set ipcp yes vjcomp
  set ipcp no vjcomp
  set link max-redial -1
  set link mtu 1492
  set link mru 1492
  set ccp yes mpp-e40
  set ccp yes mpp-e128
  set ccp yes mpp-stateless
  set link latency 1
  #set ipcp dns 10.10.1.3
  #set bundle accept encryption

EOD;

      if (!empty($pppoecfg['dns1'])) {
        $mpdconf .= "  set ipcp dns " . $pppoecfg['dns1'];
        if (!empty($pppoecfg['dns2']))
          $mpdconf .= " " . $pppoecfg['dns2'];
        $mpdconf .= "\n";
      } elseif (isset ($config['dnsmasq']['enable'])) {
        $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
        if ($syscfg['dnsserver'][0])
          $mpdconf .= " " . $syscfg['dnsserver'][0];
        $mpdconf .= "\n";
      } elseif (isset ($config['unbound']['enable'])) {
        $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
        if ($syscfg['dnsserver'][0])
          $mpdconf .= " " . $syscfg['dnsserver'][0];
        $mpdconf .= "\n";
      } elseif (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
          $mpdconf .= "  set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
      }

      if (isset ($pppoecfg['radius']['server']['enable'])) {
        $radiusport = "";
        $radiusacctport = "";
        if (isset($pppoecfg['radius']['server']['port']))
          $radiusport = $pppoecfg['radius']['server']['port'];
        if (isset($pppoecfg['radius']['server']['acctport']))
          $radiusacctport = $pppoecfg['radius']['server']['acctport'];
        $mpdconf .=<<<EOD
  set radius server {$pppoecfg['radius']['server']['ip']} "{$pppoecfg['radius']['server']['secret']}" {$radiusport} {$radiusacctport}
  set radius retries 3
  set radius timeout 10
  set auth enable radius-auth

EOD;

        if (isset ($pppoecfg['radius']['accounting'])) {
          $mpdconf .=<<<EOD
  set auth enable radius-acct

EOD;
        }
      }

      fwrite($fd, $mpdconf);
      fclose($fd);
      unset($mpdconf);

      /* write mpd.links */
      $fd = fopen("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.links", "w");
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.links in vpn_pppoe_configure().") . "\n");
        return 1;
      }

      $mpdlinks = "";

      for ($i = 0; $i < $pppoecfg['n_pppoe_units']; $i++) {
        $mpdlinks .=<<<EOD

poes{$pppoecfg['pppoeid']}{$i}:
  set phys type pppoe
  set pppoe iface {$pppoe_interface}
  set pppoe service "*"
  set pppoe disable originate
  set pppoe enable incoming

EOD;
      }

      fwrite($fd, $mpdlinks);
      fclose($fd);
      unset($mpdlinks);

      if ($pppoecfg['username']) {
        /* write mpd.secret */
        $fd = fopen("/var/etc/pppoe{$pppoecfg['pppoeid']}-vpn/mpd.secret", "w");
        if (!$fd) {
          printf(gettext("Error: cannot open mpd.secret in vpn_pppoe_configure().") . "\n");
          return 1;
        }

        $mpdsecret = "\n\n";

        if (!empty($pppoecfg['username'])) {
          $item = explode(" ", $pppoecfg['username']);
          foreach($item as $userdata) {
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

      mwexec("/usr/local/sbin/mpd4 -b -d /var/etc/pppoe{$pppoecfg['pppoeid']}-vpn -p /var/run/pppoe{$pppoecfg['pppoeid']}-vpn.pid -s poes poes");

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

  $syscfg = $config['system'];
  if (isset($config['l2tp'])) {
    $l2tpcfg = $config['l2tp'];
  } else {
    return 0;
  }

  if (!isset($l2tpcfg['mode']) || $l2tpcfg['mode'] == 'off') {
    return 0;
  }

  if (file_exists('/var/run/booting')) {
    echo gettext('Configuring L2TP VPN service...');
  }

  @mkdir('/var/etc/l2tp-vpn');

  switch (isset($l2tpcfg['mode'])?$l2tpcfg['mode']:null) {

    case 'server' :
      if ($l2tpcfg['paporchap'] == "chap")
        $paporchap = "set link enable chap";
      else
        $paporchap = "set link enable pap";

      /* write mpd.conf */
      $fd = fopen("/var/etc/l2tp-vpn/mpd.conf", "w");
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.conf in vpn_l2tp_configure().") . "\n");
        return 1;
      }
      $mpdconf = "\n\n";
      $mpdconf .=<<<EOD
l2tps:

EOD;

      for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {
        $mpdconf .= "  load l2tp{$i}\n";
      }

      for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {

        $clientip = long2ip32(ip2long($l2tpcfg['remoteip']) + $i);

        if (isset ($l2tpcfg['radius']['radiusissueips']) && isset ($l2tpcfg['radius']['enable'])) {
          $isssue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 0.0.0.0/0";
        } else {
          $isssue_ip_type = "set ipcp ranges {$l2tpcfg['localip']}/32 {$clientip}/32";
        }

        $mpdconf .=<<<EOD

l2tp{$i}:
  new -i l2tp{$i} l2tp{$i} l2tp{$i}
  {$isssue_ip_type}
  load l2tp_standard

EOD;
      }

      $mpdconf .=<<<EOD

l2tp_standard:
  set bundle disable multilink
  set bundle enable compression
  set bundle yes crypt-reqd
  set ipcp yes vjcomp
  # set ipcp ranges 131.188.69.161/32 131.188.69.170/28
  set ccp yes mppc
  set iface disable on-demand
  set iface enable proxy-arp
  set iface up-script /usr/local/sbin/vpn-linkup
  set iface down-script /usr/local/sbin/vpn-linkdown
  set link yes acfcomp protocomp
  set link no pap chap
  set link enable chap
  set link keep-alive 10 180

EOD;

      if (is_ipaddr($l2tpcfg['wins'])) {
        $mpdconf .= "  set ipcp nbns {$l2tpcfg['wins']}\n";
      }
      if (is_ipaddr($l2tpcfg['dns1'])) {
        $mpdconf .= "  set ipcp dns " . $l2tpcfg['dns1'];
        if (is_ipaddr($l2tpcfg['dns2']))
          $mpdconf .= " " . $l2tpcfg['dns2'];
        $mpdconf .= "\n";
      } elseif (isset ($config['dnsmasq']['enable'])) {
        $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
        if ($syscfg['dnsserver'][0])
          $mpdconf .= " " . $syscfg['dnsserver'][0];
        $mpdconf .= "\n";
      } elseif (isset ($config['unbound']['enable'])) {
        $mpdconf .= "  set ipcp dns " . get_interface_ip("lan");
        if ($syscfg['dnsserver'][0])
          $mpdconf .= " " . $syscfg['dnsserver'][0];
        $mpdconf .= "\n";
      } elseif (is_array($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
          $mpdconf .= "  set ipcp dns " . join(" ", $syscfg['dnsserver']) . "\n";
      }

      if (isset ($l2tpcfg['radius']['enable'])) {
        $mpdconf .=<<<EOD
  set radius server {$l2tpcfg['radius']['server']} "{$l2tpcfg['radius']['secret']}"
  set radius retries 3
  set radius timeout 10
  set auth enable radius-auth

EOD;

        if (isset ($l2tpcfg['radius']['accounting'])) {
          $mpdconf .=<<<EOD
  set auth enable radius-acct

EOD;
        }
      }

      fwrite($fd, $mpdconf);
      fclose($fd);
      unset($mpdconf);

      /* write mpd.links */
      $fd = fopen("/var/etc/l2tp-vpn/mpd.links", "w");
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.links in vpn_l2tp_configure().") . "\n");
        return 1;
      }

      $mpdlinks = "";

      for ($i = 0; $i < $l2tpcfg['n_l2tp_units']; $i++) {
        $mpdlinks .=<<<EOD

l2tp{$i}:
  set link type l2tp
  set l2tp enable incoming
  set l2tp disable originate

EOD;
      if (!empty($l2tpcfg['secret']))
          $mpdlinks .= "set l2tp secret {$l2tpcfg['secret']}\n";
      }

      fwrite($fd, $mpdlinks);
      fclose($fd);
      unset($mpdlinks);

      /* write mpd.secret */
      $fd = fopen("/var/etc/l2tp-vpn/mpd.secret", "w");
      if (!$fd) {
        printf(gettext("Error: cannot open mpd.secret in vpn_l2tp_configure().") . "\n");
        return 1;
      }

      $mpdsecret = "\n\n";

      if (is_array($l2tpcfg['user'])) {
        foreach ($l2tpcfg['user'] as $user)
          $mpdsecret .= "{$user['name']} \"{$user['password']}\" {$user['ip']}\n";
      }

      fwrite($fd, $mpdsecret);
      fclose($fd);
      unset($mpdsecret);
      chmod('/var/etc/l2tp-vpn/mpd.secret', 0600);

      legacy_netgraph_attach(get_real_interface($l2tpcfg['interface']));

      mwexec('/usr/local/sbin/mpd4 -b -d /var/etc/l2tp-vpn -p /var/run/l2tp-vpn.pid -s l2tps l2tps');

      break;

    case 'redir' :
      break;
  }

  if (file_exists('/var/run/booting')) {
    echo gettext("done") . "\n";
  }

  return 0;
}
