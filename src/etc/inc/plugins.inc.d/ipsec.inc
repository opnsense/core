<?php

/*
 * Copyright (C) 2016 Deciso B.V.
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2008 Ermal Luçi
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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

$ipsec_loglevels = array(
    "asn" => "Low-level encoding/decoding (ASN.1, X.509 etc.)",
    "cfg" => "Configuration management and plugins",
    "chd" => "CHILD_SA/IPsec SA",
    "dmn" => "Main daemon setup/cleanup/signal handling",
    "enc" => "Packet encoding/decoding encryption/decryption operations",
    "esp" => "libipsec library messages",
    "ike" => "IKE_SA/ISAKMP SA",
    "imc" => "Integrity Measurement Collector",
    "imv" => "Integrity Measurement Verifier",
    "job" => "Jobs queuing/processing and thread pool management",
    "knl" => "IPsec/Networking kernel interface",
    "lib" => "libstrongwan library messages",
    "mgr" => "IKE_SA manager, handling synchronization for IKE_SA access",
    "net" => "IKE network communication",
    "pts" => "Platform Trust Service",
    "tls" => "libtls library messages",
    "tnc" => "Trusted Network Connect"
);

$p1_ealgos = array(
    'aes' => array( 'name' => 'AES', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ), 'iketype' => null ),
    'aes128gcm16' => array( 'name' => '128 bit AES-GCM with 128 bit ICV', 'iketype' => null ),
    'aes192gcm16' => array( 'name' => '192 bit AES-GCM with 128 bit ICV', 'iketype' => null ),
    'aes256gcm16' => array( 'name' => '256 bit AES-GCM with 128 bit ICV', 'iketype' => null ),
    'camellia' => array( 'name' => 'Camellia', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ), 'iketype' => 'ikev2' ),
    'blowfish' => array( 'name' => 'Blowfish', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ), 'iketype' => null ),
    '3des' => array( 'name' => '3DES', 'iketype' => null ),
    'cast128' => array( 'name' => 'CAST128', 'iketype' => null ),
    'des' => array( 'name' => 'DES', 'iketype' => null )
);

$p1_authentication_methods = array(
    'hybrid_rsa_server' => array( 'name' => 'Hybrid RSA + Xauth', 'mobile' => true ),
    'xauth_rsa_server' => array( 'name' => 'Mutual RSA + Xauth', 'mobile' => true ),
    'xauth_psk_server' => array( 'name' => 'Mutual PSK + Xauth', 'mobile' => true ),
    'eap-tls' => array( 'name' => 'EAP-TLS', 'mobile' => true),
    'psk_eap-tls' => array( 'name' => 'RSA (local) + EAP-TLS (remote)', 'mobile' => true),
    'eap-mschapv2' => array( 'name' => 'EAP-MSCHAPV2', 'mobile' => true),
    'rsa_eap-mschapv2' => array( 'name' => 'Mutual RSA + EAP-MSCHAPV2', 'mobile' => true),
    'eap-radius' => array( 'name' => 'EAP-RADIUS', 'mobile' => true),
    'rsasig' => array( 'name' => 'Mutual RSA', 'mobile' => false ),
    'pre_shared_key' => array( 'name' => 'Mutual PSK', 'mobile' => false ),
);

$p2_ealgos = array(
    'aes' => array( 'name' => 'AES', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
    'aes128gcm16' => array( 'name' => 'aes128gcm16'),
    'aes192gcm16' => array( 'name' => 'aes192gcm16'),
    'aes256gcm16' => array( 'name' => 'aes256gcm16'),
    'blowfish' => array( 'name' => 'Blowfish', 'keysel' => array( 'lo' => 128, 'hi' => 256, 'step' => 64 ) ),
    '3des' => array( 'name' => '3DES' ),
    'cast128' => array( 'name' => 'CAST128' ),
    'des' => array( 'name' => 'DES' ),
    'null' => array( 'name' => gettext("NULL (no encryption)"))
);

$p2_halgos = array(
    'hmac_md5' => 'MD5',
    'hmac_sha1' => 'SHA1',
    'hmac_sha256' => 'SHA256',
    'hmac_sha384' => 'SHA384',
    'hmac_sha512' => 'SHA512',
    'aesxcbc' => 'AES-XCBC'
);

$p2_protos = array(
    'esp' => 'ESP',
    'ah' => 'AH'
);

function ipsec_configure()
{
    return array(
        'vpn' => array('ipsec_configure_do:2'),
    );
}

function ipsec_syslog()
{
    $logfacilities = array();

    $logfacilities['ipsec'] = array(
        'facility' => array('charon'),
        'remote' => 'vpn',
    );

    return $logfacilities;
}

function ipsec_services()
{
    global $config;

    $services = array();

    if (!empty($config['ipsec']['enable']) || !empty($config['ipsec']['client']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = 'strongswan';
        $pconfig['description'] = gettext('IPsec VPN');
        $pconfig['pidfile'] = '/var/run/charon.pid';
        $pconfig['configd'] = array(
          'restart' => array('ipsec restart'),
          'start' => array('ipsec start'),
          'stop' => array('ipsec stop'),
        );
        $services[] = $pconfig;
    }

    return $services;
}

function ipsec_interfaces()
{
    global $config;

    $interfaces = array();

    if (isset($config['ipsec']['phase1']) && isset($config['ipsec']['phase2'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (!empty($ph1ent['disabled'])) {
                continue;
            }

            foreach ($config['ipsec']['phase2'] as $ph2ent) {
                if (!empty($ph2ent['disabled']) || $ph1ent['ikeid'] != $ph2ent['ikeid']) {
                    continue;
                }

                if ((!empty($ph2ent['mobile']) && empty($config['ipsec']['client']['enable'])) ||
                    empty($config['ipsec']['enable'])) {
                    continue;
                }

                $oic = array('enable' => true);
                $oic['if'] = 'enc0';
                $oic['descr'] = 'IPsec';
                $oic['type'] = 'none';
                $oic['virtual'] = true;
                $oic['networks'] = array();
                $interfaces['enc0'] = $oic;

                break 2;
            }
        }
    }

    return $interfaces;
}

function ipsec_firewall($fw)
{
    global $config;

    if (!isset($config['system']['disablevpnrules']) &&
            isset($config['ipsec']['enable']) && isset($config['ipsec']['phase1'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (!isset($ph1ent['disabled'])) {
                // detect remote ip
                $rgip = null;
                if (isset($ph1ent['mobile'])) {
                    $rgip = "any";
                } elseif (!is_ipaddr($ph1ent['remote-gateway'])) {
                    $dns_qry_type = $ph1ent['protocol'] == 'inet6' ? DNS_AAAA : DNS_A;
                    $dns_qry_outfield = $ph1ent['protocol'] == 'inet6' ? "ipv6" : "ip";
                    $dns_records = @dns_get_record($ph1ent['remote-gateway'], $dns_qry_type);
                    if (is_array($dns_records)) {
                        foreach ($dns_records as $dns_record) {
                            if (!empty($dns_record[$dns_qry_outfield])) {
                                $rgip = $dns_record[$dns_qry_outfield];
                                break;
                            }
                        }
                    }
                } else {
                    $rgip = $ph1ent['remote-gateway'];
                }
                if (!empty($rgip)) {
                    $protos_used = array();
                    if (is_array($config['ipsec']['phase2'])) {
                        foreach ($config['ipsec']['phase2'] as $ph2ent) {
                            if ($ph2ent['ikeid'] == $ph1ent['ikeid']) {
                                if ($ph2ent['protocol'] == 'esp' || $ph2ent['protocol'] == 'ah') {
                                    if (!in_array($ph2ent['protocol'], $protos_used)) {
                                        $protos_used[] = $ph2ent['protocol'];
                                    }
                                }
                            }
                        }
                    }
                    $interface = explode("_vhid", $ph1ent['interface'])[0];
                    $baserule = array("interface" => $interface,
                                      "log" => !isset($config['syslog']['nologdefaultpass']),
                                      "quick" => false,
                                      "type" => "pass",
                                      "statetype" => "keep",
                                      "label" => "IPsec: " . (!empty($ph1ent['descr']) ? $ph1ent['descr'] : $rgip)
                                    );

                    // find gateway
                    $gwname = null;
                    foreach ($fw->getInterfaceMapping() as $intfnm => $intf) {
                        if ($intfnm == $interface) {
                            foreach ($fw->getInterfaceGateways($intf['if']) as $gwnm) {
                                $gw = $fw->getGateway($gwnm);
                                if ($gw['proto'] == $ph1ent['protocol']) {
                                    $gwname = $gwnm;
                                    break;
                                }
                            }
                        }
                    }
                    // register rules
                    $fw->registerFilterRule(
                        500000,
                        array("direction" => "out", "protocol" => "udp", "to" => $rgip, "to_port" => 500,
                              "gateway" => $gwname, "disablereplyto" => true),
                        $baserule
                    );
                    $fw->registerFilterRule(
                        500000,
                        array("direction" => "in", "protocol" => "udp", "from" => $rgip, "to_port" => 500,
                              "reply-to" => $gwname),
                        $baserule
                    );
                    if ($ph1ent['nat_traversal'] != "off") {
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "out", "protocol" => "udp", "to" => $rgip, "to_port" => 4500,
                                  "gateway" => $gwname, "disablereplyto" => true),
                            $baserule
                        );
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "in", "protocol" => "udp", "from" => $rgip, "to_port" => 4500,
                                  "reply-to" => $gwname),
                            $baserule
                        );
                    }
                    foreach ($protos_used as $proto) {
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "out", "protocol" => $proto, "to" => $rgip,
                                  "gateway" => $gwname, "disablereplyto" => true),
                            $baserule
                        );
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "in", "protocol" => $proto, "from" => $rgip,
                                  "reply-to" => $gwname),
                            $baserule
                        );
                    }
                }
            }
        }
    }
}

function ipsec_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('IPsec'),
        'section' => 'ipsec',
        'id' => 'ipsec',
    );

    return $result;
}

/*
 * Return phase1 local address
 */
function ipsec_get_phase1_src(&$ph1ent)
{
    if (!empty($ph1ent['interface'])) {
        if ($ph1ent['interface'] == 'any') {
            return '%any';
        } elseif (!is_ipaddr($ph1ent['interface'])) {
            if (strpos($ph1ent['interface'], '_vip') !== false) {
                // if this is a vip, set the interface to $ph1ent['interface']
                $if = $ph1ent['interface'];
            } else {
                // not a vip, check failover interface
                if ($ph1ent['protocol'] == "inet6") {
                    $if = get_failover_interface($ph1ent['interface'], "inet6");
                } else {
                    $if = get_failover_interface($ph1ent['interface']);
                }
            }
        } else {
            // interface is an ip address, return
            return $ph1ent['interface'];
        }
    } else {
        $if = "wan";
    }
    if ($ph1ent['protocol'] == "inet6") {
        return get_interface_ipv6($if);
    } else {
        return get_interface_ip($if);
    }
}

/*
 * Return phase2 idinfo in cidr format
 */
function ipsec_idinfo_to_cidr(&$idinfo, $addrbits = false, $mode = '')
{
    switch ($idinfo['type']) {
        case "address":
            if ($addrbits) {
                if ($mode == "tunnel6") {
                    return $idinfo['address']."/128";
                } else {
                    return $idinfo['address']."/32";
                }
            } else {
                return $idinfo['address'];
            }
            break; /* NOTREACHED */
        case "network":
            return "{$idinfo['address']}/{$idinfo['netbits']}";
            break; /* NOTREACHED */
        case "none":
        case "mobile":
            return "0.0.0.0/0";
            break; /* NOTREACHED */
        default:
            if (empty($mode) && !empty($idinfo['mode'])) {
                $mode = $idinfo['mode'];
            }

            if ($mode == "tunnel6") {
                $address = get_interface_ipv6($idinfo['type']);
                $netbits = get_interface_subnetv6($idinfo['type']);
                $address = gen_subnetv6($address, $netbits);
                return "{$address}/{$netbits}";
            } else {
                $address = get_interface_ip($idinfo['type']);
                $netbits = get_interface_subnet($idinfo['type']);
                $address = gen_subnet($address, $netbits);
                return "{$address}/{$netbits}";
            }
            break; /* NOTREACHED */
    }
}

/*
 * Return phase1 association for phase2
 */
function ipsec_lookup_phase1(&$ph2ent, &$ph1ent)
{
    global $config;

    if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
        return false;
    }
    if (!is_array($config['ipsec']['phase1'])) {
        return false;
    }
    if (empty($config['ipsec']['phase1'])) {
        return false;
    }

    foreach ($config['ipsec']['phase1'] as $ph1tmp) {
        if ($ph1tmp['ikeid'] == $ph2ent['ikeid']) {
            $ph1ent = $ph1tmp;
            return $ph1ent;
        }
    }

    return false;
}

/*
 * Check phase1 communications status
 */
function ipsec_phase1_status($ipsec_status, $ikeid)
{
    foreach ($ipsec_status as $ike) {
        if ($ike['id'] != $ikeid) {
            continue;
        }
        if ($ike['status'] == 'established') {
            return true;
        }
        break;
    }

    return false;
}

/*
 * Return dump of SPD table
 */
function ipsec_dump_spd()
{
    $fd = @popen("/sbin/setkey -DP", "r");
    $spd = array();
    if ($fd) {
        while (!feof($fd)) {
            $line = chop(fgets($fd));
            if (!$line) {
                continue;
            }
            if ($line == "No SPD entries.") {
                break;
            }
            if ($line[0] != "\t") {
                if (isset($cursp)) {
                    $spd[] = $cursp;
                }
                $cursp = array();
                $linea = explode(" ", $line);
                $cursp['srcid'] = substr($linea[0], 0, strpos($linea[0], "["));
                $cursp['dstid'] = substr($linea[1], 0, strpos($linea[1], "["));
                $i = 0;
            } elseif (isset($cursp)) {
                $linea = explode(" ", trim($line));
                switch ($i) {
                    case 1:
                        if ($linea[1] == "none") { /* don't show default anti-lockout rule */
                            unset($cursp);
                        } else {
                            $cursp['dir'] = $linea[0];
                        }
                        break;
                    case 2:
                        $upperspec = explode("/", $linea[0]);
                        $cursp['proto'] = $upperspec[0];
                        list($cursp['src'], $cursp['dst']) = explode("-", $upperspec[2]);
                        $cursp['reqid'] =  substr($upperspec[3], strpos($upperspec[3], "#")+1);
                        break;
                }
            }
            $i++;
        }
        if (isset($cursp) && count($cursp)) {
            $spd[] = $cursp;
        }
        pclose($fd);
    }

    return $spd;
}

/*
 * Return dump of SAD table
 */
function ipsec_dump_sad()
{
    $fd = @popen("/sbin/setkey -D", "r");
    $sad = array();
    if ($fd) {
        while (!feof($fd)) {
            $line = chop(fgets($fd));
            if (!$line || $line[0] == " ") {
                continue;
            }
            if ($line == "No SAD entries.") {
                break;
            }
            if ($line[0] != "\t") {
                if (is_array($cursa)) {
                    $sad[] = $cursa;
                }
                $cursa = array();
                list($cursa['src'],$cursa['dst']) = explode(" ", $line);
                $i = 0;
            } else {
                $linea = explode(" ", trim($line));
                switch ($i) {
                    case 1:
                        $cursa['proto'] = $linea[0];
                        $cursa['spi'] = substr($linea[2], strpos($linea[2], "x")+1, -1);
                        $reqid = substr($linea[3], strpos($linea[3], "=")+1);
                        $cursa['reqid'] = substr($reqid, 0, strcspn($reqid, "("));
                        break;
                    case 2:
                        $cursa['ealgo'] = $linea[1];
                        break;
                    case 3:
                        $cursa['aalgo'] = $linea[1];
                        break;
                    case 8:
                        $sadata = explode("(", $linea[1]);
                        $cursa['data'] = $sadata[0] . " B";
                        break;
                }
            }
            $i++;
        }
        if (is_array($cursa) && count($cursa)) {
            $sad[] = $cursa;
        }
        pclose($fd);
    }

    return $sad;
}

function ipsec_mobilekey_sort()
{
    global $config;

    usort($config['ipsec']['mobilekey'], function ($a, $b) {
        return strcmp($a['ident'][0], $b['ident'][0]);
    });
}

function ipsec_get_number_of_phase2($ikeid)
{
    global $config;

    $a_phase2 = $config['ipsec']['phase2'];
    $nbph2 = 0;

    if (is_array($a_phase2) && count($a_phase2)) {
        foreach ($a_phase2 as $ph2tmp) {
            if ($ph2tmp['ikeid'] == $ikeid) {
                $nbph2++;
            }
        }
    }

    return $nbph2;
}

function ipsec_resolve($hostname)
{
    if (!is_ipaddr($hostname)) {
        /* XXX IPv4-only */
        $ip = gethostbyname($hostname);
        if ($ip && $ip != $hostname) {
            $hostname = $ip;
        }
    }

    return $hostname;
}

function ipsec_find_id(&$ph1ent, $side = 'local')
{
    $id_data = null;
    if ($side == "local") {
        $id_type = $ph1ent['myid_type'];
        $id_data = isset($ph1ent['myid_data']) ? $ph1ent['myid_data'] : null;
    } elseif ($side == "peer") {
        $id_type = $ph1ent['peerid_type'];
        $id_data = isset($ph1ent['peerid_data']) ? $ph1ent['peerid_data'] : null;
        /* Only specify peer ID if we are not dealing with a mobile PSK-only tunnel */
        if (isset($ph1ent['mobile'])) {
            return null;
        }
    }

    switch ($id_type) {
        case "myaddress":
            $thisid_data = ipsec_get_phase1_src($ph1ent);
            break;
        case "dyn_dns":
            $thisid_data = ipsec_resolve($id_data);
            break;
        case "peeraddress":
            $thisid_data = ipsec_resolve($ph1ent['remote-gateway']);
            break;
        default:
            $thisid_data = !empty($id_data) ? "{$id_data}" : null;
            break;
    }
    return $thisid_data;
}

/* include all configuration functions */
function ipsec_convert_to_modp($index)
{
    $convertion = "";
    switch ($index) {
        case '1':
            $convertion = "modp768";
            break;
        case '2':
            $convertion = "modp1024";
            break;
        case '5':
            $convertion = "modp1536";
            break;
        case '14':
            $convertion = "modp2048";
            break;
        case '15':
            $convertion = "modp3072";
            break;
        case '16':
            $convertion = "modp4096";
            break;
        case '17':
            $convertion = "modp6144";
            break;
        case '18':
            $convertion = "modp8192";
            break;
        case '19':
            $convertion = "ecp256";
            break;
        case '20':
            $convertion = "ecp384";
            break;
        case '21':
            $convertion = "ecp521";
            break;
        case '22':
            $convertion = "modp1024s160";
            break;
        case '23':
            $convertion = "modp2048s224";
            break;
        case '24':
            $convertion = "modp2048s256";
            break;
        case '28':
            $convertion = "ecp256bp";
            break;
        case '29':
            $convertion = "ecp384bp";
            break;
        case '30':
            $convertion = "ecp512bp";
            break;
    }

    return $convertion;
}

/**
 * load manual defined spd entries using setkey
 */
function ipsec_configure_spd()
{
    global $config;

    $spd_entries = array();

    // cleanup, collect previous manual added spd entries (stash in spd_entries) for removal.
    exec('/sbin/setkey -PD', $lines);
    $line_count = 0;
    foreach ($lines as $line) {
        if ($line[0] != "\t") {
            $tmp = explode(' ', $line);
            if (count($tmp) >= 3) {
                $src = explode('[', $tmp[0])[0];
                $dst = explode('[', $tmp[1])[0];
            }
            $line_count = 0;
        } elseif ($line_count == 1) {
            // direction
            $direction = trim(explode(' ', $line)[0]);
        } elseif (strpos($line, '/require') !== false) {
            // we'll assume that the require items in the spd are our manual items, so
            // they will be removed first
            $spd_entries[] = sprintf("spddelete -n %s %s any -P %s;", $src, $dst, $direction);
        }
        $line_count++;
    }

    // add manual added spd entries
    if (!empty($config['ipsec']['phase1']) && !empty($config['ipsec']['phase2'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            foreach ($config['ipsec']['phase2'] as $ph2ent) {
                if (!isset($ph2ent['disabled']) && $ph1ent['ikeid'] == $ph2ent['ikeid'] && !empty($ph2ent['spd'])) {
                    $myid_data = ipsec_find_id($ph1ent, "local");
                    $peerid_spec = ipsec_find_id($ph1ent, "peer");
                    foreach (explode(',', $ph2ent['spd']) as $local_net) {
                        $proto = $ph2ent['mode'] == "tunnel" ? "4" : "6";
                        $remote_net = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
                        $spd_entries[] = sprintf(
                            "spdadd -%s %s %s any -P out ipsec %s/tunnel/%s-%s/require;",
                            $proto,
                            trim($local_net),
                            $remote_net,
                            $ph2ent['protocol'],
                            $myid_data,
                            $peerid_spec
                        );
                    }
                }
            }
        }

        $tmpfname = tempnam("/tmp", "setkey");
        file_put_contents($tmpfname, implode("\n", $spd_entries) . "\n");
        mwexec("/sbin/setkey -f ". $tmpfname, true);
        unlink($tmpfname);
    }
}

function ipsec_configure_do($verbose = false, $interface = '')
{
    global $config, $p2_ealgos, $ipsec_loglevels;

    if (!empty($interface)) {
         $active = false;
        if (isset($config['ipsec']['phase1'])) {
            foreach ($config['ipsec']['phase1'] as $phase1) {
                if (!isset($phase1['disabled']) && $phase1['interface'] == $interface) {
                    $active = true;
                }
            }
        }
        if (!$active) {
            return;
        }
    }

    /* get the automatic ping_hosts.sh ready */
    @unlink('/var/db/ipsecpinghosts');
    @touch('/var/db/ipsecpinghosts');

    //  Prefer older IPsec SAs (advanced setting)
    if (isset($config['ipsec']['preferoldsa'])) {
        set_single_sysctl("net.key.preferred_oldsa", "-30");
    } else {
        set_single_sysctl("net.key.preferred_oldsa", "0");
    }

    $syscfg = $config['system'];
    $ipseccfg = $config['ipsec'];
    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : array();
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : array();
    $a_client = isset($config['ipsec']['client']) ? $config['ipsec']['client'] : array();
    $aggressive_psk = false; // if one of the phase 1 entries has aggressive/psk combination, this will be set true

    if (!isset($ipseccfg['enable'])) {
        /* try to stop charon */
        mwexec('/usr/local/sbin/ipsec stop');

        /* wait for process to die */
        sleep(2);

        /* disallow IPSEC, it is off */
        mwexec("/sbin/ifconfig enc0 down");
        set_single_sysctl("net.inet.ip.ipsec_in_use", "0");

        return 0;
    } else {
        $certpath = "/usr/local/etc/ipsec.d/certs";
        $capath = "/usr/local/etc/ipsec.d/cacerts";
        $keypath = "/usr/local/etc/ipsec.d/private";

        mwexec("/sbin/ifconfig enc0 up");
        set_single_sysctl("net.inet.ip.ipsec_in_use", "1");

        /* needed directories for config files */
        @mkdir($capath);
        @mkdir($keypath);
        @mkdir($certpath);
        @mkdir('/usr/local/etc/ipsec.d');
        @mkdir('/usr/local/etc/ipsec.d/crls');
        @mkdir('/usr/local/etc/ipsec.d/aacerts');
        @mkdir('/usr/local/etc/ipsec.d/acerts');
        @mkdir('/usr/local/etc/ipsec.d/ocspcerts');
        @mkdir('/usr/local/etc/ipsec.d/reqs');

        if ($verbose) {
            echo 'Configuring IPsec VPN...';
        }

        /* resolve all local, peer addresses and setup pings */
        $rgmap = array();
        $ipsecpinghosts = "";

        /* step through each phase1 entry */
        foreach ($a_phase1 as $ph1ent) {
            if (isset($ph1ent['disabled'])) {
                continue;
            }

            if ($ph1ent['mode'] == "aggressive" && in_array($ph1ent['authentication_method'], array("pre_shared_key", "xauth_psk_server"))) {
                $aggressive_psk = true;
            }
            $ep = ipsec_get_phase1_src($ph1ent);

            if (isset($ph1ent['mobile'])) {
                continue;
            }

            /* step through each phase2 entry */
            foreach ($a_phase2 as $ph2ent) {
                if (isset($ph2ent['disabled'])) {
                    continue;
                }

                if ($ph1ent['ikeid'] != $ph2ent['ikeid']) {
                    continue;
                }

                /* add an ipsec pinghosts entry */
                if ($ph2ent['pinghost']) {
                    if (!isset($iflist) || !is_array($iflist)) {
                        $iflist = get_configured_interface_with_descr();
                    }
                    $viplist = get_configured_vips_list();
                    $srcip = null;
                    $local_subnet = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
                    if (is_ipaddrv6($ph2ent['pinghost'])) {
                        foreach ($iflist as $ifent => $unused) {
                            $interface_ip = get_interface_ipv6($ifent);
                            if (!is_ipaddrv6($interface_ip)) {
                                continue;
                            }
                            if (ip_in_subnet($interface_ip, $local_subnet)) {
                                $srcip = $interface_ip;
                                break;
                            }
                        }
                    } else {
                        foreach ($iflist as $ifent => $unused) {
                            $interface_ip = get_interface_ip($ifent);
                            if (!is_ipaddrv4($interface_ip)) {
                                continue;
                            }
                            if ($local_subnet == "0.0.0.0/0" || ip_in_subnet($interface_ip, $local_subnet)) {
                                $srcip = $interface_ip;
                                break;
                            }
                        }
                    }
                    /* if no valid src IP was found in configured interfaces, try the vips */
                    if (is_null($srcip)) {
                        foreach ($viplist as $vip) {
                            if (ip_in_subnet($vip['ipaddr'], $local_subnet)) {
                                $srcip = $vip['ipaddr'];
                                break;
                            }
                        }
                    }
                    $dstip = $ph2ent['pinghost'];
                    if (is_ipaddrv6($dstip)) {
                        $family = "inet6";
                    } else {
                        $family = "inet";
                    }
                    if (is_ipaddr($srcip)) {
                        $ipsecpinghosts .= "{$srcip}|{$dstip}|3|||||{$family}|\n";
                    }
                }
            }
        }
        @file_put_contents('/var/db/ipsecpinghosts', $ipsecpinghosts);

        $cnf_add_to_charon_section = "";
        $cnf_add_to_charon_section .= $aggressive_psk ? "\ti_dont_care_about_security_and_use_aggressive_mode_psk=yes\n":"";
        $cnf_add_to_charon_section .= !empty($config['ipsec']['auto_routes_disable']) ? "\tinstall_routes = no\n" : "";
        if (isset($a_client['enable']) && isset($a_client['net_list'])) {
            $cnf_add_to_charon_section .= "\tcisco_unity = yes\n";
        }

        $strongswan = <<<EOD

#Automatically generated please do not modify
starter {
    load_warning = no
}

charon {
    # number of worker threads in charon
    threads = 16
    ikesa_table_size = 32
    ikesa_table_segments = 4
    init_limit_half_open = 1000
    ignore_acquire_ts = yes
    syslog {
        daemon {
            ike_name = yes
        }
    }
{$cnf_add_to_charon_section}

EOD;

        $strongswan .= "\tplugins {\n";

        if (isset($a_client['enable'])) {
            $net_list = array();
            if (isset($a_client['net_list'])) {
                foreach ($a_phase2 as $ph2ent) {
                    if (!isset($ph2ent['disabled']) && isset($ph2ent['mobile'])) {
                        $net_list[] =  ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
                    }
                }
            }

            $strongswan .= "\t\tattr {\n";
            if (!empty($net_list)) {
                $net_list_str = implode(",", $net_list);
                $strongswan .= "\t\t\tsubnet = {$net_list_str}\n";
                $strongswan .= "\t\t\tsplit-include = {$net_list_str}\n";
            }
            $cfgservers = array();
            foreach (array('dns_server1', 'dns_server2', 'dns_server3', 'dns_server4') as $dns_server) {
                if (!empty($a_client[$dns_server])) {
                    $cfgservers[] = $a_client[$dns_server];
                }
            }
            if (!empty($cfgservers)) {
                $strongswan .= "\t\t\tdns = " . implode(",", $cfgservers) . "\n";
            }
            unset($cfgservers);
            $cfgservers = array();
            if (!empty($a_client['wins_server1'])) {
                $cfgservers[] = $a_client['wins_server1'];
            }
            if (!empty($a_client['wins_server2'])) {
                $cfgservers[] = $a_client['wins_server2'];
            }
            if (!empty($cfgservers)) {
                $strongswan .= "\t\t\tnbns = " . implode(",", $cfgservers) . "\n";
            }
            unset($cfgservers);

            if (!empty($a_client['dns_domain'])) {
                $strongswan .= "\t\t\t# Search domain and default domain\n";
                $strongswan .= "\t\t\t28674 = {$a_client['dns_domain']}\n";
                if (empty($a_client['dns_split'])) {
                    $strongswan .= "\t\t\t28675 = {$a_client['dns_domain']}";
                }
                $strongswan .= "\n";
            }

            if (!empty($a_client['dns_split'])) {
                $strongswan .= "\t\t\t28675 = {$a_client['dns_split']}\n";
            }

            if (!empty($a_client['login_banner'])) {
                $strongswan .= "\t\t\t28672 = {$a_client['login_banner']}\n";
            }

            if (isset($a_client['save_passwd'])) {
                $strongswan .= "\t\t\t28673 = 1\n";
            }

            if (!empty($a_client['pfs_group'])) {
                $strongswan .= "\t\t\t28679 = {$a_client['pfs_group']}\n";
            }
            $strongswan .= "\t\t}\n";

            $disable_xauth = false;
            foreach ($a_phase1 as $ph1ent) {
                if (!isset($ph1ent['disabled']) && isset($ph1ent['mobile'])) {
                    if ($ph1ent['authentication_method'] == "eap-radius") {
                        $disable_xauth = true; // disable Xauth when radius is used.
                        $strongswan .= "\t\teap-radius {\n";
                        $radius_servers = "";
                        $radius_server_num = 1;
                        $radius_accounting_enabled = false;
                        foreach (auth_get_authserver_list() as $auth_server) {
                            if (in_array($auth_server['name'], explode(',', $ph1ent['authservers']))) {
                                $radius_servers .= "\t\t\t\tserver" . $radius_server_num . " {\n";
                                $radius_servers .= "\t\t\t\t\taddress = " . $auth_server['host'] . "\n";
                                $radius_servers .= "\t\t\t\t\tsecret = \"" . $auth_server['radius_secret'] . "\"\n";
                                $radius_servers .= "\t\t\t\t\tauth_port = " . $auth_server['radius_auth_port'] . "\n";

                                if (!empty($auth_server['radius_acct_port'])) {
                                    $radius_servers .= "\t\t\t\t\tacct_port = " . $auth_server['radius_acct_port'] . "\n";
                                }
                                $radius_servers .= "\t\t\t\t}\n";
                                if (!empty($auth_server['radius_acct_port'])) {
                                    $radius_accounting_enabled = true;
                                }
                                $radius_server_num += 1;
                            }
                        }
                        if ($radius_accounting_enabled) {
                            $strongswan .= "\t\t\taccounting = yes\n";
                        }
                        $strongswan .= "\t\t\tservers {\n";
                        $strongswan .= $radius_servers;
                        $strongswan .= "\t\t\t}\n";
                        $strongswan .= "\t\t}\n";
                        break; // there can only be one mobile phase1, exit loop
                    }
                }
            }
            if (isset($a_client['enable']) && !$disable_xauth) {
                $strongswan .= "\t\txauth-generic {\n";
                $strongswan .= "\t\t\tscript = /usr/local/etc/inc/plugins.inc.d/ipsec/auth-user.php\n";
                $strongswan .= "\t\t\tauthcfg = unused\n";
                $strongswan .= "\t\t}\n";
            }
        }

        $strongswan .= "\t}\n}\n";
        @file_put_contents("/usr/local/etc/strongswan.conf", $strongswan);
        unset($strongswan);

        /* generate CA certificates files */
        if (isset($config['ca'])) {
            foreach ($config['ca'] as $ca) {
                if (!isset($ca['crt'])) {
                    log_error(sprintf('Error: Invalid certificate info for %s', $ca['descr']));
                    continue;
                }
                $cert = base64_decode($ca['crt']);
                $x509cert = openssl_x509_parse(openssl_x509_read($cert));
                if (!is_array($x509cert) || !isset($x509cert['hash'])) {
                    log_error(sprintf('Error: Invalid certificate hash info for %s', $ca['descr']));
                    continue;
                }
                $fname = "{$capath}/{$x509cert['hash']}.0.crt";
                if (!@file_put_contents($fname, $cert)) {
                    log_error(sprintf('Error: Cannot write IPsec CA file for %s', $ca['descr']));
                    continue;
                }
                unset($cert);
            }
        }

        $pskconf = "";

        foreach ($a_phase1 as $ph1ent) {
            if (isset($ph1ent['disabled'])) {
                continue;
            }

            if (!empty($ph1ent['certref'])) {
                $cert = lookup_cert($ph1ent['certref']);

                if (empty($cert)) {
                    log_error(sprintf('Error: Invalid phase1 certificate reference for %s', $ph1ent['name']));
                    continue;
                }

                @chmod($certpath, 0600);

                $ph1keyfile = "{$keypath}/cert-{$ph1ent['ikeid']}.key";
                if (!file_put_contents($ph1keyfile, base64_decode($cert['prv']))) {
                    log_error(sprintf('Error: Cannot write phase1 key file for %s', $ph1ent['name']));
                    continue;
                }
                @chmod($ph1keyfile, 0600);

                $ph1certfile = "{$certpath}/cert-{$ph1ent['ikeid']}.crt";
                if (!file_put_contents($ph1certfile, base64_decode($cert['crt']))) {
                    log_error(sprintf('Error: Cannot write phase1 certificate file for %s', $ph1ent['name']));
                    @unlink($ph1keyfile);
                    continue;
                }
                @chmod($ph1certfile, 0600);

                /* XXX" Traffic selectors? */
                $pskconf .= " : RSA {$ph1keyfile}\n";
            } elseif (!empty($ph1ent['pre-shared-key'])) {
                $myid = isset($ph1ent['mobile']) ? trim(ipsec_find_id($ph1ent, "local")) : "";
                $peerid_data = isset($ph1ent['mobile']) ? "%any" : ipsec_find_id($ph1ent, "peer");

                if (!empty($peerid_data)) {
                    $pskconf .= $myid . " " . trim($peerid_data) . " : PSK 0s" . base64_encode(trim($ph1ent['pre-shared-key'])) . "\n";
                }
            }
        }

        /* Add user PSKs */
        if (isset($config['system']['user']) && is_array($config['system']['user'])) {
            foreach ($config['system']['user'] as $user) {
                if (!empty($user['ipsecpsk'])) {
                    $pskconf .= "{$user['name']} : PSK 0s".base64_encode($user['ipsecpsk'])."\n";
                }
            }
            unset($user);
        }

        /* add PSKs for mobile clients */
        if (isset($ipseccfg['mobilekey'])) {
            foreach ($ipseccfg['mobilekey'] as $key) {
                if (trim(strtolower($key['ident'])) == 'any') {
                    $ident = '%any';
                } else {
                    $ident = $key['ident'];
                }
                $identType = !empty($key['type']) ? $key['type'] : "PSK";
                $pskconf .= "{$ident} : {$identType} 0s".base64_encode($key['pre-shared-key'])."\n";
            }
            unset($key);
        }

        @file_put_contents("/usr/local/etc/ipsec.secrets", $pskconf);
        chmod("/usr/local/etc/ipsec.secrets", 0600);
        unset($pskconf);

        /* begin ipsec.conf */
        $ipsecconf = "";
        if (count($a_phase1)) {
            $ipsecconf .= "# This file is automatically generated. Do not edit\n";
            $ipsecconf .= "config setup\n\tuniqueids = yes\n";
            // parse debug tags
            $cfg_loglevels = array();
            if (isset($ipsec_loglevels)) {
                foreach ($ipsec_loglevels as $lkey => $ldescr) {
                    if (isset($config['ipsec']["ipsec_{$lkey}"]) && is_numeric($config['ipsec']["ipsec_{$lkey}"]) &&
                      intval($config['ipsec']["ipsec_{$lkey}"]) >= -1 && intval($config['ipsec']["ipsec_{$lkey}"]) <= 4) {
                          $cfg_loglevels[] = $lkey . " " . $config['ipsec']["ipsec_{$lkey}"];
                    }
                }
            }
            $ipsecconf .= "\tcharondebug=\"" .implode(',', $cfg_loglevels) . "\"\n";

            if (!empty($config['ipsec']['passthrough_networks'])) {
                $ipsecconf .= "\nconn pass\n";
                $ipsecconf .= "\tright=127.0.0.1 # so this connection does not get used for other purposes\n";
                $ipsecconf .= "\tleftsubnet={$config['ipsec']['passthrough_networks']}\n";
                $ipsecconf .= "\trightsubnet={$config['ipsec']['passthrough_networks']}\n";
                $ipsecconf .= "\ttype=passthrough\n";
                $ipsecconf .= "\tauto=route\n";
            }

            foreach ($a_phase1 as $ph1ent) {
                if (isset($ph1ent['disabled'])) {
                    continue;
                }
                $conn_params = "";
                $aggressive = $ph1ent['mode'] == "aggressive" ? "yes" : "no";

                $ep = ipsec_get_phase1_src($ph1ent);
                if (empty($ep)) {
                    continue;
                }

                $keyexchange = "ikev1";
                if (!empty($ph1ent['iketype'])) {
                    $keyexchange = $ph1ent['iketype'];
                    $mobike = !empty($ph1ent['mobike']) ? "mobike = no" : "mobike = yes";
                }

                if (isset($ph1ent['mobile'])) {
                    $right_spec = "%any";
                } else {
                    $right_spec = $ph1ent['remote-gateway'];
                }

                if (!empty($ph1ent['auto'])) {
                    $conn_auto = $ph1ent['auto'];
                } elseif (isset($ph1ent['mobile'])) {
                    $conn_auto = 'add';
                } else {
                    $conn_auto = 'route';
                }

                $myid_data = ipsec_find_id($ph1ent, "local");
                $peerid_spec = ipsec_find_id($ph1ent, "peer");

                if (!empty($ph1ent['encryption-algorithm']['name']) && !empty($ph1ent['hash-algorithm'])) {
                    $list = array();
                    foreach (explode(',', $ph1ent['hash-algorithm']) as $halgo) {
                        $entry = "{$ph1ent['encryption-algorithm']['name']}";
                        if (isset($ph1ent['encryption-algorithm']['keylen'])) {
                             $entry .= "{$ph1ent['encryption-algorithm']['keylen']}";
                        }
                        $entry .= "-{$halgo}";
                        $modp = ipsec_convert_to_modp($ph1ent['dhgroup']);
                        if (!empty($modp)) {
                            $entry .= "-{$modp}";
                        }
                        $list[] = $entry;
                    }
                    $ealgosp1 = 'ike = ' . implode(',', $list) . '!';
                }

                if (!empty($ph1ent['dpd_delay']) && !empty($ph1ent['dpd_maxfail'])) {
                    if ($conn_auto == "route") {
                        $dpdline = "dpdaction = restart";
                    } else {
                        $dpdline = "dpdaction = clear";
                    }
                    $dpdline .= "\n\tdpddelay = {$ph1ent['dpd_delay']}s";
                    $dpdtimeout = $ph1ent['dpd_delay'] * ($ph1ent['dpd_maxfail'] + 1);
                    $dpdline .= "\n\tdpdtimeout = {$dpdtimeout}s";
                } else {
                    $dpdline = "dpdaction = none";
                }

                if (!empty($ph1ent['lifetime'])) {
                    $ikelifeline = "ikelifetime = {$ph1ent['lifetime']}s";
                } else {
                    $ikelifeline = '';
                }

                $rightsourceip = null;
                if (!empty($a_client['pool_address']) && isset($ph1ent['mobile'])) {
                    $rightsourceip = "\trightsourceip = {$a_client['pool_address']}/{$a_client['pool_netbits']}\n";
                }

                $authentication = "";
                switch ($ph1ent['authentication_method']) {
                    case 'eap-tls':
                        $authentication = "leftauth=eap-tls\n\trightauth=eap-tls";
                        break;
                    case 'psk_eap-tls':
                        $authentication = "leftauth=pubkey\n\trightauth=eap-tls";
                        $authentication .= "\n\teap_identity=%identity";
                        break;
                    case 'eap-mschapv2':
                        $authentication = "leftauth = pubkey\n\trightauth = eap-mschapv2";
                        $authentication .= "\n\teap_identity = %any";
                        break;
                    case 'rsa_eap-mschapv2':
                        $authentication = "leftauth = pubkey\n\trightauth = pubkey\n\trightauth2 = eap-mschapv2";
                        $authentication .= "\n\teap_identity = %any";
                        break;
                    case 'eap-radius':
                        $authentication = "leftauth = pubkey\n\trightauth = eap-radius";
                        $authentication .= "\n\trightsendcert = never";
                        $authentication .= "\n\teap_identity = %any";
                        if (empty($rightsourceip)) {
                            $rightsourceip = "\trightsourceip = %radius\n";
                        }
                        break;
                    case 'xauth_rsa_server':
                        $authentication = "leftauth = pubkey\n\trightauth = pubkey";
                        $authentication .= "\n\trightauth2 = xauth-generic";
                        break;
                    case 'xauth_psk_server':
                        $authentication = "leftauth = psk\n\trightauth = psk";
                        $authentication .= "\n\trightauth2 = xauth-generic";
                        break;
                    case 'pre_shared_key':
                        $authentication = "leftauth = psk\n\trightauth = psk";
                        break;
                    case 'rsasig':
                        $authentication = "leftauth = pubkey\n\trightauth = pubkey";
                        break;
                    case 'hybrid_rsa_server':
                        $authentication = "leftauth = xauth-generic\n\trightauth = pubkey";
                        $authentication .= "\n\trightauth2 = xauth";
                        break;
                }
                if (!empty($ph1ent['certref'])) {
                    $authentication .= "\n\tleftcert = {$certpath}/cert-{$ph1ent['ikeid']}.crt";
                    $authentication .= "\n\tleftsendcert = always";
                }
                if (!empty($ph1ent['caref'])) {
                    $ca = lookup_ca($ph1ent['caref']);
                    if (!empty($ca)) {
                        $rightca = "";
                        foreach (cert_get_subject_array($ca['crt']) as $ca_field) {
                            $rightca .= "{$ca_field['a']}={$ca_field['v']}/";
                        }
                        $authentication .= "\n\trightca = \"/$rightca\"";
                    }
                }
                $left_spec = $ep;

                if (isset($ph1ent['reauth_enable'])) {
                    $reauth = "reauth = no";
                } else {
                    $reauth = "reauth = yes";
                }

                if (isset($ph1ent['rekey_enable'])) {
                    $rekey = "rekey = no";
                } else {
                    $rekey = "rekey = yes";
                }

                $forceencaps = 'forceencaps = no';
                if (!empty($ph1ent['nat_traversal']) && $ph1ent['nat_traversal'] == 'force') {
                    $forceencaps = 'forceencaps = yes';
                }

                $ipseclifetime = 0;
                $rightsubnet_spec = array();
                $leftsubnet_spec = array();
                $ealgoAHsp2arr = array();
                $ealgoESPsp2arr = array();

                if (count($a_phase2)) {
                    foreach ($a_phase2 as $ph2ent) {
                        if ($ph1ent['ikeid'] != $ph2ent['ikeid'] || isset($ph2ent['disabled'])) {
                            continue;
                        }
                        if (isset($ph2ent['mobile']) && !isset($a_client['enable'])) {
                            continue;
                        }

                        if (($ph2ent['mode'] == 'tunnel') || ($ph2ent['mode'] == 'tunnel6')) {
                            $tunneltype = "type = tunnel";
                            $localid_type = $ph2ent['localid']['type'];
                            $leftsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
                            /* Do not print localid in some cases, such as a pure-psk or psk/xauth single phase2 mobile tunnel */
                            if (($localid_type == "none" || $localid_type == "mobile")
                                && isset($ph1ent['mobile']) && (ipsec_get_number_of_phase2($ph1ent['ikeid'])==1)) {
                                $left_spec = '%any';
                            } else {
                                if ($localid_type != "address") {
                                    $localid_type = "subnet";
                                }
                                // Don't let an empty subnet into config, it can cause parse errors. Ticket #2201.
                                if (!is_ipaddr($leftsubnet_data) && !is_subnet($leftsubnet_data) && ($leftsubnet_data != "0.0.0.0/0")) {
                                    log_error("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.");
                                    continue;
                                }
                            }

                            $leftsubnet_spec[] = $leftsubnet_data;

                            if (!isset($ph2ent['mobile'])) {
                                $tmpsubnet = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
                                $rightsubnet_spec[] = $tmpsubnet;
                            }
                        } else {
                            $tunneltype = "type = transport";
                            if ((($ph1ent['authentication_method'] == "xauth_psk_server") ||
                              ($ph1ent['authentication_method'] == "pre_shared_key")) && isset($ph1ent['mobile'])) {
                                $left_spec = "%any";
                            } else {
                                $tmpsubnet = ipsec_get_phase1_src($ph1ent);
                                $leftsubnet_spec[] = $tmpsubnet;
                            }
                            if (!isset($ph2ent['mobile'])) {
                                $rightsubnet_spec[] = $right_spec;
                            }
                        }
                        if (isset($a_client['pfs_group'])) {
                            $ph2ent['pfsgroup'] = $a_client['pfs_group'];
                        }
                        if (isset($ph2ent['protocol']) && $ph2ent['protocol'] == 'esp') {
                            $ealgoESPsp2arr_details = array();
                            if (is_array($ph2ent['encryption-algorithm-option'])) {
                                foreach ($ph2ent['encryption-algorithm-option'] as $ealg) {
                                    $ealg_id = $ealg['name'];
                                    if (isset($ealg['keylen'])) {
                                        $ealg_kl = $ealg['keylen'];
                                    } else {
                                        $ealg_kl = null;
                                    }

                                    if ($ealg_kl == "auto") {
                                        $key_hi = $p2_ealgos[$ealg_id]['keysel']['hi'];
                                        $key_lo = $p2_ealgos[$ealg_id]['keysel']['lo'];
                                        $key_step = $p2_ealgos[$ealg_id]['keysel']['step'];
                                        /* XXX: in some cases where include ordering is suspect these variables
                                        * are somehow 0 and we enter this loop forever and timeout after 900
                                        * seconds wrecking bootup */
                                        if ($key_hi != 0 and $key_lo !=0 and $key_step !=0) {
                                            for ($keylen = $key_hi; $keylen >= $key_lo; $keylen -= $key_step) {
                                                if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
                                                    foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
                                                        $halgo = str_replace('hmac_', '', $halgo);
                                                        $tmpealgo = "{$ealg_id}{$keylen}-{$halgo}";
                                                        $modp = ipsec_convert_to_modp($ph2ent['pfsgroup']);
                                                        if (!empty($modp)) {
                                                            $tmpealgo .= "-{$modp}";
                                                        }
                                                        $ealgoESPsp2arr_details[] = $tmpealgo;
                                                    }
                                                } else {
                                                    $tmpealgo = "{$ealg_id}{$keylen}";
                                                    $modp = ipsec_convert_to_modp($ph2ent['pfsgroup']);
                                                    if (!empty($modp)) {
                                                        $tmpealgo .= "-{$modp}";
                                                    }
                                                    $ealgoESPsp2arr_details[] = $tmpealgo;
                                                }
                                            }
                                        }
                                    } else {
                                        if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
                                            foreach ($ph2ent['hash-algorithm-option'] as $halgo) {
                                                $halgo = str_replace('hmac_', '', $halgo);
                                                $tmpealgo = "{$ealg_id}{$ealg_kl}-{$halgo}";
                                                $modp = ipsec_convert_to_modp($ph2ent['pfsgroup']);
                                                if (!empty($modp)) {
                                                    $tmpealgo .= "-{$modp}";
                                                }
                                                $ealgoESPsp2arr_details[] = $tmpealgo;
                                            }
                                        } else {
                                            $tmpealgo = "{$ealg_id}{$ealg_kl}";
                                            $modp = ipsec_convert_to_modp($ph2ent['pfsgroup']);
                                            if (!empty($modp)) {
                                                $tmpealgo .= "-{$modp}";
                                            }
                                            $ealgoESPsp2arr_details[] = $tmpealgo;
                                        }
                                    }
                                }
                            }
                            $ealgoESPsp2arr[] = $ealgoESPsp2arr_details;
                        } elseif (isset($ph2ent['protocol']) && $ph2ent['protocol'] == 'ah') {
                            $ealgoAHsp2arr_details = array();
                            if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
                                $modp = ipsec_convert_to_modp($ph2ent['pfsgroup']);
                                foreach ($ph2ent['hash-algorithm-option'] as $tmpAHalgo) {
                                    $tmpAHalgo = str_replace('hmac_', '', $tmpAHalgo);
                                    if (!empty($modp)) {
                                        $tmpAHalgo = "-{$modp}";
                                    }
                                    $ealgoAHsp2arr_details[] = $tmpAHalgo;
                                }
                            }
                            $ealgoAHsp2arr[] = $ealgoAHsp2arr_details;
                        }

                        if (!empty($ph2ent['lifetime'])) {
                            if ($ipseclifetime == 0 || intval($ipseclifetime) > intval($ph2ent['lifetime'])) {
                                $ipseclifetime = intval($ph2ent['lifetime']);
                            }
                        }
                    }
                }

                $connEntry =<<<EOD

conn con<<connectionId>>
  aggressive = {$aggressive}
  fragmentation = yes
  keyexchange = {$keyexchange}
  {$mobike}
  {$reauth}
  {$rekey}
  {$forceencaps}
  installpolicy = yes
  {$tunneltype}
  {$dpdline}
  left = {$left_spec}
  right = {$right_spec}
  leftid = {$myid_data}
  {$ikelifeline}

EOD;

                if ($ipseclifetime > 0) {
                    $connEntry .= "\tlifetime = {$ipseclifetime}s\n";
                }
                if (!empty($rightsourceip)) {
                    $connEntry .= "{$rightsourceip}";
                }
                if (!empty($ealgosp1)) {
                    $connEntry .= "\t{$ealgosp1}\n";
                }
                if (!empty($authentication)) {
                    $connEntry .= "\t{$authentication}\n";
                }
                if (!empty($peerid_spec)) {
                    $connEntry .= "\trightid = {$peerid_spec}\n";
                }

                // append ipsec connections
                if (!isset($ph1ent['mobile']) && $keyexchange == 'ikev1') {
                    // ikev1 not mobile
                    for ($idx = 0; $idx < count($leftsubnet_spec); ++$idx) {
                        if (count($leftsubnet_spec) == 1) {
                            $tmpconf = str_replace('<<connectionId>>', "{$ph1ent['ikeid']}", $connEntry);
                        } else {
                            // suffix connection with sequence number
                            $tmpconf = str_replace('<<connectionId>>', sprintf('%s-%03d', $ph1ent['ikeid'], $idx), $connEntry);
                        }
                        $tmpconf .= "\trightsubnet = " . $rightsubnet_spec[$idx]. "\n";
                        $tmpconf .= "\tleftsubnet = " . $leftsubnet_spec[$idx] . "\n";
                        if (!empty($ealgoESPsp2arr[$idx])) {
                            $tmpconf .= "\tesp = " . join(',', $ealgoESPsp2arr[$idx]) . "!\n";
                        }
                        if (!empty($ealgoAHsp2arr[$idx])) {
                            $tmpconf .= "\tah = " . join(',', $ealgoAHsp2arr[$idx]) . "!\n";
                        }
                        $tmpconf .= "\tauto = {$conn_auto}\n";
                        $ipsecconf .= $tmpconf;
                    }
                } else {
                    // mobile and ikev2
                    if (isset($ph1ent['tunnel_isolation'])) {
                        $ipsecconf .= str_replace('<<connectionId>>', "{$ph1ent['ikeid']}-000", $connEntry);
                        for ($idx = 0; $idx < count($leftsubnet_spec); ++$idx) {
                            // requires leading empty line:
                            $tmpconf = array('');
                            // fix for strongSwan to pick up the correct connection
                            // name from the first configured tunnel ($idx == 0):
                            $conn_suffix = $idx ? sprintf('-%03d', $idx) : '';
                            $tmpconf[] = "conn con{$ph1ent['ikeid']}{$conn_suffix}";
                            if (!empty($rightsubnet_spec[$idx])) {
                                $tmpconf[] = "\trightsubnet = {$rightsubnet_spec[$idx]}";
                            }
                            $tmpconf[] = "\tleftsubnet = {$leftsubnet_spec[$idx]}";
                            if (!empty($ealgoESPsp2arr[$idx])) {
                                $tmpconf[] = "\tesp = " . join(',', $ealgoESPsp2arr[$idx]) . '!';
                            }
                            if (!empty($ealgoAHsp2arr[$idx])) {
                                $tmpconf[] = "\tah = " . join(',', $ealgoAHsp2arr[$idx]) . '!';
                            }
                            $tmpconf[] = "\talso = con{$ph1ent['ikeid']}-000";
                            $tmpconf[] = "\tauto = {$conn_auto}";
                            // requires trailing empty line:
                            $tmpconf[] = '';
                            $ipsecconf .= join("\n", $tmpconf);
                        }
                    } else {
                        $tmpconf = str_replace('<<connectionId>>', "{$ph1ent['ikeid']}", $connEntry);
                        if (!empty($rightsubnet_spec)) {
                            $tmpconf .= "\trightsubnet = " . join(',', array_unique($rightsubnet_spec)) . "\n";
                        }
                        if (!empty($leftsubnet_spec)) {
                            $tmpconf .= "\tleftsubnet = " . join(',', array_unique($leftsubnet_spec)) . "\n";
                        }
                        // merge esp phase 2 arrays.
                        $esp_content = array();
                        foreach ($ealgoESPsp2arr as $ealgoESPsp2arr_details) {
                            foreach ($ealgoESPsp2arr_details as $esp_item) {
                                if (!in_array($esp_item, $esp_content)) {
                                    $esp_content[] = $esp_item;
                                }
                            }
                        }
                        // merge ah phase 2 arrays.
                        $ah_content = array();
                        foreach ($ealgoAHsp2arr as $ealgoAHsp2arr_details) {
                            foreach ($ealgoAHsp2arr_details as $ah_item) {
                                if (!in_array($ah_item, $ah_content)) {
                                    $ah_content[] = $ah_item;
                                }
                            }
                        }
                        if (!empty($esp_content)) {
                            $tmpconf .= "\tesp = " . join(',', $esp_content) . "!\n";
                        }
                        if (!empty($ah_content)) {
                            $tmpconf .= "\tah = " . join(',', $ah_content) . "!\n";
                        }
                        $tmpconf .= "\tauto = {$conn_auto}\n";
                        $ipsecconf .= $tmpconf;
                    }
                }
            }
        }
    }
    // dump file, replace tabs for 2 spaces
    @file_put_contents("/usr/local/etc/ipsec.conf", str_replace("\t", '  ', $ipsecconf));
    unset($ipsecconf);
    /* end ipsec.conf */

    /* mange process */
    if (isvalidpid('/var/run/charon.pid')) {
        /* Read secrets */
        mwexec('/usr/local/sbin/ipsec rereadall', false);
        /* Update configuration changes */
        mwexec('/usr/local/sbin/ipsec reload', false);
    } else {
        mwexec("/usr/local/sbin/ipsec start", false);
    }

    /* load manually defined SPD entries */
    ipsec_configure_spd();

    if ($verbose) {
        echo "done.\n";
    }
}
