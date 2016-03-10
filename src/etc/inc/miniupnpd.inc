<?php

function upnp_running () {
    if ((int)exec('/bin/pgrep -a miniupnpd | /usr/bin/wc -l') > 0) {
        return true;
    }
    return false;
}

function upnp_start() {
    if (file_exists('/var/etc/miniupnpd.conf')) {
        @unlink('/var/run/miniupnpd.pid');
        mwexec_bg('/usr/local/sbin/miniupnpd -f /var/etc/miniupnpd.conf -P /var/run/miniupnpd.pid');
    }
}

function upnp_stop() {
    killbypid('/var/run/miniupnpd.pid');
    while ((int)exec("/bin/pgrep -a miniupnpd | wc -l") > 0) {
        mwexec('killall miniupnpd 2>/dev/null', true);
    }
    mwexec('/sbin/pfctl -aminiupnpd -Fr 2>&1 >/dev/null');
    mwexec('/sbin/pfctl -aminiupnpd -Fn 2>&1 >/dev/null');
}

function upnp_configure() {
    global $config;

    if (!empty($config['installedpackages']['miniupnpd']['config'][0]['enable'])) {
        echo gettext("Starting UPnP service... ");
        sync_package_miniupnpd();
        echo "done.\n";
    }
}

function upnp_uuid() {
    /* md5 hash of wan mac */
    $uuid = md5(get_interface_mac(get_real_interface("wan")));
    /* put uuid in correct format 8-4-4-4-12 */
    return substr($uuid,0,8).'-'.substr($uuid,9,4).'-'.substr($uuid,13,4).'-'.substr($uuid,17,4).'-'.substr($uuid,21,12);
}

function sync_package_miniupnpd() {
    global $config;
    global $input_errors;

    $upnp_config = $config['installedpackages']['miniupnpd']['config'][0];
    $config_file = '/var/etc/miniupnpd.conf';

    $ext_ifname = get_real_interface($upnp_config['ext_iface']);
    if ($ext_ifname == $upnp_config['ext_iface']) {
        log_error("miniupnpd: Could not resolve real interface for {$upnp_config['ext_iface']}, exit");
        return;
    }


    $config_text = "ext_ifname={$ext_ifname}\n";
    $config_text .= "port=2189\n";

    $ifaces_active = '';

    /* since config is written before this file invoked we don't need to read post data */
    if (!empty($upnp_config['enable']) && !empty($upnp_config['iface_array'])) {
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
            $config_text .= "uuid=".upnp_uuid()."\n";
            $config_text .= "serial=".strtoupper(substr(upnp_uuid(),0,8))."\n";

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
            file_put_contents($config_file, $config_text);

            if (!upnp_running()) {
                /* if miniupnpd not running start it */
                log_error("miniupnpd: Starting service on interface: {$ifaces_active}");
                upnp_start();
            } else {
                /* or restart miniupnpd if settings were changed */
                log_error("miniupnpd: Restarting service on interface: {$ifaces_active}");
                upnp_stop();
                upnp_start();
            }
        }
    } else {
      /* user does not want miniupnpd running */
      /* lets stop the service and remove the config file */
      if (file_exists($config_file)) {
        if (empty($upnp_config['enable'])) {
            log_error("miniupnpd: Stopping service: miniupnpd disabled");
        } else {
            log_error("miniupnpd: Stopping service: no interfaces selected");
        }
        upnp_stop();
        @unlink($config_file);
      }
    }
}
