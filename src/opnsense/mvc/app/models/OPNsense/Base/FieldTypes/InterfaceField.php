<?php

/*
 * Copyright (C) 2015-2019 Deciso B.V.
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

namespace OPNsense\Base\FieldTypes;

use OPNsense\Core\Config;

/**
 * Class InterfaceField field type to select usable interfaces, currently this is kind of a backward compatibility
 * package to glue legacy interfaces into the model.
 * @package OPNsense\Base\FieldTypes
 */
class InterfaceField extends BaseListField
{
    /**
     * @var array collected options
     */
    private static $internalStaticOptionList = array();

    /**
     * @var array filters to use on the interface list
     */
    private $internalFilters = array();

    /**
     * @var string key to use for option selections, to prevent excessive reloading
     */
    private $internalCacheKey = '*';

    /**
     * @var bool add physical interfaces to selection (collected from lagg, vlan)
     */
    private $internalAddParentDevices = false;

    /**
     * @var bool allow dynamic interfaces
     */
    private $internalAllowDynamic = 0;

    /**
     *  collect parents for lagg interfaces
     *  @return array named array containing device and lagg interface
     */
    private function getConfigLaggInterfaces()
    {
        $physicalInterfaces = array();
        $configObj = Config::getInstance()->object();
        if (!empty($configObj->laggs)) {
            foreach ($configObj->laggs->children() as $key => $lagg) {
                if (!empty($lagg->members)) {
                    foreach (explode(',', $lagg->members) as $interface) {
                        if (!isset($physicalInterfaces[$interface])) {
                            $physicalInterfaces[$interface] = array();
                        }
                        $physicalInterfaces[$interface][] = (string)$lagg->laggif;
                    }
                }
            }
        }
        return $physicalInterfaces;
    }

    /**
     *  collect parents for vlan interfaces
     *  @return array named array containing device and vlan interfaces
     */
    private function getConfigVLANInterfaces()
    {
        $physicalInterfaces = array();
        $configObj = Config::getInstance()->object();
        if (!empty($configObj->vlans)) {
            foreach ($configObj->vlans->children() as $key => $vlan) {
                if (!isset($physicalInterfaces[(string)$vlan->if])) {
                    $physicalInterfaces[(string)$vlan->if] = array();
                }
                $physicalInterfaces[(string)$vlan->if][] = (string)$vlan->vlanif;
            }
        }
        return $physicalInterfaces;
    }

    /**
     * generate validation data (list of interfaces and well know ports)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList[$this->internalCacheKey])) {
            self::$internalStaticOptionList[$this->internalCacheKey] = array();

            $allInterfaces = array();
            $allInterfacesDevices = array(); // mapping device -> interface handle (lan/wan/optX)
            $configObj = Config::getInstance()->object();
            // Iterate over all interfaces configuration and collect data
            if (isset($configObj->interfaces) && $configObj->interfaces->count() > 0) {
                foreach ($configObj->interfaces->children() as $key => $value) {
                    if (!$this->internalAllowDynamic && !empty($value->internal_dynamic)) {
                        continue;
                    } elseif ($this->internalAllowDynamic == 2 && !empty($value->internal_dynamic)) {
                        if (empty($value->ipaddr) && empty($value->ipaddrv6)) {
                            continue;
                        }
                    }
                    $allInterfaces[$key] = $value;
                    if (!empty($value->if)) {
                        $allInterfacesDevices[(string)$value->if] = $key;
                    }
                }
            }

            if ($this->internalAddParentDevices) {
                // collect parents for lagg/vlan interfaces
                $physicalInterfaces = $this->getConfigLaggInterfaces();
                $physicalInterfaces = array_merge($physicalInterfaces, $this->getConfigVLANInterfaces());

                // add unique devices
                foreach ($physicalInterfaces as $interface => $devices) {
                    // construct interface node
                    $interfaceNode = new \stdClass();
                    $interfaceNode->enable = 0;
                    $interfaceNode->descr = "[{$interface}]";
                    $interfaceNode->if = $interface;
                    foreach ($devices as $device) {
                        if (!empty($allInterfacesDevices[$device])) {
                            $configuredInterface = $allInterfaces[$allInterfacesDevices[$device]];
                            if (!empty($configuredInterface->enable)) {
                                // set device enabled if any member is
                                $interfaceNode->enable = (string)$configuredInterface->enable;
                            }
                        }
                    }
                    // only add unconfigured devices
                    if (empty($allInterfacesDevices[$interface])) {
                        $allInterfaces[$interface] = $interfaceNode;
                    }
                }
            }

            // collect this items options
            foreach ($allInterfaces as $key => $value) {
                // use filters to determine relevance
                $isMatched = true;
                foreach ($this->internalFilters as $filterKey => $filterData) {
                    if (isset($value->$filterKey)) {
                        $fieldData = $value->$filterKey;
                    } else {
                        // not found, might be a boolean.
                        $fieldData = "0";
                    }

                    if (!preg_match($filterData, $fieldData)) {
                        $isMatched = false;
                    }
                }
                if ($isMatched) {
                    self::$internalStaticOptionList[$this->internalCacheKey][$key] =
                        !empty($value->descr) ? (string)$value->descr : strtoupper($key);
                }
            }
            natcasesort(self::$internalStaticOptionList[$this->internalCacheKey]);
        }
        $this->internalOptionList = self::$internalStaticOptionList[$this->internalCacheKey];
    }

    private function updateInternalCacheKey()
    {
        $tmp  = serialize($this->internalFilters);
        $tmp .= (string)$this->internalAllowDynamic;
        $tmp .= $this->internalAddParentDevices ? "Y" : "N";
        $this->internalCacheKey = md5($tmp);
    }
    /**
     * set filters to use (in regex) per field, all tags are combined
     * and cached for the next object using the same filters
     * @param $filters filters to use
     */
    public function setFilters($filters)
    {
        if (is_array($filters)) {
            $this->internalFilters = $filters;
            $this->updateInternalCacheKey();
        }
    }

    /**
     * add parent devices to the selection in case the parent has no configuration
     * @param $value boolean value 0/1
     */
    public function setAddParentDevices($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalAddParentDevices = true;
        } else {
            $this->internalAddParentDevices = false;
        }
        $this->updateInternalCacheKey();
    }

    /**
     * select if dynamic (hotplug) interfaces maybe selectable
     * @param $value Y/N/S (Yes, No, Static)
     */
    public function setAllowDynamic($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalAllowDynamic = 1;
        } elseif (trim(strtoupper($value)) == "S") {
            $this->internalAllowDynamic = 2;
        } else {
            $this->internalAllowDynamic = 0;
        }
        $this->updateInternalCacheKey();
    }
}
