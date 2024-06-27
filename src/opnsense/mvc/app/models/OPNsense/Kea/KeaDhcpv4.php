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
            if (!empty((string)$subnet->option_data_autocollect)) {
                // find first possible candidate to use as a gateway.
                $host_ip = null;
                foreach ($ifconfig as $if => $details) {
                    foreach ($details['ipv4'] as $net) {
                        if (Util::isIPInCIDR($net['ipaddr'], (string)$subnet->subnet)) {
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
                $subnet = (string)$subnet_node->subnet;
            }
            if (!Util::isIPInCIDR((string)$reservation->ip_address, $subnet)) {
                $messages->appendMessage(new Message(gettext("Address not in specified subnet"), $key . ".ip_address"));
            }
        }

        return $messages;
    }

    public function isEnabled()
    {
        return (string)$this->general->enabled == '1' && !empty((string)(string)$this->general->interfaces);
    }

    /**
     * should filter rules be enabled
     * @return bool
     */
    public function fwrulesEnabled()
    {
        return  (string)$this->general->enabled == '1' &&
                (string)$this->general->fwrules == '1' &&
                !empty((string)(string)$this->general->interfaces);
    }

    /**
     *
     */
    private function getConfigPhysicalInterfaces()
    {
        $result = [];
        $cfg = Config::getInstance()->object();
        foreach (explode(',', $this->general->interfaces) as $if) {
            if (isset($cfg->interfaces->$if) && !empty($cfg->interfaces->$if->if)) {
                $result[] = (string)$cfg->interfaces->$if->if;
            }
        }
        return $result;
    }

    private function getConfigThisServerHostname()
    {
        $hostname = (string)$this->ha->this_server_name;
        if (empty($hostname)) {
            $hostname = (string)Config::getInstance()->object()->system->hostname;
        }
        return $hostname;
    }

    private function getConfigSubnets()
    {
        $result = [];
        $subnet_id = 1;
        foreach ($this->subnets->subnet4->iterateItems() as $subnet_uuid => $subnet) {
            $record = [
                'id' => $subnet_id++,
                'subnet' => (string)$subnet->subnet,
                'next-server' => (string)$subnet->next_server,
                'option-data' => [],
                'pools' => [],
                'reservations' => []
            ];
            /* standard option-data elements */
            foreach ($subnet->option_data->iterateItems() as $key => $value) {
                $target_fieldname = str_replace('_', '-', $key);
                if ((string)$value != '') {
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
            foreach (array_filter(explode("\n", $subnet->pools)) as $pool) {
                $record['pools'][] = ['pool' => $pool];
            }
            /* static reservations */
            foreach ($this->reservations->reservation->iterateItems() as $key => $reservation) {
                if ($reservation->subnet != $subnet_uuid) {
                    continue;
                }
                $res = [];
                foreach (['hw_address', 'ip_address', 'hostname'] as $key) {
                    if (!empty((string)$reservation->$key)) {
                        $res[str_replace('_', '-', $key)] = (string)$reservation->$key;
                    }
                }
                $record['reservations'][] = $res;
            }
            $result[] = $record;
        }
        return $result;
    }

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp4.conf')
    {
        $cnf = [
            'Dhcp4' => [
                'valid-lifetime' => (int)$this->general->valid_lifetime->__toString(),
                'interfaces-config' => [
                    'interfaces' => $this->getConfigPhysicalInterfaces()
                ],
                'lease-database' => [
                    'type' => 'memfile',
                    'persist' => true,
                ],
                'control-socket' => [
                    'socket-type' => 'unix',
                    'socket-name' => '/var/run/kea4-ctrl-socket'
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
        if (!empty((string)(new KeaCtrlAgent())->general->enabled)) {
            $cnf['Dhcp4']['hooks-libraries'] = [];
            $cnf['Dhcp4']['hooks-libraries'][] = [
                'library' => '/usr/local/lib/kea/hooks/libdhcp_lease_cmds.so'
            ];
            if (!empty((string)$this->ha->enabled)) {
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
                                'max-unacked-clients' => 5,
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
                        fn($x) => (string)$x,
                        iterator_to_array($peer->iterateItems())
                    );
                }
                $cnf['Dhcp4']['hooks-libraries'][] = $record;
            }
        }
        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT), 0600);
    }
}
