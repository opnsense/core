<?php

/*
 * Copyright (C) 2023-2025 Deciso B.V.
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

class KeaDhcpv4 extends BaseModel
{
    /**
     * Before persisting data into the model, update option_data fields for selected subnets.
     * setNodes() is used in most cases (at least from our base controller), which should make this a relatvily
     * save entrypoint to enforce some data.
     */
    public function setNodes($data)
    {
        $ifconfig = json_decode((new Backend())->configdRun('interface list ifconfig'), true) ?? [];
        foreach ($this->subnets->subnet4->iterateItems() as $subnet) {
            if (!$subnet->option_data_autocollect->isEmpty()) {
                // find first possible candidate to use as a gateway.
                $host_ip = null;
                foreach ($ifconfig as $if => $details) {
                    foreach ($details['ipv4'] as $net) {
                        if (Util::isIPInCIDR($net['ipaddr'], $subnet->subnet->getValue())) {
                            $host_ip = $net['ipaddr'];
                            break 2;
                        }
                    }
                }

                if (!empty($host_ip)) {
                    $subnet->option_data->routers = $host_ip;
                    $subnet->option_data->domain_name_servers = $host_ip;
                    $subnet->option_data->ntp_servers = $host_ip;
                }
            }
        }
        return parent::setNodes($data);
    }

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
            $subnet_node = $this->getNodeByReference("subnets.subnet4.{$reservation->subnet}");
            if ($subnet_node) {
                $subnet = $subnet_node->subnet->getValue();
            }
            if (!Util::isIPInCIDR($reservation->ip_address->getValue(), $subnet)) {
                $messages->appendMessage(new Message(gettext("Address not in specified subnet"), $key . ".ip_address"));
            }
        }

        // Enforce that ddns qualifying suffix ends with a dot when set
        foreach ($this->subnets->subnet4->iterateItems() as $subnet) {
            if (!$validateFullModel && !$subnet->isFieldChanged()) {
                continue;
            }

            $suffix = $subnet->ddns_options->qualifying_suffix;
            if (!($subnet->ddns_options->send_updates->isEqual('1')) &&
                !($suffix->isEmpty()) && !str_ends_with($suffix->getValue(), '.')) {
                $messages->appendMessage(
                    new Message(
                        gettext('DDNS qualifying suffix must end with a dot.'),
                        $subnet->__reference . '.ddns_options.qualifying_suffix'
                    )
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

    /**
     * @param FieldType $node node to iterate
     * @param bool $defaults add defaults when set
     * @return array
     */
    private function collectOptionData($node, $defaults = false)
    {
        $result = [];
        foreach ($node->iterateItems() as $key => $value) {
            $target_fieldname = str_replace('_', '-', $key);
            if (!$value->isEqual('')) {
                if ($key == 'static_routes') {
                    $value = implode(',', array_map('trim', explode(',', $value->getValue())));
                }
                $result[] = [
                    'name' => $target_fieldname,
                    'data' => (string)$value
                ];
            } elseif ($key == 'domain_name' && $defaults) {
                $result[] = [
                    'name' => $target_fieldname,
                    'data' => (string)Config::getInstance()->object()->system->domain
                ];
            }
        }
        return $result;
    }

    private function getConfigSubnets()
    {
        $result = [];
        $subnet_id = 1;
        foreach ($this->subnets->subnet4->iterateItems() as $subnet_uuid => $subnet) {
            $record = [
                'id' => $subnet_id++,
                'subnet' => $subnet->subnet->getValue(),
                'next-server' => $subnet->next_server->getValue(),
                'match-client-id' => !$subnet->{'match-client-id'}->isEmpty(),
                'option-data' => $this->collectOptionData($subnet->option_data, true),
                'pools' => [],
                'reservations' => []
            ];

            // Conditionally include DDNS settings only when send-updates is enabled,
            // and only include fields that have meaningful values.;
            if ($subnet->ddns_options->send_updates->isEqual('1')) {
                $record['ddns-send-updates'] = true;
                if (!($subnet->ddns_options->qualifying_suffix->isEmpty())) {
                    $record['ddns-qualifying-suffix'] = $subnet->ddns_options->qualifying_suffix->getValue();
                }
                if ($subnet->ddns_options->update_on_renew->isEqual('1')) {
                    $record['ddns-update-on-renew'] = true;
                }
                if (!($subnet->ddns_options->conflict_resolution_mode->isEmpty())) {
                    $record['ddns-conflict-resolution-mode'] = $subnet->ddns_options->conflict_resolution_mode->getValue();
                }
            }
            /* add pools */
            foreach (array_filter(explode("\n", $subnet->pools->getValue())) as $pool) {
                $record['pools'][] = ['pool' => $pool];
            }
            /* static reservations */
            foreach ($this->reservations->reservation->iterateItems() as $key => $reservation) {
                if ($reservation->subnet != $subnet_uuid) {
                    continue;
                }
                $res = [];
                foreach (['ip_address', 'hostname'] as $key) {
                    if (!$reservation->$key->isEmpty()) {
                        $res[str_replace('_', '-', $key)] = $reservation->$key->getValue();
                    }
                }
                if (!$reservation->hw_address->isEmpty()) {
                    $res['hw-address'] = str_replace('-', ':', $reservation->hw_address->getValue());
                }

                // Add DHCP option-data elements for reservations
                $optdata = $this->collectOptionData($reservation->option_data);
                if (!empty($optdata)) {
                    $res['option-data'] = $optdata;
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
                $config[$keaFieldName] = $fieldValue->asInt();
            }
        }
        return empty($config) ? null : $config;
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp4.conf')
    {
        $cnf = [
            'Dhcp4' => [
                'valid-lifetime' => $this->general->valid_lifetime->asInt(),
                'interfaces-config' => [
                    'interfaces' => $this->getConfigPhysicalInterfaces(),
                    'dhcp-socket-type' => $this->general->dhcp_socket_type->getValue()
                ],
                'lease-database' => [
                    'type' => 'memfile',
                    'persist' => true,
                ],
                'control-socket' => [
                    'socket-type' => 'unix',
                    'socket-name' => '/var/run/kea/kea4-ctrl-socket'
                ],
                'dhcp-ddns' => [
                    'enable-updates' => false,
                    'server-ip' => '127.0.0.1',
                    'server-port' => 53001,
                ],
                'loggers' => [
                    [
                        'name' => 'kea-dhcp4',
                        'output_options' => [
                            [
                                'output' => 'syslog'
                            ]
                        ],
                        'severity' => 'INFO',
                    ]
                ],
                'subnet4' => $this->getConfigSubnets(),
            ]
        ];
        $expiredLeasesConfig = $this->getExpiredLeasesProcessingConfig();
        if ($expiredLeasesConfig !== null) {
            $cnf['Dhcp4']['expired-leases-processing'] = $expiredLeasesConfig;
        }

        foreach ($this->subnets->subnet4->iterateItems() as $subnet) {
            if ($subnet->ddns_options->send_updates->isEqual('1')) {
                $cnf['Dhcp4']['dhcp-ddns']['enable-updates'] = true;
                break;
            }
        }

        if (!(new KeaCtrlAgent())->general->enabled->isEmpty()) {
            $cnf['Dhcp4']['hooks-libraries'] = [];
            $cnf['Dhcp4']['hooks-libraries'][] = [
                'library' => '/usr/local/lib/kea/hooks/libdhcp_lease_cmds.so'
            ];
            $cnf['Dhcp4']['hooks-libraries'][] = [
                'library' => '/usr/local/lib/kea/hooks/libdhcp_host_cmds.so'
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
                                'max-unacked-clients' => $this->ha->max_unacked_clients->asInt(),
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
                $cnf['Dhcp4']['hooks-libraries'][] = $record;
            }
        }
        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT), 0600);
    }
}
