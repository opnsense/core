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

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Routing\Gateways;

class OverviewController extends ApiControllerBase
{
    public function ifinfoAction()
    {
        $cfg = Config::getInstance()->object();
        $backend = new Backend();
        $gateways = new Gateways();

        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true);
        $routes = json_decode($backend->configdRun('interface routes list -n json'), true);

        $assigned = [];
        $result = [];

        /* merge config with ifconfig */
        foreach ($cfg->interfaces->children() as $key => $node) {
            $if = (string)$node->if;
            if (!empty($if) && !empty($ifconfig[$if])) {
                $ifconfig[$if]['if_ident'] = $key;
                $ifconfig[$if]['descr'] = (string)$node->descr ?: strtoupper($key);
                $ifconfig[$if]['enabled'] = !empty((string)$node->enable);
                $assigned[$if] = $if;
            }
        }

        foreach($routes as $route) {
            if (!empty($route['netif']) && in_array($route['netif'], $assigned)) {
                $ifconfig[$route['netif']]['routes'][] = $route['destination'];
            }
        }

        error_log(print_r($ifconfig, TRUE));

        /* determine status */
        foreach ($ifconfig as $if => $config) {
            if (!in_array($if, $assigned)) {
                /* XXX mark as unassigned */
                continue;
            }

            $ifinfo = [];
            
            $ifinfo['device'] = $if;
            $ifinfo['if_ident'] = $config['if_ident'];
            $ifinfo['descr'] = $config['descr'];
            $ifinfo['enabled'] = $config['enabled'];
            $ifinfo['status'] = (isset($config['flags']) && in_array('up', $config['flags'])) ? 'up' : 'down';
            /* XXX: handle both ipv4 + ipv6 */
            $ifinfo['gateway'] = $gateways->getInterfaceGateway($ifinfo['if_ident']);
            $ifinfo['routes'] = $config['routes'] ?? [];

            /* parse IP configuration */
            

            $result[] = $ifinfo;
        }

        return $this->searchRecordsetBase($result);
    }
}