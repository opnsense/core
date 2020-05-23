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

use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

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
            if ($ipproto == "inet") {
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
    private function newKey($prio, $is_default = false)
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
            $i = 0; // sequence used in legacy edit form (item in the list)
            $reservednames = array();

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
                    if ($tag == "gateway_item" && !empty($gateway)) {
                        $reservednames[] = (string)$gateway->name;
                        $gw_arr = array();
                        foreach ($gateway as $key => $value) {
                            $gw_arr[(string)$key] = (string)$value;
                        }
                        if (empty($gw_arr['priority'])) {
                            // default priority
                            $gw_arr['priority'] = 255;
                        }
                        if (empty($gw_arr['ipprotocol'])) {
                            // default address family
                            $gw_arr['ipprotocol'] = 'inet';
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
                            $gw_arr['dynamic'] =  true;
                            $dynamic_gw[(string)$gateway->interface][] = $gw_arr;
                        }
                    }
                }
            }
            // add dynamic gateways
            foreach ($definedIntf as $ifname => $ifcfg) {
                if (empty($ifcfg['enable'])) {
                    // only consider active interfaces
                    continue;
                }
                foreach (["inet", "inet6"] as $ipproto) {
                    // filename suffix and interface type as defined in the interface
                    $descr = !empty($ifcfg['descr']) ? $ifcfg['descr'] : $ifname;
                    $fsuffix = $ipproto == "inet6" ? "v6" : "";
                    $ctype = self::convertType($ipproto, $ifcfg);
                    $ctype = $ctype != null ? $ctype : "GW";
                    // default configuration, when not set in gateway_item
                    $thisconf = [
                        "interface" => $ifname,
                        "weight" => 1,
                        "ipprotocol" => $ipproto,
                        "name" => strtoupper("{$descr}_{$ctype}"),
                        "descr" => "Interface " . strtoupper("{$descr}_{$ctype}") . " Gateway",
                        "monitor_disable" => true, // disable monitoring by default
                        "if" => $ifcfg['if'],
                        "dynamic" => true,
                        "virtual" => true
                    ];
                    // set default priority
                    if (strstr($ifcfg['if'], 'gre') || strstr($ifcfg['if'], 'gif') || strstr($ifcfg['if'], 'ovpn')) {
                        // consider tunnel type interfaces least attractive by default
                        $thisconf['priority'] = 255;
                    } else {
                        $thisconf['priority'] = 254;
                    }
                    // locate interface gateway settings
                    if (!empty($dynamic_gw[$ifname])) {
                        foreach ($dynamic_gw[$ifname] as $gwidx => $gw_arr) {
                            if ($gw_arr['ipprotocol'] == $ipproto) {
                                // dynamic gateway for this ip protocol found, use config
                                unset($dynamic_gw[$ifname][$gwidx]);
                                $thisconf = $gw_arr;
                                break;
                            }
                        }
                    }
                    // dynamic gateways dump their address in /tmp/[IF]_router[FSUFFIX]
                    if (!empty($thisconf['virtual']) && in_array($thisconf['name'], $reservednames)) {
                        // if name is already taken, don't try to add a new (virtual) entry
                        null;
                    } elseif (file_exists("/tmp/{$ifcfg['if']}_router" . $fsuffix)) {
                        $thisconf['gateway'] = trim(@file_get_contents("/tmp/{$ifcfg['if']}_router" . $fsuffix));
                        if (empty($thisconf['monitor_disable']) && empty($thisconf['monitor'])) {
                            $thisconf['monitor'] = $thisconf['gateway'];
                        }
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        $this->cached_gateways[$gwkey] = $thisconf;
                    } elseif (!empty($ifcfg['gateway_interface']) || substr($ifcfg['if'], 0, 5) == "ovpnc") {
                        // XXX: ditch ovpnc in a major upgrade in the future, supersede with interface setting
                        //      gateway_interface

                        // other predefined types, only bound by interface (e.g. openvpn)
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        // gateway should only contain a valid address, make sure its empty
                        unset($thisconf['gateway']);
                        $this->cached_gateways[$gwkey] = $thisconf;
                    } elseif (
                        $ipproto == 'inet6'
                            && in_array($ifcfg['ipaddrv6'], array('slaac', 'dhcp6', '6to4', '6rd'))
                    ) {
                        // Dynamic IPv6 interface, but no router solicit response received using rtsold.
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        // gateway should only contain a valid address, make sure its empty
                        unset($thisconf['gateway']);
                        $this->cached_gateways[$gwkey] = $thisconf;
                    } elseif (empty($thisconf['virtual'])) {
                        // skipped dynamic gateway from config, add to $dynamic_gw to handle defunct
                        $dynamic_gw[$ifname][] = $thisconf;
                    }
                }
            }
            // sort by priority
            krsort($this->cached_gateways);
            // entries left in $dynamic_gw are defunct,  add them in in disabled state
            foreach ($dynamic_gw as $intfgws) {
                foreach ($intfgws as $gw_arr) {
                    if (!empty($gw_arr)) {
                        $gw_arr['disabled'] = true;
                        $gw_arr['defunct'] = true;
                        unset($gw_arr['gateway']);
                        $this->cached_gateways[] = $gw_arr;
                    }
                }
            }
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
    public function getDefaultGW($skip = null, $ipproto = 'inet')
    {
        foreach ($this->getGateways() as $gateway) {
            if ($gateway['ipprotocol'] == $ipproto) {
                if (is_array($skip) && in_array($gateway['name'], $skip)) {
                    continue;
                } elseif (!empty($gateway['disabled']) || !empty($gateway['is_loopback']) || !empty($gateway['force_down'])) {
                    continue;
                } else {
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
    public function gatewaysIndexedByName($disabled = false, $localhost = false, $inactive = false)
    {
        $result = array();
        foreach ($this->getGateways() as $gateway) {
            if (!empty($gateway['disabled']) && !$disabled) {
                continue;
            }
            if (!empty($gateway['is_loopback']) && !$localhost) {
                continue;
            }
            if (empty($gateway['is_loopback']) && empty($gateway['if']) && !$inactive) {
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
            if ($gateway['name'] == $name && !empty($gateway['gateway'])) {
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
            if ($gateway['name'] == $name && !empty($gateway['if'])) {
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
            if ($gateway['name'] == $name && !empty($gateway['interface'])) {
                return $gateway['interface'];
            }
        }
        return null;
    }

    /**
     * @param string $interface interface name
     * @param string $ipproto inet/inet6
     * @param boolean $only_configured only return configured in interface or dynamic gateways
     * @return string|null gateway address
     */
    public function getInterfaceGateway($interface, $ipproto = "inet", $only_configured = false)
    {
        foreach ($this->getGateways() as $gateway) {
            if (!empty($gateway['disabled']) || $gateway['ipprotocol'] != $ipproto) {
                continue;
            } elseif (!empty($gateway['is_loopback']) || empty($gateway['gateway'])) {
                continue;
            }
            // The interface might have a gateway configured
            if (isset($this->configHandle->interfaces->$interface)) {
                $intf_gateway = $this->configHandle->interfaces->$interface->gateway;
            } else {
                $intf_gateway = null;
            }

            if (!empty($gateway['interface']) && $gateway['interface'] == $interface) {
                // XXX: $only_configured mimics the pre 19.7 behaviour, which means static non linked interfaces
                //      are not returned as valid gateway address (automatic outbound nat rules).
                //      An alternative setup option would be practical here, less fuzzy.
                if (!$only_configured || $intf_gateway == $gateway['name'] || !empty($gateway['dynamic'])) {
                    return $gateway['gateway'];
                }
            }
        }
        return null;
    }

    /**
     * get gateway groups and active
     * @param array $status_info gateway status info (from dpinger)
     * @return array usable gateway groups
     */
    public function getGroups($status_info)
    {
        $all_gateways = $this->gatewaysIndexedByName();
        $result = array();
        if (isset($this->configHandle->gateways)) {
            foreach ($this->configHandle->gateways->children() as $tag => $gw_group) {
                if ($tag == "gateway_group" && !empty($gw_group)) {
                    $tiers = array();
                    if (isset($gw_group->item)) {
                        foreach ($gw_group->item as $item) {
                            list($gw, $tier) = explode("|", $item);
                            if (!isset($tiers[$tier])) {
                                $tiers[$tier] =  array();
                            }
                            $tiers[$tier][] = $gw;
                        }
                    }
                    ksort($tiers);
                    $all_tiers =  array();
                    foreach ($tiers as $tieridx => $tier) {
                        $all_tiers[$tieridx] = array();
                        if (!isset($result[(string)$gw_group->name])) {
                            $result[(string)$gw_group->name] = array();
                        }
                        // check status for all gateways in this tier
                        foreach ($tier as $gwname) {
                            if (!empty($all_gateways[$gwname]['gateway']) && !empty($status_info[$gwname])) {
                                $gateway = $all_gateways[$gwname];
                                switch ($status_info[$gwname]['status']) {
                                    case 'down':
                                    case 'force_down':
                                        $is_up = false;
                                        break;
                                    case 'delay':
                                        $is_up = stristr($gw_group->trigger, 'latency') === false;
                                        break;
                                    case 'loss':
                                        $is_up = stristr($gw_group->trigger, 'loss') === false;
                                        break;
                                    default:
                                        $is_up = true;
                                }
                                $gateway_item = [
                                    'int' => $gateway['if'],
                                    'gwip' => $gateway['gateway'],
                                    'weight' => !empty($gateway['weight']) ? $gateway['weight'] : 1
                                ];
                                $all_tiers[$tieridx][] = $gateway_item;
                                if ($is_up) {
                                    $result[(string)$gw_group->name][] = $gateway_item;
                                }
                            }
                        }
                        // exit when tier has (a) usuable gateway(s)
                        if (!empty($result[(string)$gw_group->name])) {
                            break;
                        }
                    }
                    // XXX: backwards compatibility, when no tiers are up, we seem to select all from the first
                    //      found tier. not very useful, since we already seem to know these are down.
                    if (empty($result[(string)$gw_group->name])) {
                        $result[(string)$gw_group->name] = $all_tiers[array_keys($tiers)[0]];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * return gateway groups (only names)
     * @return array list of names
     */
    public function getGroupNames()
    {
        $result = array();
        if (isset($this->configHandle->gateways)) {
            foreach ($this->configHandle->gateways->children() as $tag => $gw_group) {
                if ($tag == "gateway_group") {
                    $result[] = (string)$gw_group->name;
                }
            }
        }
        return $result;
    }

    /**
     * return protocol family
     * @param string $name gateway group name
     * @return string ipprotocol family (inet, inet6, null when not found)
     */
    public function getGroupIPProto($name)
    {
        if (isset($this->configHandle->gateways)) {
            foreach ($this->configHandle->gateways->children() as $tag => $gw_group) {
                if ($tag == "gateway_group" && (string)$gw_group->name == $name) {
                    $all_gateways = $this->gatewaysIndexedByName();
                    foreach ($gw_group->item as $item) {
                        $gw = explode("|", $item)[0];
                        if (!empty($all_gateways[$gw])) {
                            return $all_gateways[$gw]['ipprotocol'];
                        }
                    }
                }
            }
        }
        return null;
    }
}
