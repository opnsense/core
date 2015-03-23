<?php
/*
	openvpn-client-export.inc
	Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2010 Ermal Luci
	All rights reserved.

	Parts of this code was originally based on vpn_ipsec_sad.php
	Copyright (C) 2003-2004 Manuel Kasper

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

require_once("globals.inc");
require_once("openvpn.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

global $current_openvpn_version, $current_openvpn_version_rev;
$current_openvpn_version     = "2.3.6";
$current_openvpn_version_rev = "01";

function openvpn_client_export_install() {
	global $current_openvpn_version;
	conf_mount_rw();
	$tarpath = "/usr/local/pkg/openvpn-client-export-{$current_openvpn_version}.tgz";
	$phpfile = "vpn_openvpn_export.php";
	$ovpndir = "/usr/local/share/openvpn";
	$workdir = "{$ovpndir}/client-export";

	if (!is_dir($workdir))
		mkdir($workdir, 0777, true);

	exec("/usr/bin/tar zxf {$tarpath} -C {$ovpndir}");
	conf_mount_ro();
}

function openvpn_client_export_deinstall() {
	global $current_openvpn_version;
	conf_mount_rw();
	$phpfile = "vpn_openvpn_export.php";
	$phpfile2 = "vpn_openvpn_export_shared.php";
	$ovpndir = "/usr/local/share/openvpn";
	$workdir = "{$ovpndir}/client-export";

	unlink_if_exists("/usr/local/www/{$phpfile}");
	unlink_if_exists("/usr/local/www/{$phpfile2}");
	unlink_if_exists("/usr/local/pkg/openvpn-client-export-{$current_openvpn_version}.tgz");
	exec("/bin/rm -r {$workdir}");
	conf_mount_ro();
}

function openvpn_client_export_prefix($srvid, $usrid = null, $crtid = null) {
	global $config;

	// lookup server settings
	$settings = $config['openvpn']['openvpn-server'][$srvid];
	if (empty($settings))
		return false;
	if ($settings['disable'])
		return false;

	$host = empty($config['system']['hostname']) ? "openvpn" : $config['system']['hostname'];
	$prot = ($settings['protocol'] == 'UDP' ? 'udp' : $settings['protocol']);
	$port = $settings['local_port'];
	
	$filename_addition = "";
	if ($usrid && is_numeric($usrid))
		$filename_addition = "-".$config['system']['user'][$usrid]['name'];
	elseif ($crtid && is_numeric($crtid) && function_exists("cert_get_cn"))
		$filename_addition = "-" . str_replace(' ', '_', cert_get_cn($config['cert'][$crtid]['crt']));

	return "{$host}-{$prot}-{$port}{$filename_addition}";
}

function openvpn_client_pem_to_pk12($outpath, $outpass, $crtpath, $keypath, $capath = false) {
	$eoutpath = escapeshellarg($outpath);
	$eoutpass = escapeshellarg($outpass);
	$ecrtpath = escapeshellarg($crtpath);
	$ekeypath = escapeshellarg($keypath);
	if ($capath) {
		$ecapath = escapeshellarg($capath);
		exec("/usr/bin/openssl pkcs12 -export -in {$ecrtpath} -inkey {$ekeypath} -certfile {$ecapath} -out {$eoutpath} -passout pass:{$eoutpass}");
	} else
		exec("/usr/bin/openssl pkcs12 -export -in {$ecrtpath} -inkey {$ekeypath} -out {$eoutpath} -passout pass:{$eoutpass}");

	unlink($crtpath);
	unlink($keypath);
	if ($capath)
		unlink($capath);
}

function openvpn_client_export_validate_config($srvid, $usrid, $crtid) {
	global $config, $g, $input_errors;

	// lookup server settings
	$settings = $config['openvpn']['openvpn-server'][$srvid];
	if (empty($settings)) {
		$input_errors[] = "Could not locate server configuration.";
		return false;
	}
	if ($settings['disable']) {
		$input_errors[] = "You cannot export for disabled servers.";
		return false;
	}

	// lookup server certificate info
	$server_cert = lookup_cert($settings['certref']);
	if (!$server_cert) 
	{
		$input_errors[] = "Could not locate server certificate.";
	} else {
		$server_ca = lookup_ca($server_cert['caref']);
		if (!$server_ca) {
			$input_errors[] = "Could not locate the CA reference for the server certificate.";
		}
		if (function_exists("cert_get_cn")) {
			$servercn = cert_get_cn($server_cert['crt']);
		}
	}

	// lookup user info
	if (is_numeric($usrid)) {
		$user = $config['system']['user'][$usrid];
		if (!$user) {
			$input_errors[] = "Could not find user settings.";
		}
	}

	// lookup user certificate info
	if ($settings['mode'] == "server_tls_user") {
		if ($settings['authmode'] == "Local Database") {
			$cert = $user['cert'][$crtid];
		} else {
			$cert = $config['cert'][$crtid];
		}
		if (!$cert)
		{
			$input_errors[] = "Could not find client certificate.";
		} else {
			// If $cert is not an array, it's a certref not a cert.
			if (!is_array($cert))
				$cert = lookup_cert($cert);
		}
	} elseif (($settings['mode'] == "server_tls") || (($settings['mode'] == "server_tls_user") && ($settings['authmode'] != "Local Database"))) {
		$cert = $config['cert'][$crtid];
		if (!$cert)
			$input_errors[] = "Could not find client certificate.";
	} else
		$nokeys = true;

	if ($input_errors)
		return false;
		
	return array($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys);
}

function openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $nokeys = false, $proxy, $expformat = "baseconf", $outpass = "", $skiptls=false, $doslines=false, $openvpnmanager, $advancedoptions = "") {
	global $config, $input_errors, $g;
	$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);

	$nl = ($doslines) ? "\r\n" : "\n";
	$conf = "";

	$validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
	if ($validconfig) {
		list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;
	} else {
		return false;
	}

	// determine basic variables
	$remotes = openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl);
	$server_port = $settings['local_port'];
	$cipher = $settings['crypto'];
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	// add basic settings
	$devmode = empty($settings['dev_mode']) ? "tun" : $settings['dev_mode'];
	if (($expformat != "inlinedroid") && ($expformat != "inlineios"))
		$conf .= "dev {$devmode}{$nl}";
	if(!empty($settings['tunnel_networkv6']) && ($expformat != "inlinedroid") && ($expformat != "inlineios")) {
		$conf .= "tun-ipv6{$nl}";
	}
	$conf .= "persist-tun{$nl}";
	$conf .= "persist-key{$nl}";

//	if ((($expformat != "inlinedroid") && ($expformat != "inlineios")) && ($proto == "tcp"))
//		$conf .= "proto tcp-client{$nl}";
	$conf .= "cipher {$cipher}{$nl}";
	$conf .= "auth {$digest}{$nl}";
	$conf .= "tls-client{$nl}";
	$conf .= "client{$nl}";
	if (($expformat != "inlinedroid") && ($expformat != "inlineios"))
		$conf .= "resolv-retry infinite{$nl}";
	$conf .= "$remotes{$nl}";

	/* Use a random local port, otherwise two clients will conflict if they run at the same time.
		May not be supported on older clients (Released before May 2010) */
	if (($randomlocalport != 0) && (substr($expformat, 0, 7) != "yealink") && ($expformat != "snom"))
		$conf .= "lport 0{$nl}";

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
				$input_errors[] = "This server uses UDP protocol and cannot communicate with HTTP proxy.";
				return;
			}
			$conf .= "http-proxy {$proxy['ip']} {$proxy['port']} ";
		}
		if ($proxy['proxy_type'] == "socks") 
			$conf .= "socks-proxy {$proxy['ip']} {$proxy['port']} ";
		if ($proxy['proxy_authtype'] != "none") {
			if (!isset($proxy['passwdfile']))
				$proxy['passwdfile'] = openvpn_client_export_prefix($srvid, $usrid, $crtid) . "-proxy";
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
	if($nokeys == false) {
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
	} else if ($settings['mode'] == "server_user") {
		if (substr($expformat, 0, 6) != "inline")
			$conf .= "ca {$cafile}{$nl}";
	}

	if ($settings['tls'] && !$skiptls) {
		if ($expformat == "yealink_t28")
			$conf .= "tls-auth /yealink/config/openvpn/keys/ta.key 1{$nl}";
		elseif ($expformat == "yealink_t38g")
			$conf .= "tls-auth /phone/config/openvpn/keys/ta.key 1{$nl}";
		elseif ($expformat == "yealink_t38g2")
			$conf .= "tls-auth /config/openvpn/keys/ta.key 1{$nl}";
		elseif ($expformat == "snom")
			$conf .= "tls-auth /openvpn/ta.key 1{$nl}";
		elseif (substr($expformat, 0, 6) != "inline")
			$conf .= "tls-auth {$prefix}-tls.key 1{$nl}";
	}

	// Prevent MITM attacks by verifying the server certificate.
	// - Disable for now, it requires the server cert to include special options
	//$conf .= "remote-cert-tls server{$nl}";

	// Extra protection for the server cert, if it's supported
	if (function_exists("cert_get_purpose")) {
		if (is_array($server_cert) && ($server_cert['crt'])) {
			$purpose = cert_get_purpose($server_cert['crt'], true);
			if ($purpose['server'] == 'Yes')
				$conf .= "ns-cert-type server{$nl}";
		}
	}

	// add optional settings
	if (!empty($settings['compression'])) {
		if ($pfs_version > 2.1)
			$conf .= "comp-lzo {$settings['compression']}{$nl}";
		else
			$conf .= "comp-lzo{$nl}";
	}

	if ($settings['passtos'])
		$conf .= "passtos{$nl}";

	if ($openvpnmanager)
	{
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
			$tempdir = "{$g['tmp_path']}/{$prefix}";
			@mkdir($tempdir, 0700, true);

			file_put_contents("{$tempdir}/{$prefix}.ovpn", $conf);

			$cafile = "{$tempdir}/{$cafile}";
			file_put_contents("{$cafile}", base64_decode($server_ca['crt']));
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
				if ($usetoken)
					openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile);
				else
					openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile, $cafile);
			}
			$command = "cd " . escapeshellarg("{$tempdir}/..")
					. " && /usr/local/bin/zip -r "
					. escapeshellarg("{$g['tmp_path']}/{$prefix}-config.zip")
					. " " . escapeshellarg($prefix);
			exec($command);
			// Remove temporary directory
			exec("rm -rf " . escapeshellarg($tempdir));
			return "{$g['tmp_path']}/{$prefix}-config.zip";
			break;
		case "inline":
		case "inlinedroid":
		case "inlineios":
			// Inline CA
			$conf .= "<ca>{$nl}" . trim(base64_decode($server_ca['crt'])) . "{$nl}</ca>{$nl}";
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
			$tempdir = "{$g['tmp_path']}/{$prefix}";
			$keydir  = "{$tempdir}/keys";
			mkdir($tempdir, 0700, true);
			mkdir($keydir, 0700, true);

			file_put_contents("{$tempdir}/vpn.cnf", $conf);

			$cafile = "{$keydir}/ca.crt";
			file_put_contents("{$cafile}", base64_decode($server_ca['crt']));
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
			exec("tar -C {$tempdir} -cf {$g['tmp_path']}/client.tar ./keys ./vpn.cnf");
			// Remove temporary directory
			exec("rm -rf {$tempdir}");
			return $g['tmp_path'] . "/client.tar";
			break;
		case "snom":
			// create template directory
			$tempdir = "{$g['tmp_path']}/{$prefix}";
			mkdir($tempdir, 0700, true);

			file_put_contents("{$tempdir}/vpn.cnf", $conf);

			$cafile = "{$tempdir}/ca.crt";
			file_put_contents("{$cafile}", base64_decode($server_ca['crt']));
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
			exec("cd {$tempdir}/ && tar -cf {$g['tmp_path']}/vpnclient.tar *");
			// Remove temporary directory
			exec("rm -rf {$tempdir}");
			return $g['tmp_path'] . "/vpnclient.tar";
			break;
		default:
			return $conf;
	}
}

function openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $outpass, $proxy, $openvpnmanager, $advancedoptions, $openvpn_version = "x86-xp") {
	global $config, $g, $input_errors, $current_openvpn_version, $current_openvpn_version_rev;
	$uname_p = trim(exec("uname -p"));

	switch ($openvpn_version) {
		case "x86-xp":
			$client_install_exe = "openvpn-install-{$current_openvpn_version}-I0{$current_openvpn_version_rev}-i686.exe";
			break;
		case "x64-xp":
			$client_install_exe = "openvpn-install-{$current_openvpn_version}-I0{$current_openvpn_version_rev}-x86_64.exe";
			break;
		case "x86-win6":
			$client_install_exe = "openvpn-install-{$current_openvpn_version}-I6{$current_openvpn_version_rev}-i686.exe";
			break;
		case "x64-win6":
			$client_install_exe = "openvpn-install-{$current_openvpn_version}-I6{$current_openvpn_version_rev}-x86_64.exe";
			break;
		default:
			$client_install_exe = "openvpn-install-{$current_openvpn_version}-I0{$current_openvpn_version_rev}-i686.exe";
	}

	$ovpndir = "/usr/local/share/openvpn";
	$workdir = "{$ovpndir}/client-export";
	if (!file_exists($workdir . "/template/{$client_install_exe}"))
		openvpn_client_export_install();

	$validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
	if ($validconfig) {
		list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;
	} else {
		return false;
	}

	// create template directory
	$tempdir = $g['tmp_path'] . "/openvpn-export-".uniqid();
	mkdir($tempdir, 0700, true);

	// create config directory
	$confdir = "{$tempdir}/config";
	if (!is_dir($conf_dir))
		mkdir($confdir, 0700, true);

	// copy the template directory
	exec("cp -r {$workdir}/template/* {$tempdir}");
	// and put the required installer exe in place
	exec("/bin/cp {$tempdir}/{$client_install_exe} {$tempdir}/openvpn-install.exe");
	if (stristr($openvpn_version, "x64"))
		rename("{$tempdir}/openvpn-postinstall64.exe", "{$tempdir}/openvpn-postinstall.exe");

	// write configuration file
	$prefix = openvpn_client_export_prefix($srvid, $usrid, $crtid);
	$cfgfile = "{$confdir}/{$prefix}-config.ovpn";
	if (!empty($proxy) && $proxy['proxy_authtype'] != "none") {
		$proxy['passwdfile'] = "{$prefix}-password";
		$pwdfle = "{$proxy['user']}\r\n";
		$pwdfle .= "{$proxy['password']}\r\n";
		file_put_contents("{$confdir}/{$proxy['passwdfile']}", $pwdfle);
	}
	$conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken,  $nokeys, $proxy, "", "baseconf", false, true, $openvpnmanager, $advancedoptions);
	if (!$conf) {
		$input_errors[] = "Could not create a config to export.";
		return false;
	}

	file_put_contents($cfgfile, $conf);

	$cafile = "{$tempdir}/config/{$prefix}-ca.crt";
	file_put_contents($cafile, base64_decode($server_ca['crt']));
	if ($settings['tls']) {
		$tlsfile = "{$tempdir}/config/{$prefix}-tls.key";
		file_put_contents($tlsfile, base64_decode($settings['tls']));
	}

	// write key files
	if ($settings['mode'] != "server_user") {
		$crtfile = "{$tempdir}/config/{$prefix}-{$user['name']}.crt";
		file_put_contents($crtfile, base64_decode($cert['crt']));
		$keyfile = "{$tempdir}/config/{$prefix}-{$user['name']}.key";
		file_put_contents($keyfile, base64_decode($cert['prv']));
		// convert to pkcs12 format
		$p12file = "{$tempdir}/config/{$prefix}.p12";
		if ($usetoken)
			openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile);
		else
			openvpn_client_pem_to_pk12($p12file, $outpass, $crtfile, $keyfile, $cafile);
	}

	// 7zip the configuration data
	chdir($tempdir);
	$files  = "config ";
	if ($openvpnmanager)
		$files .= "openvpnmanager ";

	$files .= "openvpn-install.exe ";
	$files .= "openvpn-postinstall.exe ";
	if ($usetoken)
		$procchain = 	';!@Install@!UTF-8!
RunProgram="openvpn-postinstall.exe /Import"
;!@InstallEnd@!'
;
	else
		$procchain = 	';!@Install@!UTF-8!
RunProgram="openvpn-postinstall.exe"
;!@InstallEnd@!'
;
	file_put_contents("{$tempdir}/7zipConfig",$procchain);

	if(file_exists("/usr/pbi/p7zip-{$uname_p}/bin/7z"))
		exec("/usr/pbi/p7zip-{$uname_p}/bin/7z -y a archive.7z {$files}");
	else
		exec("/usr/local/libexec/p7zip/7z -y a archive.7z {$files}");

	// create the final installer
	$outfile = "{$tempdir}-install.exe";
	chdir($g['tmp_path']);
	exec("/bin/cat {$tempdir}/7zS.sfx {$tempdir}/7zipConfig {$tempdir}/archive.7z > {$outfile}");

	// cleanup
	exec("/bin/rm -r {$tempdir}");

	return $outfile;
}

function viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, $outpass, $proxy, $openvpnmanager, $advancedoptions) {
	global $config, $g;
	$uname_p = trim(exec("uname -p"));

	$ovpndir = "/usr/local/share/openvpn/";
	if (!file_exists($workdir . "/template/openvpn-install.exe"))
		openvpn_client_export_install();

	$uniq = uniqid();
	$tempdir = $g['tmp_path'] . "/openvpn-export-" . $uniq;
	$zipfile = $g['tmp_path'] . "/{$uniq}-Viscosity.visc.zip";

	$validconfig = openvpn_client_export_validate_config($srvid, $usrid, $crtid);
	if ($validconfig) {
		list($settings, $server_cert, $server_ca, $servercn, $user, $cert, $nokeys) = $validconfig;
	} else {
		return false;
	}

	// create template directory
	mkdir($tempdir, 0700, true);
	mkdir($tempdir . "/Viscosity.visc", 0700, true);

	// Append new Viscosity.visc directory on top
	$tempdir = $tempdir . "/Viscosity.visc/";

	// write cofiguration file
	$prefix = openvpn_client_export_prefix($srvid, $usrid, $crtid);
	if (!empty($proxy) && $proxy['proxy_authtype'] != "none") {
		$proxy['passwdfile'] = "config-password";
		$pwdfle = "{$proxy['user']}\n";
		$pwdfle .= "{$proxy['password']}\n";
		file_put_contents("{$tempdir}/{$proxy['passwdfile']}", $pwdfle);
	}

	$conf = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $randomlocalport, $usetoken, true, $proxy, "baseconf", $outpass, true, true, $openvpnmanager, $advancedoptions);
	if (!$conf)
		return false;

	// We need to nuke the ca line from the above config if it exists.
	$conf = explode("\n", $conf);
	for ($i=0; $i < count($conf); $i++) {
		if ((substr($conf[$i], 0, 3) == "ca ") || (substr($conf[$i], 0, 7) == "pkcs12 "))
			unset($conf[$i]);
	}
	$conf = implode("\n", $conf);

	$friendly_name = $settings['description'];
	$visc_settings = <<<EOF
#-- Config Auto Generated By pfSense for Viscosity --#

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

	//	ca.crt		cert.crt	config.conf	key.key		ta.key

	// write ca
	$cafile = "{$tempdir}/ca.crt";
	file_put_contents($cafile, base64_decode($server_ca['crt']));

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
			exec("/usr/bin/openssl rsa -in ${eclearkeyfile} -out ${ekeyfile} -des3 -passout pass:${eoutpass}");
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
	if(file_exists("/usr/pbi/zip-{$uname_p}/bin/zip"))
		exec("cd {$tempdir}/.. && /usr/pbi/zip-{$uname_p}/bin/zip -r {$zipfile} Viscosity.visc");
	else
		exec("cd {$tempdir}/.. && /usr/local/bin/zip -r {$zipfile} Viscosity.visc");

	// Remove temporary directory
	exec("rm -rf {$tempdir}");

	return $zipfile;

}

function openvpn_client_export_sharedkey_config($srvid, $useaddr, $proxy, $zipconf = false) {
	global $config, $input_errors, $g;

	// lookup server settings
	$settings = $config['openvpn']['openvpn-server'][$srvid];
	if (empty($settings)) {
		$input_errors[] = "Could not locate server configuration.";
		return false;
	}
	if ($settings['disable']) {
		$input_errors[] = "You cannot export for disabled servers.";
		return false;
	}

	// determine basic variables
	if ($useaddr == "serveraddr") {
		$interface = $settings['interface'];
		if (!empty($settings['ipaddr']) && is_ipaddr($settings['ipaddr'])) {
			$server_host = $settings['ipaddr'];
		} else {
			if (!$interface)
				$interface = "wan";
			if (in_array(strtolower($settings['protocol']), array("udp6", "tcp6")))
				$server_host = get_interface_ipv6($interface);
			else
				$server_host = get_interface_ip($interface);
		}
	} else if ($useaddr == "serverhostname" || empty($useaddr)) {
		$server_host = empty($config['system']['hostname']) ? "" : "{$config['system']['hostname']}.";
		$server_host .= "{$config['system']['domain']}";
	} else
		$server_host = $useaddr;

	$server_port = $settings['local_port'];

	$proto = strtolower($settings['protocol']);
	if (strtolower(substr($settings['protocol'], 0, 3)) == "tcp")
		$proto .= "-client";

	$cipher = $settings['crypto'];
	$digest = !empty($settings['digest']) ? $settings['digest'] : "SHA1";

	// add basic settings
	$conf  = "dev tun\n";
	if(! empty($settings['tunnel_networkv6'])) {
		$conf .= "tun-ipv6\n";
	}
	$conf .= "persist-tun\n";
	$conf .= "persist-key\n";
	$conf .= "proto {$proto}\n";
	$conf .= "cipher {$cipher}\n";
	$conf .= "auth {$digest}\n";
	$conf .= "pull\n";
	$conf .= "resolv-retry infinite\n";
	$conf .= "remote {$server_host} {$server_port}\n";
	if ($settings['local_network']) {
		list($ip, $mask) = explode('/', $settings['local_network']);
		$mask = gen_subnet_mask($mask);
		$conf .= "route $ip $mask\n";
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
				$input_errors[] = "This server uses UDP protocol and cannot communicate with HTTP proxy.";
				return;
			}
			$conf .= "http-proxy {$proxy['ip']} {$proxy['port']} ";
		}
		if ($proxy['proxy_type'] == "socks") 
			$conf .= "socks-proxy {$proxy['ip']} {$proxy['port']} ";
		if ($proxy['proxy_authtype'] != "none") {
			if (!isset($proxy['passwdfile']))
				$proxy['passwdfile'] = openvpn_client_export_prefix($srvid) . "-proxy";
			$conf .= " {$proxy['passwdfile']} {$proxy['proxy_authtype']}";
		}
		$conf .= "\n";
	}

	// add key settings
	$prefix = openvpn_client_export_prefix($srvid);
	$shkeyfile = "{$prefix}.secret";
	$conf .= "secret {$shkeyfile}\n";

	// add optional settings
	if ($settings['compression'])
		$conf .= "comp-lzo\n";
	if ($settings['passtos'])
		$conf .= "passtos\n";

	if ($zipconf == true) {
		// create template directory
		$tempdir = "{$g['tmp_path']}/{$prefix}";
		mkdir($tempdir, 0700, true);

		file_put_contents("{$tempdir}/{$prefix}.ovpn", $conf);

		$shkeyfile = "{$tempdir}/{$shkeyfile}";
		file_put_contents("{$shkeyfile}", base64_decode($settings['shared_key']));

		if(file_exists("/usr/pbi/zip-{$uname_p}/bin/zip"))
			exec("cd {$tempdir}/.. && /usr/pbi/zip-{$uname_p}/bin/zip -r {$g['tmp_path']}/{$prefix}-config.zip {$prefix}");
		else
			exec("cd {$tempdir}/.. && /usr/local/bin/zip -r {$g['tmp_path']}/{$prefix}-config.zip {$prefix}");

		// Remove temporary directory
		exec("rm -rf {$tempdir}");
		return "{$prefix}-config.zip";
	} else
		return $conf;
}

function openvpn_client_export_build_remote_lines($settings, $useaddr, $interface, $expformat, $nl) {
	global $config;
	$remotes = array();
	if (($useaddr == "serveraddr") || ($useaddr == "servermagic") || ($useaddr == "servermagichost")) {
		$interface = $settings['interface'];
		if (!empty($settings['ipaddr']) && is_ipaddr($settings['ipaddr'])) {
			$server_host = $settings['ipaddr'];
		} else {
			if (!$interface || ($interface == "any"))
				$interface = "wan";
			if (in_array(strtolower($settings['protocol']), array("udp6", "tcp6")))
				$server_host = get_interface_ipv6($interface);
			else
				$server_host = get_interface_ip($interface);
		}
	} else if ($useaddr == "serverhostname" || empty($useaddr)) {
		$server_host = empty($config['system']['hostname']) ? "" : "{$config['system']['hostname']}.";
		$server_host .= "{$config['system']['domain']}";
	} else
		$server_host = $useaddr;

	$proto = strtolower($settings['protocol']);
	if (strtolower(substr($settings['protocol'], 0, 3)) == "tcp")
		$proto .= "-client";

	if (($expformat == "inlineios") && ($proto == "tcp-client"))
		$proto = "tcp";

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

function openvpn_client_export_find_port_forwards($targetip, $targetport, $targetproto, $skipprivate, $findhostname=false) {
	global $config, $FilterIflist;
	if (empty($FilterIflist))
		filter_generate_optcfg_array();
	$destinations = array();

	if (!is_array($config['nat']) || !is_array($config['nat']['rule']))
		return $destinations;

	foreach ($config['nat']['rule'] as $natent) {
		$dest = array();
		if (!isset($natent['disabled'])
			&& ($natent['target'] == $targetip)
			&& ($natent['local-port'] == $targetport)
			&& ($natent['protocol'] == $targetproto)) {
			$dest['proto'] = $natent['protocol'];

			// Could be multiple ports... But we can only use one.
			$dports = is_port($natent['destination']['port']) ? array($natent['destination']['port']) : filter_expand_alias_array($natent['destination']['port']);
			$dest['port'] = $dports[0];

			// Could be network or address ...
			$natif = (!$natent['interface']) ? "wan" : $natent['interface'];

			if (!isset($FilterIflist[$natif]))
				continue; // Skip if there is no interface

			$dstaddr = trim(filter_generate_address($natent, 'destination', true));
			if(!$dstaddr)
				$dstaddr = $FilterIflist[$natif]['ip'];

			$dstaddr_port = explode(" ", $dstaddr);

			if(empty($dstaddr_port[0]) || strtolower(trim($dstaddr_port[0])) == "port")
				continue; // Skip port forward if no destination address found


			if (!is_ipaddr($dstaddr_port[0]))
				continue; // We can only work with single IPs, not subnets!


			if ($skipprivate && is_private_ip($dstaddr_port[0]))
				continue; // Skipping a private IP destination!

			$dest['host'] = $dstaddr_port[0];

			if ($findhostname) {
				$hostname = openvpn_client_export_find_hostname($natif);
				if (!empty($hostname))
					$dest['host'] = $hostname;
			}

			$destinations[] = $dest;
		}
	}

	return $destinations;
}

function openvpn_client_export_find_hostname($interface) {
	global $config;
	$hostname = "";
	if (is_array($config['dyndnses']['dyndns'])) {
		foreach ($config['dyndnses']['dyndns'] as $ddns) {
			if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host']))
				return $ddns['host'];
		}
	}
	if (is_array($config['dnsupdates']['dnsupdate'])) {
		foreach ($config['dnsupdates']['dnsupdate'] as $ddns) {
			if (($ddns['interface'] == $interface) && isset($ddns['enable']) && !empty($ddns['host']) && !is_numeric($ddns['host']) && is_hostname($ddns['host']))
				return $ddns['host'];
		}
	}

}
?>
