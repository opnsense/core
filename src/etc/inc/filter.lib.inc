<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
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

function filter_core_antilockout_ports()
{
    global $config;
    $lockout_ports = array();
    /*
     * XXX Some issues here:
     *
     * 1. Why does the webgui nolockout control the ssh lockout?
     * 2. Both webgui and openssh are plugins, their code should
     *    reside in the respective plugins.inc.d file once anti-
     *    lockout is fully pluggable.
     * 3. Openssh opens the port when install media is detected
     *    and no ssh has ever been configured.  This is in line
     *    with how the plugin behaves, but probably looks odd.
     */
    if (!isset($config['system']['webgui']['noantilockout'])) {
        if (empty($config['system']['webgui']['port'])) {
            $lockout_ports[] = $config['system']['webgui']['protocol'] == "https" ? "443" : "80";
        } else {
            $lockout_ports[] = $config['system']['webgui']['port'];
        }
        if ($config['system']['webgui']['protocol'] == "https" && !isset($config['system']['webgui']['disablehttpredirect'])) {
            $lockout_ports[] = "80";
        }
        if (isset($config['system']['ssh']['enabled']) ||
            (!isset($config['system']['ssh']['noauto']) && is_install_media() && is_process_running('sshd'))) {
            $lockout_ports[] = empty($config['system']['ssh']['port']) ? "22" : $config['system']['ssh']['port'];
        }
    }
    return $lockout_ports;
}

/**
 *  core system rules
 */
function filter_core_rules_system($fw, $defaults)
{
    global $config;

    // block All IPv6 except loopback traffic
    $fw->registerFilterRule(1,
      array('interface' => 'loopback', 'ipprotocol'=>'inet6', 'disabled' => isset($config['system']['ipv6allow']),
          'label' => 'Pass all loopback IPv6'),
      $defaults['pass']
    );
    $fw->registerFilterRule(1,
      array('ipprotocol'=>'inet6','label' => 'Block all IPv6', 'disabled' => isset($config['system']['ipv6allow'])),
      $defaults['block']
    );

    // default Deny rule (when no other rules match)
    $fw->registerFilterRule(1,
      array('ipprotocol'=>'inet46', 'label' => 'Default deny rule', 'quick' => false),
      $defaults['block']
    );

    // IPv6 ICMP requirements
    $fw->registerFilterRule(1,
      array('ipprotocol'=>'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '1,2,135,136',
            'statetype' => 'keep', 'label' => 'IPv6 requirements (ICMP)'),
      $defaults['pass']
    );
    // Allow only bare essential icmpv6 packets
    $fw->registerFilterRule(1,
      array('ipprotocol'=>'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '129,133,134,135,136',
            'statetype' => 'keep', 'label' => 'IPv6 requirements (ICMP)', 'from' => 'fe80::/10',
            'to' => 'fe80::/10,ff02::/16', 'direction' => 'out' ),
      $defaults['pass']
    );
    $fw->registerFilterRule(1,
      array('ipprotocol'=>'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '128,133,134,135,136',
            'statetype' => 'keep', 'label' => 'IPv6 requirements (ICMP)', 'from' => 'fe80::/10',
            'to' => 'fe80::/10,ff02::/16', 'direction' => 'in' ),
      $defaults['pass']
    );
    $fw->registerFilterRule(1,
      array('ipprotocol'=>'inet6', 'protocol' => 'ipv6-icmp', 'icmp6-type' => '128,133,134,135,136',
            'statetype' => 'keep', 'label' => 'IPv6 requirements (ICMP)', 'from' => 'ff02::/16',
            'to' => 'fe80::/10', 'direction' => 'in' ),
      $defaults['pass']
    );
    // block all targetting port 0
    foreach (array('from_port', 'to_port') as $target) {
        $fw->registerFilterRule(1,
          array('ipprotocol'=>'inet46', 'protocol' => 'tcp/udp', $target => '0'),
          $defaults['block']
        );
    }
    // CARP defaults
    $carp_disabled = empty($config['hasync']) && empty($config['virtualip']['vip']);
    $fw->registerFilterRule(1,
      array('protocol' => 'carp', 'from' => '(self)', 'direction' => 'in', 'disabled' => !$carp_disabled),
      $defaults['block']
    );
    $fw->registerFilterRule(1,array('protocol' => 'carp'),$defaults['pass']);

    // Lockout rules
    $fw->registerFilterRule(1,
      array('protocol' => 'tcp', 'from' => '<sshlockout>', 'to' => '(self)' , 'label' => 'sshlockout', 'direction' => 'in',
        'to_port' => !empty($config['system']['ssh']['port']) ? $config['system']['ssh']['port'] : 22),
      $defaults['block']
    );
    $webport = '443';
    if (!empty($config['system']['webgui']['port'])) {
        $webport = $config['system']['webgui']['port'];
    } elseif ($config['system']['webgui']['protocol'] == 'http') {
        $webport = '80';
    }
    $fw->registerFilterRule(1,
      array('protocol' => 'tcp', 'from' => '<webConfiguratorlockout>', 'to' => '(self)' , 'label' => 'webConfiguratorlockout',
        'direction' => 'in','to_port' => $webport),
      $defaults['block']
    );

    // block all in alias <virusprot>
    $fw->registerFilterRule(1,array('from' => '<virusprot>', 'label' => 'virusprot overload table'),$defaults['block']);

    // block bogons and private nets
    $bogontmpl = array('type' => 'block', 'log' => !isset($config['syslog']['nologbogons']), 'disablereplyto' => 1);
    $privtmpl = array('type' => 'block', 'log' => !isset($config['syslog']['nologprivatenets']),
      'from' => '10.0.0.0/8,127.0.0.0/8,100.64.0.0/10,172.16.0.0/12,192.168.0.0/16,fc00::/7',
      'disablereplyto' => 1);
    foreach ($fw->getInterfaceMapping() as $intf => $intfinfo) {
        $fw->registerFilterRule(5,
          array('from' => "<bogons>", 'direction' => 'in', 'interface' => $intf,
            'label' => "block bogon IPv4 networks from ".$intfinfo['descr'],
            'disabled' => !isset($intfinfo['blockbogons'])),
          $bogontmpl
        );
        $fw->registerFilterRule(5,
          array('from' => "<bogonsv6>", 'direction' => 'in', 'interface' => $intf,
            'disabled' => !isset($config['system']['ipv6allow']) || !isset($intfinfo['blockbogons']),
            'label' => "block bogon IPv6 networks from ".$intfinfo['descr']),
          $bogontmpl
        );
        $fw->registerFilterRule(5,
          array('direction' => 'in', 'interface' => $intf,
            'label' => "Block private networks from ".$intfinfo['descr'],
            'disabled' => !isset($intfinfo['blockpriv'])),
          $privtmpl
        );
    }

    // interface configuration per type
    foreach ($fw->getInterfaceMapping() as $intf => $intfinfo) {
        // allow DHCPv6 client out, before adding bogons (sequence 1, bogons @ 5)
        if (isset($config['system']['ipv6allow']) && in_array($intfinfo['ipaddrv6'], array("slaac","dhcp6"))) {
            $fw->registerFilterRule(1,
              array('protocol' => 'udp', 'from' => 'fe80::/10', 'from_port' => 546, 'to' => 'fe80::/10',
                    'interface' => $intf, 'to_port' => 546, 'label' =>'allow dhcpv6 client in ' . $intfinfo['descr']),
              $defaults['pass']
            );
            $fw->registerFilterRule(1,
              array('protocol' => 'udp', 'from_port' => 547,'to_port' => 546, 'direction' => 'in',
                    'interface' => $intf, 'label' =>'allow dhcpv6 client in ' . $intfinfo['descr']),
              $defaults['pass']
            );
            $fw->registerFilterRule(1,
              array('protocol' => 'udp', 'from_port' => 546,'to_port' => 547, 'direction' => 'out',
                    'interface' => $intf, 'label' =>'allow dhcpv6 client in ' . $intfinfo['descr']),
              $defaults['pass']
            );
        }
        // IPv4
        switch (empty($intfinfo['ipaddr']) ? "" : $intfinfo['ipaddr']) {
            case "pptp":
                $fw->registerFilterRule(5,
                  array('protocol' => 'tcp','to_port' => 1723, 'direction' => 'in', 'statetype' => 'modulate', 'quick' => false,
                        'interface' => $intf, 'flags' => 'S/SA', 'label' =>'allow PPTP client on ' . $intfinfo['descr']),
                  $defaults['pass']
                );
                $fw->registerFilterRule(5,
                  array('protocol' => 'gre', 'direction' => 'in', 'statetype' => 'keep', 'quick' => false,
                        'interface' => $intf, 'label' =>'allow PPTP client on ' . $intfinfo['descr']),
                  $defaults['pass']
                );
                break;
            case "dhcp":
                $fw->registerFilterRule(5,
                  array('protocol' => 'udp', 'direction' => 'in', 'quick' => false, 'from_port' => 67, 'to_port' => 68,
                        'interface' => $intf, 'label' =>'allow DHCP client on ' . $intfinfo['descr']),
                  $defaults['pass']
                );
                $fw->registerFilterRule(5,
                  array('protocol' => 'udp', 'direction' => 'out', 'quick' => false, 'from_port' => 68, 'to_port' => 67,
                        'interface' => $intf, 'label' =>'allow DHCP client on ' . $intfinfo['descr']),
                  $defaults['pass']
                );
                break;
            default:
                if (isset($config['dhcpd'][$intf]['enable'])) {
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp', 'direction' => 'in', 'from_port' => 68, 'to' => '255.255.255.255',
                            'to_port' => 67, 'interface' => $intf, 'label' =>'allow access to DHCP server'),
                      $defaults['pass']
                    );
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp', 'direction' => 'in', 'from_port' => 68, 'to' => '(self)',
                            'to_port' => 67, 'interface' => $intf, 'label' =>'allow access to DHCP server'),
                      $defaults['pass']
                    );
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp', 'direction' => 'out', 'from_port' => 67, 'from' => '(self)',
                            'to_port' => 68, 'interface' => $intf, 'label' =>'allow access to DHCP server'),
                      $defaults['pass']
                    );
                    if (!empty($config['dhcpd'][$intf]['failover_peerip'])) {
                        $fw->registerFilterRule(5,
                          array('protocol' => 'tcp/udp', 'direction' => 'in', 'to' => '(self)', 'to_port' => '519,520',
                                'from' => $config['dhcpd'][$intf]['failover_peerip'],
                                'interface' => $intf, 'label' =>'allow access to DHCP failover'),
                          $defaults['pass']
                        );
                    }
                }
                break;
        }
        // IPv6
        switch(isset($intfinfo['ipaddrv6']) ? $intfinfo['ipaddrv6'] : null ) {
            case "6rd":
                $fw->registerFilterRule(5,
                  array('protocol' => '41', 'direction' => 'in', 'from' => $config['interfaces'][$intf]['gateway-6rd'],
                        'quick'=>false, 'interface' => $intf, 'label' =>'Allow 6in4 traffic in for 6rd on '.$intfinfo['descr']),
                  $defaults['pass']
                );
                $fw->registerFilterRule(5,
                  array('protocol' => '41', 'direction' => 'out', 'to' => $config['interfaces'][$intf]['gateway-6rd'],
                        'quick'=>false, 'interface' => $intf, 'label' =>'Allow 6in4 traffic out for 6rd on '.$intfinfo['descr']),
                  $defaults['pass']
                );
                break;
            case "6to4":
                $fw->registerFilterRule(5,
                  array('protocol' => '41', 'direction' => 'in', 'to' => '(self)','interface' => $intf,
                        'quick'=>false, 'label' =>'Allow 6in4 traffic in for 6to4 on '.$intfinfo['descr']),
                  $defaults['pass']
                );
                $fw->registerFilterRule(5,
                  array('protocol' => '41', 'direction' => 'out', 'from' => '(self)','interface' => $intf,
                        'quick'=>false, 'label' =>'Allow 6in4 traffic out for 6to4 on '.$intfinfo['descr']),
                  $defaults['pass']
                );
                break;
            default:
              if (isset($config['dhcpdv6'][$intf]['enable']) || isset($intfinfo['track6-interface'])
                || (!empty($config['dhcrelay6']['interface'])  && in_array($intf, explode(',', $config['dhcrelay6']['interface'])))) {
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'fe80::/10', 'to' => 'fe80::/10,ff02::/16',
                            'to_port' => 546, 'interface' => $intf,
                            'label' =>'allow access to DHCPv6 server on '.$intfinfo['descr']),
                      $defaults['pass']
                    );
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'fe80::/10', 'to' => 'ff02::/16',
                            'to_port' => 547, 'interface' => $intf,
                            'label' =>'allow access to DHCPv6 server on '.$intfinfo['descr']),
                      $defaults['pass']
                    );
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'ff02::/16', 'to' => 'fe80::/10',
                            'to_port' => 547, 'interface' => $intf,
                            'label' =>'allow access to DHCPv6 server on '.$intfinfo['descr']),
                      $defaults['pass']
                    );

                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => 'fe80::/10', 'to' => '(self)',
                            'to_port' => 546, 'interface' => $intf, 'direction' => 'in',
                            'label' =>'allow access to DHCPv6 server on '.$intfinfo['descr']),
                      $defaults['pass']
                    );
                    $fw->registerFilterRule(5,
                      array('protocol' => 'udp','ipprotocol' => 'inet6', 'from' => '(self)', 'to' => 'fe80::/10',
                            'from_port' => 547, 'interface' => $intf, 'direction' => 'out',
                            'label' =>'allow access to DHCPv6 server on '.$intfinfo['descr']),
                      $defaults['pass']
                    );
                }
                break;
        }
    }
    // loopback
    $fw->registerFilterRule(5,array('interface' => 'loopback', 'label' =>'pass loopback'),$defaults['pass']);
    // out from this Firewall
    $fw->registerFilterRule(5,array('direction' => 'out', 'statetype' =>'keep', 'allowopts' => true,
      'quick' => false, "label" => "let out anything from firewall host itself"),
        $defaults['pass']
    );
    // ipsec
    if (!empty($fw->getInterfaceMapping()['enc0'])) {
        $fw->registerFilterRule(5,
          array('direction' => 'out', 'statetype' => 'keep', 'quick' => false, 'interface' => 'enc0',
                'label' =>'IPsec internal host to host'),
          $defaults['pass']
        );
    }
    // anti-lockout
    $lockoutprts = filter_core_antilockout_ports();
    if (count($lockoutprts) > 0) {
        $lockoutif = null;
        if (!empty($config['interfaces']['lan']['if'])) {
            $lockoutif = 'lan';
        } elseif (count($config['interfaces']) == 1 && !empty($config['interfaces']['wan']['if'])) {
            $lockoutif = 'wan';
        }
        if (!empty($lockoutif)) {
            $fw->registerFilterRule(5,
              array('direction' => 'in', 'interface' => $lockoutif, 'statetype' => 'keep',
                    'protocol' => 'tcp', 'to' => '(self)', 'to_port' => implode(' ', $lockoutprts),
                    'label' =>'anti-lockout rule'),
              $defaults['pass']
            );
        }
    }

    // TODO: move to pptpd plugin when filter.lib.inc is part of the standard release
    $pptpdcfg = $config['pptpd'];
    if (isset($pptpdcfg['mode']) && $pptpdcfg['mode'] == 'server') {
        $fw->registerFilterRule(5,
          array('direction' => 'in', 'interface' => 'wan', 'statetype' => 'modulate','protocol' => 'tcp',
                'to' => '(self)', 'to_port' => '1723', 'quick' => false, 'label' =>'allow pptpd'),
          $defaults['pass']
        );
        $fw->registerFilterRule(5,
          array('direction' => 'in', 'interface' => 'wan', 'statetype' => 'modulate',
                'protocol' => 'gre', 'label' =>'allow pptpd', 'quick' => false),
          $defaults['pass']
        );
    }

    // [out from this Firewall, using the selected gateway].
    // Our default setting has been to force traffic leaving a specific interface to use the associated gateway.
    // This behaviour can be disabled, so settings can be customized using manual firewall rules.
    if (empty($config['system']['pf_disable_force_gw'])) {
        foreach ($fw->getInterfaceMapping() as $ifdescr => $ifcfg) {
            if (!isset($ifcfg['virtual']) && $ifcfg['if'] != 'lo0') {
                $intf_has_v4 = false;
                $intf_has_v6 = false;
                foreach (legacy_getall_interface_addresses($ifcfg['if']) as $addr) {
                    $intf_has_v4 = $intf_has_v4 || is_subnetv4($addr);
                    $intf_has_v6 = $intf_has_v6 || is_subnetv6($addr);
                }
                foreach ($fw->getInterfaceGateways($ifcfg['if']) as $gwname) {
                    $gwproto = $fw->getGateway($gwname)['proto'];
                    // only try to add gateway rules for traffic leaving this interface when the correct protocol is
                    // assigned to the interface (e.g. no ipv4 gateway when :network doesn't resolve to an ipv4 network)
                    if (($gwproto == 'inet' && $intf_has_v4) || ($gwproto == 'inet6' && $intf_has_v6)) {
                        $fw->registerFilterRule(100000,
                          array('from' => $ifcfg['if'], 'direction' => 'out', 'gateway' => $gwname,
                            'destination' => array('network'=> $ifdescr, "not" => true),
                            'statetype' => 'keep',
                            'allowopts' => true,
                            'quick' => false,
                            'label' => "let out anything from firewall host itself"),
                          $defaults['pass']
                        );
                    }
                }
            }
        }
    }

}
