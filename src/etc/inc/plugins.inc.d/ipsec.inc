<?php

/*
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
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

const IPSEC_LOG_SUBSYSTEMS = [
    'asn' => 'Low-level encoding/decoding (ASN.1, X.509 etc.)',
    'cfg' => 'Configuration management and plugins',
    'chd' => 'CHILD_SA/IPsec SA',
    'dmn' => 'Main daemon setup/cleanup/signal handling',
    'enc' => 'Packet encoding/decoding encryption/decryption operations',
    'esp' => 'libipsec library messages',
    'ike' => 'IKE_SA/ISAKMP SA',
    'imc' => 'Integrity Measurement Collector',
    'imv' => 'Integrity Measurement Verifier',
    'job' => 'Jobs queuing/processing and thread pool management',
    'knl' => 'IPsec/Networking kernel interface',
    'lib' => 'libstrongwan library messages',
    'mgr' => 'IKE_SA manager, handling synchronization for IKE_SA access',
    'net' => 'IKE network communication',
    'pts' => 'Platform Trust Service',
    'tls' => 'libtls library messages',
    'tnc' => 'Trusted Network Connect',
];

const IPSEC_LOG_LEVELS = [
    -1 => 'Silent',
    0 => 'Basic',
    1 => 'Audit',
    2 => 'Control',
    3 => 'Raw',
    4 => 'Highest',
];

function ipsec_get_key_type($f)
{
    $keytype = 'RSA';

    if ($k = openssl_pkey_get_private('file://' . $f)) {
        if ($d = openssl_pkey_get_details($k)) {
            switch ($d['type']) {
                case OPENSSL_KEYTYPE_RSA:
                    $keytype = 'RSA';
                    break;
                case OPENSSL_KEYTYPE_EC:
                    $keytype = 'ECDSA';
                    break;
            }
        }
    }

    return $keytype;
}

function ipsec_p1_ealgos()
{
    return array(
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
}

function ipsec_p1_authentication_methods()
{
    return array(
        'hybrid_rsa_server' => array( 'name' => 'Hybrid RSA + Xauth', 'mobile' => true ),
        'xauth_rsa_server' => array( 'name' => 'Mutual RSA + Xauth', 'mobile' => true ),
        'xauth_psk_server' => array( 'name' => 'Mutual PSK + Xauth', 'mobile' => true ),
        'eap-tls' => array( 'name' => 'EAP-TLS', 'mobile' => true),
        'psk_eap-tls' => array( 'name' => 'RSA (local) + EAP-TLS (remote)', 'mobile' => true),
        'eap-mschapv2' => array( 'name' => 'EAP-MSCHAPV2', 'mobile' => true),
        'rsa_eap-mschapv2' => array( 'name' => 'Mutual RSA + EAP-MSCHAPV2', 'mobile' => true),
        'eap-radius' => array( 'name' => 'EAP-RADIUS', 'mobile' => true),
        'rsasig' => array( 'name' => 'Mutual RSA', 'mobile' => false ),
        'pubkey' => array( 'name' => 'Mutual Public Key', 'mobile' => false ),
        'pre_shared_key' => array( 'name' => 'Mutual PSK', 'mobile' => false ),
    );
}

function ipsec_p2_ealgos()
{
    return array(
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
}

function ipsec_p2_halgos()
{
    return array(
        'hmac_md5' => 'MD5',
        'hmac_sha1' => 'SHA1',
        'hmac_sha256' => 'SHA256',
        'hmac_sha384' => 'SHA384',
        'hmac_sha512' => 'SHA512',
        'aesxcbc' => 'AES-XCBC'
    );
}

function ipsec_configure()
{
    return array(
        'ipsec' => array('ipsec_configure_do:2'),
        'ipsec_prepare' => array('ipsec_configure_vti'),
        'vpn' => array('ipsec_configure_do:2'),
    );
}

function ipsec_syslog()
{
    $logfacilities = array();

    $logfacilities['ipsec'] = array(
        'facility' => array('charon'),
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

    if (isset($config['ipsec']['phase1'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (empty($ph1ent['disabled'])) {
                $oic = array('enable' => true);
                $oic['if'] = 'enc0';
                $oic['descr'] = 'IPsec';
                $oic['type'] = 'none';
                $oic['virtual'] = true;
                $interfaces['enc0'] = $oic;
                break;
            }
        }
        // automatically register VTI's in the interfaces list
        foreach (ipsec_get_configured_vtis() as $intf => $details) {
            $interfaces[$intf] = [
                'enable' => true,
                'descr' => preg_replace('/[^a-z_0-9]/i', '', $details['descr']),
                'if' => $intf,
                'type' => 'none',
            ];
        }
    }

    return $interfaces;
}

function ipsec_firewall(\OPNsense\Firewall\Plugin $fw)
{
    global $config;

    if (
        !isset($config['system']['disablevpnrules']) &&
            isset($config['ipsec']['enable']) && isset($config['ipsec']['phase1'])
    ) {
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
                                      "#ref" => "vpn_ipsec_settings.php#disablevpnrules",
                                      "descr" => "IPsec: " . (!empty($ph1ent['descr']) ? $ph1ent['descr'] : $rgip)
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
        'services' => ["strongswan"],
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
            $if = $ph1ent['interface'];
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

/**
 * Unravel the logic in phase 2 parsing, tunnels can either be merged or isolated depending on the type.
 * This function parses the phase2 entries with (all of) it's weirdness so we can safely use this to construct
 * connection entries.
 *
 * Without breaking existing setups, we can't easily push the choices back to the user, because most of the
 * weirdness is a result of improper input handling.
 * (should ease future migration if needed as well.)
 */
function ipsec_parse_phase2($ikeid)
{
    global $config;
    $result = array(
        "type" => "tunnel",  // strongswan's default when type is not specified
        "left_override" => null, // "left=" overrides for some corner cases, should have been better as input validation
        "leftsubnets" => [],
        "rightsubnets" => [],
        "ealgoAHsp2" => [],
        "ealgoESPsp2" => [],
        "ipseclifetime" => 0,
        "reqids" => [],
        "uniqid_reqid" => []
    );

    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : array();
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : array();
    $a_client = isset($config['ipsec']['client']) ? $config['ipsec']['client'] : array();
    $p2_ealgos = ipsec_p2_ealgos();
    $ph1ent = current(array_filter($a_phase1, function ($e) use ($ikeid) {
        return $e['ikeid'] == $ikeid;
    }));
    if ($ph1ent) {
        $uniqids = [];
        $keyexchange = !empty($ph1ent['iketype']) ? $ph1ent['iketype'] : "ikev1";
        $idx = 0;
        foreach ($a_phase2 as $ph2ent) {
            if ($ph1ent['ikeid'] != $ph2ent['ikeid'] || isset($ph2ent['disabled'])) {
                continue;
            } elseif (isset($ph2ent['mobile']) && !isset($a_client['enable'])) {
                continue;
            } elseif (in_array($ph2ent['mode'], ['tunnel', 'tunnel6'])) {
                $leftsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
                // Don't let an empty subnet into config, it can cause parse errors. Ticket #2201.
                if (!is_ipaddr($leftsubnet_data) && !is_subnet($leftsubnet_data) && ($leftsubnet_data != "0.0.0.0/0")) {
                    log_error("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.");
                    continue;
                }
                $result['leftsubnets'][] = $leftsubnet_data;
                if (!isset($ph2ent['mobile'])) {
                    $result['rightsubnets'][] = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
                }
            } elseif ($ph2ent['mode'] == 'route-based') {
                if (is_ipaddrv6($ph2ent['tunnel_local'])) {
                    $result['leftsubnets'][] = '::/0';
                    $result['rightsubnets'][] = '::/0';
                } else {
                    $result['leftsubnets'][] = '0.0.0.0/0';
                    $result['rightsubnets'][] = '0.0.0.0/0';
                }
            } else {
                $result['type'] = 'transport';
                if (
                    (($ph1ent['authentication_method'] == "xauth_psk_server") ||
                    ($ph1ent['authentication_method'] == "pre_shared_key")) && isset($ph1ent['mobile'])
                ) {
                    $result['left_override'] = "%any";
                } else {
                    $tmpsubnet = ipsec_get_phase1_src($ph1ent);
                    $result['leftsubnets'][] = $tmpsubnet;
                }
                if (!isset($ph2ent['mobile'])) {
                    $result['rightsubnets'][] = $right_spec;
                }
            }
            $uniqids[] = $ph2ent['uniqid'];

            if (isset($ph2ent['mobile']) && isset($a_client['pfs_group'])) {
                $ph2ent['pfsgroup'] = $a_client['pfs_group'];
            }
            if (isset($ph2ent['protocol']) && $ph2ent['protocol'] == 'esp') {
                $ealgoESPsp2arr_details = array();
                if (is_array($ph2ent['encryption-algorithm-option'])) {
                    foreach ($ph2ent['encryption-algorithm-option'] as $ealg) {
                        $ealg_id = $ealg['name'];
                        $ealg_kl = isset($ealg['keylen']) ? $ealg['keylen'] : null;

                        if ($ealg_kl == "auto") {
                            $key_hi = $p2_ealgos[$ealg_id]['keysel']['hi'];
                            $key_lo = $p2_ealgos[$ealg_id]['keysel']['lo'];
                            $key_step = $p2_ealgos[$ealg_id]['keysel']['step'];
                            /* XXX: in some cases where include ordering is suspect these variables
                            * are somehow 0 and we enter this loop forever and timeout after 900
                            * seconds wrecking bootup */
                            if ($key_hi != 0 and $key_lo != 0 and $key_step != 0) {
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
                $result['ealgoESPsp2'][] = $ealgoESPsp2arr_details;
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
                $result['ealgoAHsp2'][] = $ealgoAHsp2arr_details;
            }

            if (!empty($ph2ent['lifetime'])) {
                if ($result['ipseclifetime'] == 0 || intval($result['ipseclifetime']) > intval($ph2ent['lifetime'])) {
                    $result['ipseclifetime'] = intval($ph2ent['lifetime']);
                }
            }
            $result['reqids'][] = !empty($ph2ent['reqid']) ? $ph2ent['reqid'] : null;
            $idx++;
        }
        if ((!isset($ph1ent['mobile']) && $keyexchange == 'ikev1') || isset($ph1ent['tunnel_isolation'])) {
            // isolated tunnels
            for ($idx = 0; $idx < count($result['leftsubnets']); ++$idx) {
                $result['uniqid_reqid'][$uniqids[$idx]] = $result['reqids'][$idx];
            }
        } else {
            // merge tunnels
            if (!empty($result['reqids'])) {
                $result['reqids'] = [min($result['reqids'])];
                for ($idx = 0; $idx < count($result['leftsubnets']); ++$idx) {
                    $result['uniqid_reqid'][$uniqids[$idx]] = $result['reqids'][0];
                }
            }
            $result['leftsubnets'] = array_unique($result['leftsubnets']);
            $result['rightsubnets'] = array_unique($result['rightsubnets']);
            // merge esp phase 2 arrays.
            $esp_content = array();
            foreach ($result['ealgoESPsp2'] as $ealgoESPsp2arr_details) {
                foreach ($ealgoESPsp2arr_details as $esp_item) {
                    if (!in_array($esp_item, $esp_content)) {
                        $esp_content[] = $esp_item;
                    }
                }
            }
            $result['ealgoESPsp2'] = $esp_content;
            // merge ah phase 2 arrays.
            $ah_content = array();
            foreach ($result['ealgoAHsp2'] as $ealgoAHsp2arr_details) {
                foreach ($ealgoAHsp2arr_details as $ah_item) {
                    if (!in_array($ah_item, $ah_content)) {
                        $ah_content[] = $ah_item;
                    }
                }
            }
            $result['ealgoAHsp2'] = $ah_content;
        }
    }
    return $result;
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
                    return $idinfo['address'] . "/128";
                } else {
                    return $idinfo['address'] . "/32";
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
            if ($mode == 'tunnel6') {
                return find_interface_networkv6(get_real_interface($idinfo['type']), 'inet6');
            } else {
                return find_interface_network(get_real_interface($idinfo['type']));
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
        $i = 0;
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
                        $cursp['reqid'] =  substr($upperspec[3], strpos($upperspec[3], "#") + 1);
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
        $cursa = null;
        $i = 0;
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
                        $cursa['spi'] = substr($linea[2], strpos($linea[2], "x") + 1, -1);
                        $reqid = substr($linea[3], strpos($linea[3], "=") + 1);
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

function ipsec_lookup_keypair($uuid)
{
    $mdl = new \OPNsense\IPsec\IPsec();
    $node = $mdl->getNodeByReference('keyPairs.keyPair.' . $uuid);

    return $node ? $node->getNodes() : null;
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
    $id_type = null;
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
        case "keyid tag":
            $thisid_data = !empty($id_data) ? "keyid:{$id_data}" : null;
            break;
        default:
            $thisid_data = !empty($id_data) ? "{$id_data}" : null;
            break;
    }
    return $thisid_data;
}

/* include all configuration functions */
function ipsec_convert_to_modp($index): string
{
    $map = [
        1 => 'modp768',
        2 => 'modp1024',
        5 => 'modp1536',
        14 => 'modp2048',
        15 => 'modp3072',
        16 => 'modp4096',
        17 => 'modp6144',
        18 => 'modp8192',
        19 => 'ecp256',
        20 => 'ecp384',
        21 => 'ecp521',
        22 => 'modp1024s160',
        23 => 'modp2048s224',
        24 => 'modp2048s256',
        28 => 'ecp256bp',
        29 => 'ecp384bp',
        30 => 'ecp512bp',
        31 => 'curve25519',
    ];

    if (!array_key_exists($index, $map)) {
        return '';
    }

    return $map[$index];
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
    $src = $dst = $direction = '';
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
            if (!empty($ph1ent['disabled'])) {
                continue;
            }
            $reqid_mapping = ipsec_parse_phase2($ph1ent['ikeid'])['uniqid_reqid'];
            foreach ($config['ipsec']['phase2'] as $ph2ent) {
                if (!isset($ph2ent['disabled']) && $ph1ent['ikeid'] == $ph2ent['ikeid'] && !empty($ph2ent['spd'])) {
                    $tunnel_src = ipsec_get_phase1_src($ph1ent);
                    $tunnel_dst = ipsec_resolve($ph1ent['remote-gateway']);

                    // XXX: remove me, temporary logging to validate https://github.com/opnsense/core/issues/1773
                    $peerid_spec = ipsec_find_id($ph1ent, "peer");
                    if (!is_ipaddr($peerid_spec)) {
                        if (is_ipaddr($ph1ent['remote-gateway'])) {
                            $peerid_spec = $ph1ent['remote-gateway'];
                        } else {
                            log_error(sprintf(
                                "spdadd: unable to match remote network on %s or %s [skipped]",
                                $peerid_spec,
                                $ph1ent['remote-gateway']
                            ));
                        }
                    }
                    $myid_data = ipsec_find_id($ph1ent, "local");
                    if ($myid_data != $tunnel_src) {
                        log_error(sprintf(
                            "spdadd: using %s in source policy, local id set to %s",
                            $tunnel_src,
                            $myid_data
                        ));
                    }
                    if ($peerid_spec != $tunnel_dst) {
                        log_error(sprintf(
                            "spdadd: using %s in destination policy, peer id set to %s",
                            $tunnel_dst,
                            $peerid_spec
                        ));
                    }
                    if (empty($reqid_mapping[$ph2ent['uniqid']])) {
                        log_error(sprintf("spdadd: unable to find reqid for %s", $ph2ent['uniqid']));
                    }
                    // XXX: end

                    if (empty($tunnel_dst) || empty($tunnel_src)) {
                        continue;
                    }

                    foreach (explode(',', $ph2ent['spd']) as $local_net) {
                        $proto = $ph2ent['mode'] == "tunnel" ? "4" : "6";
                        $remote_net = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
                        if (!empty($reqid_mapping[$ph2ent['uniqid']])) {
                            $req_id = $reqid_mapping[$ph2ent['uniqid']];
                            $spd_command = "spdadd -%s %s %s any -P out ipsec %s/tunnel/%s-%s/unique:{$req_id};";
                            $spd_entries[] = sprintf(
                                $spd_command,
                                $proto,
                                trim($local_net),
                                $remote_net,
                                $ph2ent['protocol'],
                                $tunnel_src,
                                $tunnel_dst
                            );
                        }
                    }
                }
            }
        }

        $tmpfname = tempnam("/tmp", "setkey");
        file_put_contents($tmpfname, implode("\n", $spd_entries) . "\n");
        mwexec("/sbin/setkey -f " . $tmpfname, true);
        unlink($tmpfname);
    }
}

function ipsec_configure_do($verbose = false, $interface = '')
{
    global $config;

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
    // configure VTI if needed
    ipsec_configure_vti();

    /* get the automatic ping_hosts.sh ready */
    @unlink('/var/db/ipsecpinghosts');
    @touch('/var/db/ipsecpinghosts');

    //  Prefer older IPsec SAs (advanced setting)
    if (isset($config['ipsec']['preferoldsa'])) {
        set_single_sysctl("net.key.preferred_oldsa", "-30");
    } else {
        set_single_sysctl("net.key.preferred_oldsa", "0");
    }

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
        $publickeypath = "/usr/local/etc/ipsec.d/public";
        $privatekeypath = "/usr/local/etc/ipsec.d/private";

        mwexec("/sbin/ifconfig enc0 up");
        set_single_sysctl("net.inet.ip.ipsec_in_use", "1");

        /* needed directories for config files */
        @mkdir($capath);
        @mkdir($privatekeypath);
        @mkdir($publickeypath);
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
        $ipsecpinghosts = "";

        /* step through each phase1 entry */
        foreach ($a_phase1 as $ph1ent) {
            if (isset($ph1ent['disabled'])) {
                continue;
            }

            if ($ph1ent['mode'] == "aggressive" && in_array($ph1ent['authentication_method'], array("pre_shared_key", "xauth_psk_server"))) {
                $aggressive_psk = true;
            }
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
                        foreach (array_keys($iflist) as $ifent) {
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
                        foreach (array_keys($iflist) as $ifent) {
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

        $strongswanTree = [
            '# Automatically generated, please do not modify' => '',
            'starter' => [
                'load_warning' => 'no'
            ],
            'charon' => [
                'threads' => 16,
                'ikesa_table_size' => 32,
                'ikesa_table_segments' => 4,
                'init_limit_half_open' => 1000,
                'ignore_acquire_ts' => 'yes',
                'syslog' => [
                    'identifier' => 'charon',
                    'daemon' => [
                        'ike_name' => 'yes'
                    ]
                ]
            ]
        ];

        if ($aggressive_psk) {
            $strongswanTree['charon']['i_dont_care_about_security_and_use_aggressive_mode_psk'] = 'yes';
        }
        if (!empty($config['ipsec']['auto_routes_disable'])) {
            $strongswanTree['charon']['install_routes'] = 'no';
        }
        if (isset($a_client['enable']) && isset($a_client['net_list'])) {
            $strongswanTree['charon']['cisco_unity'] = 'yes';
        }

        // Debugging configuration
        // lkey is the log key, which is a three-letter abbreviation of the subsystem to log, e.g. `ike`.
        // The value will be a number between -1 (silent) and 4 (highest verbosity).
        foreach (array_keys(IPSEC_LOG_SUBSYSTEMS) as $lkey) {
            if (
                isset($config['ipsec']["ipsec_{$lkey}"]) && is_numeric($config['ipsec']["ipsec_{$lkey}"]) &&
                array_key_exists(intval($config['ipsec']["ipsec_{$lkey}"]), IPSEC_LOG_LEVELS)
            ) {
                $strongswanTree['charon']['syslog']['daemon'][$lkey] = $config['ipsec']["ipsec_{$lkey}"];
            }
        }

        $strongswanTree['charon']['plugins'] = [];

        if (isset($a_client['enable'])) {
            $net_list = array();
            if (isset($a_client['net_list'])) {
                foreach ($a_phase2 as $ph2ent) {
                    if (!isset($ph2ent['disabled']) && isset($ph2ent['mobile'])) {
                        $net_list[] =  ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
                    }
                }
            }

            $strongswanTree['charon']['plugins']['attr'] = [];
            if (!empty($net_list)) {
                $net_list_str = implode(",", $net_list);
                $strongswanTree['charon']['plugins']['attr']['subnet'] = $net_list_str;
                $strongswanTree['charon']['plugins']['attr']['split-include'] = $net_list_str;
            }
            $cfgservers = array();
            foreach (array('dns_server1', 'dns_server2', 'dns_server3', 'dns_server4') as $dns_server) {
                if (!empty($a_client[$dns_server])) {
                    $cfgservers[] = $a_client[$dns_server];
                }
            }
            if (!empty($cfgservers)) {
                $strongswanTree['charon']['plugins']['attr']['dns'] = implode(",", $cfgservers);
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
                $strongswanTree['charon']['plugins']['attr']['nbns'] = implode(",", $cfgservers);
            }
            unset($cfgservers);

            if (!empty($a_client['dns_domain'])) {
                $strongswanTree['charon']['plugins']['attr']['# Search domain and default domain'] = '';
                $strongswanTree['charon']['plugins']['attr']['28674'] = $a_client['dns_domain'];
            }

            /*
             * 28675 --> UNITY_SPLITDNS_NAME
             * 25 --> INTERNAL_DNS_DOMAIN
             */
            foreach (array("28675", "25") as $attr) {
                if (!empty($a_client['dns_split'])) {
                    $strongswanTree['charon']['plugins']['attr'][$attr] = $a_client['dns_split'];
                } elseif (!empty($a_client['dns_domain'])) {
                    $strongswanTree['charon']['plugins']['attr'][$attr] = $a_client['dns_domain'];
                }
            }

            if (!empty($a_client['dns_split'])) {
                $strongswanTree['charon']['plugins']['attr']['28675'] = $a_client['dns_split'];
            }

            if (!empty($a_client['login_banner'])) {
                /* defang login banner, it may be multiple lines and we should not let it escape */
                $strongswanTree['charon']['plugins']['attr']['28672'] = '"' . str_replace(['\\', '"'], '', $a_client['login_banner']) . '"';
            }

            if (isset($a_client['save_passwd'])) {
                $strongswanTree['charon']['plugins']['attr']['28673'] = 1;
            }

            if (!empty($a_client['pfs_group'])) {
                $strongswanTree['charon']['plugins']['attr']['28679'] = $a_client['pfs_group'];
            }

            $disable_xauth = false;
            foreach ($a_phase1 as $ph1ent) {
                if (!isset($ph1ent['disabled']) && isset($ph1ent['mobile'])) {
                    if ($ph1ent['authentication_method'] == "eap-radius") {
                        $disable_xauth = true; // disable Xauth when radius is used.
                        $strongswanTree['charon']['plugins']['eap-radius'] = [];
                        $strongswanTree['charon']['plugins']['eap-radius']['servers'] = [];
                        $radius_server_num = 1;
                        $radius_accounting_enabled = false;
                        foreach (auth_get_authserver_list() as $auth_server) {
                            if (in_array($auth_server['name'], explode(',', $ph1ent['authservers']))) {
                                $server = [
                                    'address' => $auth_server['host'],
                                    'secret' => '"' . $auth_server['radius_secret'] . '"',
                                    'auth_port' => $auth_server['radius_auth_port'],
                                ];

                                if (!empty($auth_server['radius_acct_port'])) {
                                    $server['acct_port'] = $auth_server['radius_acct_port'];
                                }
                                $strongswanTree['charon']['plugins']['eap-radius']['servers']['server' . $radius_server_num] = $server;

                                if (!empty($auth_server['radius_acct_port'])) {
                                    $radius_accounting_enabled = true;
                                }
                                $radius_server_num += 1;
                            }
                        }
                        if ($radius_accounting_enabled) {
                            $strongswanTree['charon']['plugins']['eap-radius']['accounting'] = 'yes';
                        }
                        break; // there can only be one mobile phase1, exit loop
                    }
                }
            }
            if (isset($a_client['enable']) && !$disable_xauth) {
                $strongswanTree['charon']['plugins']['xauth-pam'] = [
                    'pam_service' => 'ipsec',
                    'session' => 'no',
                    'trim_email' => 'yes'
                ];
            }
        }

        $strongswan = generate_strongswan_conf($strongswanTree);
        $strongswan .= "\ninclude strongswan.opnsense.d/*.conf\n";
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

                $ph1keyfile = "{$privatekeypath}/cert-{$ph1ent['ikeid']}.key";
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
                $pskconf .= " : " . ipsec_get_key_type($ph1keyfile) . " {$ph1keyfile}\n";
            } elseif (!empty($ph1ent['pre-shared-key'])) {
                $myid = isset($ph1ent['mobile']) ? trim(ipsec_find_id($ph1ent, "local")) : "";
                $peerid_data = isset($ph1ent['mobile']) ? "%any" : ipsec_find_id($ph1ent, "peer");

                if (!empty($peerid_data)) {
                    $pskconf .= $myid . " " . trim($peerid_data) . " : PSK 0s" . base64_encode(trim($ph1ent['pre-shared-key'])) . "\n";
                }
            }
        }

        /* Generate files for key pairs (e.g. RSA) */
        foreach ($a_phase1 as $ph1ent) {
            if (isset($ph1ent['disabled'])) {
                continue;
            }

            if (!empty($ph1ent['local-kpref'])) {
                $keyPair = ipsec_lookup_keypair($ph1ent['local-kpref']);
                if (!$keyPair || empty($keyPair['publicKey']) || empty($keyPair['privateKey'])) {
                    log_error(sprintf('Error: Invalid phase1 local key pair reference for %s', $ph1ent['name']));
                    continue;
                }

                @chmod($publickeypath, 0600);

                $ph1publickeyfile = "${publickeypath}/publickey-local-{$ph1ent['ikeid']}.pem";
                if (!file_put_contents($ph1publickeyfile, $keyPair['publicKey'])) {
                    log_error(sprintf('Error: Cannot write phase1 local public key file for %s', $ph1ent['name']));
                    @unlink($ph1publickeyfile);
                    continue;
                }
                @chmod($ph1publickeyfile, 0600);

                $ph1privatekeyfile = "${privatekeypath}/privatekey-local-{$ph1ent['ikeid']}.pem";
                if (!file_put_contents($ph1privatekeyfile, $keyPair['privateKey'])) {
                    log_error(sprintf('Error: Cannot write phase1 local private key file for %s', $ph1ent['name']));
                    @unlink($ph1privatekeyfile);
                    continue;
                }
                @chmod($ph1privatekeyfile, 0600);

                $pskconf .= " : " . ipsec_get_key_type($ph1privatekeyfile) . " {$ph1privatekeyfile}\n";
            }

            if (!empty($ph1ent['peer-kpref'])) {
                $keyPair = ipsec_lookup_keypair($ph1ent['peer-kpref']);
                if (!$keyPair || empty($keyPair['publicKey'])) {
                    log_error(sprintf('Error: Invalid phase1 peer key pair reference for %s', $ph1ent['name']));
                    continue;
                }

                @chmod($publickeypath, 0600);

                $ph1publickeyfile = "${publickeypath}/publickey-peer-{$ph1ent['ikeid']}.pem";
                if (!file_put_contents($ph1publickeyfile, $keyPair['publicKey'])) {
                    log_error(sprintf('Error: Cannot write phase1 peer public key file for %s', $ph1ent['name']));
                    @unlink($ph1publickeyfile);
                    continue;
                }
                @chmod($ph1publickeyfile, 0600);
            }
        }

        /* Add user PSKs */
        if (isset($config['system']['user']) && is_array($config['system']['user'])) {
            foreach ($config['system']['user'] as $user) {
                if (!empty($user['ipsecpsk'])) {
                    $pskconf .= "{$user['name']} : PSK 0s" . base64_encode($user['ipsecpsk']) . "\n";
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
                $pskconf .= "{$ident} : {$identType} 0s" . base64_encode($key['pre-shared-key']) . "\n";
            }
            unset($key);
        }

        $pskconf .= "\ninclude ipsec.secrets.opnsense.d/*.secrets\n";
        @file_put_contents("/usr/local/etc/ipsec.secrets", $pskconf);
        chmod("/usr/local/etc/ipsec.secrets", 0600);
        unset($pskconf);

        /* begin ipsec.conf */
        $ipsecconf = "";
        if (count($a_phase1)) {
            $ipsecconf .= "# This file is automatically generated. Do not edit\n";
            $ipsecconf .= "config setup\n\tuniqueids = yes\n";

            if (!empty($config['ipsec']['passthrough_networks'])) {
                $ipsecconf .= "\nconn pass\n";
                $ipsecconf .= "\tright=127.0.0.1 # so this connection does not get used for other purposes\n";
                $ipsecconf .= "\tleftsubnet={$config['ipsec']['passthrough_networks']}\n";
                $ipsecconf .= "\trightsubnet={$config['ipsec']['passthrough_networks']}\n";
                $ipsecconf .= "\ttype=passthrough\n";
                $ipsecconf .= "\tauto=route\n";
            }

            foreach ($a_phase1 as $ph1ent) {
                $mobike = "";

                if (isset($ph1ent['disabled'])) {
                    continue;
                }
                $aggressive = $ph1ent['mode'] == "aggressive" ? "yes" : "no";
                $installpolicy = empty($ph1ent['noinstallpolicy']) ? "yes" : "no";

                $ep = ipsec_get_phase1_src($ph1ent);
                if (empty($ep)) {
                    continue;
                }

                $keyexchange = "ikev1";
                if (!empty($ph1ent['iketype'])) {
                    $keyexchange = $ph1ent['iketype'];
                    $mobike = !empty($ph1ent['mobike']) ? "mobike = no" : "mobike = yes";
                }

                $right_spec = '%any';
                $right_any = '';
                if (!isset($ph1ent['mobile'])) {
                    $right_spec = $ph1ent['remote-gateway'];

                    if ($ph1ent['rightallowany']) {
                        $right_any = 'rightallowany = yes';
                    }
                }

                if (!empty($ph1ent['auto'])) {
                    $conn_auto = $ph1ent['auto'];
                } elseif (isset($ph1ent['mobile'])) {
                    $conn_auto = 'add';
                } elseif (!empty($config['ipsec']['auto_routes_disable'])) {
                    $conn_auto = 'start';
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
                        if (!empty($ph1ent['dhgroup'])) {
                            foreach (explode(',', $ph1ent['dhgroup']) as $dhgrp) {
                                $entryd = $entry;
                                $modp = ipsec_convert_to_modp($dhgrp);
                                if (!empty($modp)) {
                                    $entryd .= "-{$modp}";
                                }
                                $list[] = $entryd;
                            }
                        }
                    }
                    $ealgosp1 = 'ike = ' . implode(',', array_reverse($list)) . '!';
                }

                if (!empty($ph1ent['dpd_delay']) && !empty($ph1ent['dpd_maxfail'])) {
                    if (empty($ph1ent['dpd_action'])) {
                        if (in_array($conn_auto, array('route', 'start'))) {
                            $dpdline = "dpdaction = restart";
                        } else {
                            $dpdline = "dpdaction = clear";
                        }
                    } else {
                        $dpdline = "dpdaction = {$ph1ent['dpd_action']}";
                    }
                    $dpdline .= "\n\tdpddelay = {$ph1ent['dpd_delay']}s";
                    $dpdtimeout = $ph1ent['dpd_delay'] * ($ph1ent['dpd_maxfail'] + 1);
                    $dpdline .= "\n\tdpdtimeout = {$dpdtimeout}s";
                } else {
                    $dpdline = '';
                }

                if (!empty($ph1ent['inactivity_timeout'])) {
                    $inactivityline = "inactivity = {$ph1ent['inactivity_timeout']}s";
                } else {
                    $inactivityline = '';
                }
                if (!empty($ph1ent['keyingtries'])) {
                    $keyingtriesline = "keyingtries = " ;
                    $keyingtriesline .= $ph1ent['keyingtries'] == -1 ? "%forever" : $ph1ent['keyingtries'];
                } else {
                    $keyingtriesline = '';
                }

                if (!empty($ph1ent['lifetime'])) {
                    $ikelifeline = "ikelifetime = {$ph1ent['lifetime']}s";
                } else {
                    $ikelifeline = '';
                }

                $rightsourceip = null;
                if (
                    isset($ph1ent['mobile']) &&
                      (!empty($a_client['pool_address']) || !empty($a_client['pool_address_v6']))
                ) {
                    $rightsourceip = "\trightsourceip = ";
                    if (!empty($a_client['pool_address'])) {
                        $rightsourceip .= "{$a_client['pool_address']}/{$a_client['pool_netbits']}";
                    }
                    if (!empty($a_client['pool_address_v6'])) {
                        $rightsourceip .= (!empty($a_client['pool_address']) ? "," : "");
                        $rightsourceip .= "{$a_client['pool_address_v6']}/{$a_client['pool_netbits_v6']}";
                    }
                    $rightsourceip .= "\n";
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
                        $authentication .= "\n\trightauth2 = xauth-pam";
                        break;
                    case 'xauth_psk_server':
                        $authentication = "leftauth = psk\n\trightauth = psk";
                        $authentication .= "\n\trightauth2 = xauth-pam";
                        break;
                    case 'pre_shared_key':
                        $authentication = "leftauth = psk\n\trightauth = psk";
                        break;
                    case 'rsasig':
                    case 'pubkey':
                        $authentication = "leftauth = pubkey\n\trightauth = pubkey";
                        break;
                    case 'hybrid_rsa_server':
                        $authentication = "leftauth = pubkey\n\trightauth = xauth";
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

                if (!empty($ph1ent['local-kpref'])) {
                    $authentication .= "\n\tleftsigkey = {$publickeypath}/publickey-local-{$ph1ent['ikeid']}.pem";
                }
                if (!empty($ph1ent['peer-kpref'])) {
                    $authentication .= "\n\trightsigkey = {$publickeypath}/publickey-peer-{$ph1ent['ikeid']}.pem";
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
                    if (!empty($ph1ent['margintime'])) {
                        $rekey .= "\n\tmargintime = {$ph1ent['margintime']}s";
                    }
                    if (!empty($ph1ent['rekeyfuzz'])) {
                        $rekey .= "\n\trekeyfuzz = {$ph1ent['rekeyfuzz']}%";
                    }
                }

                $forceencaps = 'forceencaps = no';
                if (!empty($ph1ent['nat_traversal']) && $ph1ent['nat_traversal'] == 'force') {
                    $forceencaps = 'forceencaps = yes';
                }

                $parsed_phase2 = ipsec_parse_phase2($ph1ent['ikeid']);
                $ep = !empty($parsed_phase2['left_override']) ? $parsed_phase2['left_override'] : $ep;

                $connEntry = <<<EOD

conn con<<connectionId>>
  aggressive = {$aggressive}
  fragmentation = yes
  keyexchange = {$keyexchange}
  {$mobike}
  {$reauth}
  {$rekey}
  {$forceencaps}
  installpolicy = {$installpolicy}
  type = {$parsed_phase2['type']}
  {$dpdline}
  {$inactivityline}
  {$keyingtriesline}
  left = {$left_spec}
  right = {$right_spec}
  {$right_any}
  leftid = {$myid_data}
  {$ikelifeline}

EOD;

                if ($parsed_phase2['ipseclifetime'] > 0) {
                    $connEntry .= "\tlifetime = {$parsed_phase2['ipseclifetime']}s\n";
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
                    for ($idx = 0; $idx < count($parsed_phase2['leftsubnets']); ++$idx) {
                        if (count($parsed_phase2['leftsubnets']) == 1) {
                            $tmpconf = str_replace('<<connectionId>>', "{$ph1ent['ikeid']}", $connEntry);
                            $tmpconf .= sprintf("\treqid = %d\n", $parsed_phase2['reqids'][$idx]);
                        } else {
                            // suffix connection with sequence number
                            $tmpconf = str_replace('<<connectionId>>', sprintf('%s-%03d', $ph1ent['ikeid'], $idx), $connEntry);
                            $tmpconf .= sprintf("\treqid = %d\n", $parsed_phase2['reqids'][$idx]);
                        }
                        $tmpconf .= "\trightsubnet = " . $parsed_phase2['rightsubnets'][$idx] . "\n";
                        $tmpconf .= "\tleftsubnet = " . $parsed_phase2['leftsubnets'][$idx] . "\n";
                        if (!empty($parsed_phase2['ealgoESPsp2'][$idx])) {
                            $tmpconf .= "\tesp = " . join(',', $parsed_phase2['ealgoESPsp2'][$idx]) . "!\n";
                        }
                        if (!empty($parsed_phase2['ealgoAHsp2'][$idx])) {
                            $tmpconf .= "\tah = " . join(',', $parsed_phase2['ealgoAHsp2'][$idx]) . "!\n";
                        }
                        $tmpconf .= "\tauto = {$conn_auto}\n";
                        $ipsecconf .= $tmpconf;
                    }
                } else {
                    // mobile and ikev2
                    if (isset($ph1ent['tunnel_isolation'])) {
                        $ipsecconf .= str_replace('<<connectionId>>', "{$ph1ent['ikeid']}-000", $connEntry);
                        for ($idx = 0; $idx < count($parsed_phase2['leftsubnets']); ++$idx) {
                            // requires leading empty line:
                            $tmpconf = array('');
                            // fix for strongSwan to pick up the correct connection
                            // name from the first configured tunnel ($idx == 0):
                            $conn_suffix = $idx ? sprintf('-%03d', $idx) : '';
                            $tmpconf[] = "conn con{$ph1ent['ikeid']}{$conn_suffix}";
                            $tmpconf[] = sprintf("\treqid = %d\n", $parsed_phase2['reqids'][$idx]);

                            if (!empty($parsed_phase2['rightsubnets'][$idx])) {
                                $tmpconf[] = "\trightsubnet = {$parsed_phase2['rightsubnets'][$idx]}";
                            }
                            $tmpconf[] = "\tleftsubnet = {$parsed_phase2['leftsubnets'][$idx]}";
                            if (!empty($parsed_phase2['ealgoESPsp2'][$idx])) {
                                $tmpconf[] = "\tesp = " . join(',', $parsed_phase2['ealgoESPsp2'][$idx]) . '!';
                            }
                            if (!empty($parsed_phase2['ealgoAHsp2'][$idx])) {
                                $tmpconf[] = "\tah = " . join(',', $parsed_phase2['ealgoAHsp2'][$idx]) . '!';
                            }
                            $tmpconf[] = "\talso = con{$ph1ent['ikeid']}-000";
                            $tmpconf[] = "\tauto = {$conn_auto}";
                            // requires trailing empty line:
                            $tmpconf[] = '';
                            $ipsecconf .= join("\n", $tmpconf);
                        }
                    } else {
                        $tmpconf = str_replace('<<connectionId>>', "{$ph1ent['ikeid']}", $connEntry);
                        if (!isset($ph1ent['mobile'])) {
                            $tmpconf .= sprintf("\treqid = %d\n", $parsed_phase2['reqids'][0]);
                        }
                        if (!empty($parsed_phase2['rightsubnets'])) {
                            $tmpconf .= "\trightsubnet = " . join(',', $parsed_phase2['rightsubnets']) . "\n";
                        }
                        if (!empty($parsed_phase2['leftsubnets'])) {
                            $tmpconf .= "\tleftsubnet = " . join(',', $parsed_phase2['leftsubnets']) . "\n";
                        }
                        if (!empty($parsed_phase2['ealgoESPsp2'])) {
                            $tmpconf .= "\tesp = " . join(',', $parsed_phase2['ealgoESPsp2']) . "!\n";
                        }
                        if (!empty($parsed_phase2['ealgoAHsp2'])) {
                            $tmpconf .= "\tah = " . join(',', $parsed_phase2['ealgoAHsp2']) . "!\n";
                        }
                        $tmpconf .= "\tauto = {$conn_auto}\n";
                        $ipsecconf .= $tmpconf;
                    }
                }
            }
        }
    }
    $ipsecconf .= "\ninclude ipsec.opnsense.d/*.conf\n";
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

function generate_strongswan_conf(array $tree, $level = 0): string
{
    $output = "";
    foreach ($tree as $key => $value) {
        $output .= str_repeat('    ', $level) . $key;

        if (strpos($key, '#') === 0) {
            $output .= "\n";
        } elseif (is_array($value)) {
            $output .= " {\n";
            $output .= generate_strongswan_conf($value, $level + 1);
            $output .= str_repeat('    ', $level) . "}\n";
        } else {
            $output .= " = " . $value . "\n";
        }
    }
    return $output;
}

function ipsec_get_configured_vtis()
{
    global $config;
    $configured_intf = array();
    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : array();
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : array();
    foreach ($a_phase1 as $ph1ent) {
        if (empty($ph1ent['disabled'])) {
            $phase2items = array();
            $phase2reqids = array();
            foreach ($a_phase2 as $ph2ent) {
                if (
                    $ph2ent['mode'] == 'route-based' &&
                        empty($ph2ent['disabled']) && $ph1ent['ikeid'] == $ph2ent['ikeid']
                ) {
                    $phase2items[] = $ph2ent;
                    if (!empty($ph2ent['reqid'])) {
                        $phase2reqids[] = $ph2ent['reqid'];
                    }
                }
            }
            foreach ($phase2items as $idx => $phase2) {
                if (empty($phase2['reqid'])) {
                    continue;
                } elseif ((!isset($ph1ent['mobile']) && $keyexchange == 'ikev1') || isset($ph1ent['tunnel_isolation'])) {
                    // isolated tunnels, every tunnel it's own reqid
                    $reqid = $phase2['reqid'];
                    $descr = empty($phase2['descr']) ? $ph1ent['descr'] : $phase2['descr'];
                } else {
                    // use smallest reqid within tunnel
                    $reqid = min($phase2reqids);
                    $descr = $ph1ent['descr'];
                }
                $intfnm = sprintf("ipsec%s", $reqid);
                if (empty($tunnels[$intfnm])) {
                    $configured_intf[$intfnm] = array("reqid" => $reqid);
                    $configured_intf[$intfnm]['local'] = ipsec_get_phase1_src($ph1ent);
                    $configured_intf[$intfnm]['remote'] = $ph1ent['remote-gateway'];
                    $configured_intf[$intfnm]['descr'] = $descr;
                    $configured_intf[$intfnm]['networks'] = array();
                }
                $inet = is_ipaddrv6($phase2['tunnel_local']) ? 'inet6' : 'inet';
                // calculate netmask for the provided tunnel
                if ($inet == 'inet6') {
                    // XXX: we don't support 128bit integers, only the last 64 bits are considered to calculate the
                    //      subnet size.
                    $tunnel_int64 = [];
                    foreach (['tunnel_local', 'tunnel_remote'] as $fieldname) {
                        $parts = explode(':', Net_IPv6::Uncompress($phase2[$fieldname], true));
                        $tunnel_int64[$fieldname] = hexdec(implode("", array_slice($parts, -4)));
                    }
                    $mask = 127 - decbin(abs($tunnel_int64['tunnel_remote'] -  $tunnel_int64['tunnel_local']));
                } else {
                    $mask = 31 - abs(ip2long($phase2['tunnel_remote']) - ip2long($phase2['tunnel_local']));
                }

                $configured_intf[$intfnm]['networks'][] = [
                    'inet' => $inet,
                    'tunnel_local' => $phase2['tunnel_local'],
                    'mask' => $mask,
                    'tunnel_remote' => $phase2['tunnel_remote']
                ];
            }
        }
    }

    return $configured_intf;
}

/**
 * Configure required Virtual Terminal Interfaces (synchronizes configuration with local interfaces named ipsec%)
 */
function ipsec_configure_vti($verbose = false)
{
    // query planned and configured interfaces
    $configured_intf = ipsec_get_configured_vtis();
    $current_interfaces = array();

    foreach (legacy_interfaces_details() as $intf => $intf_details) {
        if (strpos($intf, 'ipsec') === 0) {
            $current_interfaces[$intf] = $intf_details;
        }
    }

    if ($verbose) {
        echo 'Creating IPsec VTI instances...';
        flush();
    }

    // drop changed or not existing interfaces and tunnel endpoints
    foreach ($current_interfaces as $intf => $intf_details) {
        if (
            empty($configured_intf[$intf])
            || $configured_intf[$intf]['local'] != $intf_details['tunnel']['src_addr']
            || $configured_intf[$intf]['remote'] != $intf_details['tunnel']['dest_addr']
        ) {
            log_error(sprintf("destroy interface %s", $intf));
            legacy_interface_destroy($intf);
            unset($current_interfaces[$intf]);
        } else {
            foreach (array('ipv4', 'ipv6') as $proto) {
                foreach ($intf_details[$proto] as $addr) {
                    if (!empty($addr['endpoint'])) {
                        $isfound = false;
                        foreach ($configured_intf[$intf]['networks'] as $network) {
                            if (
                                $network['tunnel_local'] == $addr['ipaddr']
                                    && $network['tunnel_remote']  == $addr['endpoint']
                            ) {
                                $isfound = true;
                                break;
                            }
                        }
                        if (!$isfound) {
                            log_error(sprintf(
                                "remove tunnel %s %s from interface %s",
                                $addr['ipaddr'],
                                $addr['endpoint'],
                                $intf
                            ));
                            mwexecf('/sbin/ifconfig %s %s %s delete', array(
                            $intf, $proto == 'ipv6' ? 'inet6' : 'inet',  $addr['ipaddr'], $addr['endpoint']
                            ));
                        }
                    }
                }
            }
        }
    }

    // configure new interfaces and tunnels
    foreach ($configured_intf as $intf => $intf_details) {
        // create required interfaces
        $inet = is_ipaddrv6($intf_details['local']) ? 'inet6' : 'inet';
        if (empty($current_interfaces[$intf])) {
            // prevent ipsec vti interface to hit 32768 limit (create numbered, rename and attach afterwards)
            if (legacy_interface_create("ipsec", $intf) != null) {
                mwexecf('/sbin/ifconfig %s reqid %s', array($intf, $intf_details['reqid']));
                mwexecf(
                    '/sbin/ifconfig %s %s tunnel %s %s up',
                    array($intf, $inet, $intf_details['local'], $intf_details['remote'])
                );
            }
        }
        // create new tunnel endpoints
        foreach ($intf_details['networks'] as $endpoint) {
            if (!empty($current_interfaces[$intf])) {
                $already_configured = $current_interfaces[$intf][$endpoint['inet'] == 'inet6' ? 'ipv6' : 'ipv4'];
            } else {
                $already_configured = array();
            }
            $isfound = false;
            foreach ($already_configured as $addr) {
                if (!empty($addr['endpoint'])) {
                    if (
                        $endpoint['tunnel_local'] == $addr['ipaddr']
                            && $endpoint['tunnel_remote']  == $addr['endpoint']
                    ) {
                        $isfound = true;
                    }
                }
            }
            if (!$isfound) {
                if ($endpoint['inet'] == 'inet') {
                    mwexecf('/sbin/ifconfig %s %s %s %s', array(
                        $intf, $endpoint['inet'],  sprintf("%s/%s", $endpoint['tunnel_local'], $endpoint['mask']),
                        $endpoint['tunnel_remote']
                    ));
                } else {
                    // XXX: don't specify a tunnel endpoint for ipv6, although this looks like an illogical
                    //      construction, a netmask seems to be a requirement for some ipv6 consumers (frr)
                    mwexecf('/sbin/ifconfig %s %s %s', array(
                        $intf, $endpoint['inet'],  sprintf("%s/%s", $endpoint['tunnel_local'], $endpoint['mask'])
                    ));
                }
            }
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}
