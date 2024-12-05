<?php

/*
 * Copyright (C) 2019-2024 Deciso B.V.
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

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;
use OPNsense\Interface\Autoconf;
use OPNsense\Base\Messages\Message;

class Gateways extends BaseModel
{
    var $configHandle = null;
    var $gatewaySeq = 0;
    var $cached_gateways = array();

    public function __construct()
    {
        parent::__construct();
        $this->configHandle = Config::getInstance()->object();
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        foreach ($this->gateway_item->iterateItems() as $gateway) {
            if (!$validateFullModel && !$gateway->isFieldChanged()) {
                continue;
            }
            $this->gateway_item->calculateCurrent($gateway);
            $ref = $gateway->__reference;
            $this->validateNameChange($gateway, $messages, $ref);
            $this->validateDynamicMatch($gateway, $messages, $ref);
            foreach (['gateway', 'monitor'] as $key) {
                if (empty((string)$gateway->$key) || (string)$gateway->$key == 'dynamic') {
                    continue;
                } elseif ((string)$gateway->ipprotocol === 'inet' && !Util::isIpv4Address((string)$gateway->$key)) {
                    $messages->appendMessage(new Message(gettext('Invalid IPv4 address'), $ref . '.' . $key));
                } elseif ((string)$gateway->ipprotocol === 'inet6' && !Util::isIpv6Address((string)$gateway->$key)) {
                    $messages->appendMessage(new Message(gettext('Invalid IPv6 address'), $ref . '.' . $key));
                }
            }
            if (intval((string)$gateway->current_latencylow) > intval((string)$gateway->current_latencyhigh)) {
                $msg = gettext("The high latency threshold needs to be higher than the low latency threshold.");
                $messages->appendMessage(new Message($msg, $ref . ".latencyhigh"));
            }
            if (intval((string)$gateway->current_losslow) > intval((string)$gateway->current_losshigh)) {
                $msg = gettext("The high Packet Loss threshold needs to be higher than the low Packet Loss threshold.");
                $messages->appendMessage(new Message($msg, $ref . ".losshigh"));
            }
            if (
                intval((string)$gateway->current_time_period) < (
                    2 * (intval((string)$gateway->current_interval) + intval((string)$gateway->current_loss_interval))
                )
            ) {
                $msg = gettext(
                    "The time period needs to be at least 2 times the sum of the probe interval and the loss interval."
                );
                $messages->appendMessage(new Message($msg, $ref . ".time_period"));
            }
        }
        return $messages;
    }

    private function validateNameChange($node, $messages, $ref)
    {
        if (empty($ref)) {
            return;
        }

        $new = (string)$node->name;
        $cfg = Config::getInstance()->object();
        if (!empty($cfg->OPNsense->Gateways) && !empty($cfg->OPNsense->Gateways->gateway_item)) {
            /* Exclude legacy components from validation */
            foreach ($cfg->OPNsense->Gateways->gateway_item as $item) {
                $uuid = (string)$item->attributes()->uuid;
                if ($uuid === explode('.', $ref)[1]) {
                    $old = (string)$item->name;
                    if ($old !== $new) {
                        $messages->appendMessage(
                            new Message(gettext("Changing name on a gateway is not allowed."), $ref . ".name")
                        );
                    }
                }
            }
        }
    }

    private function validateDynamicMatch($node, $messages, $ref)
    {
        if (Util::isIpAddress((string)$node->gateway)) {
            // not dynamic, so no validation needed. protocol validation is handled earlier in the chain
            return;
        }
        $ipproto = (string)$node->ipprotocol === 'inet' ? 'ipaddr' : 'ipaddrv6';
        $if = (string)$node->interface;
        $ifcfg = Config::getInstance()->object()->interfaces->$if;
        if (!empty((string)$ifcfg->$ipproto) && Util::isIpAddress((string)$ifcfg->$ipproto)) {
            $ipFormat = $ipproto === 'ipaddr' ? 'IPv4' : 'IPv6';
            $messages->appendMessage(new Message(
                sprintf(
                    gettext("Dynamic gateway values cannot be specified for interfaces with a static %s configuration."),
                    $ipFormat
                ),
                $ref . ".gateway"
            ));
        }
    }

    /**
     * Backwards compatibility for wizard, setaddr
     */
    public function createOrUpdateGateway($fields, $uuid = null)
    {
        if ($uuid != null) {
            $node = $this->getNodeByReference('gateway_item.' . $uuid);
        } else {
            /* Create gateway */
            $node = $this->getNodeByReference('gateway_item');
            if ($node != null && $node->isArrayType()) {
                $uuid = $this->gateway_item->generateUUID();
                $node = $node->Add();
                $node->setAttributeValue("uuid", $uuid);
            }
        }

        if ($node != null && !empty($fields) && is_array($fields)) {
            $node->setNodes($fields);
            /* disable exception on validation failure */
            $this->serializeToConfig(false, true);
        }
    }

    /**
     * Iterate over all gateways defined in the config.
     * If no MVC model is available, use the legacy config.
     * @return \Generator
     */
    public function gatewayIterator()
    {
        $use_legacy = true;
        foreach ($this->gateway_item->iterateItems() as $gateway) {
            $record = [];
            foreach ($gateway->iterateItems() as $key => $value) {
                $record[$key] = (string)$value;
                /* current_ values are virtual, in which case we need to fetch explicit */
                $current_key = "current_{$key}";
                if (isset($gateway->$current_key)) {
                    $record[$current_key] = (string)$gateway->$current_key;
                }
            }
            $record['uuid'] = (string)$gateway->getAttributes()['uuid'];
            yield $record;
            $use_legacy = false;
        }

        if ($use_legacy) {
            $config = Config::getInstance()->object();
            if (!empty($config->gateways) && count($config->gateways->children()) > 0) {
                foreach ($config->gateways->children() as $tag => $gateway) {
                    if ($tag == 'gateway_item' && count($gateway->children()) > 0) {
                        $record = [];
                        // iterate over the individual nodes since empty nodes still return a
                        // SimpleXMLObject when the container is converted to an array
                        foreach ($gateway->children() as $node) {
                            $record[$node->getName()] = (string)$node;
                        }

                        /* impute possible missing values */
                        if (empty($record['priority'])) {
                            // default priority
                            $record['priority'] = 255;
                        }

                        if (empty($record['ipprotocol'])) {
                            // default address family
                            $record['ipprotocol'] = 'inet';
                        }

                        if (empty($record['monitor_disable'])) {
                            $record['monitor_disable'] = 0;
                        }
                        // backwards compatibility, hook "current_" fields
                        foreach ($this->gateway_item->getDpingerDefaults() as $key => $value) {
                            if (empty($record[$key])) {
                                // make sure node exists without value set
                                $record[$key] = '';
                                $record['current_' . $key] = $value;
                            } else {
                                $record['current_' . $key] = $record[$key];
                            }
                        }
                        $record['uuid'] = '';
                        yield $record;
                    }
                }
            }
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
     * return the device name present in the system for the specific configuration
     * @param string $ifname name of the interface
     * @param array $definedIntf configuration of interface
     * @param string $ipproto inet/inet6 type
     * @return string $realif target device name
     */
    private function getRealInterface($definedIntf, $ifname, $ipproto = 'inet')
    {
        if (empty($definedIntf[$ifname])) {
            /* name already resolved or invalid */
            return $ifname;
        }

        $ifcfg = $definedIntf[$ifname];
        $realif = $ifcfg['if'];

        if (isset($ifcfg['wireless']) && !strstr($realif, '_wlan')) {
            $realif .= '_wlan0';
        }

        if ($ipproto == 'inet6') {
            switch ($ifcfg['ipaddrv6'] ?? 'none') {
                case '6rd':
                case '6to4':
                    $realif = "{$ifname}_stf";
                    break;
                default:
                    break;
            }
        }

        return $realif;
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
            foreach ($this->gatewayIterator() as $gw_arr) {
                if (in_array($gw_arr['name'], $reservednames)) {
                    syslog(
                        LOG_WARNING,
                        'Gateway: duplicated entry "' . $gw_arr['name'] . '" in config.xml needs manual removal'
                    );
                }
                $reservednames[] = $gw_arr['name'];
                $gw_arr['if'] = $this->getRealInterface($definedIntf, $gw_arr['interface'], $gw_arr['ipprotocol']);
                $gw_arr['attribute'] = $i++;
                if (Util::isIpAddress($gw_arr['gateway'])) {
                    if (empty($gw_arr['monitor_disable']) && empty($gw_arr['monitor'])) {
                        $gw_arr['monitor'] = $gw_arr['gateway'];
                    }
                    $gwkey = $this->newKey($gw_arr['priority'], !empty($gw_arr['defaultgw']));
                    $this->cached_gateways[$gwkey] = $gw_arr;
                } else {
                    // dynamic gateways might have settings, temporary store
                    if (empty($dynamic_gw[$gw_arr['interface']])) {
                        $dynamic_gw[$gw_arr['interface']] = array();
                    }
                    $gw_arr['dynamic'] = true;
                    $dynamic_gw[$gw_arr['interface']][] = $gw_arr;
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
                    $realif = $this->getRealInterface($definedIntf, $ifname, $ipproto);
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
                        "gateway_interface" => false, // Dynamic gateway policy
                        "if" => $realif,
                        "dynamic" => true,
                        "virtual" => true
                    ];
                    // set default priority
                    if (strstr($realif, 'gre') || strstr($realif, 'gif') || strstr($realif, 'ovpn')) {
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
                    if (!empty($thisconf['virtual']) && in_array($thisconf['name'], $reservednames)) {
                        /* if name is already taken, don't try to add a new (virtual) entry */
                    } elseif (($router = Autoconf::getRouter($realif, $ipproto)) != null) {
                        $thisconf['gateway'] = $router;
                        if (empty($thisconf['monitor_disable']) && empty($thisconf['monitor'])) {
                            $thisconf['monitor'] = $thisconf['gateway'];
                        }
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        $this->cached_gateways[$gwkey] = $thisconf;
                    } elseif (!empty($ifcfg['gateway_interface']) || substr($realif, 0, 5) == 'ovpnc') {
                        // XXX: ditch ovpnc in a major upgrade in the future, supersede with interface setting
                        //      gateway_interface

                        // other predefined types, only bound by interface (e.g. openvpn)
                        $gwkey = $this->newKey($thisconf['priority'], !empty($thisconf['defaultgw']));
                        // gateway should only contain a valid address, make sure its empty
                        unset($thisconf['gateway']);
                        $thisconf['gateway_interface'] = true;
                        $this->cached_gateways[$gwkey] = $thisconf;
                    } elseif (
                        $ipproto == 'inet6'
                            && in_array($ifcfg['ipaddrv6'] ?? 'none', ['slaac', 'dhcp6', '6to4', '6rd'])
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
     * since getGateways() is correctly ordered, we just need to find the first active, not down gateway
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
                } elseif (
                    !empty($gateway['disabled']) ||
                    !empty($gateway['defunct']) ||
                    !empty($gateway['is_loopback']) ||
                    !empty($gateway['force_down'])
                ) {
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
     * @param bool $inactive is inactive
     * @return array name => gateway
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
     * @param string $name gateway name
     * @return string|null gateway address
     */
    public function getAddress($name)
    {
        foreach ($this->getGateways() as $gateway) {
            if ($gateway['name'] == $name && !empty($gateway['gateway'])) {
                $result = $gateway['gateway'];
                if (preg_match('/^fe[89ab][0-9a-f]:/i', $gateway['gateway'])) {
                    /* link-local, suffix interface */
                    $result .= "%{$gateway['if']}";
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
     * @param string $property the gateway property
     * @return string|null gateway address
     */
    public function getInterfaceGateway($interface, $ipproto = "inet", $only_configured = false, $property = 'gateway')
    {
        foreach ($this->getGateways() as $gateway) {
            if (!empty($gateway['disabled']) || !empty($gateway['defunct']) || $gateway['ipprotocol'] != $ipproto) {
                continue;
            } elseif (!empty($gateway['is_loopback']) || empty($gateway['gateway'])) {
                continue;
            }
            // The interface might have a gateway configured
            if (isset($this->configHandle->interfaces->$interface)) {
                $gwfield = $ipproto == "inet" ? "gateway" : "gatewayv6";
                $intf_gateway = $this->configHandle->interfaces->$interface->$gwfield;
            } else {
                $intf_gateway = null;
            }

            if (!empty($gateway['interface']) && $gateway['interface'] == $interface) {
                // XXX: $only_configured mimics the pre 19.7 behaviour, which means static non linked interfaces
                //      are not returned as valid gateway address (automatic outbound nat rules).
                //      An alternative setup option would be practical here, less fuzzy.
                if (!$only_configured || $intf_gateway == $gateway['name'] || !empty($gateway['dynamic'])) {
                    return isset($gateway[$property]) ? $gateway[$property] : null;
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
                                $tiers[$tier] = [];
                            }
                            $tiers[$tier][] = $gw;
                        }
                    }
                    ksort($tiers);
                    $all_tiers = [];
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
                                    case 'delay+loss':
                                        $is_up = stristr($gw_group->trigger, 'latency') === false &&
                                                    stristr($gw_group->trigger, 'loss') === false;
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
                                    'poolopts' => isset($gw_group->poolopts) ? (string)$gw_group->poolopts : null,
                                    'weight' => !empty($gateway['weight']) ? $gateway['weight'] : 1
                                ];
                                $all_tiers[$tieridx][] = $gateway_item;
                                if ($is_up) {
                                    $result[(string)$gw_group->name][] = $gateway_item;
                                }
                            }
                        }
                        // exit when tier has usable gateways
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
