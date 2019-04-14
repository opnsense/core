<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Routing;

use \OPNsense\Core\Config;
use \OPNsense\Firewall\Util;

/**
 * Class Gateways
 * @package OPNsense\Firewall
 */
class Gateways
{
    var $configHandle = null;
    var $gatewaySeq = 0;
    var $ifconfig = array();
    var $cached_gateways = array();

    /**
     * Construct new gateways object
     * @param array $ifconfig containing serialized ifconfig data
     */
    public function __construct(array $ifconfig)
    {
        $this->configHandle = Config::getInstance()->object();
        if ($ifconfig !== null) {
            $this->ifconfig = $ifconfig;
        }
    }

    /**
     * return all non virtual interfaces
     * @return array
     */
    private function getDefinedInterfaces()
    {
        $result = array();
        if (!empty($this->configHandle->interfaces)) {
            foreach ($this->configHandle->interfaces->children() as $ifname => $iface) {
                if (!isset($iface->virtual) || $iface->virtual != "1") {
                    $result[$ifname] = array();
                    foreach ($iface as $key => $value) {
                        $result[$ifname][(string)$key] = (string)$value;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * return the type of the interface, for backwards compatibility
     * @param string $ipproto inet/inet6 type
     * @param array $ifcfg
     * @return string type name
     */
    private static function convertType($ipproto, $ifcfg)
    {
        if (!empty($ifcfg['if'])) {
            if  ($ipproto == "inet") {
                if (substr($ifcfg['if'], 0, 5) == "ovpnc") {
                    return "VPNv4";
                } elseif (in_array(substr($ifcfg['if'], 0, 3), array('gif', 'gre'))) {
                    return "TUNNELv4";
                }

            } elseif ($ipproto == "inet6" && !empty($ifcfg['if'])) {
                if (substr($ifcfg['if'], 0, 5) == "ovpnc") {
                    return 'VPNv6';
                } elseif (in_array(substr($ifcfg['if'], 0, 3), array('gif', 'gre'))) {
                    return 'TUNNELv6';
                }
            }
        }
        // default
        if ($ipproto == "inet") {
            return !empty($ifcfg['ipaddr']) && !Util::isIpAddress($ifcfg['ipaddr']) ? $ifcfg['ipaddr'] : null;
        } else {
            return !empty($ifcfg['ipaddrv6']) && !Util::isIpAddress($ifcfg['ipaddrv6']) ? $ifcfg['ipaddrv6'] : null;
        }
    }

    /**
     * generate new sort key for a gateway
     * @param string|int $prio priority
     * @param bool $is_default is default wan gateway
     * @return string key
     */
    private function newKey($prio, $is_default=false)
    {
        if (empty($this->cached_gateways)) {
            $this->gatewaySeq = 1;
        }
        if ($prio > 255) {
            $prio = 255;
        }
        return sprintf("%01d%04d%010d", $is_default, 256 - $prio, $this->gatewaySeq++);
    }

    /**
     * return all defined gateways
     * @return array
     */
    public function getGateways()
    {
        if (empty($this->cached_gateways)) {
            // results are cached within this object
            $definedIntf = $this->getDefinedInterfaces();
            $dynamic_gw = array();
            $gatewaySeq = 1;
            $i=0; // sequence used in legacy edit form (item in the list)

            // add loopback, lowest priority
            $this->cached_gateways[$this->newKey(255)] = [
                'name' => 'Null4',
                'if' => 'lo0',
                'interface' => 'loopback',
                'ipprotocol' => 'inet',
                'gateway' => '127.0.0.1',
                'priority' => 255,
                'is_loopback' => true
            ];
            $this->cached_gateways[$this->newKey(255)] = [
                'name' => 'Null6',
                'if' => 'lo0',
                'interface' => 'loopback',
                'ipprotocol' => 'inet6',
                'gateway' => '::1',
                'priority' => 255,
                'is_loopback' => true
            ];
            // iterate configured gateways
            if (!empty($this->configHandle->gateways)) {
                  foreach ($this->configHandle->gateways->children() as $tag => $gateway) {
                      if ($tag == "gateway_item") {
                          $gw_arr = array();
                          foreach ($gateway as $key => $value) {
                              $gw_arr[(string)$key] = (string)$value;
                          }
                          if (empty($gw_arr['priority'])) {
                              // default priority
                              $gw_arr['priority'] = 255;
                          }
                          $gw_arr["if"] = $definedIntf[$gw_arr["interface"]]['if'];
                          $gw_arr["attribute"] = $i++;
                          if (Util::isIpAddress($gateway->gateway)) {
                              if (empty($gw_arr['monitor_disable']) && empty($gw_arr['monitor'])) {
                                  $gw_arr['monitor'] = $gw_arr['gateway'];
                              }
                              $gwkey = $this->newKey($gw_arr['priority'], !empty($gw_arr['defaultgw']));
                              $this->cached_gateways[$gwkey] = $gw_arr;
                          } else {
                              // dynamic gateways might have settings, temporary store
                              if (empty($dynamic_gw[(string)$gateway->interface])) {
                                  $dynamic_gw[(string)$gateway->interface] = array();
                              }
                              $dynamic_gw[(string)$gateway->interface][] = $gw_arr;
                          }
                      }
                  }
            }
            // add dynamic gateways
            foreach ($definedIntf as $ifname => $ifcfg) {
                foreach (["inet", "inet6"] as $ipproto) {
                    // filename suffix and interface type as defined in the interface
                    $fsuffix = $ipproto == "inet6" ? "v6" : "";
                    $ctype = self::convertType($ipproto, $ifcfg);
                    $ctype = $ctype != null ? $ctype : "GW";
                    // default configuration, when not set in gateway_item
                    $thisconf = [
                        "priority" => 255,
                        "interface" => $ifname,
                        "weight" => 1,
                        "ipprotocol" => $ipproto,
                        "name" => strtoupper("{$ifname}_{$ctype}"),
                        "descr" => "Interface " . strtoupper("{$ifname}_{$ctype}") . " Gateway",
                        "monitor_disable" => true, // disable monitoring by default
                        "if" => $ifcfg['if']
                    ];
                    if (file_exists("/tmp/{$ifcfg['if']}_defaultgw".$fsuffix)) {
                        $thisconf["defaultgw"] = true;
                    }
                    // locate interface gateway settings
                    if (!empty($dynamic_gw[$ifname])) {
                        foreach ($dynamic_gw[$ifname] as $gw_arr) {
                            if ($gw_arr['ipprotocol'] == $ipproto) {
                                // dynamic gateway for this ip protocol found, use config
                                $thisconf = $gw_arr;
                                break;
                            }
                        }
                    }
                    $thisconf['dynamic'] = true;
                    // dynamic gateways dump their address in /tmp/[IF]_router[FSUFFIX]
                    if (file_exists("/tmp/{$ifcfg['if']}_router".$fsuffix)) {
                        $thisconf['gateway'] = trim(@file_get_contents("/tmp/{$ifcfg['if']}_router".$fsuffix));
                        if (empty($thisconf['monitor_disable']) && empty($thisconf['monitor'])) {
                            $thisconf['monitor'] = $thisconf['gateway'];
                        }
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        $this->cached_gateways[$gwkey] = $thisconf;
                    // } elseif (!empty($this->ifconfig[$thisconf["if"]]["tunnel"]["dest_addr"])) {
                    //     // tunnel devices with a known endpoint
                    //     $thisconf['gateway'] = $this->ifconfig[$thisconf["if"]]["tunnel"]["dest_addr"];
                    //     $tunnel_ipproto = strpos($thisconf['gateway'], ":") != false ? "inet6" : "inet";
                    //     if ($tunnel_ipproto == $ipproto) {
                    //         if (empty($thisconf['monitor_disable']) && empty($thisconf['monitor'])) {
                    //             $thisconf['monitor'] = $thisconf['gateway'];
                    //         }
                    //         $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                    //         $this->cached_gateways[$gwkey] = $thisconf;
                    //     }
                    } elseif (self::convertType($ipproto, $ifcfg) != null) {
                        // other predefined types, only bound by interface (e.g. openvpn)
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        // gateway should only contain a valid address, make sure its empty
                        unset($thisconf['gateway']);
                        $this->cached_gateways[$gwkey] = $thisconf;
                    }
                }
            }
            // sort by priority
            krsort($this->cached_gateways);
        }
        return $this->cached_gateways;
    }

    /**
     * determine default gateway, exclude gateways in skip list
     * since getGateways() is correcly ordered, we just need to find the first active, not down gateway
     * @param array|null $skip list of gateways to ignore
     * @param string $ipproto inet/inet6 type
     * @return string type name
     */
    public function getDefaultGW($skip=null, $ipproto='inet')
    {
        foreach ($this->getGateways() as $gateway) {
            if ($gateway['ipprotocol'] == $ipproto) {
                if (is_array($skip) && in_array($gateway['name'], $skip)) {
                    continue;
                } elseif (!empty($gateway['disabled'])) {
                    continue;
                } elseif (!empty($gateway['gateway'])) {
                    return $gateway;
                }
            }
        }
        // not found
        return null;
    }

    /**
     * determine default gateway, exclude gateways in skip list
     * @param bool $disabled return disabled gateways
     * @param bool $localhost inet/inet6 type
     * @return string type name
     */
    public function gatewaysIndexedByName($disabled=false, $localhost=false, $inactive=false)
    {
        $result = array();
        foreach ($this->getGateways() as $gateway) {
            if (!empty($gateway['disabled']) && !$disabled) {
                continue;
            }
            if (!empty($gateway['is_loopback']) && !$localhost) {
                continue;
            }
            if (empty($gateway['is_loopback']) && empty($gateway['if']) && !$inactive){
                continue;
            }
            $result[$gateway['name']] = $gateway;
        }
        return $result;
    }

    /**
     * @param string $ipproto inet/inet6
     * @return bool has any gateway configured for the requested protocol
     */
    public function hasGateways($ipproto)
    {
        foreach ($this->getGateways() as $gateway) {
            if (empty($gateway['disabled']) && $ipproto == $gateway['ipprotocol']) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $name gateway name
     * @return string|null gateway address
     */
    public function getAddress($name)
    {
        foreach ($this->getGateways() as $gateway) {
            if ($gateway['name'] == $name && !empty($gateway['gateway'])){
                $result = $gateway['gateway'];
                if (strtolower(substr($gateway['gateway'], 0, 5)) == "fe80:") {
                    // link local, suffix interface
                    $result .= "%{$gw['if']}";
                }
                return $result;
            }
        }
        return null;
    }

    /**
     * @param string $name gateway name
     * @return string|null gateway interface
     */
    public function getInterface($name)
    {
        foreach ($this->getGateways() as $gateway) {
            if ($gateway['name'] == $name && !empty($gateway['if'])){
                return $gateway['if'];
            }
        }
        return null;
    }

    /**
     * @param string $name gateway name
     * @return string|null gateway interface
     */
    public function getInterfaceName($name)
    {
        foreach ($this->getGateways() as $gateway) {
            if ($gateway['name'] == $name && !empty($gateway['interface'])){
                return $gateway['interface'];
            }
        }
        return null;
    }

    /**
     * @param string $interface interface name
     * @param string $ipproto inet/inet6
     * @return string|null gateway address
     */
    public function getInterfaceGateway($interface, $ipproto="inet")
    {
        foreach ($this->getGateways() as $gateway) {
            if (!empty($gateway['disabled']) || $gateway['ipprotocol'] != $ipproto) {
                continue;
            } elseif (!empty($gateway['is_loopback']) || empty($gateway['gateway'])) {
                continue;
            }

            if (!empty($gateway['interface']) && $gateway['interface'] == $interface){
                return $gateway['gateway'];
            }
        }
        return null;
    }
}
