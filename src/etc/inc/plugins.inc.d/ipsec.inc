<?php

/*
 * Copyright (C) 2016-2023 Deciso B.V.
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
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
        'aes128' => array( 'name' => 'aes', 'keylen' => "128", 'descr' => gettext("AES128")),
        'aes192' => array( 'name' => 'aes', 'keylen' => "192", 'descr' => gettext("AES192")),
        'aes256' => array( 'name' => 'aes', 'keylen' => "256", 'descr' => gettext("AES256")),
        'aes128gcm16' => array( 'name' => 'aes128gcm16', 'descr' => 'aes128gcm16'),
        'aes192gcm16' => array( 'name' => 'aes192gcm16', 'descr' => 'aes192gcm16'),
        'aes256gcm16' => array( 'name' => 'aes256gcm16', 'descr' => 'aes256gcm16'),
        'null' => array( 'name' => 'null', 'descr' => gettext("NULL (no encryption)"))
    );
}

function ipsec_p2_halgos()
{
    return array(
        'hmac_sha1' => 'SHA1',
        'hmac_sha256' => 'SHA256',
        'hmac_sha384' => 'SHA384',
        'hmac_sha512' => 'SHA512',
        'aesxcbc' => 'AES-XCBC'
    );
}

function ipsec_configure()
{
    return [
        'ipsec' => ['ipsec_configure_do:2'],
        'vpn' => ['ipsec_configure_do:2'],
    ];
}

function ipsec_syslog()
{
    $logfacilities = [];

    $logfacilities['ipsec'] = array(
        'facility' => array('charon'),
    );

    return $logfacilities;
}

function ipsec_services()
{
    global $config;

    $services = [];

    if (!empty($config['ipsec']['enable']) || (new \OPNsense\IPsec\Swanctl())->isEnabled()) {
        $pconfig = [];
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

    $swanctl = (new \OPNsense\IPsec\Swanctl());
    $interfaces = [];

    $is_enabled = $swanctl->isEnabled();
    if (!$is_enabled && isset($config['ipsec']['phase1'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (empty($ph1ent['disabled'])) {
                $is_enabled = true;
                break;
            }
        }
    }

    if ($is_enabled) {
        $oic = ['enable' => true];
        $oic['if'] = 'enc0';
        $oic['descr'] = 'IPsec';
        $oic['type'] = 'none';
        $oic['virtual'] = true;
        $interfaces['enc0'] = $oic;

        $vtis = array_merge(ipsec_get_configured_vtis(), $swanctl->getVtiDevices());
        foreach ($vtis as $intf => $details) {
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

function ipsec_devices()
{
    $devices = [];
    $names = [];

    $vtis = array_merge(ipsec_get_configured_vtis(), (new \OPNsense\IPsec\Swanctl())->getVtiDevices());
    foreach ($vtis as $device => $details) {
        $names[$device] = [
            'descr' => sprintf('%s (%s)', $device, $details['descr']),
            'ifdescr' => sprintf('%s', $details['descr']),
            'name' => $device,
        ];
    }

    $devices[] = [
        'function' => 'ipsec_configure_device',
        'configurable' => false,
        'pattern' => '^ipsec',
        'volatile' => true,
        'names' => $names,
        'type' => 'ipsec',
    ];

    $devices[] = ['pattern' => '^enc', 'volatile' => true];

    return $devices;
}

function ipsec_firewall(\OPNsense\Firewall\Plugin $fw)
{
    global $config;

    if (
        !isset($config['system']['disablevpnrules']) &&
            isset($config['ipsec']['enable']) && isset($config['ipsec']['phase1'])
    ) {
        $enable_replyto = empty($config['system']['disablereplyto']);
        $enable_routeto = empty($config['system']['pf_disable_force_gw']);
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (!isset($ph1ent['disabled'])) {
                // detect remote ip
                $rgip = null;
                if (isset($ph1ent['mobile'])) {
                    $rgip = "any";
                } elseif (!is_ipaddr($ph1ent['remote-gateway'])) {
                    $rgip = ipsec_resolve($ph1ent['remote-gateway'], $ph1ent['protocol']);
                } else {
                    $rgip = $ph1ent['remote-gateway'];
                }
                if (!empty($rgip)) {
                    $protos_used = [];
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
                    $interface = explode('_vip', $ph1ent['interface'])[0];
                    $baserule = array("interface" => $interface,
                                      "log" => !isset($config['syslog']['nologdefaultpass']),
                                      "quick" => false,
                                      "type" => "pass",
                                      "statetype" => "keep",
                                      "#ref" => "vpn_ipsec_settings.php#disablevpnrules",
                                      "descr" => "IPsec: " . (!empty($ph1ent['descr']) ? $ph1ent['descr'] : $rgip)
                                    );

                    // find gateway
                    $gwname = $fw->getGateways()->getInterfaceGateway($interface, $ph1ent['protocol'], true, 'name');
                    if ((strpos($rgip, ':') === false ? 'inet' : 'inet6') != $ph1ent['protocol']) {
                        // XXX: prevent route-to when gateway and remote address family mismatch
                        $enable_routeto = false;
                    }
                    // register rules
                    $fw->registerFilterRule(
                        500000,
                        array("direction" => "out", "protocol" => "udp", "to" => $rgip, "to_port" => 500,
                              "gateway" => $enable_routeto ? $gwname : null, "disablereplyto" => true),
                        $baserule
                    );
                    $fw->registerFilterRule(
                        500000,
                        array("direction" => "in", "protocol" => "udp", "from" => $rgip, "to_port" => 500,
                              "reply-to" => $enable_replyto ? $gwname : null),
                        $baserule
                    );
                    if ($ph1ent['nat_traversal'] != "off") {
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "out", "protocol" => "udp", "to" => $rgip, "to_port" => 4500,
                                  "gateway" => $enable_routeto ? $gwname : null, "disablereplyto" => true),
                            $baserule
                        );
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "in", "protocol" => "udp", "from" => $rgip, "to_port" => 4500,
                                  "reply-to" => $enable_replyto ? $gwname : null),
                            $baserule
                        );
                    }
                    foreach ($protos_used as $proto) {
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "out", "protocol" => $proto, "to" => $rgip,
                                  "gateway" => $enable_routeto ? $gwname : null, "disablereplyto" => true),
                            $baserule
                        );
                        $fw->registerFilterRule(
                            500000,
                            array("direction" => "in", "protocol" => $proto, "from" => $rgip,
                                  "reply-to" => $enable_replyto ? $gwname : null),
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
    $result = [];

    $result[] = array(
        'description' => gettext('IPsec'),
        'section' => 'ipsec,OPNsense.IPsec,OPNsense.Swanctl',
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
    if ($ph1ent['protocol'] == "inet46") {
        $ipv4 = get_interface_ip($if);
        $ipv6 = get_interface_ipv6($if);
        return $ipv4 ? $ipv4 . ($ipv6 ? ',' . $ipv6 : '') : $ipv6;
    } elseif ($ph1ent['protocol'] == "inet6") {
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
    $result = [
        "type" => "tunnel",  // strongswan's default when type is not specified
        "local_ts" => [],
        "remote_ts" => [],
        "ah_proposals" => [],
        "esp_proposals" => [],
        "life_time" => [],
        "rekey_time" => [],
        "rand_time" => [],
        "reqids" => [],
        "uniqid_reqid" => []
    ];

    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : [];
    $a_client = isset($config['ipsec']['client']) ? $config['ipsec']['client'] : [];
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
            } elseif (isset($ph1ent['mobile']) && !isset($a_client['enable'])) {
                continue;
            } elseif (in_array($ph2ent['mode'], ['tunnel', 'tunnel6'])) {
                $leftsubnet_data = ipsec_idinfo_to_cidr($ph2ent['localid'], false, $ph2ent['mode']);
                if (!is_ipaddr($leftsubnet_data) && !is_subnet($leftsubnet_data) && ($leftsubnet_data != "0.0.0.0/0")) {
                    log_msg("Invalid IPsec Phase 2 \"{$ph2ent['descr']}\" - {$ph2ent['localid']['type']} has no subnet.", LOG_ERR);
                    continue;
                }
                $result['local_ts'][] = $leftsubnet_data;
                if (!isset($ph1ent['mobile'])) {
                    $result['remote_ts'][] = ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode']);
                }
            } elseif ($ph2ent['mode'] == 'route-based') {
                if (is_ipaddrv6($ph2ent['tunnel_local'])) {
                    $result['local_ts'][] = '::/0';
                    $result['remote_ts'][] = '::/0';
                } else {
                    $result['local_ts'][] = '0.0.0.0/0';
                    $result['remote_ts'][] = '0.0.0.0/0';
                }
            } else {
                $result['type'] = 'transport';
                if (
                    !((($ph1ent['authentication_method'] == "xauth_psk_server") ||
                    ($ph1ent['authentication_method'] == "pre_shared_key")) && isset($ph1ent['mobile']))
                ) {
                    $result['local_ts'][] = ipsec_get_phase1_src($ph1ent);
                }
                if (!isset($ph1ent['mobile'])) {
                    $result['remote_ts'][] = $ph1ent['remote-gateway'];
                }
            }
            $uniqids[] = $ph2ent['uniqid'];

            if (isset($ph1ent['mobile']) && isset($a_client['pfs_group'])) {
                $ph2ent['pfsgroup'] = $a_client['pfs_group'];
            }
            if (isset($ph2ent['protocol']) && $ph2ent['protocol'] == 'esp') {
                $ealgoESPsp2arr_details = [];
                if (is_array($ph2ent['encryption-algorithm-option'])) {
                    foreach ($ph2ent['encryption-algorithm-option'] as $algidx => $ealg) {
                        foreach (ipsec_p2_ealgos() as $algo_name => $algo_data) {
                            $tmpealgo = "";
                            if ($algo_name == $ealg['name']) {
                                $tmpealgo = $algo_name;
                            } elseif ($algo_data['name'] == $ealg['name']) {
                                // XXX: extract and convert legacy encryption-algorithm-option setting
                                //      (pre 22.1 saved values)
                                if ($ealg['keylen'] == $algo_data['keylen'] || $ealg['keylen'] == "auto") {
                                    $tmpealgo = $algo_name;
                                }
                            }
                            if (!empty($tmpealgo)) {
                                $modp = isset($ph2ent['pfsgroup']) ? ipsec_convert_to_modp($ph2ent['pfsgroup']) : null;
                                if (is_array($ph2ent['hash-algorithm-option'])) {
                                    foreach (array_keys(ipsec_p2_halgos()) as $halgo) {
                                        if (in_array($halgo, $ph2ent['hash-algorithm-option'])) {
                                            $ealgoESPsp2arr_details[] = sprintf(
                                                "%s-%s%s",
                                                $tmpealgo,
                                                str_replace('hmac_', '', $halgo),
                                                !empty($modp) ? "-{$modp}" : ""
                                            );
                                        }
                                    }
                                } else {
                                    $ealgoESPsp2arr_details[] = $tmpealgo . (!empty($modp) ? "-{$modp}" : "");
                                }
                            }
                        }
                    }
                }
                $result['esp_proposals'][] = $ealgoESPsp2arr_details;
            } elseif (isset($ph2ent['protocol']) && $ph2ent['protocol'] == 'ah') {
                $ealgoAHsp2arr_details = [];
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
                $result['ah_proposals'][] = $ealgoAHsp2arr_details;
            }

            if (isset($ph1ent['rekey_enable'])) {
                $result['life_time'][] = 0;
            } else {
                $result['life_time'][] = !empty($ph2ent['lifetime']) ? $ph2ent['lifetime'] : null;
            }

            if (!empty($ph1ent['margintime']) && !empty($ph1ent['rekeyfuzz']) && !empty($ph2ent['lifetime'])) {
                $result['rekey_time'][] = ($ph2ent['lifetime'] - $ph1ent['margintime']);
                $result['rand_time'][] = ($ph1ent['margintime'] * $ph1ent['rekeyfuzz']);
            } else {
                $result['rekey_time'][] = null;
                $result['rand_time'][] = null;
            }
            $result['reqids'][] = !empty($ph2ent['reqid']) ? $ph2ent['reqid'] : null;
            $idx++;
        }
        if ((!isset($ph1ent['mobile']) && $keyexchange == 'ikev1') || isset($ph1ent['tunnel_isolation'])) {
            // isolated tunnels
            for ($idx = 0; $idx < count($result['local_ts']); ++$idx) {
                $result['uniqid_reqid'][$uniqids[$idx]] = $result['reqids'][$idx];
            }
        } else {
            // merge tunnels
            if (!empty($result['reqids'])) {
                $result['reqids'] = [min($result['reqids'])];
                for ($idx = 0; $idx < count($result['local_ts']); ++$idx) {
                    $result['uniqid_reqid'][$uniqids[$idx]] = $result['reqids'][0];
                }
            }
            $result['local_ts'] = array_unique($result['local_ts']);
            $result['remote_ts'] = array_unique($result['remote_ts']);
            // merge esp phase 2 arrays.
            $esp_content = [];
            foreach ($result['esp_proposals'] as $ealgoESPsp2arr_details) {
                foreach ($ealgoESPsp2arr_details as $esp_item) {
                    if (!in_array($esp_item, $esp_content)) {
                        $esp_content[] = $esp_item;
                    }
                }
            }
            $result['esp_proposals'] = $esp_content;
            // merge ah phase 2 arrays.
            $ah_content = [];
            foreach ($result['ah_proposals'] as $ealgoAHsp2arr_details) {
                foreach ($ealgoAHsp2arr_details as $ah_item) {
                    if (!in_array($ah_item, $ah_content)) {
                        $ah_content[] = $ah_item;
                    }
                }
            }
            $result['ah_proposals'] = $ah_content;
        }
    }
    return $result;
}

/*
 * Return phase2 idinfo in cidr format
 */
function ipsec_idinfo_to_cidr(&$idinfo, $addrbits = false, $mode = '')
{
    switch ($idinfo['type'] ?? 'none') {
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
                list (, $network) = interfaces_routed_address6($idinfo['type']);
                return $network;
            } else {
                list (, $network) = interfaces_primary_address($idinfo['type']);
                return $network;
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


function ipsec_resolve($hostname, $ipproto = 'inet')
{
    if (!is_ipaddr($hostname)) {
        $dns_qry_type = $ipproto == 'inet6' ? DNS_AAAA : DNS_A;
        $dns_qry_outfield = $ipproto == 'inet6' ? "ipv6" : "ip";
        $dns_records = @dns_get_record($hostname, $dns_qry_type);
        if (is_array($dns_records)) {
            foreach ($dns_records as $dns_record) {
                if (!empty($dns_record[$dns_qry_outfield])) {
                    return $dns_record[$dns_qry_outfield];
                }
            }
        }
        return null;
    }

    return $hostname;
}

function ipsec_find_id(&$ph1ent, $side = 'local')
{
    if ($side == "local") {
        $id_type = $ph1ent['myid_type'] ?? null;
        $id_data = isset($ph1ent['myid_data']) ? $ph1ent['myid_data'] : null;
    } elseif ($side == "peer") {
        $id_type = $ph1ent['peerid_type'] ?? null;
        $id_data = isset($ph1ent['peerid_data']) ? $ph1ent['peerid_data'] : null;
        /* Only specify peer ID if we are not dealing with a mobile PSK-only tunnel */
        if (isset($ph1ent['mobile'])) {
            return null;
        }
    } else {
        return null;
    }

    if ($id_type == "myaddress") {
        $thisid_data = ipsec_get_phase1_src($ph1ent);
    } elseif ($id_type == "dyn_dns") {
        $thisid_data = ipsec_resolve($id_data);
    } elseif ($id_type == "peeraddress") {
        $thisid_data = ipsec_resolve($ph1ent['remote-gateway']);
    } elseif (empty($id_data)) {
        $thisid_data = null;
    } elseif (in_array($id_type, ["asn1dn", "fqdn"])) {
        if (strpos($id_data, "#") !== false) {
            $thisid_data = "\"{$id_type}:{$id_data}\"";
        } else {
            $thisid_data = "{$id_type}:{$id_data}";
        }
    } elseif ($id_type == "keyid tag") {
        $thisid_data = "keyid:{$id_data}";
    } elseif ($id_type == "user_fqdn") {
        $thisid_data = "userfqdn:{$id_data}";
    } else {
        $thisid_data = $id_data;
    }

    return trim($thisid_data);
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

    $spd_entries = [];

    /**
     * try to figure out which SPD entries where created manually and collect them in a list sequenced by reqid
     * when the entry is either bound to an interface (ifname=) or has a lifetime, these where not likely created by us
     */
    exec('/sbin/setkey -PD', $lines);
    $line_count = 0;
    $src = $dst = $direction = $reqid = '';
    $is_automatic = false;
    $previous_spd_entries = [];
    foreach ($lines as $line) {
        if ($line[0] != "\t") {
            $tmp = explode(' ', $line);
            $src = $dst = $direction = $reqid = '';
            if (count($tmp) >= 3) {
                $src = explode('[', $tmp[0])[0];
                $dst = explode('[', $tmp[1])[0];
            }
            $line_count = 0;
            $is_automatic = false;
        } elseif ($line_count == 1) {
            // direction
            $direction = trim(explode(' ', $line)[0]);
        } elseif (strpos($line, '/unique:') !== false) {
            $reqid = explode('/unique:', $line)[1];
        } elseif (strpos($line, "\tlifetime:") === 0 || strpos($line, "ifname=") > 0) {
            $is_automatic = true;
        } elseif (strpos($line, "\trefcnt") === 0 && !$is_automatic && $reqid != "") {
            if (empty($previous_spd_entries[$reqid])) {
                $previous_spd_entries[$reqid] = [];
            }
            $previous_spd_entries[$reqid][] = sprintf("spddelete -n %s %s any -P %s;", $src, $dst, $direction);
        }
        $line_count++;
    }

    // process manual added spd entries
    if (!empty($config['ipsec']['phase1']) && !empty($config['ipsec']['phase2'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (!empty($ph1ent['disabled'])) {
                continue;
            }
            $reqid_mapping = ipsec_parse_phase2($ph1ent['ikeid'])['uniqid_reqid'];
            foreach (array_unique(array_values($reqid_mapping)) as $reqid) {
                if (!empty($previous_spd_entries[$reqid])) {
                    foreach ($previous_spd_entries[$reqid] as $spd_entry) {
                        $spd_entries[] = $spd_entry;
                    }
                    unset($previous_spd_entries[$reqid]);
                }
            }
            foreach ($config['ipsec']['phase2'] as $ph2ent) {
                if (!isset($ph2ent['disabled']) && $ph1ent['ikeid'] == $ph2ent['ikeid'] && !empty($ph2ent['spd']) && !empty($ph2ent['remoteid'])) {
                    $tunnel_src = ipsec_get_phase1_src($ph1ent);
                    $tunnel_dst = ipsec_resolve($ph1ent['remote-gateway'], $ph1ent['protocol']);

                    if (empty($tunnel_dst) || empty($tunnel_src)) {
                        log_msg(sprintf(
                            "spdadd: skipped for tunnel %s-%s (reqid :%s, local: %s, remote: %s)",
                            $tunnel_src,
                            $tunnel_dst,
                            !empty($reqid_mapping[$ph2ent['uniqid']]) ? $reqid_mapping[$ph2ent['uniqid']] : "",
                            $ph2ent['spd'],
                            ipsec_idinfo_to_cidr($ph2ent['remoteid'], false, $ph2ent['mode'])
                        ), LOG_ERR);
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

/**
 * setup list of hosts which will be "pinged" via cron on regular intervals
 */
function ipsec_setup_pinghosts()
{
    global $config;

    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : [];

    @unlink('/var/db/ipsecpinghosts');

    if (!isset($config['ipsec']['enable'])) {
        return;
    }

    /* resolve all local, peer addresses and setup pings */
    $ipsecpinghosts = "";

    /* step through each phase1 entry */
    foreach ($a_phase1 as $ph1ent) {
        if (isset($ph1ent['disabled']) || isset($ph1ent['mobile'])) {
            continue;
        }

        /* step through each phase2 entry */
        foreach ($a_phase2 as $ph2ent) {
            if (isset($ph2ent['disabled']) || $ph1ent['ikeid'] != $ph2ent['ikeid']) {
                continue;
            }

            /* add an ipsec pinghosts entry */
            if (!empty($ph2ent['pinghost'])) {
                if (!isset($iflist) || !is_array($iflist)) {
                    $iflist = get_configured_interface_with_descr();
                }
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
                    foreach (config_read_array('virtualip', 'vip') as $vip) {
                        if (ip_in_subnet($vip['subnet'], $local_subnet)) {
                            $srcip = $vip['subnet'];
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

    if (!empty($ipsecpinghosts)) {
        /* get the automatic ping_hosts.sh ready */
        @file_put_contents('/var/db/ipsecpinghosts', $ipsecpinghosts);
    }
}

/**
 * Populate /usr/local/etc/strongswan.conf
 */
function ipsec_write_strongswan_conf()
{
    global $config;
    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : [];
    $a_client = isset($config['ipsec']['client']) ? $config['ipsec']['client'] : [];
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

    foreach ($a_phase1 as $ph1ent) {
        if (isset($ph1ent['disabled'])) {
            continue;
        }

        if ($ph1ent['mode'] ?? '' == "aggressive" && in_array($ph1ent['authentication_method'], array("pre_shared_key", "xauth_psk_server"))) {
            $strongswanTree['charon']['i_dont_care_about_security_and_use_aggressive_mode_psk'] = 'yes';
            break;
        }
    }

    $strongswanTree['charon']['install_routes'] = 'no';
    if (isset($a_client['enable']) && isset($a_client['net_list'])) {
        $strongswanTree['charon']['cisco_unity'] = 'yes';
    }
    if (!empty($config['ipsec']['max_ikev1_exchanges'])) {
        $strongswanTree['charon']['max_ikev1_exchanges'] = $config['ipsec']['max_ikev1_exchanges'];
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

    $radius_auth_servers = null;
    $disable_xauth = false;
    if (isset($a_client['enable'])) {
        $net_list = [];
        if (isset($a_client['net_list'])) {
            foreach ($a_phase1 as $ph1ent) {
                if (isset($ph1ent['disabled']) || !isset($ph1ent['mobile'])) {
                    continue;
                }
                foreach ($a_phase2 as $ph2ent) {
                    if ($ph1ent['ikeid'] == $ph2ent['ikeid'] && !isset($ph2ent['disabled']) && !empty($ph2ent['localid'])) {
                        $net_list[] = ipsec_idinfo_to_cidr($ph2ent['localid'], true, $ph2ent['mode']);
                    }
                }
            }
        }

        $strongswanTree['charon']['plugins']['attr'] = [];
        if (!empty($net_list)) {
            $net_list_str = implode(",", $net_list);
            $strongswanTree['charon']['plugins']['attr']['subnet'] = $net_list_str;
            $strongswanTree['charon']['plugins']['attr']['split-include'] = $net_list_str;
        }
        $cfgservers = [];
        foreach (array('dns_server1', 'dns_server2', 'dns_server3', 'dns_server4') as $dns_server) {
            if (!empty($a_client[$dns_server])) {
                $cfgservers[] = $a_client[$dns_server];
            }
        }
        if (!empty($cfgservers)) {
            $strongswanTree['charon']['plugins']['attr']['dns'] = implode(",", $cfgservers);
        }
        $cfgservers = [];
        if (!empty($a_client['wins_server1'])) {
            $cfgservers[] = $a_client['wins_server1'];
        }
        if (!empty($a_client['wins_server2'])) {
            $cfgservers[] = $a_client['wins_server2'];
        }
        if (!empty($cfgservers)) {
            $strongswanTree['charon']['plugins']['attr']['nbns'] = implode(",", $cfgservers);
        }

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

        foreach ($a_phase1 as $ph1ent) {
            if (!isset($ph1ent['disabled']) && isset($ph1ent['mobile'])) {
                if ($ph1ent['authentication_method'] == "eap-radius") {
                    $radius_auth_servers = $ph1ent['authservers'];
                    break; // there can only be one mobile phase1, exit loop
                }
            }
        }
    }
    if (empty($radius_auth_servers) && !empty($a_client['radius_source'])) {
        $radius_auth_servers = $a_client['radius_source'];
    }
    $mdl = new \OPNsense\IPsec\Swanctl();
    if ((isset($a_client['enable']) || $mdl->isEnabled()) && !empty($radius_auth_servers)) {
        $disable_xauth = true; // disable Xauth when radius is used.
        $strongswanTree['charon']['plugins']['eap-radius'] = [];
        $strongswanTree['charon']['plugins']['eap-radius']['servers'] = [];
        $radius_server_num = 1;
        $radius_accounting_enabled = false;

        foreach (auth_get_authserver_list() as $auth_server) {
            if (in_array($auth_server['name'], explode(',', $radius_auth_servers))) {
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
        if ($mdl->radiusUsesGroups()) {
            $strongswanTree['charon']['plugins']['eap-radius']['class_group'] = 'yes';
        }
    }
    if ((isset($a_client['enable']) && !$disable_xauth) || (new \OPNsense\IPsec\Swanctl())->isEnabled()) {
        $strongswanTree['charon']['plugins']['xauth-pam'] = [
            'pam_service' => 'ipsec',
            'session' => 'no',
            'trim_email' => 'yes'
        ];
    }

    $strongswan = generate_strongswan_conf($strongswanTree);
    $strongswan .= "\ninclude strongswan.opnsense.d/*.conf\n";
    @file_put_contents("/usr/local/etc/strongswan.conf", $strongswan);
}

/**
 *  generate CA certificates files
 */
function ipsec_write_cas()
{
    global $config;
    $capath = "/usr/local/etc/swanctl/x509ca";
    $cafiles = [];
    foreach (isset($config['ca']) ? $config['ca'] : [] as $ca) {
        $cert = base64_decode($ca['crt']);
        $x509cert = openssl_x509_parse(openssl_x509_read($cert));
        if (is_array($x509cert) && isset($x509cert['hash'])) {
            $fname = "{$capath}/{$x509cert['hash']}.0.crt";
            $cafiles[] = $fname;
            file_put_contents($fname, $cert);
        } else {
            log_msg(sprintf('Error: Invalid certificate hash info for %s', $ca['descr']), LOG_ERR);
        }
    }
    foreach (glob("{$capath}/*.0.crt") as $fname) {
        if (!in_array($fname, $cafiles)) {
            unlink($fname);
        }
    }
}

/**
 * Write certificates
 */
function ipsec_write_certs()
{
    global $config;
    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];

    foreach ((new \OPNsense\IPsec\Swanctl())->getUsedCertrefs() as $certref) {
        $cert = lookup_cert($certref);
        if (empty($cert)) {
            log_msg(sprintf('Error: Invalid certificate reference for %s', $ph1ent['name']), LOG_ERR);
            continue;
        }

        $ph1keyfile = "/usr/local/etc/swanctl/private/{$certref}.key";
        @touch($ph1keyfile);
        @chmod($ph1keyfile, 0600);
        file_put_contents($ph1keyfile, base64_decode($cert['prv']));

        $ph1certfile = "/usr/local/etc/swanctl/x509/{$certref}.crt";
        @touch($ph1certfile);
        @chmod($ph1certfile, 0600);
        file_put_contents($ph1certfile, base64_decode($cert['crt']));
    }
    foreach ($a_phase1 as $ph1ent) {
        if (isset($ph1ent['disabled'])) {
            continue;
        }
        if (!empty($ph1ent['certref'])) {
            $cert = lookup_cert($ph1ent['certref']);

            if (empty($cert)) {
                log_msg(sprintf('Error: Invalid phase1 certificate reference for %s', $ph1ent['name']), LOG_ERR);
                continue;
            }

            $ph1keyfile = "/usr/local/etc/swanctl/private/cert-{$ph1ent['ikeid']}.key";
            @touch($ph1keyfile);
            @chmod($ph1keyfile, 0600);
            file_put_contents($ph1keyfile, base64_decode($cert['prv']));

            $ph1certfile = "/usr/local/etc/swanctl/x509/cert-{$ph1ent['ikeid']}.crt";
            @touch($ph1certfile);
            @chmod($ph1certfile, 0600);
            file_put_contents($ph1certfile, base64_decode($cert['crt']));
        }
    }
}

/**
 * Generate files for key pairs (e.g. RSA)
 */
function ipsec_write_keypairs()
{
    $paths = [
      'publicKey' => '/usr/local/etc/swanctl/pubkey',
      'privateKey' => '/usr/local/etc/swanctl/private'
    ];
    $filenames = [];
    foreach ((new \OPNsense\IPsec\IPsec())->keyPairs->keyPair->iterateItems() as $uuid => $keyPair) {
        foreach ($paths as $key => $path) {
            if (!empty((string)$keyPair->$key)) {
                $filename = "{$path}/{$uuid}.pem";
                @touch($filename);
                @chmod($filename, 0600);
                file_put_contents($filename, (string)$keyPair->$key);
                $filenames[] = $filename;
            }
        }
    }
    foreach ($paths as $path) {
        foreach (glob("{$path}/*.pem") as $filename) {
            if (!in_array($filename, $filenames)) {
                @unlink($filename);
            }
        }
    }
}

/**
 * build secrets structure
 */
function ipsec_write_secrets()
{
    global $config;
    $secrets = [];
    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];

    foreach ($a_phase1 as $seq => $ph1ent) {
        if (isset($ph1ent['disabled'])) {
            continue;
        }
        if (!empty($ph1ent['pre-shared-key'])) {
            $myid = isset($ph1ent['mobile']) ? ipsec_find_id($ph1ent, "local") : "";
            $peerid_data = isset($ph1ent['mobile']) ? "%any" : ipsec_find_id($ph1ent, "peer");

            if (!empty($peerid_data)) {
                if (!empty($myid)) {
                    $secrets['ike-p1-' . $seq] = [
                        'id-0' => $myid,
                        'id-1' => trim($peerid_data),
                        'secret' => '0s' . base64_encode(trim($ph1ent['pre-shared-key']))
                    ];
                } else {
                    $secrets['ike-p1-' . $seq] = [
                        'id-0' => trim($peerid_data),
                        'secret' => '0s' . base64_encode(trim($ph1ent['pre-shared-key']))
                    ];
                }
            }
        }
    }

    foreach ((new \OPNsense\IPsec\IPsec())->preSharedKeys->preSharedKey->iterateItems() as $uuid => $psk) {
        $keyType = $psk->keyType == 'PSK' ? 'ike' : strtolower($psk->keyType);
        $dataKey = "{$keyType}-{$uuid}";
        $secrets[$dataKey] = ['id-0' => (string)$psk->ident];
        if (!empty((string)$psk->remote_ident)) {
            $secrets[$dataKey]['id-1'] = (string)$psk->remote_ident;
        }
        $secrets[$dataKey]['secret'] = '0s' . base64_encode((string)$psk->Key);
    }

    return $secrets;
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

    /* configure VTI if needed */
    ipsec_configure_vti();
    /* setup "ping" cron job*/
    ipsec_setup_pinghosts();

    //  Prefer older IPsec SAs (advanced setting)
    if (isset($config['ipsec']['preferoldsa'])) {
        set_single_sysctl('net.key.preferred_oldsa', '-30');
    } else {
        set_single_sysctl('net.key.preferred_oldsa', '0');
    }

    $ipseccfg = $config['ipsec'] ?? [];
    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : [];
    $a_client = isset($config['ipsec']['client']) ? $config['ipsec']['client'] : [];

    if (!isset($ipseccfg['enable'])) {
        mwexec('/usr/local/etc/rc.d/strongswan onestop', true);
        mwexec('/sbin/ifconfig enc0 down', true);
        return;
    }

    if (get_single_sysctl('net.inet.ipsec.def_policy') == '') {
        mwexec('/sbin/kldload ipsec');
    }
    mwexec('/sbin/ifconfig enc0 up');

    service_log('Configuring IPsec VPN...', $verbose);

    /* cleanup legacy ipsec.conf bits but then recreate structure to mute charon complaints */
    mwexec('rm -rf /usr/local/etc/ipsec.d');
    foreach (['aacerts', 'acerts', 'cacerts', 'certs', 'crls', 'ocspcerts', 'private', 'reqs'] as $dir) {
        mkdir("/usr/local/etc/ipsec.d/{$dir}", 0664, true);
    }
    foreach (['/usr/local/etc/ipsec.conf', '/usr/local/etc/ipsec.secrets'] as $file) {
        /* unlink AND copy in case the sample files are not available */
        @unlink($file);
        @copy("{$file}.sample", $file);
    }

    ipsec_write_strongswan_conf();
    ipsec_write_cas();
    ipsec_write_certs();
    ipsec_write_keypairs();

    /* begin ipsec.conf, hook mvc configuration first */
    $swanctl = (new \OPNsense\IPsec\Swanctl())->getConfig();
    $swanctl['secrets'] = ipsec_write_secrets();

    if (!empty($config['ipsec']['passthrough_networks'])) {
        $swanctl['connections']['pass'] = [
            'remote_addrs' => '127.0.0.1',
            'unique' => 'replace',
            'children' => [
                'pass' => [
                        'local_ts' => $config['ipsec']['passthrough_networks'],
                        'remote_ts' => $config['ipsec']['passthrough_networks'],
                        'mode' => 'pass',
                        'start_action' => 'route'
                    ]
            ]
        ];
    }
    if (count($a_phase1)) {
        foreach ($a_phase1 as $ph1ent) {
            if (isset($ph1ent['disabled'])) {
                continue;
            }
            $ep = ipsec_get_phase1_src($ph1ent);
            if (empty($ep)) {
                continue;
            }

            $connection = [
                'unique' => !empty($ph1ent['unique']) ? $ph1ent['unique'] : 'replace',
                'aggressive' => ($ph1ent['mode'] ?? '') == 'aggressive' ? 'yes' : 'no',
                'version' => ($ph1ent['iketype'] ?? '') == 'ikev2' ? 2 : 1,
                'mobike' => !empty($ph1ent['mobike']) ? 'no' : 'yes',
                'local_addrs' => $ep,
                'local-0' => [
                    'id' => ipsec_find_id($ph1ent, "local")
                ],
                'remote-0' => [
                    'id' => ipsec_find_id($ph1ent, "peer") ?? '%any'
                ],
                'encap' => !empty($ph1ent['nat_traversal']) && $ph1ent['nat_traversal'] == 'force' ? 'yes' : 'no',
            ];
            if (!isset($ph1ent['mobile'])) {
                $connection['remote_addrs'] = $ph1ent['remote-gateway'];
                if (!empty($ph1ent['rightallowany'])) {
                    $connection['remote_addrs'] .= ',0.0.0.0/0,::/0';
                }
            } else {
                $connection['remote_addrs'] = '%any'; // default
            }
            if (!isset($ph1ent['reauth_enable']) && !empty($ph1ent['lifetime']) && !empty($ph1ent['margintime'])) {
                // XXX: should probably move to a gui setting for reauth_time and deprecate "Disable Reauth"
                $connection['reauth_time'] =  ($ph1ent['lifetime'] - $ph1ent['margintime']) . ' s';
            }
            if (isset($ph1ent['rekey_enable'])) {
                $connection['rekey_time'] = '0 s';
                $connection['reauth_time'] = '0 s';
                // XXX todo -> child connections.<conn>.children.<child>.life_time=0
            } elseif (!empty($ph1ent['margintime']) && !empty($ph1ent['rekeyfuzz']) && !empty($ph1ent['lifetime'])) {
                $connection['rekey_time'] = ($ph1ent['lifetime'] - $ph1ent['margintime']) . ' s';
                $connection['over_time'] = $ph1ent['margintime'] . ' s';
                $connection['rand_time'] = ($ph1ent['margintime'] * $ph1ent['rekeyfuzz']) . ' s';
            }
            if (!empty($ph1ent['dpd_delay']) && !empty($ph1ent['dpd_maxfail'])) {
                $connection['dpd_delay'] = "{$ph1ent['dpd_delay']} s";
                $dpdtimeout = $ph1ent['dpd_delay'] * ($ph1ent['dpd_maxfail'] + 1);
                $connection['dpd_timeout'] = "{$dpdtimeout} s";
            }
            if (!empty($ph1ent['keyingtries'])) {
                $connection['keyingtries'] = $ph1ent['keyingtries'] == -1 ? "0" : $ph1ent['keyingtries'];
            }

            if (isset($ph1ent['mobile'])) {
                $pools = [];
                if (!empty($a_client['pool_address'])) {
                    $pools[] = 'defaultv4';
                    if (!isset($swanctl['pools']['defaultv4'])) {
                        $swanctl['pools']['defaultv4'] = [
                            'addrs' => "{$a_client['pool_address']}/{$a_client['pool_netbits']}"
                        ];
                    }
                }
                if (!empty($a_client['pool_address_v6'])) {
                    $pools[] = 'defaultv6';
                    if (!isset($swanctl['pools']['defaultv6'])) {
                        $swanctl['pools']['defaultv6'] = [
                            'addrs' => "{$a_client['pool_address_v6']}/{$a_client['pool_netbits_v6']}"
                        ];
                    }
                }
                if (!empty($pools)) {
                    $connection['pools'] = join(',', $pools);
                }
            }

            switch ($ph1ent['authentication_method']) {
                case 'eap-tls':
                    $connection['local-0']['auth'] = 'eap-tls';
                    $connection['remote-0']['auth'] = 'eap-tls';
                    break;
                case 'psk_eap-tls':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'eap-tls';
                    $connection['remote-0']['eap_id'] = '%any';
                    break;
                case 'eap-mschapv2':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'eap-mschapv2';
                    $connection['remote-0']['eap_id'] = '%any';
                    break;
                case 'rsa_eap-mschapv2':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'pubkey';
                    $connection['remote-1']['eap_id'] = '%any';
                    $connection['remote-1']['auth'] = 'eap-mschapv2';
                    break;
                case 'eap-radius':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'eap-radius';
                    $connection['remote-0']['eap_id'] = '%any';
                    $connection['send_certreq'] = 'no';
                    if (!isset($connection['pools'])) {
                        $connection['pools'] = 'radius';
                    }
                    break;
                case 'xauth_rsa_server':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'pubkey';
                    $connection['remote-1']['auth'] = 'xauth-pam';
                    break;
                case 'xauth_psk_server':
                    $connection['local-0']['auth'] = 'psk';
                    $connection['remote-0']['auth'] = 'psk';
                    $connection['remote-1']['auth'] = 'xauth-pam';
                    break;
                case 'pre_shared_key':
                    $connection['local-0']['auth'] = 'psk';
                    $connection['remote-0']['auth'] = 'psk';
                    break;
                case 'rsasig':
                case 'pubkey':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'pubkey';
                    break;
                case 'hybrid_rsa_server':
                    $connection['local-0']['auth'] = 'pubkey';
                    $connection['remote-0']['auth'] = 'xauth';
                    break;
            }

            if (!empty($ph1ent['certref'])) {
                $connection['local-0']['certs'] = "cert-{$ph1ent['ikeid']}.crt";
                $connection['send_cert'] = 'always';
            }
            if (!empty($ph1ent['caref'])) {
                $ca = lookup_ca($ph1ent['caref']);
                if (!empty($ca)) {
                    $cert = base64_decode($ca['crt']);
                    $x509cert = openssl_x509_parse(openssl_x509_read($cert));
                    if (is_array($x509cert) && isset($x509cert['hash'])) {
                        $connection['remote-0']['cacerts'] = "{$x509cert['hash']}.0.crt";
                    }
                }
            }
            if (!empty($ph1ent['local-kpref'])) {
                $connection['local-0']['pubkeys'] = "{$ph1ent['local-kpref']}.pem";
            }
            if (!empty($ph1ent['peer-kpref'])) {
                $connection['remote-0']['pubkeys'] = "{$ph1ent['peer-kpref']}.pem";
            }
            if (!empty($ph1ent['encryption-algorithm']['name']) && !empty($ph1ent['hash-algorithm'])) {
                $list = [];
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
                $connection['proposals'] = implode(',', array_reverse($list));
            }

            // XXX: should enforce explicit choice in the gui, it's also a phase 2 property in reality
            if (!empty($ph1ent['auto']) && $ph1ent['auto'] != 'add') {
                $start_action = $ph1ent['auto'];
            } elseif (isset($ph1ent['mobile']) || ($ph1ent['auto'] ?? '')  == 'add') {
                $start_action = 'none';
            } elseif (!empty($config['ipsec']['auto_routes_disable'])) {
                $start_action = 'start';
            } else {
                $start_action = 'trap';
            }

            $parsed_phase2 = ipsec_parse_phase2($ph1ent['ikeid']);

            $base_child_conf = [
                'start_action' => $start_action,
                'policies' => empty($ph1ent['noinstallpolicy']) ? 'yes' : 'no',
                'mode' => $parsed_phase2['type'],
                'sha256_96' => isset($ph1ent['sha256_96']) ? 'yes' : 'no'
            ];
            if (!empty($ph1ent['inactivity_timeout'])) {
                $base_child_conf['inactivity'] = "{$ph1ent['inactivity_timeout']} s";
            }
            if (!empty($ph1ent['closeaction']) && in_array($ph1ent['closeaction'], ['hold', 'restart'])) {
                $base_child_conf['close_action'] = $ph1ent['closeaction'] == 'hold' ? 'trap' : 'start';
            }
            if (!empty($ph1ent['dpd_delay']) && !empty($ph1ent['dpd_maxfail'])) {
                if (empty($ph1ent['dpd_action']) && in_array($start_action, ['trap', 'start'])) {
                    $base_child_conf['dpd_action'] = 'start';
                } elseif (!empty($ph1ent['dpd_action']) && $ph1ent['dpd_action'] == 'restart') {
                    $base_child_conf['dpd_action'] = 'start';
                }
            }

            if (
                isset($ph1ent['tunnel_isolation'])
                || (!isset($ph1ent['mobile']) && ($ph1ent['iketype'] ?? 'ikev1') == 'ikev1')
            ) {
                $this_conn = $connection;
                $this_conn['children'] = [];
                for ($idx = 0; $idx < count($parsed_phase2['local_ts']); ++$idx) {
                    $child_id = sprintf('con%s-%03d', $ph1ent['ikeid'], $idx);
                    $this_conn['children'][$child_id] = $base_child_conf;
                    $this_conn['children'][$child_id]['local_ts'] = $parsed_phase2['local_ts'][$idx];
                    $this_conn['children'][$child_id]['remote_ts'] = $parsed_phase2['remote_ts'][$idx];
                    if (!isset($ph1ent['mobile'])) {
                        $this_conn['children'][$child_id]['reqid'] = $parsed_phase2['reqids'][$idx];
                    }
                    foreach (['esp_proposals', 'ah_proposals', 'life_time', 'rekey_time', 'rand_time'] as $fieldname) {
                        if (isset($parsed_phase2[$fieldname][$idx]) && $parsed_phase2[$fieldname][$idx] != null) {
                            if (is_array($parsed_phase2[$fieldname][$idx])) {
                                $this_conn['children'][$child_id][$fieldname] = join(
                                    ',',
                                    $parsed_phase2[$fieldname][$idx]
                                );
                            } else {
                                $this_conn['children'][$child_id][$fieldname] = $parsed_phase2[$fieldname][$idx] . " s";
                            }
                        }
                    }
                }
                $swanctl['connections']["con{$ph1ent['ikeid']}"] = $this_conn;
            } else {
                $this_conn = $connection;
                $this_connid = "con{$ph1ent['ikeid']}";
                $this_conn['children'][$this_connid] = $base_child_conf;
                $this_conn['children'][$this_connid]['local_ts'] = join(',', $parsed_phase2['local_ts']);
                $this_conn['children'][$this_connid]['remote_ts'] = join(',', $parsed_phase2['remote_ts']);
                if (!empty($parsed_phase2['reqids']) && !isset($ph1ent['mobile'])) {
                    $this_conn['children'][$this_connid]['reqid'] = $parsed_phase2['reqids'][0];
                }
                if (!empty($parsed_phase2['esp_proposals'])) {
                    $this_conn['children'][$this_connid]['esp_proposals'] = join(',', $parsed_phase2['esp_proposals']);
                }
                if (!empty($parsed_phase2['ah_proposals'])) {
                    $this_conn['children'][$this_connid]['ah_proposals'] = join(',', $parsed_phase2['ah_proposals']);
                }
                foreach (['life_time', 'rekey_time', 'rand_time'] as $fieldname) {
                    $values = array_diff($parsed_phase2[$fieldname], [null]);
                    if (!empty($values)) {
                        $this_conn['children'][$this_connid][$fieldname] = min($values) . " s";
                    }
                }
                $swanctl['connections'][$this_connid] = $this_conn;
            }
        }
    }

    $swanctltxt = "# This file is automatically generated. Do not edit\n";
    $swanctltxt .= generate_strongswan_conf($swanctl);
    $swanctltxt .= "# Include config snippets\n";
    $swanctltxt .= "include conf.d/*.conf\n";

    file_put_contents("/usr/local/etc/swanctl/swanctl.conf", $swanctltxt);

    /* mange process */
    if (isvalidpid('/var/run/charon.pid')) {
        /* Update configuration changes */
        mwexec_bg('/usr/local/etc/rc.d/strongswan onereload', false);
    } else {
        mwexec_bg("/usr/local/etc/rc.d/strongswan onestart", false);
    }

    /* load manually defined SPD entries */
    ipsec_configure_spd();

    service_log("done.\n", $verbose);

    /* reload routes on all attached VTI devices */
    plugins_configure('route_reload', $verbose, [array_keys(array_merge(ipsec_get_configured_vtis(), (new \OPNsense\IPsec\Swanctl())->getVtiDevices()))]);
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

    $a_phase1 = isset($config['ipsec']['phase1']) ? $config['ipsec']['phase1'] : [];
    $a_phase2 = isset($config['ipsec']['phase2']) ? $config['ipsec']['phase2'] : [];
    $configured_intf = [];

    foreach ($a_phase1 as $ph1ent) {
        if (empty($ph1ent['disabled'])) {
            $phase2items = [];
            $phase2reqids = [];

            foreach ($a_phase2 as $ph2ent) {
                if (
                    isset($ph2ent['mode']) && $ph2ent['mode'] == 'route-based' &&
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
                } elseif ((!isset($ph1ent['mobile']) && $ph1ent['iketype'] == 'ikev1') || isset($ph1ent['tunnel_isolation'])) {
                    // isolated tunnels, every tunnel it's own reqid
                    $reqid = $phase2['reqid'];
                    $descr = empty($phase2['descr']) ? $ph1ent['descr'] : $phase2['descr'];
                } else {
                    // use smallest reqid within tunnel
                    $reqid = min($phase2reqids);
                    $descr = $ph1ent['descr'] ?? '';
                }
                $intfnm = sprintf("ipsec%s", $reqid);
                if (empty($configured_intf[$intfnm])) {
                    $configured_intf[$intfnm] = ['reqid' => $reqid];
                    $configured_intf[$intfnm]['local'] = ipsec_get_phase1_src($ph1ent);
                    $configured_intf[$intfnm]['remote'] = $ph1ent['remote-gateway'];
                    $configured_intf[$intfnm]['descr'] = $descr;
                    $configured_intf[$intfnm]['networks'] = [];
                }

                $inet = is_ipaddrv6($phase2['tunnel_local']) ? 'inet6' : 'inet';

                $configured_intf[$intfnm]['networks'][] = [
                    'inet' => $inet,
                    'mask' => find_smallest_cidr([$phase2['tunnel_local'], $phase2['tunnel_remote']], $inet),
                    'tunnel_local' => $phase2['tunnel_local'],
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
function ipsec_configure_vti($verbose = false, $device = null)
{
    // query planned and configured interfaces
    $configured_intf = array_merge(ipsec_get_configured_vtis(), (new \OPNsense\IPsec\Swanctl())->getVtiDevices());
    $current_interfaces = [];

    foreach (legacy_interfaces_details() as $intf => $intf_details) {
        if (strpos($intf, 'ipsec') === 0) {
            $current_interfaces[$intf] = $intf_details;
        }
    }

    service_log(sprintf('Creating IPsec VTI instance%s...', empty($device) ? 's' : " {$device}"), $verbose);

    // drop changed or not existing interfaces and tunnel endpoints
    foreach ($current_interfaces as $intf => $intf_details) {
        if ($device !== null && $device != $intf) {
            continue;
        }

        if (empty($configured_intf[$intf])) {
            log_msg(sprintf("destroy interface %s", $intf), LOG_DEBUG);
            legacy_interface_destroy($intf);
            unset($current_interfaces[$intf]);
        } else {
            foreach (['ipv4', 'ipv6'] as $proto) {
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
                            log_msg(sprintf(
                                "remove tunnel %s %s from interface %s",
                                $addr['ipaddr'],
                                $addr['endpoint'],
                                $intf
                            ), LOG_DEBUG);
                            mwexecf('/sbin/ifconfig %s %s %s delete', [
                                $intf, $proto == 'ipv6' ? 'inet6' : 'inet',  $addr['ipaddr'], $addr['endpoint']
                            ]);
                        }
                    }
                }
            }
        }
    }

    // configure new interfaces and tunnels
    foreach ($configured_intf as $intf => $intf_details) {
        if ($device !== null && $device != $intf) {
            continue;
        }

        // create required interfaces
        if (empty($current_interfaces[$intf])) {
            // prevent ipsec vti interface to hit 32768 limit (create numbered, rename and attach afterwards)
            if (legacy_interface_create('ipsec', $intf) === null) {
                break;
            }
        }
        // [re]initialise if_ipsec
        mwexecf('/sbin/ifconfig %s reqid %s', [$intf, $intf_details['reqid']]);
        if (!empty($intf_details['local']) && !empty($intf_details['remote'])) {
            mwexecf('/sbin/ifconfig %s %s tunnel %s %s up', [
                $intf,
                is_ipaddrv6($intf_details['local']) ? 'inet6' : 'inet',
                $intf_details['local'],
                $intf_details['remote']
            ]);
        }
        // create new tunnel endpoints
        foreach ($intf_details['networks'] as $endpoint) {
            if (!empty($current_interfaces[$intf])) {
                $already_configured = $current_interfaces[$intf][$endpoint['inet'] == 'inet6' ? 'ipv6' : 'ipv4'];
            } else {
                $already_configured = [];
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
                    mwexecf('/sbin/ifconfig %s %s %s %s', [
                        $intf, $endpoint['inet'],  sprintf("%s/%s", $endpoint['tunnel_local'], $endpoint['mask']),
                        $endpoint['tunnel_remote']
                    ]);
                } else {
                    // XXX: don't specify a tunnel endpoint for ipv6, although this looks like an illogical
                    //      construction, a netmask seems to be a requirement for some ipv6 consumers (frr)
                    mwexecf('/sbin/ifconfig %s %s %s', [
                        $intf, $endpoint['inet'],  sprintf("%s/%s", $endpoint['tunnel_local'], $endpoint['mask'])
                    ]);
                }
            }
        }
    }

    service_log("done.\n", $verbose);
}

function ipsec_configure_device($device)
{
    ipsec_configure_vti(false, $device);
}
