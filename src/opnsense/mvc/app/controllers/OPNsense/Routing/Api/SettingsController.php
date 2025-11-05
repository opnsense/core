<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Routing\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Routing\Gateways';
    protected static $internalModelName = 'gateways';

    public function reconfigureAction()
    {
        $result = ["status" => "failed"];
        if ($this->request->isPost()) {
            (new Backend())->configdRun('interface routes configure');
            $result = ["status" => "ok"];
        }

        return $result;
    }

    public function searchGatewayAction()
    {
        $cfg = Config::getInstance()->object();
        $ifconfig = json_decode((new Backend())->configdRun('interface list ifconfig'), true);
        $gateways_status = json_decode((new Backend())->configdRun('interface gateways status'), true);
        $gateways = array_values($this->getModel()->gatewaysIndexedByName(true, false, true));
        $down_gateways = !empty((string)$cfg->system->gw_switch_default) ? array_map(function ($gw) {
            if (str_contains($gw['status'], 'down')) {
                return $gw['name'];
            }
        }, $gateways_status) : [];

        $default_gwv4 = $this->getModel()->getDefaultGW($down_gateways, 'inet');
        $default_gwv6 = $this->getModel()->getDefaultGW($down_gateways, 'inet6');

        foreach ($gateways as $idx => $gateway) {
            if (empty($gateway['uuid'])) {
                $gateways[$idx]['uuid'] = $gateway['name'];
            }

            /*
             * Flags used by view to filter or format elements:
             *
             * This output does not consistently represent the model data
             * in types returned and actual values but is kept this way to
             * provide a compatible API experience for existing API users.
             */
            $gateways[$idx]['virtual'] = !empty($gateway['virtual']);
            $gateways[$idx]['disabled'] = !empty($gateway['disabled']);
            $gateways[$idx]['upstream'] = !empty($gateway['defaultgw']);
            $gateways[$idx]['defaultgw'] = false;
            foreach (['default_gwv4', 'default_gwv6'] as $default_gw) {
                /* gateway might be configured as defaultgw, whether it is active is determined here */
                if (!empty($$default_gw)) {
                    if ($gateway['name'] == $$default_gw['name']) {
                        $gateways[$idx]['defaultgw'] = true;
                    }
                }
            }

            /* format interface name */
            $gateways[$idx]['interface_descr'] = (string)$cfg->interfaces->{$gateway['interface']}->descr ?: strtoupper($gateway['interface']);

            /* parse gateway and monitoring status */
            $i = array_search($gateway['name'], array_column($gateways_status, 'name'));
            $gateways[$idx]['status'] = $i !== false ? $gateways_status[$i]['status_translated'] : 'Pending';
            foreach (['delay', 'stddev', 'loss'] as $status_kw) {
                $gateways[$idx][$status_kw] = $i !== false ? $gateways_status[$i][$status_kw] : '~';
            }
            $gateways[$idx]['label_class'] = 'fa fa-plug text-default';
            if ($i !== false) {
                if (str_contains($gateways_status[$i]['status'], 'down')) {
                    $gateways[$idx]['label_class'] = 'fa fa-plug text-danger';
                } elseif (str_contains($gateways_status[$i]['status'], 'loss') || str_contains($gateways_status[$i]['status'], 'delay')) {
                    $gateways[$idx]['label_class'] = 'fa fa-plug text-warning';
                } elseif (str_contains($gateways_status[$i]['status'], 'none')) {
                    $gateways[$idx]['label_class'] = 'fa fa-plug text-success';
                }
            } elseif (empty($gateway['disabled']) && !empty($gateway['monitor_disable'])) {
                $gateways[$idx]['label_class'] = 'fa fa-plug text-success';
            }

            /* warn about misconfigured gateways */
            if (empty($gateway['fargw']) && array_key_exists('gateway', $gateway)) {
                if (Util::isIpAddress($gateway['gateway'])) {
                    /* exclude non-static entries in the config */
                    $proto = $gateway['ipprotocol'] === 'inet' ? 'ipaddr' : 'ipaddrv6';
                    $ip = (string)$cfg->interfaces->{$gateway['interface']}->{$proto};
                    $include = (!empty($ip) && Util::isIpAddress($ip));
                    if ($include && array_key_exists($gateway['if'], $ifconfig)) {
                        $ipproto = $gateway['ipprotocol'] === 'inet' ? 'ipv4' : 'ipv6';
                        $subnets = [];

                        foreach ($ifconfig[$gateway['if']][$ipproto] as $ip) {
                            if (!empty($ip['ipaddr']) && !empty($ip['subnetbits'])) {
                                $subnets[] = $ip['ipaddr'] . '/' . $ip['subnetbits'];
                            }
                        }

                        $match = false;
                        foreach ($subnets as $subnet) {
                            if (Util::isIPInCIDR($gateway['gateway'], $subnet)) {
                                $match = true;
                                break;
                            }
                        }

                        if (empty($subnets) || !$match) {
                            $gateways[$idx]['status'] = gettext('Misconfigured Gateway IP');
                            $gateways[$idx]['label_class'] = 'fa fa-plug text-warning';
                        }
                    }
                }
            }

            if (empty($gateway['is_loopback']) && empty($gateway['if'])) {
                $gateways[$idx]['status'] = gettext('No interface attached');
                $gateways[$idx]['label_class'] = 'fa fa-plug text-warning';
            }
        }

        return $this->searchRecordsetBase($gateways);
    }

    public function getGatewayAction($uuid = null)
    {
        if (!$this->isValidUUID($uuid)) {
            /* uuid is likely a gateway name (legacy config) */
            $gateway = $this->getModel()->gatewaysIndexedByName(true, false, true)[$uuid] ?? [];
            if (!empty($gateway)) {
                if (!empty($gateway['dynamic'])) {
                    $gateway['gateway'] = null;
                }
                $node = $this->getModel()->gateway_item->Add();
                $node->setNodes($gateway);
                return ['gateway_item' => $node->getNodes()];
            } else {
                /* make sure getBase() returns a node */
                $uuid = null;
            }
        }

        return $this->getBase('gateway_item', 'gateway_item', $uuid);
    }

    public function setGatewayAction($uuid)
    {
        if (!$this->isValidUUID($uuid)) {
            $mdl = $this->getModel();
            $uuid = $mdl->gateway_item->generateUUID();
        }

        $result = $this->setBase('gateway_item', 'gateway_item', $uuid);

        return $result;
    }

    public function addGatewayAction()
    {
        return $this->addBase("gateway_item", "gateway_item");
    }

    public function delGatewayAction($uuid)
    {
        $result = ["result" => "failed"];
        if ($this->request->isPost()) {
            if ($uuid != null) {
                $gateway = $this->getModel()->getNodeByReference('gateway_item.' . $uuid);
                $cfg = Config::getInstance()->object();
                foreach ($cfg->interfaces->children() as $tag => $interface) {
                    if ((string)$interface->gateway == (string)$gateway->name) {
                        throw new UserException(sprintf(
                            gettext("Gateway %s cannot be deleted because it is in use on Interface '%s'"),
                            $gateway->name,
                            $interface->descr ?? $tag
                        ));
                    }
                }

                $groups = [];
                foreach ($cfg->gateways->children() as $tag => $gw_group) {
                    if ($tag == 'gateway_group' && !empty($gw_group)) {
                        foreach ($gw_group->item as $item) {
                            $name = explode("|", (string)$item);
                            if ($name[0] == $gateway->name) {
                                $groups[] = (string)$gw_group->name;
                            }
                        }
                    }
                }

                if (!empty($groups)) {
                    throw new UserException(sprintf(
                        gettext("Gateway %s cannot be deleted because it is in use on Gateway Group(s) '%s'"),
                        $gateway->name,
                        implode(', ', $groups)
                    ));
                }

                $routes = [];
                foreach ($cfg->staticroutes->children() as $route) {
                    if (!empty($route)) {
                        if ((string)$route->gateway == $gateway->name) {
                            $routes[] = (string)$route->network;
                        }
                    }
                }

                if (!empty($routes)) {
                    throw new UserException(sprintf(
                        gettext("Gateway %s cannot be deleted because it is in use on Static Route(s) '%s'"),
                        $gateway->name,
                        implode(', ', $routes)
                    ));
                }

                $result = $this->delBase('gateway_item', $uuid);
            }
        }

        return $result;
    }

    public function toggleGatewayAction($uuid, $enabled = null)
    {
        /* mimick default API "enable" behaviour by inverting.
         * it's not enough to invert the $enabled parameter, since
         * the toggle might be implicit.
         */
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference('gateway_item' . '.' . $uuid);
                if ($node != null) {
                    $result['changed'] = true;
                    if ($enabled == "0" || $enabled == "1") {
                        /* invert "enabled" */
                        $disabled = $enabled == "0" ? "1" : "0";
                        $result['result'] = empty($disabled) ? "Enabled" : "Disabled";
                        $result['changed'] = (string)$node->disabled !== $disabled;
                        $node->disabled = $disabled;
                    } elseif ($enabled !== null) {
                        // failed
                        $result['changed'] = false;
                    } elseif ((string)$node->disabled == "0") {
                        $result['result'] = "Disabled";
                        $node->disabled = "1";
                    } else {
                        $result['result'] = "Enabled";
                        $node->disabled = "0";
                    }
                    // if item has toggled, serialize to config and save
                    if ($result['changed']) {
                        $this->save();
                    }
                }
            }
        }
        return $result;
    }
}
