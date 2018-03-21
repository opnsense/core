<?php

/*
    Copyright (C) 2010 Ermal Luçi
    Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

require_once("guiconfig.inc");
require_once("plugins.inc.d/openvpn.inc");
require_once("services.inc");
require_once("filter.inc");
require_once("interfaces.inc");

function filter_generate_port(& $rule, $target = "source", $isnat = false) {
    $src = "";

    if (isset($rule['protocol'])) {
        $rule['protocol'] = strtolower($rule['protocol']);
    }
    if (isset($rule['protocol']) && in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
        if (!empty($rule[$target]['port'])) {
            $port = alias_expand(str_replace('-', ':', $rule[$target]['port']));
            if (!empty($port)) {
                $src = " port " . $port;
            }
        }
    }

    return $src;
}

function filter_generate_address(&$FilterIflist, &$rule, $target = 'source', $isnat = false)
{
    global $config;

    $src = '';

    if (isset($rule[$target]['any'])) {
        $src = "any";
    } elseif (!empty($rule[$target]['network'])) {
        $network_name = $rule[$target]['network'];
        $matches = "";
        if ($network_name == '(self)') {
            $src = "(self)";
        } elseif (preg_match("/^(wan|lan|opt[0-9]+)ip$/", $network_name, $matches)) {
            if (empty($FilterIflist[$matches[1]]['if'])) {
                // interface non-existent or in-active
                return null;
            }
            $src = "({$FilterIflist["{$matches[1]}"]['if']})";
        } else {
            if (empty($FilterIflist[$network_name]['if'])) {
                // interface non-existent or in-active
                return null;
            }
            $src = "({$FilterIflist[$network_name]['if']}:network)";
        }
        if (isset($rule[$target]['not'])) {
            $src = " !{$src}";
        }
    } elseif ($rule[$target]['address']) {
        $expsrc = alias_expand($rule[$target]['address']);
        if (isset($rule[$target]['not'])) {
            $not = "!";
        } else {
            $not = "";
        }
        $src = " {$not} {$expsrc}";
    }
    $src .= filter_generate_port($rule, $target, $isnat);

    return $src;
}

function openvpn_client_export_prefix($srvid, $usrid = null, $crtid = null)
{
    global $config;

    // lookup server settings
    $settings = $config['openvpn']['openvpn-server'][$srvid];
    if (empty($settings)) {
        return false;
    }
    if (!empty($settings['disable'])) {
        return false;
    }

    $host = empty($config['system']['hostname']) ? "openvpn" : $config['system']['hostname'];
    $prot = ($settings['protocol'] == 'UDP' ? 'udp' : $settings['protocol']);
    $port = $settings['local_port'];

    $filename_addition = "";
    if ($usrid && is_numeric($usrid)) {
        $filename_addition = "-".$config['system']['user'][$usrid]['name'];
    } elseif ($crtid && is_numeric($crtid)) {
        $filename_addition = "-" . str_replace(' ', '_', cert_get_cn($config['cert'][$crtid]['crt']));
    }

    return "{$host}-{$prot}-{$port}{$filename_addition}";
}

function openvpn_client_pem_to_pk12($outpath, $outpass, $crtpath, $keypath, $capath = false)
{
    $eoutpath = escapeshellarg($outpath);
    $eoutpass = escapeshellarg($outpass);
    $ecrtpath = escapeshellarg($crtpath);
    $ekeypath = escapeshellarg($keypath);
    if ($capath) {
        $ecapath = escapeshellarg($capath);
        exec("/usr/local/bin/openssl pkcs12 -export -in {$ecrtpath} -inkey {$ekeypath} -certfile {$ecapath} -out {$eoutpath} -passout pass:{$eoutpass}");
    } else {
        exec("/usr/local/bin/openssl pkcs12 -export -in {$ecrtpath} -inkey {$ekeypath} -out {$eoutpath} -passout pass:{$eoutpass}");
    }

    unlink($crtpath);
    unlink($keypath);
    if ($capath) {
        unlink($capath);
    }
}

function openvpn_client_export_validate_config($srvid, $usrid, $crtid)
{
    global $config, $input_errors;
    $nokeys = false;

    // lookup server settings
    $settings = $config['openvpn']['openvpn-server'][$srvid];
    if (empty($settings)) {
        $input_errors[] = gettext("Could not locate server configuration.");
        return false;
    }
    if (!empty($settings['disable'])) {
        $input_errors[] = gettext("You cannot export for disabled servers.");
        return false;
    }

    // lookup server certificate info
    $server_cert = lookup_cert($settings['certref']);
    if (!$server_cert) {
        $input_errors[] = gettext("Could not locate server certificate.");
    } else {
        $server_ca = ca_chain($server_cert);
        if (empty($server_ca)) {
            $input_errors[] = gettext("Could not locate the CA reference for the server certificate.");
        }
        $servercn = cert_get_cn($server_cert['crt']);
    }

    // lookup user info
    if (is_numeric($usrid)) {
        $user = $config['system']['user'][$usrid];
        if (!$user) {
            $input_errors[] = gettext("Could not find user settings.");
        }
    }

    // lookup user certificate info
    if ($settings['mode'] == "server_tls_user") {
        if ($settings['authmode'] == "Local Database") {
            $cert = $user['cert'][$crtid];
        } else {
            $cert = $config['cert'][$crtid];
        }
        if (!$cert) {
            $input_errors[] = gettext("Could not find client certificate.");
        } else {
            // If $cert is not an array, it's a certref not a cert.
            if (!is_array($cert)) {
                $cert = lookup_cert($cert);
            }
        }
    } elseif (($settings['mode'] == "server_tls") || (($settings['mode'] == "server_tls_user") && ($settings['authmode'] != "Local Database"))) {
        $cert = $config['cert'][$crtid];
        if (!$cert) {
            $input_errors[] = gettext("Could not find client certificate.");
        }
    } else {
        $nokeys = true;
    }

    if ($input_errors) {
        return false;
    }

    return array($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys);
}

function openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $nokeys = false, $proxy, $expformat = "baseconf", $outpass = "", $skiptls=false, $doslines=false, $openvpnmanager, $advancedoptions = "")
{
    global $config, $input_errors;

    $nl = ($doslines) ? "\r\n" : "\n";
    $conf = "";

    $validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
    if (!$validconfig) {
        return false;
    }

    list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;

    // determine basic variables
    $remotes = openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl);
    $server_port = $settings['local_port'];
    $cipher = $settings['crypto'];
    $digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

    // add basic settings
    $devmode = empty($settings['dev_mode']) ? "tun" : $settings['dev_mode'];
    if (($expformat != "inlinedroid") && ($expformat != "inlineios")) {
        $conf .= "dev {$devmode}{$nl}";
    }
    if (!empty($settings['tunnel_networkv6']) && ($expformat != "inlinedroid") && ($expformat != "inlineios")) {
        $conf .= "tun-ipv6{$nl}";
    }
    $conf .= "persist-tun{$nl}";
    $conf .= "persist-key{$nl}";

  //  if ((($expformat != "inlinedroid") && ($expformat != "inlineios")) && ($proto == "tcp"))
  //    $conf .= "proto tcp-client{$nl}";
    $conf .= "cipher {$cipher}{$nl}";
    $conf .= "auth {$digest}{$nl}";
    $conf .= "tls-client{$nl}";
    $conf .= "client{$nl}";
    if (isset($settings['reneg-sec']) && $settings['reneg-sec'] != "") {
        $conf .= "reneg-sec {$settings['reneg-sec']}{$nl}";
    }
    if (($expformat != "inlinedroid") && ($expformat != "inlineios")) {
        $conf .= "resolv-retry infinite{$nl}";
    }
    $conf .= "$remotes{$nl}";

    /* Use a random local port, otherwise two clients will conflict if they run at the same time.
      May not be supported on older clients (Released before May 2010) */
    if (($randomlocalport != 0) && (substr($expformat, 0, 7) != "yealink") && ($expformat != "snom")) {
        $conf .= "lport 0{$nl}";
    }

    /* This line can cause problems with auth-only setups and also with Yealink/Snom phones
      since they are stuck on an older OpenVPN version that does not support this feature. */
    if (!empty($servercn) && !$nokeys) {
        switch ($verifyservercn) {
            case "none":
                break;
            case "tls-remote":
                $conf .= "tls-remote {$servercn}{$nl}";
                break;
            case "tls-remote-quote":
                $conf .= "tls-remote \"{$servercn}\"{$nl}";
                break;
            default:
                if ((substr($expformat, 0, 7) != "yealink") && ($expformat != "snom")) {
                    $conf .= "verify-x509-name \"{$servercn}\" name{$nl}";
                }
        }
    }

    if (!empty($proxy)) {
        if ($proxy['proxy_type'] == "http") {
            if (strtoupper(substr($settings['protocol'], 0, 3)) == "UDP") {
                $input_errors[] = gettext("This server uses UDP protocol and cannot communicate with HTTP proxy.");
                return;
            }
            $conf .= "http-proxy {$proxy['ip']} {$proxy['port']} ";
        }
        if ($proxy['proxy_type'] == "socks") {
            $conf .= "socks-proxy {$proxy['ip']} {$proxy['port']} ";
        }
        if ($proxy['proxy_authtype'] != "none") {
            if (!isset($proxy['passwdfile'])) {
                $proxy['passwdfile'] = openvpn_client_export_prefix($srvid, $usrid, $crtid) . "-proxy";
            }
            $conf .= " {$proxy['passwdfile']} {$proxy['proxy_authtype']}";
        }
        $conf .= "{$nl}";
    }

    // add user auth settings
    switch($settings['mode']) {
        case 'server_user':
        case 'server_tls_user':
            $conf .= "auth-user-pass{$nl}";
            break;
    }

    // add key settings
    $prefix = openvpn_client_export_prefix($srvid, $usrid, $crtid);
    $cafile = "{$prefix}-ca.crt";
    if ($nokeys == false) {
        if ($expformat == "yealink_t28") {
            $conf .= "ca /yealink/config/openvpn/keys/ca.crt{$nl}";
            $conf .= "cert /yealink/config/openvpn/keys/client1.crt{$nl}";
            $conf .= "key /yealink/config/openvpn/keys/client1.key{$nl}";
        } elseif ($expformat == "yealink_t38g") {
            $conf .= "ca /phone/config/openvpn/keys/ca.crt{$nl}";
            $conf .= "cert /phone/config/openvpn/keys/client1.crt{$nl}";
            $conf .= "key /phone/config/openvpn/keys/client1.key{$nl}";
        } elseif ($expformat == "yealink_t38g2") {
            $conf .= "ca /config/openvpn/keys/ca.crt{$nl}";
            $conf .= "cert /config/openvpn/keys/client1.crt{$nl}";
            $conf .= "key /config/openvpn/keys/client1.key{$nl}";
        } elseif ($expformat == "snom") {
            $conf .= "ca /openvpn/ca.crt{$nl}";
            $conf .= "cert /openvpn/phone1.crt{$nl}";
            $conf .= "key /openvpn/phone1.key{$nl}";
        } elseif ($usetoken) {
            $conf .= "ca {$cafile}{$nl}";
            $conf .= "cryptoapicert \"SUBJ:{$user['name']}\"{$nl}";
        } elseif (substr($expformat, 0, 6) != "inline") {
            $conf .= "pkcs12 {$prefix}.p12{$nl}";
        }
    } elseif ($settings['mode'] == "server_user") {
        if (substr($expformat, 0, 6) != "inline") {
            $conf .= "ca {$cafile}{$nl}";
        }
    }

    if ($settings['tls'] && !$skiptls) {
        if ($expformat == "yealink_t28") {
            $conf .= "tls-auth /yealink/config/openvpn/keys/ta.key 1{$nl}";
        } elseif ($expformat == "yealink_t38g") {
            $conf .= "tls-auth /phone/config/openvpn/keys/ta.key 1{$nl}";
        } elseif ($expformat == "yealink_t38g2") {
            $conf .= "tls-auth /config/openvpn/keys/ta.key 1{$nl}";
        } elseif ($expformat == "snom") {
            $conf .= "tls-auth /openvpn/ta.key 1{$nl}";
        } elseif (substr($expformat, 0, 6) != "inline") {
            $conf .= "tls-auth {$prefix}-tls.key 1{$nl}";
        }
    }

    // Prevent MITM attacks by verifying the server certificate.
    // - Disable for now, it requires the server cert to include special options
    //$conf .= "remote-cert-tls server{$nl}";

    if (is_array($server_cert) && ($server_cert['crt'])) {
        $purpose = cert_get_purpose($server_cert['crt'], true);
        if ($purpose['server'] == 'Yes') {
            $conf .= "remote-cert-tls server{$nl}";
        }
    }

    // add optional settings
    if (!empty($settings['compression'])) {
        $conf .= "comp-lzo {$settings['compression']}{$nl}";
    }

    if ($settings['passtos']) {
        $conf .= "passtos{$nl}";
    }

    if ($openvpnmanager) {
        if (!empty($settings['client_mgmt_port'])) {
            $client_mgmt_port = $settings['client_mgmt_port'];
        } else {
            $client_mgmt_port = 166;
        }
        $conf .= $nl;
        $conf .= "# dont terminate service process on wrong password, ask again{$nl}";
        $conf .= "auth-retry interact{$nl}";
        $conf .= "# open management channel{$nl}";
        $conf .= "management 127.0.0.1 {$client_mgmt_port}{$nl}";
        $conf .= "# wait for management to explicitly start connection{$nl}";
        $conf .= "management-hold{$nl}";
        $conf .= "# query management channel for user/pass{$nl}";
        $conf .= "management-query-passwords{$nl}";
        $conf .= "# disconnect VPN when management program connection is closed{$nl}";
        $conf .= "management-signal{$nl}";
        $conf .= "# forget password when management disconnects{$nl}";
        $conf .= "management-forget-disconnect{$nl}";
        $conf .= $nl;
    };

    // add advanced options
    $advancedoptions = str_replace("\r\n", "\n", $advancedoptions);
    $advancedoptions = str_replace("\n", $nl, $advancedoptions);
    $advancedoptions = str_replace(";", $nl, $advancedoptions);
    $conf .= $advancedoptions;
    $conf .= $nl;

    switch ($expformat) {
        case "zip":
            // create template directory
            $tempdir = "/tmp/{$prefix}";
            @mkdir($tempdir, 0700, true);

            file_put_contents("{$tempdir}/{$prefix}.ovpn", $conf);

            $cafile = "{$tempdir}/{$cafile}";
            file_put_contents("{$cafile}", $server_ca);
            if ($settings['tls']) {
                $tlsfile = "{$tempdir}/{$prefix}-tls.key";
                file_put_contents($tlsfile, base64_decode($settings['tls']));
            }

            // write key files
            if ($settings['mode'] != "server_user") {
                $crtfile = "{$tempdir}/{$prefix}-cert.crt";
                file_put_contents($crtfile, base64_decode($cert['crt']));
                $keyfile = "{$tempdir}/{$prefix}.key";
                file_put_contents($keyfile, base64_decode($cert['prv']));

                // convert to pkcs12 format
                $p12file = "{$tempdir}/{$prefix}.p12";
                if ($usetoken) {
                    openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile);
                } else {
                    openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile, $cafile);
                }
            }
            $command = "cd " . escapeshellarg("{$tempdir}/..")
                . " && /usr/local/bin/zip -r "
                . escapeshellarg("/tmp/{$prefix}-config.zip")
                . " " . escapeshellarg($prefix);
            exec($command);
            // Remove temporary directory
            exec("rm -rf " . escapeshellarg($tempdir));
            return "/tmp/{$prefix}-config.zip";
            break;
        case "inline":
        case "inlinedroid":
        case "inlineios":
            // Inline CA
            $conf .= "<ca>{$nl}" . trim($server_ca) . "{$nl}</ca>{$nl}";
            if ($settings['mode'] != "server_user") {
                // Inline Cert
                $conf .= "<cert>{$nl}" . trim(base64_decode($cert['crt'])) . "{$nl}</cert>{$nl}";
                // Inline Key
                $conf .= "<key>{$nl}" . trim(base64_decode($cert['prv'])) . "{$nl}</key>{$nl}";
            } else {
                // Work around OpenVPN Connect assuming you have a client cert even when you don't need one
                $conf .= "setenv CLIENT_CERT 0{$nl}";
            }
            // Inline TLS
            if ($settings['tls']) {
                $conf .= "<tls-auth>{$nl}" . trim(base64_decode($settings['tls'])) . "{$nl}</tls-auth>{$nl} key-direction 1{$nl}";
            }
            return $conf;
            break;
        case "yealink_t28":
        case "yealink_t38g":
        case "yealink_t38g2":
            // create template directory
            $tempdir = "/tmp/{$prefix}";
            $keydir  = "{$tempdir}/keys";
            mkdir($tempdir, 0700, true);
            mkdir($keydir, 0700, true);

            file_put_contents("{$tempdir}/vpn.cnf", $conf);

            $cafile = "{$keydir}/ca.crt";
            file_put_contents("{$cafile}", $server_ca);
            if ($settings['tls']) {
                $tlsfile = "{$keydir}/ta.key";
                file_put_contents($tlsfile, base64_decode($settings['tls']));
            }

            // write key files
            if ($settings['mode'] != "server_user") {
                $crtfile = "{$keydir}/client1.crt";
                file_put_contents($crtfile, base64_decode($cert['crt']));
                $keyfile = "{$keydir}/client1.key";
                file_put_contents($keyfile, base64_decode($cert['prv']));
            }
            exec("tar -C {$tempdir} -cf /tmp/client.tar ./keys ./vpn.cnf");
            // Remove temporary directory
            exec("rm -rf {$tempdir}");
            return '/tmp/client.tar';
        case "snom":
            // create template directory
            $tempdir = "/tmp/{$prefix}";
            mkdir($tempdir, 0700, true);

            file_put_contents("{$tempdir}/vpn.cnf", $conf);

            $cafile = "{$tempdir}/ca.crt";
            file_put_contents("{$cafile}", $server_ca);
            if ($settings['tls']) {
                $tlsfile = "{$tempdir}/ta.key";
                file_put_contents($tlsfile, base64_decode($settings['tls']));
            }

            // write key files
            if ($settings['mode'] != "server_user") {
                $crtfile = "{$tempdir}/phone1.crt";
                file_put_contents($crtfile, base64_decode($cert['crt']));
                $keyfile = "{$tempdir}/phone1.key";
                file_put_contents($keyfile, base64_decode($cert['prv']));
            }
            exec("cd {$tempdir}/ && tar -cf /tmp/vpnclient.tar *");
            // Remove temporary directory
            exec("rm -rf {$tempdir}");
            return '/tmp/vpnclient.tar';
        default:
            return $conf;
    }
}

function viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $outpass, $proxy, $openvpnmanager, $advancedoptions, $compression_type)
{
    global $config;

    $validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
    if (!$validconfig) {
        return false;
    }

    list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;

    // create template directory
    $baseTempDir = "/tmp/openvpn-export-" . uniqid();
    $tempdir = $baseTempDir . "/Viscosity.visc";
    mkdir($tempdir, 0700, true);

    // write cofiguration file
    $prefix = openvpn_client_export_prefix($srvid, $usrid, $crtid);
    if (!empty($proxy) && $proxy['proxy_authtype'] != "none") {
        $proxy['passwdfile'] = "config-password";
        $pwdfle = "{$proxy['user']}\n";
        $pwdfle .= "{$proxy['password']}\n";
        file_put_contents("{$tempdir}/{$proxy['passwdfile']}", $pwdfle);
    }

    $conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, true, $proxy, "baseconf", $outpass, true, true, $openvpnmanager, $advancedoptions);
    if (!$conf) {
        return false;
    }

    // We need to nuke the ca line from the above config if it exists.
    $conf = explode("\n", $conf);
    for ($i=0; $i < count($conf); $i++) {
        if ((substr($conf[$i], 0, 3) == "ca ") || (substr($conf[$i], 0, 7) == "pkcs12 ")) {
            unset($conf[$i]);
        }
    }
    $conf = implode("\n", $conf);

    $friendly_name = $settings['description'];
    $visc_settings = <<<EOF
#-- Config Auto Generated for Viscosity --#

#viscosity startonopen false
#viscosity dhcp true
#viscosity dnssupport true
#viscosity name {$friendly_name}

EOF;

  $configfile = "{$tempdir}/config.conf";
  $conf .= "ca ca.crt\n";
  $conf .= "tls-auth ta.key 1\n";
  if ($settings['mode'] != "server_user") {
    $conf .= <<<EOF
cert cert.crt
key key.key
EOF;
    }

    file_put_contents($configfile, $visc_settings . "\n" . $conf);

    //  ca.crt    cert.crt  config.conf  key.key    ta.key

    // write ca
    $cafile = "{$tempdir}/ca.crt";
    file_put_contents($cafile, $server_ca);

    if ($settings['mode'] != "server_user") {
        // write user .crt
        $crtfile = "{$tempdir}/cert.crt";
        file_put_contents($crtfile, base64_decode($cert['crt']));

        // write user .key
        if (!empty($outpass)) {
            $keyfile = "{$tempdir}/key.key";
            $clearkeyfile = "{$tempdir}/key-clear.key";
            file_put_contents($clearkeyfile, base64_decode($cert['prv']));
            $eoutpass = escapeshellarg($outpass);
            $ekeyfile = escapeshellarg($keyfile);
            $eclearkeyfile = escapeshellarg($clearkeyfile);
            exec("/usr/local/bin/openssl rsa -in ${eclearkeyfile} -out ${ekeyfile} -des3 -passout pass:${eoutpass}");
            unlink($clearkeyfile);
        } else {
            $keyfile = "{$tempdir}/key.key";
            file_put_contents($keyfile, base64_decode($cert['prv']));
        }
    }

    // TLS support?
    if ($settings['tls']) {
        $tlsfile = "{$tempdir}/ta.key";
        file_put_contents($tlsfile, base64_decode($settings['tls']));
    }

    // Zip Viscosity file
    if ($compression_type == 'targz') {
        $outputfile = "/tmp/{$uniq}-Viscosity.visz";
        exec("cd {$tempdir}/.. && /usr/bin/tar cfz {$outputfile} Viscosity.visc");
    } else {
        $outputfile = "/tmp/{$uniq}-Viscosity.visc.zip";
        exec("cd {$tempdir}/.. && /usr/local/bin/zip -r {$outputfile} Viscosity.visc");
    }

    // Remove temporary directory
    exec("rm -rf {$baseTempDir}");

    return $outputfile;
}

function openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, $zipconf = false)
{
    global $config, $input_errors;

    // lookup server settings
    $settings = $config['openvpn']['openvpn-server'][$srvid];
    if (empty($settings)) {
        $input_errors[] = gettext("Could not locate server configuration.");
        return false;
    }
    if ($settings['disable']) {
        $input_errors[] = gettext("You cannot export for disabled servers.");
        return false;
    }

    // determine basic variables
    if ($useaddr == "serveraddr") {
        $interface = $settings['interface'];
        if (!empty($settings['ipaddr']) && is_ipaddr($settings['ipaddr'])) {
            $server_host = $settings['ipaddr'];
        } else {
            if (!$interface) {
                $interface = "wan";
            }
            if (in_array(strtolower($settings['protocol']), array("udp6", "tcp6"))) {
                $server_host = get_interface_ipv6($interface);
            } else {
                $server_host = get_interface_ip($interface);
            }
        }
    } elseif ($useaddr == "serverhostname" || empty($useaddr)) {
        $server_host = empty($config['system']['hostname']) ? "" : "{$config['system']['hostname']}.";
        $server_host .= "{$config['system']['domain']}";
    } else {
        $server_host = $useaddr;
    }

    $server_port = $settings['local_port'];

    $proto = strtolower($settings['protocol']);
    if (strtolower(substr($settings['protocol'], 0, 3)) == "tcp") {
        $proto .= "-client";
    }

    $cipher = $settings['crypto'];
    $digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

    // add basic settings
    $conf  = "dev tun\n";
    if (!empty($settings['tunnel_networkv6'])) {
        $conf .= "tun-ipv6\n";
    }
    $conf .= "persist-tun\n";
    $conf .= "persist-key\n";
    $conf .= "proto {$proto}\n";
    $conf .= "cipher {$cipher}\n";
    $conf .= "auth {$digest}\n";
    $conf .= "pull\n";
    $conf .= "resolv-retry infinite\n";
    if (isset($settings['reneg-sec']) && $settings['reneg-sec'] != "") {
        $conf .= "reneg-sec {$settings['reneg-sec']}\n";
    }
    $conf .= "remote {$server_host} {$server_port}\n";
    if (!empty($settings['local_network'])) {
        $conf .= openvpn_gen_routes($settings['local_network'], 'ipv4');
    }
    if (!empty($settings['local_networkv6'])) {
        $conf .= openvpn_gen_routes($settings['local_networkv6'], 'ipv6');
    }
    if (!empty($settings['tunnel_network'])) {
        list($ip, $mask) = explode('/', $settings['tunnel_network']);
        $mask = gen_subnet_mask($mask);
        $baselong = ip2long32($ip) & ip2long($mask);
        $ip1 = long2ip32($baselong + 1);
        $ip2 = long2ip32($baselong + 2);
        $conf .= "ifconfig $ip2 $ip1\n";
    }
    $conf .= "keepalive 10 60\n";
    $conf .= "ping-timer-rem\n";

    if (!empty($proxy)) {
        if ($proxy['proxy_type'] == "http") {
            if ($proto == "udp") {
                $input_errors[] = gettext("This server uses UDP protocol and cannot communicate with HTTP proxy.");
                return;
            }
            $conf .= "http-proxy {$proxy['ip']} {$proxy['port']} ";
        }
        if ($proxy['proxy_type'] == "socks") {
            $conf .= "socks-proxy {$proxy['ip']} {$proxy['port']} ";
        }
        if ($proxy['proxy_authtype'] != "none") {
            if (!isset($proxy['passwdfile'])) {
                $proxy['passwdfile'] = openvpn_client_export_prefix($srvid) . "-proxy";
            }
            $conf .= " {$proxy['passwdfile']} {$proxy['proxy_authtype']}";
        }
        $conf .= "\n";
    }

    // add key settings
    $prefix = openvpn_client_export_prefix($srvid);
    $shkeyfile = "{$prefix}.secret";
    $conf .= "secret {$shkeyfile}\n";

    // add optional settings
    if ($settings['compression']) {
        $conf .= "comp-lzo\n";
    }
    if ($settings['passtos']) {
        $conf .= "passtos\n";
    }

    if ($zipconf == true) {
        // create template directory
        $tempdir = "/tmp/{$prefix}";
        mkdir($tempdir, 0700, true);
        file_put_contents("{$tempdir}/{$prefix}.ovpn", $conf);
        $shkeyfile = "{$tempdir}/{$shkeyfile}";
        file_put_contents("{$shkeyfile}", base64_decode($settings['shared_key']));
        if (!empty($proxy['passwdfile'])) {
            $pwdfle = "{$proxy['user']}\n";
            $pwdfle .= "{$proxy['password']}\n";
            file_put_contents("{$tempdir}/{$proxy['passwdfile']}", $pwdfle);
        }
        exec("cd {$tempdir}/.. && /usr/local/bin/zip -r /tmp/{$prefix}-config.zip {$prefix}");
        // Remove temporary directory
        exec("rm -rf {$tempdir}");
        return "/tmp/{$prefix}-config.zip";
    } else {
        file_put_contents("/tmp/{$prefix}.ovpn", $conf);
        return "/tmp/{$prefix}.ovpn";
    }
}

function openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl) {
    global $config;
    $remotes = array();
    if (($useaddr == "serveraddr") || ($useaddr == "servermagic") || ($useaddr == "servermagichost")) {
        $interface = $settings['interface'];
        if (!empty($settings['ipaddr']) && is_ipaddr($settings['ipaddr'])) {
            $server_host = $settings['ipaddr'];
        } else {
            if (!$interface || ($interface == "any")) {
                $interface = "wan";
            }
            if (in_array(strtolower($settings['protocol']), array("udp6", "tcp6"))) {
                $server_host = get_interface_ipv6($interface);
            } else {
                $server_host = get_interface_ip($interface);
            }
        }
    } elseif ($useaddr == "serverhostname" || empty($useaddr)) {
        $server_host = empty($config['system']['hostname']) ? "" : "{$config['system']['hostname']}.";
        $server_host .= "{$config['system']['domain']}";
    } else {
        $server_host = $useaddr;
    }

    $proto = strtolower($settings['protocol']);
    if (strtolower(substr($settings['protocol'], 0, 3)) == "tcp") {
        $proto .= "-client";
    }

    if (($expformat == "inlineios") && ($proto == "tcp-client")) {
        $proto = "tcp";
    }

    if (($useaddr == "servermagic") || ($useaddr == "servermagichost")) {
        $destinations = openvpn_client_export_find_port_forwards($server_host, $settings['local_port'], $proto, true, ($useaddr == "servermagichost"));
        foreach ($destinations as $dest) {
            $remotes[] = "remote {$dest['host']} {$dest['port']} {$dest['proto']}";
        }
    } else {
        $remotes[] = "remote {$server_host} {$settings['local_port']} {$proto}";
    }

    return implode($nl, $remotes);
}

function openvpn_client_export_find_port_forwards($targetip, $targetport, $targetproto, $skipprivate, $findhostname = false)
{
    global $config;

    $FilterIflist = legacy_config_get_interfaces(array("enable" => true));
    $destinations = array();

    if (!isset($config['nat']['rule'])) {
        return $destinations;
    }

    foreach ($config['nat']['rule'] as $natent) {
        $dest = array();
        if (!isset($natent['disabled'])
          && ($natent['target'] == $targetip)
          && ($natent['local-port'] == $targetport)
          && ($natent['protocol'] == $targetproto)) {
            $dest['proto'] = $natent['protocol'];

            // Could be multiple ports... But we can only use one.
            $dports = is_port($natent['destination']['port']) ? array($natent['destination']['port']) : filter_core_get_port_alias($natent['destination']['port']);
            $dest['port'] = $dports[0];

            // Could be network or address ...
            $natif = (!$natent['interface']) ? "wan" : $natent['interface'];

            if (!isset($FilterIflist[$natif])) {
                continue; // Skip if there is no interface
            }

            $dstaddr = trim(filter_generate_address($FilterIflist, $natent, 'destination', true));
            if (!$dstaddr) {
                $dstaddr = $FilterIflist[$natif]['ip'];
            }

            $dstaddr_port = explode(" ", $dstaddr);

            if (empty($dstaddr_port[0]) || strtolower(trim($dstaddr_port[0])) == "port") {
                continue; // Skip port forward if no destination address found
            }
            if (!is_ipaddr($dstaddr_port[0])) {
                continue; // We can only work with single IPs, not subnets!
            }
            if ($skipprivate && is_private_ip($dstaddr_port[0])) {
                continue; // Skipping a private IP destination!
            }

            $dest['host'] = $dstaddr_port[0];

            if ($findhostname) {
                $hostname = openvpn_client_export_find_hostname($natif);
                if (!empty($hostname)) {
                    $dest['host'] = $hostname;
                }
            }
            $destinations[] = $dest;
        }
    }

    return $destinations;
}

function openvpn_client_export_find_hostname($interface)
{
    global $config;

    $hostname = '';

    if (isset($config['dyndnses']['dyndns'])) {
        foreach ($config['dyndnses']['dyndns'] as $ddns) {
            if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host'])) {
                return $ddns['host'];
            }
        }
    }
    if (isset($config['dnsupdates']['dnsupdate'])) {
        foreach ($config['dnsupdates']['dnsupdate'] as $ddns) {
            if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host'])) {
                return $ddns['host'];
            }
        }
    }
}

$ras_server = array();
if (isset($config['openvpn']['openvpn-server'])) {
    // collect info
    foreach ($config['openvpn']['openvpn-server'] as $sindex => $server) {
        if (isset($server['disable'])) {
            continue;
        }
        $ras_user = array();
        $ras_certs = array();
        if (stripos($server['mode'], "server") === false && $server['mode'] != "p2p_shared_key") {
            continue;
        }
        if (($server['mode'] == "server_tls_user") && ($server['authmode'] == "Local Database")) {
            if (isset($config['system']['user'])) {
                foreach ($config['system']['user'] as $uindex => $user) {
                    if (!isset($user['cert'])) {
                        continue;
                    }
                    foreach ($user['cert'] as $cindex => $cert) {
                        // If $cert is not an array, it's a certref not a cert.
                        if (!is_array($cert)) {
                            $cert = lookup_cert($cert);
                        }

                        if ($cert['caref'] != $server['caref']) {
                            continue;
                        }
                        $ras_userent = array();
                        $ras_userent['uindex'] = $uindex;
                        $ras_userent['cindex'] = $cindex;
                        $ras_userent['name'] = $user['name'];
                        $ras_userent['certname'] = $cert['descr'];
                        $ras_user[] = $ras_userent;
                    }
                }
            }
        } elseif (($server['mode'] == "server_tls") || (($server['mode'] == "server_tls_user") && ($server['authmode'] != "Local Database"))) {
            if (isset($config['cert'])) {
                foreach ($config['cert'] as $cindex => $cert) {
                    if (($cert['caref'] != $server['caref']) || ($cert['refid'] == $server['certref'])) {
                        continue;
                    }
                    $ras_cert_entry['cindex'] = $cindex;
                    $ras_cert_entry['certname'] = $cert['descr'];
                    $ras_cert_entry['certref'] = $cert['refid'];
                    $ras_certs[] = $ras_cert_entry;
                }
            }
        }

        $ras_serverent = array();
        $prot = $server['protocol'];
        $port = $server['local_port'];
        if ($server['description']) {
            $name = "{$server['description']} {$prot}:{$port}";
        } else {
            $name = "Server {$prot}:{$port}";
        }
        $ras_serverent['index'] = $sindex;
        $ras_serverent['name'] = $name;
        $ras_serverent['users'] = $ras_user;
        $ras_serverent['certs'] = $ras_certs;
        $ras_serverent['mode'] = $server['mode'];
        $ras_server[] = $ras_serverent;
    }

    // handle request export..
    if (!empty($_GET['act'])) {
        $input_errors = array();
        $exp_path = false;
        $act = $_GET['act'];
        $srvid = isset($_GET['srvid']) ? $_GET['srvid'] : false;
        $usrid = isset($_GET['usrid']) ? $_GET['usrid'] : false;
        $crtid = isset($_GET['crtid']) ? $_GET['crtid'] : false;
        if ($srvid === false) {
            header(url_safe('Location: /vpn_openvpn_export.php'));
            exit;
        }

        if ($config['openvpn']['openvpn-server'][$srvid]['mode'] == "server_user") {
            $nokeys = true;
        } else {
            $nokeys = false;
        }

        $useaddr = '';
        if (isset($_GET['useaddr']) && !empty($_GET['useaddr'])) {
            $useaddr = trim($_GET['useaddr']);
        }

        if (!(is_ipaddr($useaddr) || is_hostname($useaddr) ||
            in_array($useaddr, array("serveraddr", "servermagic", "servermagichost", "serverhostname")))) {
            $input_errors[] = gettext("You need to specify an IP or hostname.");
        }

        $advancedoptions = isset($_GET['advancedoptions']) ? $_GET['advancedoptions'] : null;
        $openvpnmanager = isset($_GET['openvpnmanager']) ? $_GET['openvpnmanager'] : null;

        $verifyservercn = isset($_GET['verifyservercn']) ? $_GET['verifyservercn'] : null;
        $randomlocalport = isset($_GET['randomlocalport']) ? $_GET['randomlocalport'] : null;
        $usetoken = $_GET['usetoken'];
        if ($usetoken && (substr($act, 0, 10) == "confinline")) {
            $input_errors[] = gettext("You cannot use Microsoft Certificate Storage with an Inline configuration.");
        }
        if ($usetoken && (($act == "conf_yealink_t28") || ($act == "conf_yealink_t38g") || ($act == "conf_yealink_t38g2") || ($act == "conf_snom"))) {
            $input_errors[] = gettext("You cannot use Microsoft Certificate Storage with a Yealink or SNOM configuration.");
        }
        $password = "";
        if (!empty($_GET['password'])) {
            $password = $_GET['password'];
        }

        $proxy = "";
        if (!empty($_GET['proxy_addr']) || !empty($_GET['proxy_port'])) {
            $proxy = array();
            if (empty($_GET['proxy_addr'])) {
                $input_errors[] = gettext("You need to specify an address for the proxy port.");
            } else {
                $proxy['ip'] = $_GET['proxy_addr'];
            }
            if (empty($_GET['proxy_port'])) {
                $input_errors[] = gettext("You need to specify a port for the proxy IP.");
            } else {
                $proxy['port'] = $_GET['proxy_port'];
            }
            if (isset($_GET['proxy_type'])) {
                $proxy['proxy_type'] = $_GET['proxy_type'];
            }
            if (isset($_GET['proxy_authtype'])) {
                $proxy['proxy_authtype'] = $_GET['proxy_authtype'];
                if ($_GET['proxy_authtype'] != "none") {
                    if (empty($_GET['proxy_user'])) {
                        $input_errors[] = gettext("You need to specify a username with the proxy config.");
                    } else {
                        $proxy['user'] = $_GET['proxy_user'];
                    }
                    if (!empty($_GET['proxy_user']) && empty($_GET['proxy_password'])) {
                        $input_errors[] = gettext("You need to specify a password with the proxy user.");
                    } else {
                        $proxy['password'] = $_GET['proxy_password'];
                    }
                }
            }
        }

        $exp_name = openvpn_client_export_prefix($srvid, $usrid, $crtid);

        if (substr($act, 0, 4) == "conf") {
            switch ($act) {
                case "confzip":
                    $exp_name = urlencode($exp_name."-config.zip");
                    $expformat = "zip";
                    break;
                case "conf_yealink_t28":
                    $exp_name = urlencode("client.tar");
                    $expformat = "yealink_t28";
                    break;
                case "conf_yealink_t38g":
                    $exp_name = urlencode("client.tar");
                    $expformat = "yealink_t38g";
                    break;
                case "conf_yealink_t38g2":
                    $exp_name = urlencode("client.tar");
                    $expformat = "yealink_t38g2";
                    break;
                case "conf_snom":
                    $exp_name = urlencode("vpnclient.tar");
                    $expformat = "snom";
                    break;
                case "confinline":
                    $exp_name = urlencode($exp_name."-config.ovpn");
                    $expformat = "inline";
                    break;
                case "confinlinedroid":
                    $exp_name = urlencode($exp_name."-android-config.ovpn");
                    $expformat = "inlinedroid";
                    break;
                case "confinlineios":
                    $exp_name = urlencode($exp_name."-ios-config.ovpn");
                    $expformat = "inlineios";
                    break;
                default:
                    $exp_name = urlencode($exp_name."-config.ovpn");
                    $expformat = "baseconf";
            }
            $exp_path = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $nokeys, $proxy, $expformat, $password, false, false, $openvpnmanager, $advancedoptions);
        } elseif ($act == "visc") {
            $exp_name = urlencode($exp_name."-Viscosity.visc.zip");
            $exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, 'zip');
        } elseif ($act == "visz") {
            $exp_name = urlencode($exp_name."-Viscosity.visz");
            $exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $password, $proxy, $openvpnmanager, $advancedoptions, 'targz');
        } elseif ( $act == 'skconf')  {
            $exp_path = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, false);
            $exp_name = urlencode($exp_name."-config.ovpn");
        } elseif ( $act == 'skzipconf')  {
            $exp_path = openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, true);
            $exp_name = urlencode(basename($exp_path));
        }

        if (!$exp_path) {
            $input_errors[] = gettext("Failed to export config files!");
        }

        if (count($input_errors) == 0) {
            if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
                $exp_size = strlen($exp_path);
            } else {
                $exp_size = filesize($exp_path);
            }
            header('Pragma: ');
            header('Cache-Control: ');
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$exp_name}");
            header("Content-Length: $exp_size");
            header("X-Content-Type-Options: nosniff");
            if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
                echo $exp_path;
            } else {
                readfile($exp_path);
                @unlink($exp_path);
            }
            exit;
        }
    }
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
<script>
    $( document ).ready(function() {
        $("#server").change(function(){
            $('.server_item').hide();
            $('tr[data-server-index="'+$(this).val()+'"]').show();
            switch ($("#server :selected").data('mode')) {
                case "p2p_shared_key":
                    $(".mode_server :input").prop( "disabled", true );
                    $(".mode_server").hide();
                    break;
                default:
                    $(".mode_server :input").prop( "disabled", false );
                    $(".mode_server").show();
            }
            $(window).resize(); // force zebra re-stripe (opnsense_standard_table_form)
        });
        $("#server").change();

        $("#useaddr").change(function(){
            if ($(this).val() == 'other') {
                $('#HostName').show();
                $("#useaddr_hostname").prop( "disabled", false );
            } else {
                $('#HostName').hide();
                $("#useaddr_hostname").prop( "disabled", true );
            }
        });
        $("#pass,#conf").keyup(function(){
          if ($("#usepass").is(':checked')) {
              if ($("#pass").val() != $("#conf").val()) {
                  $("#usepass_opts").addClass('has-error');
                  $("#usepass_opts").removeClass('has-success');
              } else {
                  $("#usepass_opts").addClass('has-success');
                  $("#usepass_opts").removeClass('has-error');
              }
          }
        });
        $("#proxypass,#proxyconf").keyup(function(){
          if ($("#useproxypass option:selected").text() != 'none') {
              if ($("#proxypass").val() != $("#proxyconf").val()) {
                  $("#useproxypass_opts").addClass('has-error');
                  $("#useproxypass_opts").removeClass('has-success');
              } else {
                  $("#useproxypass_opts").addClass('has-success');
                  $("#useproxypass_opts").removeClass('has-error');
              }
          }
        });


        $("#usepass").change(function(){
            if ($(this).is(':checked')) {
                $("#usepass_opts").show();
            } else {
                $("#usepass_opts").hide();
            }
        });

        $("#useproxy, #useproxypass").change(function(){
            if ($("#useproxy").prop("checked")){
                $("#useproxy_opts").show();
            } else {
                $("#useproxy_opts").hide();
            }
            if ($("#useproxypass option:selected").text() != 'none') {
                $("#useproxypass_opts").show();
            } else {
                $("#useproxypass_opts").hide();
            }
        });

        $(".export_select").change(function(){
            if ($(this).val() != "") {
                var params = {};
                params['act'] = $(this).val();
                params['srvid'] = $("#server").val();
                if ($("#useaddr").val() == 'other') {
                    params['useaddr'] = $("#useaddr_hostname").val();
                } else {
                    params['useaddr'] = $("#useaddr").val();
                }
                if ($("#randomlocalport").is(':checked')) {
                    params['randomlocalport'] = 1;
                } else {
                    params['randomlocalport'] = 0;
                }
                if ($("#usetoken").is(':checked')) {
                    params['usetoken'] = 1;
                } else {
                    params['usetoken'] = 0;
                }
                if ($("#usepass").is(':checked')) {
                    params['password'] = $("#pass").val();
                }
                if ($("#useproxy").is(':checked')) {
                    params['proxy_type'] = $("#useproxytype").val();
                    params['proxy_addr'] = $("#proxyaddr").val();
                    params['proxy_port'] = $("#proxyport").val();
                    params['proxy_authtype'] = $("#useproxypass").val();
                    if ($("#useproxypass").val() != "none") {
                        params['proxy_user'] = $("#proxyuser").val();
                        params['proxy_password'] = $("#proxypass").val();
                    }
                }
                if ($("#openvpnmanager").is(':checked')) {
                    params['openvpnmanager'] = 1;
                } else {
                    params['openvpnmanager'] = 0;
                }
                params['advancedoptions'] = $("#advancedoptions").val();
                params['verifyservercn'] = $("#verifyservercn").val();
                if ($(this).data('type') == 'cert') {
                    params['crtid'] = $(this).data('id');
                } else if ($(this).data('type') == 'user') {
                    params['usrid'] = $(this).data('id');
                    params['crtid'] = $(this).data('certid');
                }
                var link=document.createElement('a');
                document.body.appendChild(link);
                link.href= "/vpn_openvpn_export.php?" + $.param( params );
                link.click();
                $(this).val("");
            }
        });
    });
</script>

<?php
if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
}
if (isset($savemsg)) {
    print_info_box($savemsg);
}
?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <div class="table-responsive">
            <table class="table table-striped opnsense_standard_table_form" >
              <tr>
                <td style="width:22%"></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td style="vertical-align:top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Remote Access Server");?></td>
                <td>
                  <select name="server" id="server">
<?php
                    foreach ($ras_server as $server) :?>
                    <option value="<?=$server['index'];?>" data-mode="<?=$server['mode'];?>"><?=htmlspecialchars($server['name']);?></option>
<?php
                    endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <td style="vertical-align:top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Host Name Resolution");?></td>
                <td>
                      <select name="useaddr" id="useaddr">
                        <option value="serveraddr" ><?=gettext("Interface IP Address");?></option>
                        <option value="servermagic" ><?=gettext("Automatic Multi-WAN IPs (port forward targets)");?></option>
                        <option value="servermagichost" ><?=gettext("Automatic Multi-WAN dynamic DNS Hostnames (port forward targets)");?></option>
                        <option value="serverhostname" ><?=gettext("Installation hostname");?></option>
                        <?php if (isset($config['dyndnses']['dyndns'])) :
?>
                        <?php foreach ($config['dyndnses']['dyndns'] as $ddns) :
?>
                        <option value="<?= $ddns["host"] ?>"><?=gettext("Dynamic DNS");?>: <?= htmlspecialchars($ddns["host"]); ?></option>
<?php
                        endforeach; ?>
<?php
                        endif; ?>
                    <?php if (isset($config['dnsupdates']['dnsupdate'])) :
?>
                        <?php foreach ($config['dnsupdates']['dnsupdate'] as $ddns) :
?>
                        <option value="<?= $ddns["host"] ?>"><?=gettext("Dynamic DNS");?>: <?= htmlspecialchars($ddns["host"]); ?></option>
<?php
                        endforeach; ?>
<?php
                        endif; ?>
                        <option value="other"><?=gettext("Other");?></option>
                      </select>
                      <div id="HostName" style="display:none;" >
                        <div>
                          <?=gettext("Enter the hostname or IP address the client will use to connect to this server.");?>
                        </div>
                        <input name="useaddr_hostname" type="text" id="useaddr_hostname" size="40" />
                      </div>
                </td>
              </tr>
              <tr class="mode_server">
                <td style="vertical-align:top"><a id="help_for_verify_server_cn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Verify Server CN");?></td>
                <td>
                      <select name="verifyservercn" id="verifyservercn">
                        <option value="auto"><?=gettext("Automatic - Use verify-x509-name (OpenVPN 2.3+) where possible");?></option>
                        <option value="tls-remote"><?=gettext("Use tls-remote (deprecated, use only on clients prior to OpenVPN 2.3)");?></option>
                        <option value="tls-remote-quote"><?=gettext("Use tls-remote and quote the server CN");?></option>
                        <option value="none"><?=gettext("Do not verify the server CN");?></option>
                      </select>
                      <div class="hidden" data-for="help_for_verify_server_cn">
                        <?=gettext("Optionally verify the server certificate Common Name (CN) when the client connects. Current clients, including the most recent versions of Windows, Viscosity, Tunnelblick, OpenVPN on iOS and Android and so on should all work at the default automatic setting.");?><br/><br/>
                        <?=gettext("Only use tls-remote if you must use an older client that you cannot control. The option has been deprecated by OpenVPN and will be removed in the next major version.");?><br/><br/>
                        <?=gettext("With tls-remote the server CN may optionally be enclosed in quotes. This can help if the server CN contains spaces and certain clients cannot parse the server CN. Some clients have problems parsing the CN with quotes. Use only as needed.");?>
                      </div>
                </td>
              </tr>
              <tr class="mode_server">
                <td style="vertical-align:top"><a id="help_for_random_local_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Random Local Port");?></td>
                <td>
                      <input name="randomlocalport" id="randomlocalport" type="checkbox" value="yes" checked="CHECKED" />
                      <div class="hidden" data-for="help_for_random_local_port">
                        <?=gettext("Use a random local source port (lport) for traffic from the client. Without this set, two clients may not run concurrently.");?>
                        <br/>
                        <?=gettext("NOTE: Not supported on older clients. Automatically disabled for Yealink and Snom configurations."); ?>
                      </div>
              </tr>
              <tr class="mode_server">
                <td style="vertical-align:top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Certificate Export Options");?></td>
                <td>
                      <div>
                        <input name="usetoken" id="usetoken" type="checkbox" value="yes" />
                        <?=gettext("Use Microsoft Certificate Storage instead of local files.");?>
                      </div>
                      <div>
                        <input name="usepass" id="usepass" type="checkbox" value="yes" />
                        <?=gettext("Use a password to protect the pkcs12 file contents or key in Viscosity bundle.");?>
                      </div>
                      <div id="usepass_opts" style="display:none">
                        <label for="pass"><?=gettext("Password");?></label>
                        <input name="pass" id="pass" class="form-control" type="password" value="" />
                        <label for="conf"><?=gettext("Confirmation");?></label>
                        <input name="conf" id="conf" class="form-control" type="password" value="" />
                      </div>
                </td>
              </tr>
              <tr>
                <td style="vertical-align:top"><a id="help_for_http_proxy" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use Proxy");?></td>
                <td>
                      <input name="useproxy" id="useproxy" type="checkbox" value="yes" />
                      <div class="hidden" data-for="help_for_http_proxy">
                        <?=gettext("Use a proxy to communicate with the server.");?>
                      </div>
                      <div id="useproxy_opts" style="display:none" >
                        <label for="useproxytype"><?=gettext("Type");?></label>
                        <select name="useproxytype" id="useproxytype">
                          <option value="http"><?=gettext("HTTP");?></option>
                          <option value="socks"><?=gettext("SOCKS");?></option>
                        </select>
                        <label for="proxyaddr"><?=gettext("IP Address");?></label>
                        <input name="proxyaddr" id="proxyaddr" type="text" class="formfld unknown" size="30" value="" />
                        <label for="proxyport"><?=gettext("Port");?></label>
                        <input name="proxyport" id="proxyport" type="text" class="formfld unknown" size="5" value="" />
                        <div>
                          <label for="useproxypass"><?=gettext("Choose proxy authentication if any.");?></label>
                          <select name="useproxypass" id="useproxypass">
                            <option value="none"><?=gettext("none");?></option>
                            <option value="basic"><?=gettext("basic");?></option>
                            <option value="ntlm"><?=gettext("ntlm");?></option>
                          </select>
                          <div id="useproxypass_opts" style="display:none">
                            <label for="proxyuser"><?=gettext("Username");?></label>
                            <input name="proxyuser" id="proxyuser" type="text" class="formfld unknown" value="" />
                            <label for="proxypass"><?=gettext("Password");?></label>
                            <input name="proxypass" id="proxypass" type="password" class="form-control" value="" />
                            <label for="proxyconf"><?=gettext("Confirmation");?></label>
                            <input name="proxyconf" id="proxyconf" type="password" class="form-control" value="" />
                          </div>
                        </div>
                      </div>
                </td>
              </tr>
              <tr class="mode_server">
                <td style="vertical-align:top"><a id="help_for_openvpnmanager" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Management Interface OpenVPN Manager");?></td>
                <td>
                      <input name="openvpnmanager" id="openvpnmanager" type="checkbox" value="yes" />
                      <div class="hidden" data-for="help_for_openvpnmanager">
                        <?=gettext('This will change the generated .ovpn configuration to allow for usage of the management interface. '.
                        'With this OpenVPN can be used also by non-administrator users. '.
                        'This is also useful for Windows systems where elevated permissions are needed to add routes to the system.');?>
                      </div>
                </td>
              </tr>
              <tr class="mode_server">
                <td style="vertical-align:top"><a id="help_for_advancedoptions" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Additional configuration options");?></td>
                <td>
                      <textarea rows="6" cols="68" name="advancedoptions" id="advancedoptions"></textarea><br/>
                      <div class="hidden" data-for="help_for_advancedoptions">
                        <?=gettext("Enter any additional options you would like to add to the OpenVPN client export configuration here, separated by a line break or semicolon"); ?><br/>
                        <?=gettext("EXAMPLE: remote-random"); ?>;
                      </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_clientpkg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Client Install Packages");?></td>
                <td>
                  <table id="export_users" class="table table-striped table-condensed">
                    <thead>
                      <tr>
                        <td style="width:25%" ><b><?=gettext("User");?></b></td>
                        <td style="width:35%" ><b><?=gettext("Certificate Name");?></b></td>
                        <td style="width:40%" ><b><?=gettext("Export");?></b></td>
                      </tr>
                    </thead>
                    <tbody>
<?php
                    foreach ($ras_server as $server) :
                      foreach ($server['users'] as $user):?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=$user['name'];?></td>
                        <td><?=str_replace("'", "\\'", $user['certname']);?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="user" data-id="<?=$user['uindex'];?>" data-certid="<?=$user['cindex'];?>">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="confzip"><?=gettext("Archive");?></option>
                              <option value="conf"><?=gettext("File Only");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Inline Configurations");?>">
                              <option value="confinlinedroid"><?=gettext("Android");?></option>
                              <option value="confinlineios"><?=gettext("OpenVPN Connect (iOS/Android)");?></option>
                              <option value="confinline"><?=gettext("Others");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Mac OSX / Windows");?>">
                              <option value="visc"><?=gettext("Viscosity Bundle (OSX)");?></option>
                              <option value="visz"><?=gettext("Viscosity Bundle (Windows)");?></option>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
<?php
                      endforeach;
                      foreach ($server['certs'] as $certidx => $cert) :?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=$server['mode'] == 'server_tls' ? gettext("Certificate (SSL/TLS, no Auth)") : gettext("Certificate with External Auth") ?></td>
                        <td><?=str_replace("'", "\\'", $cert['certname']);?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="cert" data-id="<?=$cert['cindex'];?>">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="confzip"><?=gettext("Archive");?></option>
                              <option value="conf"><?=gettext("File Only");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Inline Configurations");?>">
                              <option value="confinlinedroid"><?=gettext("Android");?></option>
                              <option value="confinlineios"><?=gettext("OpenVPN Connect (iOS/Android)");?></option>
                              <option value="confinline"><?=gettext("Others");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Mac OSX / Windows");?>">
                              <option value="visc"><?=gettext("Viscosity Bundle (OSX)");?></option>
                              <option value="visz"><?=gettext("Viscosity Bundle (Windows)");?></option>
                            </optgroup>
<?php
                            if ($server['mode'] == 'server_tls'):?>
                            <optgroup label="<?=gettext("Yealink SIP Handsets");?>">
                              <option value="conf_yealink_t28"><?=gettext("T28");?></option>
                              <option value="conf_yealink_t38g"><?=gettext("T38G (1)");?></option>
                              <option value="conf_yealink_t38g2"><?=gettext("T38G (2)");?></option>
                              <option value="conf_snom"><?=gettext("SNOM SIP Handset");?></option>

                            </optgroup>
<?php
                            endif;?>
                          </select>
                        </td>
                      </tr>
<?php
                      endforeach;
                      if ($server['mode'] == 'server_user'):?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=gettext("Authentication Only (No Cert)");?></td>
                        <td><?=gettext("none");?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="server">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="confzip"><?=gettext("Archive");?></option>
                              <option value="conf"><?=gettext("File Only");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Inline Configurations");?>">
                              <option value="confinlinedroid"><?=gettext("Android");?></option>
                              <option value="confinlineios"><?=gettext("OpenVPN Connect (iOS/Android)");?></option>
                              <option value="confinline"><?=gettext("Others");?></option>
                            </optgroup>
                            <optgroup label="<?=gettext("Mac OSX / Windows");?>">
                              <option value="visc"><?=gettext("Viscosity Bundle (OSX)");?></option>
                              <option value="visz"><?=gettext("Viscosity Bundle (Windows)");?></option>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
<?php
                      endif;
                      if ($server['mode'] == 'p2p_shared_key'):?>
                      <tr class="server_item" data-server-index="<?=$server['index'];?>" data-server-mode="<?=$server['mode'];?>">
                        <td><?=gettext("Other Shared Key OS Client");?></td>
                        <td><?=gettext("none");?></td>
                        <td>
                          <select class="selectpicker export_select" data-type="server">
                            <optgroup label="">
                                <option value="">-</option>
                            </optgroup>
                            <optgroup label="<?=gettext("Standard Configurations");?>">
                              <option value="skconf"><?=gettext("Configuration");?></option>
                              <option value="skzipconf"><?=gettext("Configuration archive");?></option>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
<?php
                      endif;
                    endforeach;?>
                      </tbody>
                    </table>
                    <div class="hidden" data-for="help_for_clientpkg">
                      <br/><br/>
                      <?= gettext("If you expect to see a certain client in the list but it is not there, it is usually due to a CA mismatch between the OpenVPN server instance and the client certificates found in the User Manager.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="vertical-align:top"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Links to OpenVPN clients");?></td>
                  <td>
                    <a href="http://www.sparklabs.com/viscosity/" target="_blank"><?= gettext("Viscosity") ?></a> - <?= gettext("Recommended client for Mac OSX and Windows") ?><br/>
                    <a href="http://openvpn.net/index.php/open-source/downloads.html" target="_blank"><?= gettext("OpenVPN Community Client") ?></a> - <?=gettext("Binaries for Windows, Source for other platforms.")?><br/>
                    <a href="https://play.google.com/store/apps/details?id=de.blinkt.openvpn" target="_blank"><?= gettext("OpenVPN For Android") ?></a> - <?=gettext("Recommended client for Android")?><br/>
                    <a href="http://www.featvpn.com/" target="_blank"><?= gettext("FEAT VPN For Android") ?></a> - <?=gettext("For older versions of Android")?><br/>
                    <?= gettext("OpenVPN Connect") ?>: <a href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn" target="_blank"><?=gettext("Android (Google Play)")?></a> or <a href="https://itunes.apple.com/us/app/openvpn-connect/id590379981" target="_blank"><?=gettext("iOS (App Store)")?></a> - <?= gettext("Recommended client for iOS") ?><br/>
                    <a href="https://tunnelblick.net" target="_blank"><?= gettext("Tunnelblick") ?></a> - <?= gettext("Free client for OSX") ?>
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
</section>

<?php include("foot.inc"); ?>
