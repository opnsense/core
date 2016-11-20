<?php

/*
    Copyright (C) 2016 Deciso B.V.
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

function miniupnpd_enabled()
{
    global $config;

    return isset($config['installedpackages']['miniupnpd']['config'][0]['enable']);
}

function miniupnpd_firewall($fw)
{
    if (!miniupnpd_enabled()) {
        return;
    }

    $fw->registerAnchor('miniupnpd/*', 'rdr');
    $fw->registerAnchor('miniupnpd/*', 'fw');
}

function miniupnpd_services()
{
    $services = array();

    if (!miniupnpd_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'miniupnpd';
    $pconfig['description'] = gettext('Univeral Plug and Play');
    $pconfig['php']['restart'] = array('miniupnpd_stop', 'miniupnpd_start');
    $pconfig['php']['start'] = array('miniupnpd_start');
    $pconfig['php']['stop'] = array('miniupnpd_stop');
    $pconfig['pidfile'] = '/var/run/miniupnpd.pid';
    $services[] = $pconfig;

    return $services;
}

function miniupnpd_start()
{
    if (!miniupnpd_enabled()) {
        return;
    }

    if (isvalidpid('/var/run/miniupnpd.pid')) {
        return;
    }

    mwexec_bg('/usr/local/sbin/miniupnpd -f /var/etc/miniupnpd.conf -P /var/run/miniupnpd.pid');
}

function miniupnpd_stop()
{
    killbypid('/var/run/miniupnpd.pid', 'TERM', true);
    mwexec('/sbin/pfctl -aminiupnpd -Fr 2>&1 >/dev/null');
    mwexec('/sbin/pfctl -aminiupnpd -Fn 2>&1 >/dev/null');
}

function miniupnpd_configure()
{
    return array('miniupnpd_configure_do');
}

function miniupnpd_uuid()
{
    /* md5 hash of wan mac */
    $uuid = md5(get_interface_mac(get_real_interface("wan")));
    /* put uuid in correct format 8-4-4-4-12 */
    return substr($uuid,0,8).'-'.substr($uuid,9,4).'-'.substr($uuid,13,4).'-'.substr($uuid,17,4).'-'.substr($uuid,21,12);
}

function miniupnpd_configure_do($verbose = false)
{
    global $config;

    miniupnpd_stop();

    if (!miniupnpd_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting UPnP service...';
        flush();
    }

    $upnp_config = $config['installedpackages']['miniupnpd']['config'][0];

    $ext_ifname = get_real_interface($upnp_config['ext_iface']);
    if ($ext_ifname == $upnp_config['ext_iface']) {
        if ($verbose) {
            echo "failed.\n";
        }
        return;
    }

    $config_text = "ext_ifname={$ext_ifname}\n";
    $config_text .= "port=2189\n";

    $ifaces_active = '';

    /* since config is written before this file invoked we don't need to read post data */
    if (!empty($upnp_config['iface_array'])) {
        foreach(explode(',', $upnp_config['iface_array']) as $iface) {
            /* Setting the same internal and external interface is not allowed. */
            if ($iface == $upnp_config['ext_iface']) {
                continue;
            }
            $if = get_real_interface($iface);
            /* above function returns iface if fail */
            if ($if!=$iface) {
                $addr = find_interface_ip($if);
                $bits = find_interface_subnet($if);
                /* check that the interface has an ip address before adding parameters */
                if (is_ipaddr($addr)) {
                  $config_text .= "listening_ip={$if}\n";
                  if (!$ifaces_active) {
                      $webgui_ip = $addr;
                      $ifaces_active = $iface;
                  } else {
                      $ifaces_active .= ", {$iface}";
                  }
                } else {
                    log_error("miniupnpd: Interface {$iface} has no ip address, ignoring");
                }
            } else {
                log_error("miniupnpd: Could not resolve real interface for {$iface}");
            }
        }

        if (!empty($ifaces_active)) {
            /* override wan ip address, common for carp, etc */
            if (!empty($upnp_config['overridewanip'])) {
                $config_text .= "ext_ip={$upnp_config['overridewanip']}\n";
            }
            /* set upload and download bitrates */
            if (!empty($upnp_config['download']) && !empty($upnp_config['upload'])) {
                $download = $upnp_config['download']*1000;
                $upload = $upnp_config['upload']*1000;
                $config_text .= "bitrate_down={$download}\n";
                $config_text .= "bitrate_up={$upload}\n";
            }

            $config_text .= "secure_mode=yes\n";

            /* enable logging of packets handled by miniupnpd rules */
            if (!empty($upnp_config['logpackets'])) {
                $config_text .= "packet_log=yes\n";
            }

            /* enable system uptime instead of miniupnpd uptime */
            if (!empty($upnp_config['sysuptime'])) {
                $config_text .= "system_uptime=yes\n";
            }

            /* set webgui url */
            if (!empty($config['system']['webgui']['protocol'])) {
                $config_text .= "presentation_url={$config['system']['webgui']['protocol']}://{$webgui_ip}";
                if (!empty($config['system']['webgui']['port'])) {
                    $config_text .= ":{$config['system']['webgui']['port']}";
                }
                $config_text .= "/\n";
            }

            /* set uuid and serial */
            $config_text .= "uuid=".miniupnpd_uuid()."\n";
            $config_text .= "serial=".strtoupper(substr(miniupnpd_uuid(),0,8))."\n";

            /* set model number */
            $config_text .= "model_number=".file_get_contents("/usr/local/opnsense/version/opnsense")."\n";

            /* upnp access restrictions */
            for ($i=1; $i<=4; $i++) {
                if ($upnp_config["permuser{$i}"]) {
                    $config_text .= "{$upnp_config["permuser{$i}"]}\n";
                }
            }

            if (!empty($upnp_config['permdefault'])) {
                $config_text .= "deny 0-65535 0.0.0.0/0 0-65535\n";
            }

            /* Allow UPnP or NAT-PMP as requested */
            $config_text .= "enable_upnp="   . ( $upnp_config['enable_upnp']   ? "yes\n" : "no\n" );
            $config_text .= "enable_natpmp=" . ( $upnp_config['enable_natpmp'] ? "yes\n" : "no\n" );

            /* write out the configuration */
            file_put_contents('/var/etc/miniupnpd.conf', $config_text);

            log_error("miniupnpd: Starting service on interface: {$ifaces_active}");
            miniupnpd_start();
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}
