#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2018 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
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

/*
 * OpenVPN calls this script to authenticate a user
 * based on a username and password. We lookup these
 * in our config.xml file and check the credentials.
 */

require_once("config.inc");
require_once("auth.inc");
require_once("util.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/openvpn.inc");

function get_openvpn_server($serverid)
{
    global $config;
    if (isset($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as $server) {
            if ("server{$server['vpnid']}" == $serverid) {
                return $server;
            }
        }
    }
    return null;
}

function parse_auth_properties($props)
{
    $result = array();
    if (!empty($props['Framed-IP-Address']) && !empty($props['Framed-IP-Netmask'])) {
        $cidrmask = 32-log((ip2long($props['Framed-IP-Netmask']) ^ ip2long('255.255.255.255'))+1, 2);
        $result['tunnel_network'] = $props['Framed-IP-Address'] . "/" . $cidrmask;
    }
    if (!empty($props['Framed-Route']) && is_array($props['Framed-Route'])) {
        $result['local_network'] = implode(",", $props['Framed-Route']);
    }
    return $result;
}

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

if (count($argv) > 6) {
    $authmodes = explode(',', $argv[5]);
    $username = base64_decode(str_replace('%3D', '=', $argv[1]));
    $password = base64_decode(str_replace('%3D', '=', $argv[2]));
    $common_name = $argv[3];
    $modeid = $argv[6];
    $strictusercn = $argv[4] == 'false' ? false : true;

    $a_server = get_openvpn_server($modeid);

    // primary input validation
    $error_message = null;
    if (($strictusercn === true) && ($common_name != $username)) {
        $error_message = sprintf(
            "Username does not match certificate common name (%s != %s), access denied.",
            $username,
            $common_name
        );
    } elseif (!is_array($authmodes)) {
        $error_message = 'No authentication server has been selected to authenticate against. ' .
            "Denying authentication for user {$username}";
    } elseif ($a_server == null) {
        $error_message = "OpenVPN '$modeid' was not found. Denying authentication for user {$username}";
    } elseif (!empty($a_server['local_group']) && !in_array($a_server['local_group'], getUserGroups($username))) {
        $error_message = "OpenVPN '$modeid' requires the local group {$a_server['local_group']}. " .
            "Denying authentication for user {$username}";
    }
    if ($error_message != null) {
        syslog(LOG_WARNING, $error_message);
        closelog();
        exit(1);
    }

    if (file_exists("/var/etc/openvpn/{$modeid}.ca")) {
        putenv("LDAPTLS_CACERT=/var/etc/openvpn/{$modeid}.ca");
        putenv("LDAPTLS_REQCERT=never");
    }

    // perform the actual authentication
    $authFactory = new OPNsense\Auth\AuthenticationFactory;
    foreach ($authmodes as $authName) {
        $authenticator = $authFactory->get($authName);
        if ($authenticator) {
            if ($authenticator->authenticate($username, $password)) {
                $vpnid = filter_var($a_server['vpnid'], FILTER_SANITIZE_NUMBER_INT);
                // fetch or  create client specif override
                $all_cso = openvpn_fetch_csc_list();
                if (!empty($all_cso[$vpnid][$username])) {
                    $cso = $all_cso[$vpnid][$username];
                } else {
                    $cso = array("common_name" => $username);
                }
                $cso = array_merge($cso, parse_auth_properties($authenticator->getLastAuthProperties()));
                $cso_filename = openvpn_csc_conf_write($cso, $a_server);
                if (!empty($cso_filename)) {
                    syslog(LOG_NOTICE, "user '{$username}' authenticated using '{$authName}' cso :{$cso_filename}");
                } else {
                    syslog(LOG_NOTICE, "user '{$username}' authenticated using '{$authName}'");
                }
                closelog();
                exit(0);
            }
        }
    }

    // deny access and log
    syslog(LOG_WARNING, "user '{$username}' could not authenticate.");
    closelog();
    exit(-1);
}

syslog(LOG_ERR, "invalid user authentication environment");
closelog();
exit(-1);
