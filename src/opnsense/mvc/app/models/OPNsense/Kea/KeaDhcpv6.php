<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Kea;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Core\File;
use OPNsense\Firewall\Util;

class KeaDhcpv6 extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // validate changed reservations
        foreach ($this->reservations->reservation->iterateItems() as $reservation) {
            if (!$validateFullModel && !$reservation->isFieldChanged()) {
                continue;
            }
            $key = $reservation->__reference;
            $subnet = "";
            $subnet_node = $this->getNodeByReference("subnets.subnet6.{$reservation->subnet}");
            if ($subnet_node) {
                $subnet = $subnet_node->subnet->getValue();
            }
            if (!Util::isIPInCIDR($reservation->ip_address->getValue(), $subnet)) {
                $messages->appendMessage(new Message(gettext("Address not in specified subnet"), $key . ".ip_address"));
            }
        }
        // validate changed subnets
        $this_interfaces = $this->general->interfaces->getValues();
        foreach ($this->subnets->subnet6->iterateItems() as $subnet) {
            if (!$validateFullModel && !$subnet->isFieldChanged()) {
                continue;
            }
            $key = $subnet->__reference;
            if (!in_array($subnet->interface->getValue(), $this_interfaces)) {
                $messages->appendMessage(
                    new Message(gettext("Interface not configured in general settings"), $key . ".interface")
                );
            }
        }

        return $messages;
    }

    public function isEnabled()
    {
        return $this->general->enabled->isEqual('1') && !$this->general->interfaces->isEmpty();
    }

    /**
     * should filter rules be enabled
     * @return bool
     */
    public function fwrulesEnabled()
    {
        return  $this->general->enabled->isEqual('1') &&
                $this->general->fwrules->isEqual('1') &&
                !$this->general->interfaces->isEmpty();
    }

    /**
     *
     */
    private function getConfigPhysicalInterfaces()
    {
        $result = [];
        $cfg = Config::getInstance()->object();
        foreach ($this->general->interfaces->getValues() as $if) {
            if (isset($cfg->interfaces->$if) && !empty($cfg->interfaces->$if->if)) {
                $result[] = (string)$cfg->interfaces->$if->if;
            }
        }
        return $result;
    }

    private function getConfigThisServerHostname()
    {
        $hostname = $this->ha->this_server_name->getValue();
        if (empty($hostname)) {
            $hostname = (string)Config::getInstance()->object()->system->hostname;
        }
        return $hostname;
    }

    private function getConfigSubnets()
    {
        $cfg = Config::getInstance()->object();
        $result = [];
        $subnet_id = 1;
        foreach ($this->subnets->subnet6->iterateItems() as $subnet_uuid => $subnet) {
            $record = [
                'id' => $subnet_id++,
                'subnet' => $subnet->subnet->getValue(),
                'option-data' => [],
                'pools' => [],
                'pd-pools' => [],
                'reservations' => []
            ];
            $if = $subnet->interface->getValue();
            if (isset($cfg->interfaces->$if) && !empty($cfg->interfaces->$if->if)) {
                $record['interface'] = (string)$cfg->interfaces->$if->if;
            }
            if (!$subnet->{'pd-allocator'}->isEmpty()) {
                $record['pd-allocator'] = $subnet->{'pd-allocator'}->getValue();
            }
            if (!$subnet->allocator->isEmpty()) {
                $record['allocator'] = $subnet->allocator->getValue();
            }
            /* standard option-data elements */
            foreach ($subnet->option_data->iterateItems() as $key => $value) {
                $target_fieldname = str_replace('_', '-', $key);
                if (!$value->isEqual('')) {
                    $record['option-data'][] = [
                        'name' => $target_fieldname,
                        'data' => (string)$value
                    ];
                } elseif ($key == 'domain_name') {
                    $record['option-data'][] = [
                        'name' => $target_fieldname,
                        'data' => (string)Config::getInstance()->object()->system->domain
                    ];
                }
            }
            /* add pools */
            foreach (array_filter(explode("\n", $subnet->pools->getValue())) as $pool) {
                $record['pools'][] = ['pool' => $pool];
            }
            /* add pd-pools */
            foreach ($this->pd_pools->pd_pool->iterateItems() as $key => $pdpool) {
                if ($pdpool->subnet != $subnet_uuid) {
                    continue;
                }
                $record['pd-pools'][] = [
                    'prefix' => $pdpool->prefix->getValue(),
                    'prefix-len' => (int)$pdpool->prefix_len->getValue(),
                    'delegated-len' => (int)$pdpool->delegated_len->getValue()
                ];
            }
            /* static reservations */
            foreach ($this->reservations->reservation->iterateItems() as $key => $reservation) {
                if (!$reservation->subnet->isEqual($subnet_uuid)) {
                    continue;
                }
                $res = ['option-data' => []];
                foreach (['duid', 'hostname'] as $key) {
                    if (!$reservation->$key->isEmpty()) {
                        $res[str_replace('_', '-', $key)] = $reservation->$key->getValue();
                    }
                }
                $res['ip-addresses'] = explode(',', $reservation->ip_address->getValue());
                if (!$reservation->domain_search->isEmpty()) {
                    $res['option-data'][] = [
                        'name' => 'domain-search',
                        'data' => $reservation->domain_search->getValue()
                    ];
                }
                $record['reservations'][] = $res;
            }
            $result[] = $record;
        }
        return $result;
    }

    private function getExpiredLeasesProcessingConfig()
    {
        $config = [];
        $lexpireFields = iterator_to_array($this->lexpire->iterateItems());
        foreach ($lexpireFields as $fieldName => $fieldValue) {
            if (!$fieldValue->isEqual('')) {
                $keaFieldName = str_replace('_', '-', $fieldName);
                $config[$keaFieldName] = (int)$fieldValue->getValue();
            }
        }
        return empty($config) ? null : $config;
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp6.conf')
    {
        $cnf = [
            'Dhcp6' => [
                'valid-lifetime' => (int)$this->general->valid_lifetime->getValue(),
                'interfaces-config' => [
                    'interfaces' => $this->getConfigPhysicalInterfaces()
                ],
                'lease-database' => [
                    'type' => 'memfile',
                    'persist' => true,
                ],
                'control-socket' => [
                    'socket-type' => 'unix',
                    'socket-name' => '/var/run/kea/kea6-ctrl-socket'
                ],
                'loggers' => [
                    [
                        'name' => 'kea-dhcp6',
                        'output_options' => [
                            [
                                'output' => 'syslog'
                            ]
                        ],
                        'severity' => 'INFO',
                    ]
                ],
                'subnet6' => $this->getConfigSubnets(),
            ]
        ];
        $expiredLeasesConfig = $this->getExpiredLeasesProcessingConfig();
        if ($expiredLeasesConfig !== null) {
            $cnf['Dhcp6']['expired-leases-processing'] = $expiredLeasesConfig;
        }
        if (!(new KeaCtrlAgent())->general->enabled->isEmpty()) {
            $cnf['Dhcp6']['hooks-libraries'] = [];
            $cnf['Dhcp6']['hooks-libraries'][] = [
                'library' => '/usr/local/lib/kea/hooks/libdhcp_lease_cmds.so'
            ];
            if (!$this->ha->enabled->isEmpty()) {
                $record = [
                    'library' => '/usr/local/lib/kea/hooks/libdhcp_ha.so',
                    'parameters' => [
                        'high-availability' => [
                            [
                                'this-server-name' => $this->getConfigThisServerHostname(),
                                'mode' => 'hot-standby',
                                'heartbeat-delay' => 10000,
                                'max-response-delay' => 60000,
                                'max-ack-delay' => 5000,
                                'max-unacked-clients' => (int)$this->ha->max_unacked_clients->getValue(),
                                'sync-timeout' => 60000,
                            ]
                        ]
                    ]
                ];
                foreach ($this->ha_peers->peer->iterateItems() as $peer) {
                    if (!isset($record['parameters']['high-availability'][0]['peers'])) {
                        $record['parameters']['high-availability'][0]['peers'] = [];
                    }
                    $record['parameters']['high-availability'][0]['peers'][] = array_map(
                        fn($x) => $x->getValue(),
                        iterator_to_array($peer->iterateItems())
                    );
                }
                $cnf['Dhcp6']['hooks-libraries'][] = $record;
            }
        }
        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT), 0600);
    }
}
