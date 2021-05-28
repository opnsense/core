<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

function filter_core_bootstrap($fw)
{
    global $config;
    // set defaults
    $filter_rule_defaults = array();
    $filter_rule_defaults['pass'] = array(
      "type" => "pass",
      "log" => !isset($config['syslog']['nologdefaultpass']),
      "disablereplyto" => 1 // don't generate "reply-to" tags on internal rules by default
    );
    $filter_rule_defaults['block'] = array(
      "type" => "block",
      "log" => !isset($config['syslog']['nologdefaultblock']),
      "disablereplyto" => 1 // don't generate "reply-to" tags on internal rules by default
    );

    // setup system filter rules
    filter_core_rules_system($fw, $filter_rule_defaults);
}


/**
 * Initialize firewall plugin system with interfaces and gateways
 * @return \OPNsense\Firewall\Plugin
 */
function filter_core_get_initialized_plugin_system()
{
    $fw = new \OPNsense\Firewall\Plugin();
    $ifdetails = legacy_interfaces_details();
    $gateways = new \OPNsense\Routing\Gateways($ifdetails);
    $cnfint = legacy_config_get_interfaces(array("enable" => true));
    foreach ($cnfint as $key => &$value) {
        // to set "reply-to" we need to know the gateway for our interface, let's collect it here and pass it on to
        // setInterfaceMapping()
        if (!is_ipaddrv4($value['ipaddr']) || (!empty($value['gateway']) && $value['gateway'] != 'none')) {
            $value['gateway'] = $gateways->getInterfaceGateway($key, "inet", true);
        } else {
            $value['gateway'] = null;
        }
        if (!is_ipaddrv6($value['ipaddrv6']) || (!empty($value['gatewayv6']) && $value['gatewayv6'] != 'none')) {
            $value['gatewayv6'] = $gateways->getInterfaceGateway($key, "inet6", true);
        } else {
            $value['gatewayv6'] = null;
        }
        // In some cases we need to know if there currently are addresses configured on an interface, we pass
        // the relevant ifconfig data to our interfacemapping (prevents "could not parse host specification" on load)
        if (!empty($ifdetails[$value['if']])) {
            $value['ifconfig'] = array();
            $value['ifconfig']['ipv4'] = $ifdetails[$value['if']]['ipv4'];
            $value['ifconfig']['ipv6'] = $ifdetails[$value['if']]['ipv6'];
        }
    }
    // init interfaces and gateways
    $fw->setInterfaceMapping($cnfint);
    $fw->setGateways($gateways);
    $fw->setIfconfigDetails($ifdetails);
    $fw->setGatewayGroups($gateways->getGroups(return_gateways_status()));
    return $fw;
}

function filter_core_get_antilockout()
{
    global $config;

    $lockout_ports = array();
    $lockout_if = null;

    if (isset($config['system']['webgui']['noantilockout'])) {
        return array();
    }

    if (!empty($config['interfaces']['lan']['if'])) {
        $lockout_if = 'lan';
    } elseif (!empty($config['interfaces']['opt1']['if'])) {
        $lockout_if = 'opt1';
    } elseif (count(get_configured_interface_with_descr()) == 1 && !empty($config['interfaces']['wan']['if'])) {
        $lockout_if = 'wan';
    } else {
        return array();
    }

    /*
     * XXX Some issues here:
     *
     * 1. Why does the webgui nolockout control the ssh lockout?
     * 2. Both webgui and openssh are plugins, their code should
     *    reside in the respective plugins.inc.d file once anti-
     *    lockout is fully pluggable.
     * 3. OpenSSH opens the port when install media is detected
     *    and no ssh has ever been configured. This is in line
     *    with how the plugin behaves, but probably looks odd.
     */
    if (empty($config['system']['webgui']['port'])) {
        $lockout_ports[] = $config['system']['webgui']['protocol'] == 'https' ? '443' : '80';
    } else {
        $lockout_ports[] = $config['system']['webgui']['port'];
    }
    if ($config['system']['webgui']['protocol'] == 'https' && !isset($config['system']['webgui']['disablehttpredirect'])) {
        $lockout_ports[] = '80';
    }
    if (
        isset($config['system']['ssh']['enabled']) ||
        (!isset($config['system']['ssh']['noauto']) && is_install_media() && is_process_running('sshd'))
    ) {
        $lockout_ports[] = empty($config['system']['ssh']['port']) ? '22' : $config['system']['ssh']['port'];
    }

    if (!count($lockout_ports)) {
        return array();
    }

    sort($lockout_ports);

    /* return a convenient one-entry array to iterate over for our callers */
    return array($lockout_if => $lockout_ports);
}

/**
 * recursively collect port alias(es) contents
 * @param string|null $aliasname alias name, or null to fetch all aliases
 * @param array $aliases aliases already parsed (prevent deadlock)
 * @return array containing ports
 * @throws \OPNsense\Base\ModelException
 */
function filter_core_get_port_alias($aliasname, $aliases = array(), $aliasObject = null)
{
    $response = array();
    $aliases[] = $aliasname;
    $aliasObject = $aliasObject == null ? (new \OPNsense\Firewall\Alias()) : $aliasObject;
    foreach ($aliasObject->aliasIterator() as $aliased) {
        if ($aliasname == $aliased['name'] && $aliased['type'] == 'port') {
            foreach (explode("\n", $aliased['content']) as $address) {
                if (is_alias($address)) {
                    if (!in_array($address, $aliases)) {
                        foreach (filter_core_get_port_alias($address, $aliases, $aliasObject) as $port) {
                            if (!in_array($port, $response)) {
                                $response[] = $port;
                            }
                        }
                    }
                } elseif ((is_port($address) || is_portrange($address)) && !in_array($address, $response)) {
                    $response[] = $address;
                }
            }
        }
    }
    return $response;
}


/**
 * Collect vpn networks for outbound rules
 */
function filter_core_get_default_nat_outbound_networks()
{
    global $config;
    $result = array("127.0.0.0/8");
    // Add openvpn networks
    foreach (array('openvpn-server', 'openvpn-client') as $section) {
        if (!empty($config['openvpn'][$section])) {
            foreach ($config['openvpn'][$section] as $ovpn) {
                if (!isset($ovpn['disable']) && !empty($ovpn['tunnel_network'])) {
                    $result[] = $ovpn['tunnel_network'];
                }
            }
        }
    }
    // Add ipsec network pool if specified
    if (!empty($config['ipsec']['client']['pool_address']) && !empty($config['ipsec']['client']['pool_netbits'])) {
        $result[] = "{$config['ipsec']['client']['pool_address']}/{$config['ipsec']['client']['pool_netbits']}";
    }

    return $result;
}

/**
 *  core system rules
 */
function filter_core_rules_system($fw, $defaults)
{
    global $config;

    // block All IPv6 except loopback traffic
    $fw->registerFilterRule(
        1,
        array('interface' => 'lo0', 'ipprotocol' => 'inet6', 'disabled' => isset($config['system']['ipv6allow']),
          'descr' => 'Pass all loopback IPv6', '#ref' => 'system_advanced_firewall.php#ipv6allow'),
        $defaults['pass']
    );
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet6','descr' => 'Block all IPv6', 'disabled' => isset($config['system']['ipv6allow']),
            '#ref' => 'system_advanced_firewall.php#ipv6allow'),
        $defaults['block']
    );

    // default Deny rule (when no other rules match)
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet46', 'descr' => 'Default deny rule', 'quick' => false),
        $defaults['block']
    );

    // IPv6 ICMP requirements
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '1,2,135,136',
            'statetype' => 'keep', 'descr' => 'IPv6 RFC4890 requirements (ICMP)'),
        $defaults['pass']
    );
    // Allow only bare essential icmpv6 packets
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '128,129,133,134,135,136',
            'statetype' => 'keep', 'descr' => 'IPv6 RFC4890 requirements (ICMP)', 'from' => '(self)',
            'to' => 'fe80::/10,ff02::/16', 'direction' => 'out' ),
        $defaults['pass']
    );
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '128,133,134,135,136',
            'statetype' => 'keep', 'descr' => 'IPv6 RFC4890 requirements (ICMP)', 'from' => 'fe80::/10',
            'to' => 'fe80::/10,ff02::/16', 'direction' => 'in' ),
        $defaults['pass']
    );
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '128,133,134,135,136',
            'statetype' => 'keep', 'descr' => 'IPv6 RFC4890 requirements (ICMP)', 'from' => 'ff02::/16',
            'to' => 'fe80::/10', 'direction' => 'in' ),
        $defaults['pass']
    );
    $fw->registerFilterRule(
        1,
        array('ipprotocol' => 'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '128,133,134,135,136',
            'statetype' => 'keep', 'descr' => 'IPv6 RFC4890 requirements (ICMP)', 'from' => '::',
            'to' => 'ff02::/16', 'direction' => 'in' ),
        $defaults['pass']
    );
    // block all targetting port 0
    foreach (array('from_port', 'to_port') as $target) {
        $fw->registerFilterRule(
            1,
            array('ipprotocol' => 'inet46', 'protocol' => 'tcp/udp', $target => '0',
                'descr' => 'block all targetting port 0'),
            $defaults['block']
        );
    }
    // CARP defaults
    $carp_disabled = empty($config['hasync']) && empty($config['virtualip']['vip']);
    $fw->registerFilterRule(
        1,
        array('protocol' => 'carp', 'descr' => 'CARP defaults', '#ref' => 'system_hasync.php',
            'from' => '(self)', 'direction' => 'in', 'disabled' => !$carp_disabled),
        $defaults['block']
    );
    $fw->registerFilterRule(
        1,
        array('protocol' => 'carp', 'direction' => 'any', 'descr' => 'CARP defaults', '#ref' => 'firewall_virtual_ip.php'),
        $defaults['pass']
    );

    // Lockout rules
    $fw->registerFilterRule(
        1,
        array('protocol' => 'tcp', 'from' => '<sshlockout>', 'to' => '(self)' , 'descr' => 'sshlockout', 'direction' => 'in',
        'to_port' => !empty($config['system']['ssh']['port']) ? $config['system']['ssh']['port'] : 22),
        $defaults['block']
    );
    $webport = '443';
    if (!empty($config['system']['webgui']['port'])) {
        $webport = $config['system']['webgui']['port'];
    } elseif ($config['system']['webgui']['protocol'] == 'http') {
        $webport = '80';
    }
    $fw->registerFilterRule(
        1,
        array('protocol' => 'tcp', 'from' => '<sshlockout>', 'to' => '(self)' , 'descr' => 'sshlockout',
        'direction' => 'in','to_port' => $webport),
        $defaults['block']
    );

    // block all in alias <virusprot>
    $fw->registerFilterRule(1, array('from' => '<virusprot>', 'descr' => 'virusprot overload table'), $defaults['block']);

    // block bogons and private nets
    $bogontmpl = array('type' => 'block', 'log' => !isset($config['syslog']['nologbogons']), 'disablereplyto' => 1);
    $privtmpl = array('type' => 'block', 'log' => !isset($config['syslog']['nologprivatenets']),
      'from' => '10.0.0.0/8,127.0.0.0/8,100.64.0.0/10,172.16.0.0/12,192.168.0.0/16',
      'disablereplyto' => 1);
    foreach ($fw->getInterfaceMapping() as $intf => $intfinfo) {
        $fw->registerFilterRule(
            5,
            array('from' => "<bogons>", 'direction' => 'in', 'interface' => $intf, 'ipprotocol' => 'inet',
            'descr' => "Block bogon IPv4 networks from " . $intfinfo['descr'],
            '#ref' => "interfaces.php?if=" . $intf . "#blockbogons",
            'disabled' => !isset($intfinfo['blockbogons'])),
            $bogontmpl
        );
        $fw->registerFilterRule(
            5,
            array('from' => "<bogonsv6>", 'direction' => 'in', 'interface' => $intf, 'ipprotocol' => 'inet6',
            'disabled' => !isset($config['system']['ipv6allow']) || !isset($intfinfo['blockbogons']),
            '#ref' => "interfaces.php?if=" . $intf . "#blockbogons",
            'descr' => "Block bogon IPv6 networks from " . $intfinfo['descr']),
            $bogontmpl
        );
        $fw->registerFilterRule(
            5,
            array('direction' => 'in', 'interface' => $intf, 'ipprotocol' => 'inet',
            '#ref' => "interfaces.php?if=" . $intf . "#blockpriv",
            'descr' => "Block private networks from " . $intfinfo['descr'],
            'disabled' => !isset($intfinfo['blockpriv'])),
            $privtmpl
        );
        $fw->registerFilterRule(
            5,
            array('direction' => 'in', 'interface' => $intf, 'ipprotocol' => 'inet6',
            '#ref' => "interfaces.php?if=" . $intf . "#blockpriv",
            'descr' => "Block private networks from " . $intfinfo['descr'], 'from' => 'fc00::/7',
            'disabled' => !isset($intfinfo['blockpriv'])),
            $privtmpl
        );
    }

    // interface configuration per type
    foreach ($fw->getInterfaceMapping() as $intf => $intfinfo) {
        // allow DHCPv6 client out, before adding bogons (sequence 1, bogons @ 5)
        if (isset($config['system']['ipv6allow']) && in_array($intfinfo['ipaddrv6'], array("slaac","dhcp6"))) {
            $fw->registerFilterRule(
                1,
                array('protocol' => 'udp', 'from' => 'fe80::/10', 'from_port' => 546, 'to' => 'fe80::/10',
                    'interface' => $intf, 'to_port' => 546, 'descr' => 'allow dhcpv6 client in ' . $intfinfo['descr'],
                    '#ref' => 'system_advanced_firewall.php#ipv6allow'),
                $defaults['pass']
            );
            $fw->registerFilterRule(
                1,
                array('protocol' => 'udp', 'from_port' => 547,'to_port' => 546, 'direction' => 'in',
                    'interface' => $intf, 'descr' => 'allow dhcpv6 client in ' . $intfinfo['descr'],
                    '#ref' => 'system_advanced_firewall.php#ipv6allow'),
                $defaults['pass']
            );
            $dhcpv6_opts = array(
                'descr' => 'allow dhcpv6 client in ' . $intfinfo['descr'],
                '#ref' => 'system_advanced_firewall.php#ipv6allow',
                'direction' => 'out',
                'interface' => $intf,
                'protocol' => 'udp',
                'from_port' => 546,
                'to_port' => 547,
            );
            if (isset($intfinfo['dhcp6vlanprio'])) {
                 $dhcpv6_opts['set-prio'] = $intfinfo['dhcp6vlanprio'];
            }
            $fw->registerFilterRule(1, $dhcpv6_opts, $defaults['pass']);
        }
        // IPv4
        switch (empty($intfinfo['ipaddr']) ? "" : $intfinfo['ipaddr']) {
            case "pptp":
                $fw->registerFilterRule(
                    5,
                    array('protocol' => 'tcp','to_port' => 1723, 'direction' => 'in', 'statetype' => 'modulate', 'quick' => false,
                        '#ref' => "interfaces.php?if=" . $intf . "#type",
                        'interface' => $intf, 'flags' => 'S/SA', 'descr' => 'allow PPTP client on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                $fw->registerFilterRule(
                    5,
                    array('protocol' => 'gre', 'direction' => 'in', 'statetype' => 'keep', 'quick' => false,
                        '#ref' => "interfaces.php?if=" . $intf . "#type",
                        'interface' => $intf, 'descr' => 'allow PPTP client on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                break;
            case "dhcp":
                $fw->registerFilterRule(
                    5,
                    array('protocol' => 'udp', 'direction' => 'in', 'quick' => false, 'from_port' => 67, 'to_port' => 68,
                        '#ref' => "interfaces.php?if=" . $intf . "#type",
                        'interface' => $intf, 'descr' => 'allow DHCP client on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                $fw->registerFilterRule(
                    5,
                    array('protocol' => 'udp', 'direction' => 'out', 'quick' => false, 'from_port' => 68, 'to_port' => 67,
                        '#ref' => "interfaces.php?if=" . $intf . "#type",
                        'interface' => $intf, 'descr' => 'allow DHCP client on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                break;
            default:
                if (isset($config['dhcpd'][$intf]['enable'])) {
                    $fw->registerFilterRule(
                        5,
                        array('protocol' => 'udp', 'direction' => 'in', 'from_port' => 68, 'to' => '255.255.255.255',
                            '#ref' => "services_dhcp.php?if=" . $intf . "#enable",
                            'to_port' => 67, 'interface' => $intf, 'descr' => 'allow access to DHCP server'),
                        $defaults['pass']
                    );
                    $fw->registerFilterRule(
                        5,
                        array('protocol' => 'udp', 'direction' => 'in', 'from_port' => 68, 'to' => '(self)',
                            '#ref' => "services_dhcp.php?if=" . $intf . "#enable",
                            'to_port' => 67, 'interface' => $intf, 'descr' => 'allow access to DHCP server'),
                        $defaults['pass']
                    );
                    $fw->registerFilterRule(
                        5,
                        array('protocol' => 'udp', 'direction' => 'out', 'from_port' => 67, 'from' => '(self)',
                            '#ref' => "services_dhcp.php?if=" . $intf . "#enable",
                            'to_port' => 68, 'interface' => $intf, 'descr' => 'allow access to DHCP server'),
                        $defaults['pass']
                    );
                    if (!empty($config['dhcpd'][$intf]['failover_peerip'])) {
                        $fw->registerFilterRule(
                            5,
                            array('protocol' => 'tcp/udp', 'direction' => 'in', 'to' => '(self)', 'to_port' => '519,520',
                                '#ref' => "services_dhcp.php?if=" . $intf . "#failover_peerip",
                                'from' => $config['dhcpd'][$intf]['failover_peerip'],
                                'interface' => $intf, 'descr' => 'allow access to DHCP failover'),
                            $defaults['pass']
                        );
                    }
                }
                break;
        }
        // IPv6
        switch (isset($intfinfo['ipaddrv6']) ? $intfinfo['ipaddrv6'] : null) {
            case "6rd":
                $fw->registerFilterRule(
                    5,
                    array('protocol' => '41', 'direction' => 'in', 'from' => $config['interfaces'][$intf]['gateway-6rd'],
                        '#ref' => "interfaces.php?if=" . $intf . "#type6",
                        'quick' => false, 'interface' => $intf, 'descr' => 'Allow 6in4 traffic in for 6rd on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                $fw->registerFilterRule(
                    5,
                    array('protocol' => '41', 'direction' => 'out', 'to' => $config['interfaces'][$intf]['gateway-6rd'],
                        '#ref' => "interfaces.php?if=" . $intf . "#type6",
                        'quick' => false, 'interface' => $intf, 'descr' => 'Allow 6in4 traffic out for 6rd on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                break;
            case "6to4":
                $fw->registerFilterRule(
                    5,
                    array('protocol' => '41', 'direction' => 'in', 'to' => '(self)','interface' => $intf,
                        '#ref' => "interfaces.php?if=" . $intf . "#type6",
                        'quick' => false, 'descr' => 'Allow 6in4 traffic in for 6to4 on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                $fw->registerFilterRule(
                    5,
                    array('protocol' => '41', 'direction' => 'out', 'from' => '(self)','interface' => $intf,
                        '#ref' => "interfaces.php?if=" . $intf . "#type6",
                        'quick' => false, 'descr' => 'Allow 6in4 traffic out for 6to4 on ' . $intfinfo['descr']),
                    $defaults['pass']
                );
                break;
            default:
                $dhcpdv6_enabled = isset($config['dhcpdv6'][$intf]['enable']);
                $track6_enabled = isset($intfinfo['track6-interface']);
                $dhcrelay6_interfaces = array();
                if (!empty($config['dhcrelay6']['interface']) && isset($config['dhcrelay6']['enable'])) {
                    $dhcrelay6_interfaces = explode(',', $config['dhcrelay6']['interface']);
                }
                if ($dhcpdv6_enabled || $track6_enabled || in_array($intf, $dhcrelay6_interfaces)) {
                    $fw->registerFilterRule(
                        1,
                        array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'fe80::/10', 'to' => 'fe80::/10,ff02::/16',
                            'to_port' => 546, 'interface' => $intf,
                            'descr' => 'allow access to DHCPv6 server on ' . $intfinfo['descr']),
                        $defaults['pass']
                    );
                      $fw->registerFilterRule(
                          1,
                          array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'fe80::/10', 'to' => 'ff02::/16',
                            'to_port' => 547, 'interface' => $intf,
                            'descr' => 'allow access to DHCPv6 server on ' . $intfinfo['descr']),
                          $defaults['pass']
                      );
                      $fw->registerFilterRule(
                          1,
                          array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'ff02::/16', 'to' => 'fe80::/10',
                            'to_port' => 547, 'interface' => $intf,
                            'descr' => 'allow access to DHCPv6 server on ' . $intfinfo['descr']),
                          $defaults['pass']
                      );
                      $fw->registerFilterRule(
                          1,
                          array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'fe80::/10', 'to' => '(self)',
                            'to_port' => 546, 'interface' => $intf, 'direction' => 'in',
                            'descr' => 'allow access to DHCPv6 server on ' . $intfinfo['descr']),
                          $defaults['pass']
                      );
                      $fw->registerFilterRule(
                          1,
                          array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => '(self)', 'to' => 'fe80::/10',
                            'from_port' => 547, 'interface' => $intf, 'direction' => 'out',
                            'descr' => 'allow access to DHCPv6 server on ' . $intfinfo['descr']),
                          $defaults['pass']
                      );
                }
                break;
        }
    }
    // loopback
    $fw->registerFilterRule(5, array('interface' => 'lo0', 'descr' => 'pass loopback'), $defaults['pass']);
    // out from this Firewall
    $fw->registerFilterRule(
        5,
        array('direction' => 'out', 'statetype' => 'keep', 'allowopts' => true,
        'quick' => false, "descr" => "let out anything from firewall host itself"),
        $defaults['pass']
    );
    // ipsec
    if (!empty(iterator_to_array($fw->getInterfaceMapping())['enc0'])) {
        $fw->registerFilterRule(
            5,
            array('direction' => 'out', 'statetype' => 'keep', 'quick' => false, 'interface' => 'enc0',
                '#ref' => 'vpn_ipsec.php#enable',
                'descr' => 'IPsec internal host to host'),
            $defaults['pass']
        );
    }

    foreach (filter_core_get_antilockout() as $lockoutif => $lockoutprts) {
        $fw->registerFilterRule(
            5,
            array(
                'direction' => 'in',
                'interface' => $lockoutif,
                'statetype' => 'keep',
                'protocol' => 'tcp',
                'to' => '(self)',
                'to_port' => implode(' ', $lockoutprts),
                'descr' => 'anti-lockout rule',
                '#ref' => 'system_advanced_firewall.php#noantilockout'
            ),
            $defaults['pass']
        );
    }

    // TODO: move to pptpd plugin when filter.lib.inc is part of the standard release
    $pptpdcfg = $config['pptpd'];
    if (isset($pptpdcfg['mode']) && $pptpdcfg['mode'] == 'server') {
        $fw->registerFilterRule(
            5,
            array('direction' => 'in', 'interface' => 'wan', 'statetype' => 'modulate','protocol' => 'tcp',
                'to' => '(self)', 'to_port' => '1723', 'quick' => false, 'descr' => 'allow pptpd'),
            $defaults['pass']
        );
        $fw->registerFilterRule(
            5,
            array('direction' => 'in', 'interface' => 'wan', 'statetype' => 'modulate',
                'protocol' => 'gre', 'descr' => 'allow pptpd', 'quick' => false),
            $defaults['pass']
        );
    }

    // [out from this Firewall, using the selected gateway].
    // Our default setting has been to force traffic leaving a specific interface to use the associated gateway.
    // This behaviour can be disabled, so settings can be customized using manual firewall rules.
    if (empty($config['system']['pf_disable_force_gw'])) {
        foreach ($fw->getInterfaceMapping() as $ifdescr => $ifcfg) {
            if (!isset($ifcfg['internal_dynamic']) && $ifcfg['if'] != 'lo0') {
                $intf_has_v4 = false;
                $intf_has_v6 = false;
                foreach (array_keys(interfaces_addresses($ifcfg['if'], true, $fw->getIfconfigDetails())) as $addr) {
                    $intf_has_v4 = $intf_has_v4 || is_subnetv4($addr);
                    $intf_has_v6 = $intf_has_v6 || is_subnetv6($addr);
                }
                foreach ($fw->getInterfaceGateways($ifcfg['if']) as $gwname) {
                    $gwcfg = $fw->getGateway($gwname);
                    if (empty($gwcfg['gateway'])) {
                        // XXX: only force traffic on explict gateway [address] selection,
                        //      exclude gateway_interface types.
                        continue;
                    }
                    // only try to add gateway rules for traffic leaving this interface
                    // when the correct protocol is assigned to the interface
                    if (($gwcfg['proto'] == 'inet' && $intf_has_v4) || ($gwcfg['proto'] == 'inet6' && $intf_has_v6)) {
                        $fw->registerFilterRule(
                            100000,
                            array('from' => "({$ifcfg['if']})", 'direction' => 'out', 'gateway' => $gwname,
                            'destination' => array('network' => $ifdescr, "not" => true),
                            'statetype' => 'keep',
                            'allowopts' => true,
                            'quick' => false,
                            '#ref' => 'system_advanced_firewall.php#pf_disable_force_gw',
                            'descr' => "let out anything from firewall host itself (force gw)"),
                            $defaults['pass']
                        );
                    }
                }
            }
        }
    }
}
