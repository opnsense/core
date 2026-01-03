#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018-2023 Deciso B.V.
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

require_once("config.inc");
require_once("auth.inc");
require_once("util.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/openvpn.inc");


/**
 * Parse provisioning properties supplied by the authenticator
 * @param array $props key value store containing addresses and routes
 * @return array formatted like openvpn_csc_conf_write() expects
 */
function parse_auth_properties($props)
{
    $result = [];
    if (!empty($props['Framed-IP-Address']) && !empty($props['Framed-IP-Netmask'])) {
        $cidrmask = 32 - log((ip2long($props['Framed-IP-Netmask']) ^ ip2long('255.255.255.255')) + 1, 2);
        $result['tunnel_network'] = $props['Framed-IP-Address'] . "/" . $cidrmask;
    }
    if (!empty($props['Framed-Route']) && is_array($props['Framed-Route'])) {
        $result['local_network'] = implode(",", $props['Framed-Route']);
    }
    return $result;
}

/**
 * perform authentication
 * @param string $common_name certificate common name for this connection
 * @param string $serverid server identifier
 * @param string $method method to use, supply username+password via-env or via-file
 * @param string $auth_file when using a file, defines the name to use
 * @return string|bool an error string or true when properly authenticated
 */
function do_auth($common_name, $serverid, $method, $auth_file)
{
    $username = $password = false;
    if ($method == 'via-file') {
        // via-file
        if (!empty($auth_file) && is_file($auth_file)) {
            $lines = explode("\n", file_get_contents($auth_file));
            if (count($lines) >= 2) {
                $username = $lines[0];
                $password = $lines[1];
            }
        }
    } else {
        // via-env
        $username = getenv('username');
        $password = getenv('password');
    }
    if (empty($username) || empty($password)) {
        return "username or password missing ({$method} - {$auth_file})";
    }

    $a_server = $serverid !== null ? (new OPNsense\OpenVPN\OpenVPN())->getInstanceById($serverid, 'server') : null;
    if ($a_server == null) {
        return "OpenVPN '$serverid' was not found. Denying authentication for user {$username}";
    } elseif (!empty($a_server['strictusercn']) && $username != $common_name) {
        // only ignore case when explicitly set (strictusercn=2)
        if (!($a_server['strictusercn'] == 2 && strtolower($username) == strtolower($common_name))) {
            return sprintf(
                "Username does not match certificate common name (%s != %s), access denied.",
                $username,
                $common_name
            );
        }
    } elseif (empty($a_server['authmode'])) {
        return 'No authentication server has been selected to authenticate against. ' .
        "Denying authentication for user {$username}";
    }

    if (file_exists("/var/etc/openvpn/server{$serverid}.ca")) {
        putenv("LDAPTLS_CACERT=/var/etc/openvpn/server{$serverid}.ca");
        putenv("LDAPTLS_REQCERT=never");
    }
    // perform the actual authentication
    $authFactory = new OPNsense\Auth\AuthenticationFactory();
    foreach (explode(',', $a_server['authmode']) as $authName) {
        $authenticator = $authFactory->get($authName);
        if ($authenticator) {
            if (strpos($password, 'SCRV1:') === 0) {
                // static-challenge https://github.com/OpenVPN/openvpn/blob/v2.4.7/doc/management-notes.txt#L1146
                // validate and concat password into our default pin+password
                $tmp = explode(':', $password);
                if (count($tmp) == 3) {
                    $pass = base64_decode($tmp[1]);
                    $pin = base64_decode($tmp[2]);
                    if ($pass !== false && $pin !== false) {
                        if (
                            isset(class_uses($authenticator)[OPNsense\Auth\TOTP::class]) &&
                            $authenticator->isPasswordFirst()
                        ) {
                            $password = $pass . $pin;
                        } else {
                            $password = $pin . $pass;
                        }
                    }
                }
            }

            if ($authenticator->authenticate($username, $password)) {
                if (!empty($a_server['local_group']) && !in_array($a_server['local_group'], getUserGroups($username))) {
                    return "OpenVPN '$serverid' requires the local group {$a_server['local_group']}. " .
                        "Denying authentication for user {$username}";
                }
                // fetch or create client specific override
                $common_name = empty($a_server['cso_login_matching']) ? $common_name : $username;
                syslog(
                    LOG_NOTICE,
                    "Locate overwrite for '{$common_name}' using server '{$serverid}' (vpnid: {$a_server['vpnid']})"
                );
                $cso = (new OPNsense\OpenVPN\OpenVPN())->getOverwrite($serverid, $common_name, parse_auth_properties($authenticator->getLastAuthProperties()));
                if (empty($cso)) {
                    return "authentication failed for user '{$username}'. No tunnel network provisioned, but required.";
                }
                $cso_filename = openvpn_csc_conf_write($cso, $a_server);
                if (!empty($cso_filename)) {
                    $tmp = empty($a_server['cso_login_matching']) ? "CSO [CN]" : "CSO [USER]";
                    syslog(LOG_NOTICE, "user '{$username}' authenticated using '{$authName}' {$tmp}:{$cso_filename}");
                } else {
                    syslog(LOG_NOTICE, "user '{$username}' authenticated using '{$authName}'");
                }
                return true;
            }
        }
    }
    return "user '{$username}' could not authenticate.";
}

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

/* parse environment variables */
$parms = [];
$parmlist = ['auth_server', 'auth_method', 'common_name', 'auth_file', 'auth_defer', 'auth_control_file'];
foreach ($parmlist as $key) {
    $parms[$key] = isset(getenv()[$key]) ? getenv()[$key] : null;
}

/* perform authentication */
$response = do_auth($parms['common_name'], $parms['auth_server'], $parms['auth_method'], $parms['auth_file']);

if (is_string($response)) {
    // send failure message to log
    syslog(LOG_WARNING, $response);
}

if (!empty($parms['auth_defer'])) {
    if (!empty($parms['auth_control_file'])) {
        file_put_contents($parms['auth_control_file'], sprintf("%d", $response === true ? '1' : '0'));
    }
    exit(0);
} else {
    if ($response === true) {
        exit(0);
    } else {
        exit(1);
    }
}
