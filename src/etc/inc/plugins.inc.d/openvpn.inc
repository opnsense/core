<?php

/*
 * Copyright (C) 2016 Deciso B.V.
 * Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2006 Fernando Lemos
 * Copyright (C) 2005 Peter Allgeyer <allgeyer@web.de>
 * Copyright (C) 2004 Peter Curran <peter@closeconsultants.com>
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

function openvpn_configure()
{
    return array(
        'remote' => array('openvpn_configure_do'),
        'vpn' => array('openvpn_configure_do:2'),
    );
}

function openvpn_syslog()
{
    $logfacilities = array();

    $logfacilities['openvpn'] = array(
        'facility' => array('openvpn'),
        'remote' => 'vpn',
    );

    return $logfacilities;
}

function openvpn_services()
{
    global $config;

    $services = array();

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as $setting) {
                if (empty($setting['disable'])) {
                    $pconfig = array();
                    $pconfig['description'] = "OpenVPN {$mode}: " . htmlspecialchars($setting['description']);
                    $pconfig['pidfile'] = "/var/run/openvpn_{$mode}{$setting['vpnid']}.pid";
                    $pconfig['php']['restart'] = array('openvpn_configure_single');
                    $pconfig['php']['start'] = array('openvpn_configure_single');
                    $pconfig['php']['args'] = array('id');
                    $pconfig['id'] = $setting['vpnid'];
                    $pconfig['name'] = 'openvpn';
                    $services[] = $pconfig;
                }
            }
        }
    }

    return $services;
}

function openvpn_interfaces()
{
    global $config;

    $interfaces = array();

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as &$settings) {
                if (empty($settings['disable'])) {
                    $oic = array('enable' => true);
                    $oic['if'] = 'openvpn';
                    $oic['descr'] = 'OpenVPN';
                    $oic['type'] = 'group';
                    $oic['virtual'] = true;
                    $oic['networks'] = array();
                    $interfaces['openvpn'] = $oic;
                    break 2;
                }
            }
        }
    }

    return $interfaces;
}

function openvpn_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('OpenVPN'),
        'section' => 'openvpn',
        'id' => 'openvpn',
    );

    return $result;
}

function openvpn_verbosity_level()
{
    return array(
       0 => gettext('0 (none)'),
       1 => gettext('1 (default)'),
       2 => gettext('2'),
       3 => gettext('3 (recommended)'),
       4 => gettext('4'),
       5 => gettext('5'),
       6 => gettext('6'),
       7 => gettext('7'),
       8 => gettext('8'),
       9 => gettext('9'),
       10 => gettext('10'),
       11 => gettext('11'),
    );
}

function openvpn_compression_modes()
{
    return array(
        '' => gettext('No Preference'),
        'no' => gettext('Disabled - No Compression'),
        'adaptive' => gettext('Enabled with Adaptive Compression'),
        'yes' => gettext('Enabled without Adaptive Compression'),
    );
}

function openvpn_get_protocols()
{
    return array('UDP', 'UDP4', 'UDP6', 'TCP', 'TCP4', 'TCP6');
}

function openvpn_create_key()
{
    $fp = popen("/usr/local/sbin/openvpn --genkey --secret /dev/stdout 2>/dev/null", "r");
    if (!$fp) {
        return false;
    }
    $rslt = stream_get_contents($fp);
    pclose($fp);

    return $rslt;
}

function openvpn_vpnid_used($vpnid)
{
    global $config;

    if (isset($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as &$settings) {
            if ($vpnid == $settings['vpnid']) {
                return true;
            }
        }
    }

    if (isset($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as &$settings) {
            if ($vpnid == $settings['vpnid']) {
                return true;
            }
        }
    }

    return false;
}

function openvpn_vpnid_next()
{
    $vpnid = 1;
    while (openvpn_vpnid_used($vpnid)) {
        $vpnid++;
    }
    return $vpnid;
}

function openvpn_port_used($prot, $interface, $port, $curvpnid = 0)
{
    global $config;

    if (strlen($prot) > 3) {
        /* ignore "4" or "6" if given */
        $prot = substr($prot, 0, 3);
    }

    if (isset($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as & $settings) {
            if (isset($settings['disable'])) {
                continue;
            }

            if ($curvpnid != 0 && $curvpnid == $settings['vpnid']) {
                continue;
            }

            if ($port == $settings['local_port'] && strpos($settings['protocol'], $prot) === 0 &&
              ($interface == $settings['interface'] || $interface == "any" || $settings['interface'] == "any")) {
                return $settings['vpnid'];
            }
        }
    }

    if (isset($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as & $settings) {
            if (isset($settings['disable'])) {
                continue;
            }

            if ($curvpnid != 0 && $curvpnid == $settings['vpnid']) {
                continue;
            }

            if ($port == $settings['local_port'] && strpos($settings['protocol'], $prot) === 0 &&
              ($interface == $settings['interface'] || $interface == "any" || $settings['interface'] == "any")) {
                return $settings['vpnid'];
            }
        }
    }

    return 0;
}

function openvpn_port_next($prot, $interface = "wan")
{
    $port = 1194;

    while (openvpn_port_used($prot, $interface, $port)) {
        $port++;
    }

    while (openvpn_port_used($prot, "any", $port)) {
        $port++;
    }

    return $port;
}

function openvpn_get_cipherlist()
{
    $ciphers = array();
    exec('/usr/local/sbin/openvpn --show-ciphers', $lines);
    foreach ($lines as $line) {
        if (strstr($line, '(') !== false) {
            $cipher = explode(' ', $line)[0];
            $ciphers[$cipher] = $line;
        }
    }
    ksort($ciphers);
    $ciphers["none"] = gettext("None (No Encryption)");
    return $ciphers;
}

function openvpn_get_digestlist()
{
    $digests = array();
    exec('/usr/local/sbin/openvpn --show-digests', $lines);
    foreach ($lines as $line) {
        if (strstr($line, 'digest size') !== false) {
            $digest = explode(' ', $line)[0];
            $bits = explode(' ', explode('bit', $line)[0])[1];
            $digests[$digest] = $digest . " (".$bits."-bit)";
        }
    }
    ksort($digests);
    $digests["none"] = gettext("None (No Authentication)");
    return $digests;
}

function openvpn_get_engines()
{
    $openssl_engines = array('none' => 'No Hardware Crypto Acceleration');
    exec('/usr/local/bin/openssl engine -t -c 2> /dev/null', $openssl_engine_output);

    if (!count($openssl_engine_output)) {
        /* LibreSSL doesn't offer anything of value */
        return $openssl_engines;
    }

    $openssl_engine_output = implode("\n", $openssl_engine_output);
    $openssl_engine_output = preg_replace("/\\n\\s+/", "|", $openssl_engine_output);
    $openssl_engine_output = explode("\n", $openssl_engine_output);

    foreach ($openssl_engine_output as $oeo) {
        $keep = true;
        $details = explode("|", $oeo);
        $engine = array_shift($details);
        $linematch = array();
        preg_match("/\((.*)\)\s(.*)/", $engine, $linematch);
        foreach ($details as $dt) {
            if (strpos($dt, "unavailable") !== false) {
                $keep = false;
            }
            if (strpos($dt, "available") !== false) {
                continue;
            }
            if (strpos($dt, "[") !== false) {
                $ciphers = trim($dt, "[]");
            }
        }
        if (!empty($ciphers)) {
            $ciphers = " - " . $ciphers;
        }
        if (strlen($ciphers) > 60) {
            $ciphers = substr($ciphers, 0, 60) . " ... ";
        }
        if ($keep) {
            $openssl_engines[$linematch[1]] = $linematch[2] . $ciphers;
        }
    }
    return $openssl_engines;
}

function openvpn_validate_engine($engine)
{
    $engines = openvpn_get_engines();
    return array_key_exists($engine, $engines);
}

function openvpn_validate_port($value, $name)
{
    $value = trim($value);
    if (empty($value) || !is_numeric($value) || $value < 0 || ($value > 65535)) {
        return sprintf(gettext("The field '%s' must contain a valid port, ranging from 0 to 65535."), $name);
    }
    return false;
}

function openvpn_validate_cidr($value, $name, $multiple = false, $ipproto = 'ipv4', $allow_hosts = false)
{
    $error_multi = gettext("The field '%s' must contain only valid %s CIDR range(s) separated by commas.");
    $error_single = gettext("The field '%s' must contain a single valid %s CIDR range.");

    $value = trim($value);
    $error = false;
    if (empty($value)) {
        return false;
    }
    $networks = explode(',', $value);

    if (!$multiple && (count($networks) > 1)) {
        return sprintf($error_single, $name, $ipproto);
    }

    foreach ($networks as $network) {
        if ($ipproto == 'ipv4') {
            $error = !openvpn_validate_cidr_ipv4($network, $allow_hosts);
        } else {
            $error = !openvpn_validate_cidr_ipv6($network);
        }
        if ($error) {
            break;
        }
    }

    if ($error) {
        if (!$multiple) {
            return sprintf($error_single, $name, $ipproto);
        }

        return sprintf($error_multi, $name, $ipproto);
    }

    return false;
}

function openvpn_validate_cidr_ipv4($value, $allow_hosts = false)
{
    $value = trim($value);
    if (!empty($value)) {
        list($ip, $mask) = explode('/', $value);
        if (!is_ipaddrv4($ip) or !is_numeric($mask) or ($mask > 32) or ($mask < 0)) {
            return false;
        }
        /* IPv4 case is very strict, cannot be a host address */
        $mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
        if (!$allow_hosts && (ip2long($ip) & $mask) != ip2long($ip)) {
            return false;
        }
    }
    return true;
}

function openvpn_validate_cidr_ipv6($value)
{
    $value = trim($value);
    if (!empty($value)) {
        list($ipv6, $prefix) = explode('/', $value);
        if (empty($prefix)) {
            $prefix = '128';
        }
        if (!is_ipaddrv6($ipv6) or !is_numeric($prefix) or ($prefix > 128) or ($prefix < 0)) {
            return false;
        }
    }
    return true;
}

function openvpn_add_dhcpopts(&$settings, &$conf)
{
    if (!empty($settings['dns_domain'])) {
        $conf .= "push \"dhcp-option DOMAIN {$settings['dns_domain']}\"\n";
    }
    if (!empty($settings['dns_server1'])) {
        $conf .= "push \"dhcp-option DNS {$settings['dns_server1']}\"\n";
    }
    if (!empty($settings['dns_server2'])) {
        $conf .= "push \"dhcp-option DNS {$settings['dns_server2']}\"\n";
    }
    if (!empty($settings['dns_server3'])) {
        $conf .= "push \"dhcp-option DNS {$settings['dns_server3']}\"\n";
    }
    if (!empty($settings['dns_server4'])) {
        $conf .= "push \"dhcp-option DNS {$settings['dns_server4']}\"\n";
    }

    if (!empty($settings['push_register_dns'])) {
        $conf .= "push \"register-dns\"\n";
    }

    if (!empty($settings['ntp_server1'])) {
        $conf .= "push \"dhcp-option NTP {$settings['ntp_server1']}\"\n";
    }
    if (!empty($settings['ntp_server2'])) {
        $conf .= "push \"dhcp-option NTP {$settings['ntp_server2']}\"\n";
    }

    if (!empty($settings['netbios_enable'])) {
        if (!empty($settings['dhcp_nbttype']) && ($settings['dhcp_nbttype'] != 0)) {
            $conf .= "push \"dhcp-option NBT {$settings['dhcp_nbttype']}\"\n";
        }
        if (!empty($settings['dhcp_nbtscope'])) {
            $conf .= "push \"dhcp-option NBS {$settings['dhcp_nbtscope']}\"\n";
        }

        if (!empty($settings['wins_server1'])) {
            $conf .= "push \"dhcp-option WINS {$settings['wins_server1']}\"\n";
        }
        if (!empty($settings['wins_server2'])) {
            $conf .= "push \"dhcp-option WINS {$settings['wins_server2']}\"\n";
        }
    }

    if (!empty($settings['gwredir'])) {
        $conf .= "push \"redirect-gateway def1\"\n";
    }
}

function openvpn_add_custom(& $settings, & $conf)
{
    if (!empty($settings['custom_options'])) {
        $options = explode(';', $settings['custom_options']);
        if (is_array($options)) {
            foreach ($options as $option) {
                $conf .= "$option\n";
            }
        } else {
            $conf .= "{$settings['custom_options']}\n";
        }
    }
}

function openvpn_add_keyfile($data, &$conf, $mode_id, $directive, $opt = '')
{
    $fpath = "/var/etc/openvpn/{$mode_id}.{$directive}";
    openvpn_create_dirs();
    $data = str_replace("\r", "", base64_decode($data));
    file_put_contents($fpath, str_replace("\n\n", "\n", $data));
    //chown($fpath, 'nobody');
    //chgrp($fpath, 'nobody');
    @chmod($fpath, 0600);

    $conf .= "{$directive} {$fpath} {$opt}\n";
}

function openvpn_reconfigure($mode, $settings, $device_only = false)
{
    if (empty($settings)) {
        return;
    }

    openvpn_create_dirs();

    /*
     * NOTE: Deleting tap devices causes spontaneous reboots. Instead,
     * we use a vpnid number which is allocated for a particular client
     * or server configuration. ( see openvpn_vpnid_next() )
     */
    $vpnid = $settings['vpnid'];
    $mode_id = $mode.$vpnid;

    if (isset($settings['dev_mode'])) {
        $tunname = "{$settings['dev_mode']}{$vpnid}";
    } else {  /* defaults to tun */
        $tunname = "tun{$vpnid}";
        $settings['dev_mode'] = "tun";
    }

    if ($mode == "server") {
        $devname = "ovpns{$vpnid}";
    } else {
        $devname = "ovpnc{$vpnid}";
    }

    /* is our device already configured */
    if (!does_interface_exist($devname)) {
        /* create the tap device if required */
        if (!file_exists("/dev/{$tunname}")) {
            exec("/sbin/ifconfig " . escapeshellarg($tunname) . " create");
        }
        /* rename the device */
        mwexec("/sbin/ifconfig " . escapeshellarg($tunname) . " name " . escapeshellarg($devname));
        /* add the device to the openvpn group */
        mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " group openvpn");
    }

    if ($device_only || isset($settings['disable'])) {
        return;
    }

    $proto = strtolower($settings['protocol']);
    if (substr($settings['protocol'], 0, 3) == "TCP") {
        $proto = "{$proto}-{$mode}";
    }
    $dev_mode = $settings['dev_mode'];
    $cipher = $settings['crypto'];
    // OpenVPN defaults to SHA1, so use it when unset to maintain compatibility.
    $digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

    $interface = get_failover_interface($settings['interface']);
    $ipaddr = $settings['ipaddr'];

    // If a specific ip address (VIP) is requested, use it.
    // Otherwise, if a specific interface is requested, use it
    // If "any" interface was selected, local directive will be ommited.
    if (is_ipaddr($ipaddr)) {
        $iface_ip=$ipaddr;
    } elseif (!empty($interface) && $interface != "any") {
        if (stristr($settings['protocol'], "6") !== false) {
            $iface_ip = get_interface_ipv6($interface);
        } else {
            $iface_ip = get_interface_ip($interface);
        }
    }

    $conf = "dev {$devname}\n";
    if (isset($settings['verbosity_level'])) {
        $conf .= "verb {$settings['verbosity_level']}\n";
    }

    $conf .= "dev-type {$settings['dev_mode']}\n";
    switch ($settings['dev_mode']) {
        case "tun":
            if (!$settings['no_tun_ipv6']) {
                $conf .= "tun-ipv6\n";
            }
            break;
    }

    $conf .= "dev-node /dev/{$tunname}\n";
    $conf .= "writepid /var/run/openvpn_{$mode_id}.pid\n";
    $conf .= "#user nobody\n";
    $conf .= "#group nobody\n";
    $conf .= "script-security 3\n";
    $conf .= "daemon\n";
    $conf .= "keepalive 10 60\n";
    $conf .= "ping-timer-rem\n";
    $conf .= "persist-tun\n";
    $conf .= "persist-key\n";
    $conf .= "proto {$proto}\n";
    $conf .= "cipher {$cipher}\n";
    $conf .= "auth {$digest}\n";
    $conf .= "up /usr/local/etc/inc/plugins.inc.d/openvpn/ovpn-linkup\n";
    $conf .= "down /usr/local/etc/inc/plugins.inc.d/openvpn/ovpn-linkdown\n";

    switch ($settings['mode']) {
        case 'server_user':
        case 'server_tls_user':
            $conf .= "client-disconnect \"/usr/local/etc/inc/plugins.inc.d/openvpn/attributes.sh {$mode_id} \"\n";
            break;
        case 'server_tls':
        case 'p2p_tls':
            // For non user auth types setup client specific overrides, user authenticated ones are commissioned
            // using the auth script in option auth-user-pass-verify
            $conf .= "client-connect \"/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn_setup_cso.php {$mode_id}\"\n";
            $conf .= "client-disconnect \"/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn_cleanup_cso.php {$mode_id} \"\n";
        default:
            break;
    }

    if (!empty($iface_ip)) {
        $conf .= "local {$iface_ip}\n";
    }

    if (openvpn_validate_engine($settings['engine']) && ($settings['engine'] != "none")) {
        $conf .= "engine {$settings['engine']}\n";
    }

    // server specific settings
    if ($mode == 'server') {
        list($ip, $cidr) = explode('/', $settings['tunnel_network']);
        list($ipv6, $prefix) = explode('/', $settings['tunnel_networkv6']);
        $mask = gen_subnet_mask($cidr);

        // configure tls modes
        switch ($settings['mode']) {
            case 'p2p_tls':
            case 'server_tls':
            case 'server_user':
            case 'server_tls_user':
                $conf .= "tls-server\n";
                break;
        }

        // configure p2p/server modes
        switch ($settings['mode']) {
            case 'p2p_tls':
                // If the CIDR is less than a /30, OpenVPN will complain if you try to
                //  use the server directive. It works for a single client without it.
                //  See ticket #1417
                if (!empty($ip) && !empty($mask) && ($cidr < 30)) {
                    $conf .= "server {$ip} {$mask}\n";
                    $conf .= "client-config-dir /var/etc/openvpn-csc/".$vpnid."\n";
                    if (is_ipaddr($ipv6)) {
                        $conf .= "server-ipv6 {$ipv6}/{$prefix}\n";
                    }
                }
                /* XXX FALLTHROUGH */
            case 'p2p_shared_key':
                if (!empty($ip) && !empty($mask)) {
                    list($ip1, $ip2) = openvpn_get_interface_ip($ip, $mask);
                    if ($settings['dev_mode'] == 'tun') {
                        $conf .= "ifconfig {$ip1} {$ip2}\n";
                    } else {
                        $conf .= "ifconfig {$ip1} {$mask}\n";
                    }
                }
                if (!empty($ipv6) && !empty($prefix)) {
                    list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
                    if ($settings['dev_mode'] == 'tun') {
                        $conf .= "ifconfig-ipv6 {$ipv6_1} {$ipv6_2}\n";
                    } else {
                        $conf .= "ifconfig-ipv6 {$ipv6_1} {$prefix}\n";
                    }
                }
                break;
            case 'server_tls':
            case 'server_user':
            case 'server_tls_user':
                if (!empty($ip) && !empty($mask)) {
                    $conf .= "server {$ip} {$mask}\n";
                    if (is_ipaddr($ipv6)) {
                        $conf .= "server-ipv6 {$ipv6}/{$prefix}\n";
                    }
                    $conf .= "client-config-dir /var/etc/openvpn-csc/".$vpnid."\n";
                } else {
                    if ($settings['serverbridge_dhcp']) {
                        if (!empty($settings['serverbridge_interface']) &&
                            strcmp($settings['serverbridge_interface'], "none")) {
                            $biface_ip=get_interface_ip($settings['serverbridge_interface']);
                            $biface_sm=gen_subnet_mask(get_interface_subnet($settings['serverbridge_interface']));
                            if (is_ipaddrv4($biface_ip) && is_ipaddrv4($settings['serverbridge_dhcp_start']) &&
                                is_ipaddrv4($settings['serverbridge_dhcp_end'])) {
                                $conf .= "server-bridge {$biface_ip} {$biface_sm} " .
                                    "{$settings['serverbridge_dhcp_start']} {$settings['serverbridge_dhcp_end']}\n";
                                $conf .= "client-config-dir /var/etc/openvpn-csc/".$vpnid."\n";
                            } else {
                                $conf .= "mode server\n";
                            }
                        } else {
                            $conf .= "mode server\n";
                        }
                    }
                }
                break;
        }

        // configure user auth modes
        switch ($settings['mode']) {
            case 'server_user':
                $conf .= "verify-client-cert none\n";
                /* FALLTHROUGH */
            case 'server_tls_user':
                /* username-as-common-name is not compatible with server-bridge */
                if (stristr($conf, "server-bridge") === false && empty($settings['use-common-name'])) {
                    $conf .= "username-as-common-name\n";
                }
                if (!empty($settings['authmode'])) {
                    $strictusercn = "false";
                    if ($settings['strictusercn']) {
                        $strictusercn = "true";
                    }
                    $conf .= "auth-user-pass-verify \"/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn_auth_verify " .
                        "user '{$settings['authmode']}' '{$strictusercn}' '{$mode_id}'\" via-env\n";
                }
                break;
        }
        if (!isset($settings['cert_depth']) && (strstr($settings['mode'], 'tls'))) {
            $settings['cert_depth'] = 1;
        }
        if (is_numeric($settings['cert_depth'])) {
            if (($mode == 'client') && empty($settings['certref'])) {
                $cert = "";
            } else {
                $cert = lookup_cert($settings['certref']);
                /* XXX: Seems not used at all! */
                $servercn = urlencode(cert_get_cn($cert['crt']));
                $conf .= "tls-verify \"/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn_auth_verify " .
                    "tls '{$servercn}' {$settings['cert_depth']}\"\n";
            }
        }

        // The local port to listen on
        $conf .= "lport {$settings['local_port']}\n";

        // The management port to listen on
        $conf .= "management /var/etc/openvpn/{$mode_id}.sock unix\n";

        if ($settings['maxclients']) {
            $conf .= "max-clients {$settings['maxclients']}\n";
        }

        // Can we push routes
        if ($settings['local_network']) {
            $conf .= openvpn_gen_routes($settings['local_network'], "ipv4", true);
        }
        if ($settings['local_networkv6']) {
            $conf .= openvpn_gen_routes($settings['local_networkv6'], "ipv6", true);
        }

        switch ($settings['mode']) {
            case 'server_tls':
            case 'server_user':
            case 'server_tls_user':
                // Configure client dhcp options
                openvpn_add_dhcpopts($settings, $conf);
                if ($settings['client2client']) {
                    $conf .= "client-to-client\n";
                }
                break;
        }
        if (isset($settings['duplicate_cn'])) {
            $conf .= "duplicate-cn\n";
        }
    }

    // client specific settings
    if ($mode == 'client') {
        // configure p2p mode
        switch ($settings['mode']) {
            case 'p2p_tls':
                $conf .= "tls-client\n";
                /* XXX FALLTHROUGH */
            case 'shared_key':
                $conf .= "client\n";
                break;
        }

        // If there is no bind option at all (ip and/or port), add "nobind" directive
        //  Otherwise, use the local port if defined, failing that, use lport 0 to
        //  ensure a random source port.
        if ((empty($iface_ip)) && (!$settings['local_port'])) {
            $conf .= "nobind\n";
        } elseif ($settings['local_port']) {
            $conf .= "lport {$settings['local_port']}\n";
        } else {
            $conf .= "lport 0\n";
        }

        // Use unix socket to overcome the problem on any type of server
        $conf .= "management /var/etc/openvpn/{$mode_id}.sock unix\n";

        // The remote server
        $server_addr_a = explode(',', $settings['server_addr']);
        $server_port_a = explode(',', $settings['server_port']);
        foreach ($server_addr_a as $i => $unused) {
            $conf .= "remote {$server_addr_a[$i]} {$server_port_a[$i]}\n";
        }

        if (!empty($settings['use_shaper'])) {
            $conf .= "shaper {$settings['use_shaper']}\n";
        }

        if (!empty($settings['tunnel_network'])) {
            list($ip, $mask) = explode('/', $settings['tunnel_network']);
            $mask = gen_subnet_mask($mask);
            list($ip1, $ip2) = openvpn_get_interface_ip($ip, $mask);
            if ($settings['dev_mode'] == 'tun') {
                $conf .= "ifconfig {$ip2} {$ip1}\n";
            } else {
                $conf .= "ifconfig {$ip2} {$mask}\n";
            }
        }

        if (!empty($settings['tunnel_networkv6'])) {
            list($ipv6, $prefix) = explode('/', $settings['tunnel_networkv6']);
            list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
            if ($settings['dev_mode'] == 'tun') {
                $conf .= "ifconfig-ipv6 {$ipv6_2} {$ipv6_1}\n";
            } else {
                $conf .= "ifconfig-ipv6 {$ipv6_2} {$prefix}\n";
            }
        }

        if ($settings['auth_user'] && $settings['auth_pass']) {
            $up_file = "/var/etc/openvpn/{$mode_id}.up";
            $conf .= "auth-user-pass {$up_file}\n";
            $userpass = "{$settings['auth_user']}\n";
            $userpass .= "{$settings['auth_pass']}\n";
            file_put_contents($up_file, $userpass);
        }

        if ($settings['proxy_addr']) {
            $conf .= "http-proxy {$settings['proxy_addr']} {$settings['proxy_port']}";
            if ($settings['proxy_authtype'] != "none") {
                $conf .= " /var/etc/openvpn/{$mode_id}.pas {$settings['proxy_authtype']}";
                $proxypas = "{$settings['proxy_user']}\n";
                $proxypas .= "{$settings['proxy_passwd']}\n";
                file_put_contents("/var/etc/openvpn/{$mode_id}.pas", $proxypas);
            }
            $conf .= " \n";
        }
    }

    // Add remote network route if set
    if (openvpn_validate_cidr($settings['remote_network'], "", true, "ipv4") === false) {
        $conf .= openvpn_gen_routes($settings['remote_network'], "ipv4", false);
    }
    if (openvpn_validate_cidr($settings['remote_networkv6'], "", true, "ipv6") === false) {
        $conf .= openvpn_gen_routes($settings['remote_networkv6'], "ipv6", false);
    }

    // Write the settings for the keys
    switch ($settings['mode']) {
        case 'p2p_shared_key':
            openvpn_add_keyfile($settings['shared_key'], $conf, $mode_id, "secret");
            break;
        case 'p2p_tls':
        case 'server_tls':
        case 'server_tls_user':
        case 'server_user':
            $ca = lookup_ca($settings['caref']);
            openvpn_add_keyfile($ca['crt'], $conf, $mode_id, "ca");

            if (!empty($settings['certref'])) {
                $cert = lookup_cert($settings['certref']);
                openvpn_add_keyfile($cert['crt'], $conf, $mode_id, "cert");
                openvpn_add_keyfile($cert['prv'], $conf, $mode_id, "key");
            }
            if ($mode == 'server') {
                $conf .= "dh /usr/local/etc/dh-parameters.{$settings['dh_length']}\n";
            }
            if (!empty($settings['crlref'])) {
                $crl = lookup_crl($settings['crlref']);
                crl_update($crl);
                openvpn_add_keyfile($crl['text'], $conf, $mode_id, "crl-verify");
            }
            if ($settings['tls']) {
                if ($mode == "server") {
                    $tlsopt = 0;
                } else {
                    $tlsopt = 1;
                }
                openvpn_add_keyfile($settings['tls'], $conf, $mode_id, "tls-auth", $tlsopt);
            }
            break;
    }

    if (!empty($settings['compression'])) {
        $conf .= "comp-lzo {$settings['compression']}\n";
    }

    if ($settings['passtos']) {
        $conf .= "passtos\n";
    }

    if ($settings['dynamic_ip']) {
        $conf .= "persist-remote-ip\n";
        $conf .= "float\n";
    }

    if ($settings['topology_subnet']) {
        $conf .= "topology subnet\n";
    }

    if ($mode == "client") {
        if ($settings['route_no_pull']) {
            $conf .= "route-nopull\n";
        }

        if ($settings['route_no_exec']) {
            $conf .= "route-noexec\n";
        }

        if ($settings['resolve_retry']) {
            $conf .= "resolv-retry infinite\n";
        }

        if ($settings['remote_random']) {
            $conf .= "remote-random\n";
        }
    }

    if (isset($settings['reneg-sec']) && $settings['reneg-sec'] != '') {
        $conf .= "reneg-sec {$settings['reneg-sec']}\n";
    }

    openvpn_add_custom($settings, $conf);

    openvpn_create_dirs();
    $fpath = "/var/etc/openvpn/{$mode_id}.conf";
    file_put_contents($fpath, $conf);
    unset($conf);
    $fpath = "/var/etc/openvpn/{$mode_id}.interface";
    file_put_contents($fpath, $interface);
    //chown($fpath, 'nobody');
    //chgrp($fpath, 'nobody');
    @chmod("/var/etc/openvpn/{$mode_id}.conf", 0600);
    @chmod("/var/etc/openvpn/{$mode_id}.interface", 0600);
    @chmod("/var/etc/openvpn/{$mode_id}.key", 0600);
    @chmod("/var/etc/openvpn/{$mode_id}.tls-auth", 0600);
    @chmod("/var/etc/openvpn/{$mode_id}.conf", 0600);
}

function openvpn_restart($mode, $settings)
{
    $vpnid = $settings['vpnid'];
    $mode_id = $mode.$vpnid;

    killbypid("/var/run/openvpn_{$mode_id}.pid", 'TERM', true);

    if (isset($settings['disable'])) {
        return;
    }

    /* Do not start a client if we are a CARP backup on this vip! */
    if (($mode == "client") && (strstr($settings['interface'], "_vip") &&
        get_carp_interface_status($settings['interface']) == "BACKUP")) {
        return;
    }

    /* Check if client is bound to a gateway group */
    $a_groups = return_gateway_groups_array();
    if (is_array($a_groups[$settings['interface']])) {
        /* the interface is a gateway group. If a vip is defined and its a CARP backup then do not start */
        if (($a_groups[$settings['interface']][0]['vip'] <> "") &&
            get_carp_interface_status($a_groups[$settings['interface']][0]['vip']) == "BACKUP") {
            return;
        }
    }

    /* start the new process */
    $fpath = "/var/etc/openvpn/{$mode_id}.conf";
    openvpn_clear_route($mode, $settings);
    mwexec_bg("/usr/local/sbin/openvpn --config " . escapeshellarg($fpath));

    if (!file_exists("/var/run/booting")) {
        configd_run("filter reload");
    }
}

function openvpn_delete($mode, &$settings)
{
    $vpnid = $settings['vpnid'];
    $mode_id = $mode.$vpnid;

    if (isset($settings['dev_mode'])) {
        $tunname = "{$settings['dev_mode']}{$vpnid}";
    } else {  /* defaults to tun */
        $tunname = "tun{$vpnid}";
    }

    if ($mode == "server") {
        $devname = "ovpns{$vpnid}";
    } else {
        $devname = "ovpnc{$vpnid}";
    }

    /* kill the process if running */
    killbypid("/var/run/openvpn_{$mode_id}.pid");

    /* remove the device from the openvpn group */
    mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " -group openvpn");

    /* restore the original adapter name */
    mwexec("/sbin/ifconfig " . escapeshellarg($devname) . " name " . escapeshellarg($tunname));

    /* remove the configuration files */
    @array_map('unlink', glob("/var/etc/openvpn/{$mode_id}.*"));
}

/**
 * generate config (text) data for a single client specific override
 * @param array $settings csc item
 * @param array $server openvpn server item
 * @return string|boolean filename or false when unable to (missing common name or vpnid)
 */
function openvpn_csc_conf_write($settings, $server)
{
    if (empty($settings['common_name']) || empty($server['vpnid'])) {
        return false;
    }
    $conf = '';
    if (!empty($settings['block'])) {
        $conf .= "disable\n";
    }

    if (!empty($settings['push_reset'])) {
        $conf .= "push-reset\n";
    }

    if (!empty($settings['tunnel_network'])) {
        list($ip, $mask) = explode('/', $settings['tunnel_network']);
        if ($server['dev_mode'] == 'tun' && empty($server['topology_subnet'])) {
            $baselong = ip2long32($ip) & gen_subnet_mask_long($mask);
            $serverip = long2ip32($baselong + 1);
            $clientip = long2ip32($baselong + 2);
            $conf .= "ifconfig-push {$clientip} {$serverip}\n";
        } else {
            $conf .= "ifconfig-push {$ip} " . gen_subnet_mask($mask) . "\n";
        }
    }
    if (!empty($settings['tunnel_networkv6'])) {
        list($ipv6, $prefix) = explode('/', $settings['tunnel_networkv6']);
        list($ipv6_1, $ipv6_2) = openvpn_get_interface_ipv6($ipv6, $prefix);
        if ($server['dev_mode'] == 'tun' && empty($server['topology_subnet'])) {
            $conf .= "ifconfig-ipv6-push {$ipv6_2} {$ipv6_1}\n";
        } else {
            $conf .= "ifconfig-ipv6-push {$ipv6_2} {$prefix}\n";
        }
    }

    if (!empty($settings['local_network'])) {
        $conf .= openvpn_gen_routes($settings['local_network'], "ipv4", true);
    }
    if (!empty($settings['local_networkv6'])) {
        $conf .= openvpn_gen_routes($settings['local_networkv6'], "ipv6", true);
    }

    // Add a remote network iroute if set
    if (!empty($settings['remote_network'])
      && openvpn_validate_cidr($settings['remote_network'], "", true, "ipv4") === false) {
        $conf .= openvpn_gen_routes($settings['remote_network'], "ipv4", false, true);
    }
    // Add a remote network iroute if set
    if (!empty($settings['remote_networkv6'])
      && openvpn_validate_cidr($settings['remote_networkv6'], "", true, "ipv6") === false) {
        $conf .= openvpn_gen_routes($settings['remote_networkv6'], "ipv6", false, true);
    }

    openvpn_add_dhcpopts($settings, $conf);

    if (!empty($settings['gwredir'])) {
        $conf .= "push \"redirect-gateway def1\"\n";
    }

    openvpn_add_custom($settings, $conf);

    $vpnid = filter_var($server['vpnid'], FILTER_SANITIZE_NUMBER_INT);
    $target_filename = "/var/etc/openvpn-csc/".$vpnid."/".$settings['common_name'];
    if (!empty($conf)) {
        file_put_contents($target_filename, $conf);
        chown($target_filename, 'nobody');
        chgrp($target_filename, 'nobody');
        return $target_filename;
    } elseif (is_file($target_filename)) {
        unlink($target_filename);
        return null;
    }
}

/**
 * Construct a single array containing all client specific overrides per server and common name
 * @return array [vpnid][common_name] = settings
 */
function openvpn_fetch_csc_list()
{
    global $config;
    $result = array();
    if (!empty($config['openvpn']['openvpn-csc']) && is_array($config['openvpn']['openvpn-csc'])) {
        $all_servers = array_keys(openvpn_get_remote_access_servers());
        foreach ($config['openvpn']['openvpn-csc'] as $settings) {
            if (!isset($settings['disable'])) {
                if (!empty($settings['ovpn_servers'])) {
                    $ovpn_servers = explode(',', $settings['ovpn_servers']);
                } else {
                    $ovpn_servers = $all_servers;
                }
                foreach ($ovpn_servers as $vpnid) {
                    if (in_array($vpnid, $all_servers)) {
                        $vpnid = filter_var($vpnid, FILTER_SANITIZE_NUMBER_INT);
                        if (!isset($result[$vpnid])) {
                            $result[$vpnid] = array();
                        }
                        $result[$vpnid][$settings['common_name']] = $settings;
                    }
                }
            }
        }
    }
    return $result;
}

function openvpn_prepare_all($verbose = false)
{
    global $config;

    if (!isset($config['openvpn'])) {
        return;
    }

    if ($verbose) {
        echo 'Creating OpenVPN instances...';
        flush();
    }

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as &$settings) {
                openvpn_reconfigure($mode, $settings, true);
            }
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function openvpn_configure_interface($interface)
{
    global $config;

    if (substr($interface, 0, 5) == 'ovpns') {
        if (isset($config['openvpn']['openvpn-server'])) {
            foreach ($config['openvpn']['openvpn-server'] as $server) {
                if ($interface == "ovpns{$server['vpnid']}") {
                    log_error("OpenVPN: Resync server {$server['description']}");
                    openvpn_reconfigure('server', $server);
                    openvpn_restart('server', $server);
                }
            }
            unset($server);
        }
    } elseif (substr($interface, 0, 5) == 'ovpnc') {
        if (isset($config['openvpn']['openvpn-client'])) {
            foreach ($config['openvpn']['openvpn-client'] as $client) {
                if ($interface == "ovpnc{$client['vpnid']}") {
                    log_error("OpenVPN: Resync server {$client['description']}");
                    openvpn_reconfigure('client', $client);
                    openvpn_restart('client', $client);
                }
            }
            unset($client);
        }
    }
}

function openvpn_configure_single($id)
{
    global $config;

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as &$settings) {
                if ($id != $settings['vpnid']) {
                    continue;
                }
                openvpn_reconfigure($mode, $settings);
                openvpn_restart($mode, $settings);
                return;
            }
        }
    }
}

function openvpn_configure_do($verbose = false, $interface = '')
{
    global $config;

    openvpn_create_dirs();

    if (!isset($config['openvpn'])) {
        return;
    }

    if (!empty($interface)) {
        $interface_real = get_real_interface($interface);
        if (substr($interface_real, 0, 4) == 'ovpn') {
            return;
        }
        log_error(sprintf(
            'Resyncing OpenVPN instances for interface %s.',
            convert_friendly_interface_to_friendly_descr($interface)
        ));
    } else {
        log_error('Resyncing OpenVPN instances.');
    }

    if ($verbose) {
        echo 'Syncing OpenVPN settings...';
        flush();
    }

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as &$settings) {
                if (empty($interface) || $interface == $settings['interface']) {
                    openvpn_reconfigure($mode, $settings);
                    openvpn_restart($mode, $settings);
                }
            }
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function openvpn_get_active_servers($type = 'multipoint')
{
    global $config;

    $servers = array();
    if (isset($config['openvpn']['openvpn-server']) && is_array($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as & $settings) {
            if (empty($settings) || isset($settings['disable'])) {
                continue;
            }

            $prot = $settings['protocol'];
            $port = $settings['local_port'];

            $server = array();
            $server['port'] = ($settings['local_port']) ? $settings['local_port'] : 1194;
            $server['mode'] = $settings['mode'];
            if ($settings['description']) {
                $server['name'] = "{$settings['description']} {$prot}:{$port}";
            } else {
                $server['name'] = "Server {$prot}:{$port}";
            }
            $server['conns'] = array();
            $server['vpnid'] = $settings['vpnid'];
            $server['mgmt'] = "server{$server['vpnid']}";
            $socket = "unix:///var/etc/openvpn/{$server['mgmt']}.sock";
            list($tn, $sm) = explode('/', $settings['tunnel_network']);

            if ((($server['mode'] == "p2p_shared_key") || ($sm >= 30) ) && ($type == "p2p")) {
                $servers[] = openvpn_get_client_status($server, $socket);
            } elseif (($server['mode'] != "p2p_shared_key") && ($type == "multipoint") && ($sm < 30)) {
                $servers[] = openvpn_get_server_status($server, $socket);
            }
        }
    }
    return $servers;
}

function openvpn_get_server_status($server, $socket)
{
    $errval;
    $errstr;
    $fp = @stream_socket_client($socket, $errval, $errstr, 1);
    if ($fp) {
        stream_set_timeout($fp, 1);

        /* send our status request */
        fputs($fp, "status 3\n");

        /* recv all response lines */
        while (!feof($fp)) {
            /* read the next line */
            $line = fgets($fp, 1024);
            $info = stream_get_meta_data($fp);
            if ($info['timed_out']) {
                break;
            }
            /* parse header list line */
            if (strstr($line, "HEADER")) {
                continue;
            }
            /* parse end of output line */
            if (strstr($line, "END") || strstr($line, "ERROR")) {
                break;
            }
            /* parse client list line */
            if (strstr($line, "CLIENT_LIST")) {
                $list = explode("\t", $line);
                $conn = array();
                $conn['common_name'] = $list[1];
                $conn['remote_host'] = $list[2];
                $conn['virtual_addr'] = $list[3];
                $conn['bytes_recv'] = $list[5];
                $conn['bytes_sent'] = $list[6];
                $conn['connect_time'] = date('Y-m-d H:i:s', $list[8]);
                $server['conns'][] = $conn;
            }
            /* parse routing table lines */
            if (strstr($line, "ROUTING_TABLE")) {
                $list = explode("\t", $line);
                $conn = array();
                $conn['virtual_addr'] = $list[1];
                $conn['common_name'] = $list[2];
                $conn['remote_host'] = $list[3];
                $conn['last_time'] = $list[4];
                $server['routes'][] = $conn;
            }
        }
        /* cleanup */
        fclose($fp);
    } else {
        $conn = array();
        $conn['common_name'] = "[error]";
        $conn['remote_host'] = "Unable to contact daemon";
        $conn['virtual_addr'] = "Service not running?";
        $conn['bytes_recv'] = 0;
        $conn['bytes_sent'] = 0;
        $conn['connect_time'] = 0;
        $server['conns'][] = $conn;
    }
    return $server;
}

function openvpn_get_active_clients()
{
    global $config;

    $clients = array();
    if (isset($config['openvpn']['openvpn-client']) && is_array($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as & $settings) {
            if (empty($settings) || isset($settings['disable'])) {
                continue;
            }

            $prot = $settings['protocol'];
            $port = ($settings['local_port']) ? ":{$settings['local_port']}" : "";

            $client = array();
            $client['port'] = $settings['local_port'];
            if ($settings['description']) {
                $client['name'] = "{$settings['description']} {$prot}{$port}";
            } else {
                $client['name'] = "Client {$prot}{$port}";
            }

            $client['vpnid'] = $settings['vpnid'];
            $client['mgmt'] = "client{$client['vpnid']}";
            $socket = "unix:///var/etc/openvpn/{$client['mgmt']}.sock";
            $client['status']="down";
            $clients[] = openvpn_get_client_status($client, $socket);
        }
    }
    return $clients;
}

function openvpn_get_client_status($client, $socket)
{
    $errval;
    $errstr;
    $fp = @stream_socket_client($socket, $errval, $errstr, 1);
    if ($fp) {
        stream_set_timeout($fp, 1);
        /* send our status request */
        fputs($fp, "state all\n");

        /* recv all response lines */
        while (!feof($fp)) {
            /* read the next line */
            $line = fgets($fp, 1024);

            $info = stream_get_meta_data($fp);
            if ($info['timed_out']) {
                break;
            }

            /* Get the client state */
            $list = explode(",", $line);
            if (count($list) > 1) {
                $client['connect_time'] = date('Y-m-d H:i:s', $list[0]);
            }
            if (strstr($line, "CONNECTED")) {
                $client['status']="up";
                $client['virtual_addr'] = $list[3];
                $client['remote_host'] = $list[4];
            } elseif (strstr($line, "CONNECTING")) {
                $client['status']="connecting";
            } elseif (strstr($line, "ASSIGN_IP")) {
                $client['status'] = "waiting";
                $client['virtual_addr'] = $list[3];
            } elseif (strstr($line, "RECONNECTING")) {
                $client['status'] = "reconnecting";
                $client['status'] .= "; " . $list[2];
            } elseif (strstr($line, "END") || strstr($line, "ERROR")) {
                /* parse end of output line */
                break;
            }
        }

        /* If up, get read/write stats */
        if (strcmp($client['status'], "up") == 0) {
            fputs($fp, "status 2\n");
            /* recv all response lines */
            while (!feof($fp)) {
                /* read the next line */
                $line = fgets($fp, 1024);

                $info = stream_get_meta_data($fp);
                if ($info['timed_out']) {
                    break;
                }

                $list = explode(",", $line);
                if (strstr($line, "TCP/UDP read bytes")) {
                    $client['bytes_recv'] = $list[1];
                } elseif (strstr($line, "TCP/UDP write bytes")) {
                    $client['bytes_sent'] = $list[1];
                } elseif (strstr($line, "END")) {
                    /* parse end of output line */
                    break;
                }
            }
        }
        fclose($fp);
    } else {
        $DisplayNote=true;
        $client['remote_host'] = "Unable to contact daemon";
        $client['virtual_addr'] = "Service not running?";
        $client['bytes_recv'] = 0;
        $client['bytes_sent'] = 0;
        $client['connect_time'] = 0;
    }
    return $client;
}

function openvpn_create_dirs()
{
    @mkdir('/var/etc/openvpn-csc', 0750);
    @mkdir('/var/etc/openvpn', 0750);
    foreach (openvpn_get_remote_access_servers() as $server) {
        $vpnid = filter_var($server['vpnid'], FILTER_SANITIZE_NUMBER_INT);
        $csc_path = '/var/etc/openvpn-csc/'.$vpnid;
        if (is_file($csc_path)) {
            // if the vpnid exists as file, remove it first
            unlink($csc_path);
        }
        @mkdir($csc_path, 0750);
    }
}

function openvpn_get_interface_ip($ip, $mask)
{
    $baselong = ip2long32($ip) & ip2long($mask);
    $ip1 = long2ip32($baselong + 1);
    $ip2 = long2ip32($baselong + 2);
    return array($ip1, $ip2);
}

function openvpn_get_interface_ipv6($ipv6, $prefix)
{
    $basev6 = gen_subnetv6($ipv6, $prefix);
    // Is there a better way to do this math?
    $ipv6_arr = explode(':', $basev6);
    $last = hexdec(array_pop($ipv6_arr));
    $ipv6_1 = Net_IPv6::compress(Net_IPv6::uncompress(implode(':', $ipv6_arr) . ':' . dechex($last + 1)));
    $ipv6_2 = Net_IPv6::compress(Net_IPv6::uncompress(implode(':', $ipv6_arr) . ':' . dechex($last + 2)));
    return array($ipv6_1, $ipv6_2);
}

function openvpn_clear_route($mode, $settings)
{
    if (empty($settings['tunnel_network'])) {
        return;
    }
    list($ip, $cidr) = explode('/', $settings['tunnel_network']);
    $mask = gen_subnet_mask($cidr);
    $clear_route = false;

    switch ($settings['mode']) {
        case 'shared_key':
            $clear_route = true;
            break;
        case 'p2p_tls':
        case 'p2p_shared_key':
            if ($cidr == 30) {
                $clear_route = true;
            }
            break;
    }

    if ($clear_route && !empty($ip) && !empty($mask)) {
        list($ip1, $ip2) = openvpn_get_interface_ip($ip, $mask);
        $ip_to_clear = ($mode == "server") ? $ip1 : $ip2;
        /* XXX: Family for route? */
        mwexec("/sbin/route -q delete {$ip_to_clear}");
    }
}

function openvpn_gen_routes($value, $ipproto = "ipv4", $push = false, $iroute = false)
{
    $routes = "";
    if (empty($value)) {
        return "";
    }
    $networks = explode(',', $value);

    foreach ($networks as $network) {
        if ($ipproto == "ipv4") {
            $route = openvpn_gen_route_ipv4($network, $iroute);
        } else {
            $route = openvpn_gen_route_ipv6($network, $iroute);
        }
        if ($push) {
            $routes .= "push \"{$route}\"\n";
        } else {
            $routes .= "{$route}\n";
        }
    }
    return $routes;
}

function openvpn_gen_route_ipv4($network, $iroute = false)
{
    $i = ($iroute) ? "i" : "";
    list($ip, $mask) = explode('/', trim($network));
    $mask = gen_subnet_mask($mask);
    return "{$i}route $ip $mask";
}

function openvpn_gen_route_ipv6($network, $iroute = false)
{
    $i = ($iroute) ? "i" : "";
    list($ipv6, $prefix) = explode('/', trim($network));
    if (empty($prefix)) {
        $prefix = "128";
    }
    return "{$i}route-ipv6 ${ipv6}/${prefix}";
}

/**
 * Retrieve a list of remote access servers, indexed by vpnid
 */
function openvpn_get_remote_access_servers()
{
    global $config;
    $result = array();

    if (!empty($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as $server) {
            if (in_array($server['mode'], array('server_tls', 'server_user', 'server_tls_user', 'p2p_tls'))) {
                $result[$server['vpnid']] = $server;
            }
        }
    }
    return $result;
}

// Resync and restart all VPNs using a gateway group.
function openvpn_configure_gwgroup($gwgroupname = '')
{
    global $config;

    if (empty($gwgroupname)) {
        log_error("openvpn_configure_gwgroup() called without gwgroup parameter.");
        return;
    }

    if (isset($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as &$settings) {
            if ($gwgroupname == $settings['interface']) {
                log_error("Resyncing OpenVPN server for gateway group " .
                    $gwgroupname . " server " . $settings["description"] . ".");
                openvpn_reconfigure('server', $settings);
                openvpn_restart('server', $settings);
            }
        }
    }

    if (isset($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as &$settings) {
            if ($gwgroupname == $settings['interface']) {
                log_error("Resyncing OpenVPN client for gateway group " .
                    $gwgroupname . " client " . $settings["description"] . ".");
                openvpn_reconfigure('client', $settings);
                openvpn_restart('client', $settings);
            }
        }
    }
}

function openvpn_refresh_crls()
{
    global $config;

    openvpn_create_dirs();

    if (isset($config['openvpn']['openvpn-server']) && is_array($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as $settings) {
            if (empty($settings) || isset($settings['disable'])) {
                continue;
            }
            // Write the settings for the keys
            switch ($settings['mode']) {
                case 'p2p_tls':
                case 'server_tls':
                case 'server_tls_user':
                case 'server_user':
                    if (!empty($settings['crlref'])) {
                        $crl = lookup_crl($settings['crlref']);
                        crl_update($crl);
                        $fpath = "/var/etc/openvpn/server{$settings['vpnid']}.crl-verify";
                        file_put_contents($fpath, base64_decode($crl['text']));
                        @chmod($fpath, 0644);
                    }
                    break;
            }
        }
    }
}

function openvpn_resync_if_needed($mode, $ovpn_settings, $interface)
{
    global $config;

    $resync_needed = true;
    if (isset($ovpn_settings['disable'])) {
        $resync_needed = false;
    } else {
        if (!empty($interface)) {
            $mode_id = $mode . $ovpn_settings['vpnid'];
            $fpath = "/var/etc/openvpn/{$mode_id}.interface";
            if (file_exists($fpath)) {
                $current_device = file_get_contents($fpath);
                $current_device = trim($current_device, " \t\n");
                $new_device = get_failover_interface($ovpn_settings['interface']);
                if (isset($config['interfaces'][$interface])) {
                    $this_device = $config['interfaces'][$interface]['if'];
                    if (($current_device == $new_device) && ($current_device != $this_device)) {
                        $resync_needed = false;
                    }
                }
            }
        }
    }
    if ($resync_needed == true) {
        log_error("OpenVPN: Resync " . $mode_id . " " . $ovpn_settings['description']);
        openvpn_reconfigure($mode, $ovpn_settings);
        openvpn_restart($mode, $ovpn_settings);
    }
}

function openvpn_carp_stop($carp_iface)
{
    global $config;

    $friendly_descr = convert_friendly_interface_to_friendly_descr($carp_iface);

    if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as $settings) {
            if ($settings['interface'] == $carp_iface) {
                log_error("Stopping OpenVPN client instance on {$friendly_descr} " .
                    'because of transition to CARP backup.');
                openvpn_restart('client', $settings);
            }
        }
    }
}

function openvpn_carp_start($carp_iface)
{
    global $config;

    $friendly_descr = convert_friendly_interface_to_friendly_descr($carp_iface);

    if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as $settings) {
            if ($settings['interface'] == $carp_iface) {
                log_error("Starting OpenVPN client instance on {$friendly_descr} " .
                    'because of transition to CARP master.');
                openvpn_restart('client', $settings);
            }
        }
    }
    if (is_array($config['openvpn']) && is_array($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as $settings) {
            if ($settings['interface'] == $carp_iface) {
                log_error("Starting OpenVPN server instance on {$friendly_descr} " .
                    'because of transition to CARP master.');
                openvpn_restart('server', $settings);
            }
        }
    }
}
