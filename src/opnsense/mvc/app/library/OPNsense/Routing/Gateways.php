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
    var $ifconfig = array();

    /**
     * Construct new gateways object
     */
    public function __construct()
    {
        $this->configHandle = Config::getInstance()->object();
    }

    /**
     * @param array $payload containing serialized ifconfig data
     */
    public function setIfconfig($payload)
    {
        $this->ifconfig = $payload;
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
     * return all defined gateways
     * @return array
     */
    public function getGateways()
    {
        $result = array();
        $definedIntf = $this->getDefinedInterfaces();
        $dynamic_gw = array();
        $gatewaySeq = 1;
        // iterate configured gateways
        if (!empty($this->configHandle->gateways)) {
              foreach ($this->configHandle->gateways->children() as $tag => $gateway) {
                  $gw_arr = array();
                  foreach ($gateway as $key => $value) {
                      $gw_arr[(string)$key] = (string)$value;
                  }
                  $gw_arr['priority'] = 1; // XXX define in gateway
                  if ($tag == "gateway_item") {
                      $gw_arr["if"] = $definedIntf[$gw_arr["interface"]]['if'];
                      if (Util::isIpAddress($gateway->gateway)) {
                          $gwkey = sprintf("%d%010d", $gw_arr['priority'], $gatewaySeq);
                          $result[$gwkey] = $gw_arr;
                      } else {
                          // dynamic gateways might have settings, temporary store
                          if (empty($dynamic_gw[(string)$gateway->interface])) {
                              $dynamic_gw[(string)$gateway->interface] = array();
                          }
                          $dynamic_gw[(string)$gateway->interface][] = $gw_arr;
                      }
                  }
                  $gatewaySeq++;
              }
        }
        // add dynamic gateways
        foreach ($definedIntf as $ifname => $ifcfg) {
            foreach (["inet", "inet6"] as $ipproto) {
                // filename suffix and interface type as defined in the interface
                $fsuffix = $ipproto == "inet6" ? "v6" : "";
                $ctype = $ipproto == "inet" ? $ifcfg['ipaddr'] : $ifcfg['ipaddrv6'];
                // default configuration, when not set in gateway_item
                $thisconf = [
                    "priority" => 1,
                    "interface" => $ifname,
                    "weight" => 1,
                    "ipprotocol" => $ipproto,
                    "name" => strtoupper("{$ifname}_{$ctype}"),
                    "descr" => "Interface " . strtoupper("{$ifname}_{$ctype}") . " Gateway",
                    "if" => $ifcfg['if'],
                    "defaultgw" => file_exists("/tmp/{$ifcfg['if']}_defaultgw".$fsuffix)
                ];
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
                // dynamic gateways dump their addres in /tmp/[IF]_router[FSUFFIX]
                if (file_exists("/tmp/{$ifcfg['if']}_router".$fsuffix)) {
                    $thisconf['gateway'] = trim(@file_get_contents("/tmp/{$ifcfg['if']}_router".$fsuffix));
                    $gwkey = sprintf("%d%010d", $gw_arr['priority'], $gatewaySeq);
                    $result[$gwkey] = $thisconf;
                    $gatewaySeq++;
                }
            }
        }
        // sort by priority
        ksort($result);
        return $result;
    }

}
