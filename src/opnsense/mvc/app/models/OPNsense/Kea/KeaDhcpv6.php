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
use OPNsense\Interface\Idassoc;

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
                if (!$subnet_node->dynamic_prefix->isEmpty()) {
                    $messages->appendMessage(
                        new Message(gettext("Reservations cannot be assigned to dynamic prefix subnets."), $key . ".subnet")
                    );
                    continue;
                }
                $subnet = $subnet_node->subnet->getValue();
            }
            if (!Util::isIPInCIDR($reservation->ip_address->getValue(), $subnet) && !$reservation->ip_address->isEmpty()) {
                $messages->appendMessage(new Message(gettext("Address not in specified subnet"), $key . ".ip_address"));
            }
            if (!Util::isIPv6PrefixInPrefix($reservation->prefix->getValue(), $subnet) && !$reservation->prefix->isEmpty()) {
                $messages->appendMessage(new Message(gettext("Prefix not in specified subnet"), $key . ".prefix"));
            }
            if ($reservation->ip_address->isEmpty() && $reservation->prefix->isEmpty()) {
                $messages->appendMessage(new Message(gettext("Either an IP address or a Prefix should be specified."), $key . ".ip_address"));
            }
            if (!$reservation->duid->isEmpty() && !$reservation->hw_address->isEmpty()) {
                $messages->appendMessage(new Message(gettext("Either a DUID or an MAC address should be specified, but not both"), $key . ".duid"));
            } elseif ($reservation->duid->isEmpty() && $reservation->hw_address->isEmpty()) {
                $messages->appendMessage(new Message(gettext("Either a DUID or an MAC address should be specified."), $key . ".duid"));
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
                    new Message(gettext('Interface is not selected in the general settings.'), $key . ".interface")
                );
            }
            if (!$subnet->dynamic_prefix->isEmpty()) {
                if (!$subnet->pools->isEmpty()) {
                    $messages->appendMessage(
                        new Message(gettext('Pools cannot be configured when dynamic prefix is enabled, they are automatically generated.'), $key . ".pools")
                    );
                }
            } else {
                foreach ($subnet->pools->checkSubnet($subnet->subnet->getValue()) as $pool) {
                    $messages->appendMessage(
                        new Message(sprintf(gettext('Pool "%s" not in specified subnet.'), $pool), $key . ".pools")
                    );
                }
            }
            if ($subnet->dynamic_prefix->isEmpty() && $subnet->subnet->isEmpty()) {
                $messages->appendMessage(
                    new Message(gettext('Subnet is required when dynamic prefix is disabled.'), $key . ".subnet")
                );
            }
            if (!$subnet->dynamic_prefix->isEmpty() && !$subnet->subnet->isEmpty()) {
                $messages->appendMessage(
                    new Message(gettext('Subnet must be empty when dynamic prefix is enabled.'), $key . ".subnet")
                );
            }
            if (!$subnet->dynamic_prefix->isEmpty()) {
                foreach ($this->subnets->subnet6->iterateItems() as $tmpsubnet) {
                    if ($key === $tmpsubnet->__reference) {
                        continue;
                    }
                    if (
                        !$tmpsubnet->dynamic_prefix->isEmpty() &&
                        $tmpsubnet->interface->isEqual($subnet->interface->getValue())
                    ) {
                        $messages->appendMessage(
                            new Message(gettext('Only one dynamic prefix subnet may be configured per interface.'), $key . ".interface")
                        );
                        break;
                    }
                }
                $dynamic_pd_pool_count = 0;
                foreach ($this->pd_pools->pd_pool->iterateItems() as $tmppool) {
                    if ($tmppool->subnet->isEqual($subnet->getAttribute('uuid'))) {
                        $dynamic_pd_pool_count++;
                    }
                }
                if ($dynamic_pd_pool_count > 1) {
                    $messages->appendMessage(
                        new Message(gettext('Only one PD pool may be configured for a dynamic prefix subnet.'), $key . ".dynamic_prefix")
                    );
                }
                // This validation is not ideal, but it prevents user error on initial dynamic subnet configuration
                if (empty(Idassoc::prefix($subnet->interface->getValue()))) {
                    $messages->appendMessage(
                        new Message(gettext('Interface has no identity association prefix configuration.'), $key . ".dynamic_prefix")
                    );
                }
            }
            if (!$subnet->option_data_autocollect->isEmpty()) {
                if (!$subnet->option_data->dns_servers->isEmpty()) {
                    $messages->appendMessage(
                        new Message(gettext('DNS servers must be empty when option data autocollect is enabled.'), $key . '.option_data.dns_servers'));
                }
                if (!$subnet->option_data->domain_search->isEmpty()) {
                    $messages->appendMessage(
                        new Message(gettext('Domain search must be empty when option data autocollect is enabled.'), $key . '.option_data.domain_search'));
                }
            }
        }
        // validate changed pd_pools
        foreach ($this->pd_pools->pd_pool->iterateItems() as $pool) {
            if (!$validateFullModel && !$pool->isFieldChanged()) {
                continue;
            }
            $key = $pool->__reference;
            // dynamic pd_pool validation
            if (($subnet_node = $this->getNodeByReference("subnets.subnet6.{$pool->subnet}")) !== null && !$subnet_node->dynamic_prefix->isEmpty()) {
                foreach ($this->pd_pools->pd_pool->iterateItems() as $tmppool) {
                    if ($key === $tmppool->__reference) {
                        continue;
                    }
                    if ($tmppool->subnet->isEqual($pool->subnet->getValue())) {
                        $messages->appendMessage(
                            new Message(gettext("Only one PD pool may be configured for a dynamic prefix subnet."), $key . ".subnet")
                        );
                        break;
                    }
                }
                if (!$pool->prefix->isEmpty()) {
                    $messages->appendMessage(
                        new Message(gettext("Prefix must be empty when attached to a dynamic prefix subnet."), $key . ".prefix")
                    );
                }
                if (!$pool->prefix_len->isEmpty()) {
                    $messages->appendMessage(
                        new Message(gettext("Prefix length must be empty when attached to a dynamic prefix subnet."), $key . ".prefix_len")
                    );
                }
                $idassoc = Idassoc::prefix($subnet_node->interface->getValue());
                if (!empty($idassoc['prefix_allocated'])) {
                    $pd_prefixes = Util::splitIPv6Prefix($idassoc['prefix_allocated']);
                    if (!empty($pd_prefixes[1])) {
                        $pd_prefix_len = (int)explode('/', $pd_prefixes[1], 2)[1];
                        if ($pd_prefix_len > 64) {
                            $messages->appendMessage(
                                new Message(
                                    sprintf(
                                        gettext('Dynamic prefix "%s" is too small to create a non-overlapping PD pool, split prefix length would be "%d".'),
                                        $idassoc['prefix_allocated'],
                                        $pd_prefix_len
                                    ),
                                    $key . ".delegated_len"
                                )
                            );
                        } elseif ($pool->delegated_len->asInt() < $pd_prefix_len) {
                            $messages->appendMessage(
                                new Message(
                                    sprintf(
                                        gettext('Delegated length %d must be longer than or equal to dynamic PD pool prefix length %d.'),
                                        $pool->delegated_len->asInt(),
                                        $pd_prefix_len
                                    ),
                                    $key . ".delegated_len"
                                )
                            );
                        }
                    }
                }
                continue;
            }
            // static pd_pool validation
            if ($pool->prefix_len->isEmpty()) {
                $messages->appendMessage(new Message(gettext("Prefix length is required."), $key . ".prefix_len"));
                continue;
            }
            if ($pool->prefix_len->asInt() > $pool->delegated_len->asInt()) {
                $messages->appendMessage(new Message(gettext("Delegated length must be longer than or equal to prefix length"), $key . ".delegated_len"));
            }
            $subnet = $pool->prefix->getValue() . "/" . $pool->prefix_len->getValue();
            $trange = Util::cidrToRange($subnet);
            if (empty($trange)) {
                $messages->appendMessage(new Message(gettext("Invalid Prefix specified"), $key . ".prefix"));
                continue;
            } elseif (!Util::isSubnetStrict($subnet)) {
                $messages->appendMessage(new Message(gettext("Invalid Pool boundaries, offered address is not the first address in the prefix."), $key . ".prefix"));
            }
            foreach ($this->pd_pools->pd_pool->iterateItems() as $tmppool) {
                if ($key === $tmppool->__reference) {
                    continue;
                }
                $tmpsubnet = $this->getNodeByReference("subnets.subnet6.{$tmppool->subnet}");
                if ($tmpsubnet !== null && !$tmpsubnet->dynamic_prefix->isEmpty()) {
                    continue;
                }
                $osubnet = $tmppool->prefix->getValue() . "/" . $tmppool->prefix_len->getValue();
                $orange = Util::cidrToRange($osubnet);
                if (empty($orange)) {
                    continue;
                }
                if (Util::isIPInCIDR($orange[0], $subnet) || Util::isIPInCIDR($trange[0], $osubnet)) {
                    $messages->appendMessage(new Message(gettext("Pool overlaps with an existing one."), $key . ".prefix"));
                }
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

    private function getConfigPhysicalInterfaces()
    {
        $result = [];
        foreach ($this->general->interfaces->getValues() as $interface) {
            $device = Util::getRealInterface($interface, 'inet6');
            if (!empty($device)) {
                $result[] = $device;
            }
        }
        return $result;
    }

    private function getConfigPrimaryAddress6(string $interface): ?string
    {
        $addresses = json_decode((new Backend())->configdpRun('interface address', [$interface]), true);
        foreach ($addresses[$interface] as $address) {
            if ($address['family'] === 'inet6') {
                return $address['address'];
            }
        }
        return null;
    }

    private function getConfigThisServerHostname()
    {
        $hostname = $this->ha->this_server_name->getValue();
        if (empty($hostname)) {
            $hostname = (string)Config::getInstance()->object()->system->hostname;
        }
        return $hostname;
    }

    private function getConfigSubnets($ddns_enabled = false, &$needs_no_leases_class = false)
    {
        $result = [];
        $subnet_id = 1;
        foreach ($this->subnets->subnet6->iterateItems() as $subnet_uuid => $subnet) {
            // If subnet is dynamic, seed an initial subnet value so KEA can start
            $if = $subnet->interface->getValue();
            $subnet_value = $subnet->subnet->getValue();
            $idassoc = [];
            if (!$subnet->dynamic_prefix->isEmpty()) {
                // XXX: If a subnet has been created for an interface that does not exist anymore,
                // or the interface was removed from the identity association but still exists in the KEA config,
                // there won't be a prefix and KEA will fail to start. Ideally this should be validated
                // in the core interface configuration, it cannot be validated inside KEA.
                $idassoc = Idassoc::prefix($if);
                $subnet_value = $idassoc['prefix_allocated'] ?? '';
            }
            $record = [
                'id' => $subnet_id++,
                'subnet' => $subnet_value,
                'option-data' => [],
                'pools' => [],
                'pd-pools' => [],
                'reservations' => []
            ];
            /* add valid-lifetime at this level if given */
            if ($subnet->valid_lifetime->isSet()) {
                $record['valid-lifetime'] = $subnet->valid_lifetime->asInt();
            }
            $device = Util::getRealInterface($subnet->interface->getValue(), 'inet6');
            if (!empty($device)) {
                $record['interface'] = $device;
            }
            if (!$subnet->{'pd-allocator'}->isEmpty()) {
                $record['pd-allocator'] = $subnet->{'pd-allocator'}->getValue();
            }
            if (!$subnet->allocator->isEmpty()) {
                $record['allocator'] = $subnet->allocator->getValue();
            }
            /* add description and other custom keys - not parsed by KEA */
            $record['user-context'] = ['uuid' => $subnet->getAttribute('uuid')];
            if (!$subnet->description->isEmpty()) {
                $record['user-context']['description'] = $subnet->description->getValue();
            }
            if (!$subnet->dynamic_prefix->isEmpty()) {
                // Used by hook script to know which subnets have a dynamic prefix, it reads the running conf from socket
                $record['user-context']['dynamic_prefix'] = true;
                $record['user-context']['prefix_valid'] = $idassoc['prefix_valid'] ?? false;
                $record['user-context']['prefix_source'] = $idassoc['prefix_source'] ?? $if;
                // If the prefix is temporary placeholder, we will not send leases to any client
                if (empty($idassoc['prefix_valid'])) {
                    $record['client-classes'] = ['NO_LEASES_PLEASE'];
                    $needs_no_leases_class = true;
                }
            }
            /* standard option-data elements */
            foreach ($subnet->option_data->iterateItems() as $key => $value) {
                $target_fieldname = str_replace('_', '-', $key);
                if (!$value->isEqual('')) {
                    $record['option-data'][] = [
                        'name' => $target_fieldname,
                        'data' => (string)$value
                    ];
                }
            }
            /* optionally collect system option-data, helpful for dynamic prefix configurations */
            if (!$subnet->option_data_autocollect->isEmpty()) {
                $domain = (string)Config::getInstance()->object()->system->domain;
                if (!empty($domain)) {
                    $record['option-data'][] = [
                        'name' => 'domain-search',
                        'data' => $domain
                    ];
                }

                $primary_address6 = $this->getConfigPrimaryAddress6($subnet->interface->getValue());
                if ($primary_address6 !== null) {
                    $record['option-data'][] = [
                        'name' => 'dns-servers',
                        'data' => $primary_address6
                    ];
                }
            }
            /* add pools */
            if (!$subnet->dynamic_prefix->isEmpty()) {
                if (!empty($idassoc['prefix_on_link'])) {
                    $record['pools'][] = ['pool' => $idassoc['prefix_on_link']];
                }
            } else {
                foreach ($subnet->pools->getValues() as $pool) {
                    $record['pools'][] = ['pool' => $pool];
                }
            }
            /* add pd-pools */
            foreach ($this->pd_pools->pd_pool->iterateItems() as $key => $pdpool) {
                if ($pdpool->subnet != $subnet_uuid) {
                    continue;
                }
                if (!$subnet->dynamic_prefix->isEmpty()) {
                    $pd_prefixes = Util::splitIPv6Prefix($record['subnet']);
                    if (empty($pd_prefixes[1])) {
                        continue;
                    }
                    [$pd_prefix, $pd_prefix_len] = explode('/', $pd_prefixes[1], 2);
                    $entry = [
                        'prefix' => $pd_prefix,
                        'prefix-len' => (int)$pd_prefix_len,
                        'delegated-len' => $pdpool->delegated_len->asInt()
                    ];
                } else {
                    $entry = [
                        'prefix' => $pdpool->prefix->getValue(),
                        'prefix-len' => $pdpool->prefix_len->asInt(),
                        'delegated-len' => $pdpool->delegated_len->asInt()
                    ];
                }
                /* add description and other custom keys - not parsed by KEA */
                $entry['user-context'] = ['uuid' => $pdpool->getAttribute('uuid')];
                if (!$pdpool->description->isEmpty()) {
                    $entry['user-context']['description'] = $pdpool->description->getValue();
                }
                $record['pd-pools'][] = $entry;
            }
            /* static reservations */
            foreach ($this->reservations->reservation->iterateItems() as $key => $reservation) {
                if (!$reservation->subnet->isEqual($subnet_uuid)) {
                    continue;
                }
                $res = ['option-data' => []];
                foreach (['duid', 'hw_address', 'hostname'] as $key) {
                    if (!$reservation->$key->isEmpty()) {
                        $res[str_replace('_', '-', $key)] = $reservation->$key->getValue();
                    }
                }
                if (!$reservation->ip_address->isEmpty()) {
                    $res['ip-addresses'] = $reservation->ip_address->getValues();
                }
                if (!$reservation->prefix->isEmpty()) {
                    $res['prefixes'] = $reservation->prefix->getValues();
                }
                if (!$reservation->domain_search->isEmpty()) {
                    $res['option-data'][] = [
                        'name' => 'domain-search',
                        'data' => $reservation->domain_search->getValue()
                    ];
                }
                /* append raw options */
                foreach ($reservation->option->getValues() as $uuid) {
                    $option = $this->getNodeByReference("options.option.$uuid");
                    if ($option === null) {
                        continue;
                    }
                    $entry = [
                        'code' => $option->code->asInt(),
                        'csv-format' => false,
                        'data' => $option->data->encodeValue(),
                        'always-send' => !$option->force->isEmpty(),
                    ];
                    /* add description and other custom keys - not parsed by KEA */
                    $entry['user-context'] = ['uuid' => $option->getAttribute('uuid')];
                    if (!$option->description->isEmpty()) {
                        $entry['user-context']['description'] = $option->description->getValue();
                    }
                    /* only conditionally send the option when a client option matches */
                    if (!$option->match_code->isEmpty()) {
                        $entry['client-classes'] = [$uuid];
                    }
                    $res['option-data'][] = $entry;
                }
                /* add description and other custom keys - not parsed by KEA */
                $res['user-context'] = ['uuid' => $reservation->getAttribute('uuid')];
                if (!$reservation->description->isEmpty()) {
                    $res['user-context']['description'] = $reservation->description->getValue();
                }
                $record['reservations'][] = $res;
            }
            /* append raw options */
            foreach ($subnet->option->getValues() as $uuid) {
                $option = $this->getNodeByReference("options.option.$uuid");
                if ($option === null) {
                    continue;
                }
                $entry = [
                    'code' => $option->code->asInt(),
                    'csv-format' => false,
                    'data' => $option->data->encodeValue(),
                    'always-send' => !$option->force->isEmpty(),
                ];
                /* add description and other custom keys - not parsed by KEA */
                $entry['user-context'] = ['uuid' => $option->getAttribute('uuid')];
                if (!$option->description->isEmpty()) {
                    $entry['user-context']['description'] = $option->description->getValue();
                }
                /* only conditionally send the option when a client option matches */
                if (!$option->match_code->isEmpty()) {
                    $entry['client-classes'] = [$uuid];
                }
                $record['option-data'][] = $entry;
            }
            /* DDNS per subnet settings */
            if ($ddns_enabled) {
                if (!$subnet->ddns_qualifying_suffix->isEmpty()) {
                    $record['ddns-qualifying-suffix'] = $subnet->ddns_qualifying_suffix->getValue();
                }
                $record['ddns-send-updates'] = !$subnet->ddns_dns_server->isEmpty();
                $record['ddns-override-no-update'] = !$subnet->ddns_override_no_update->isEmpty();
                $record['ddns-override-client-update'] = !$subnet->ddns_override_client_update->isEmpty();
                $record['ddns-update-on-renew'] = !$subnet->ddns_update_on_renew->isEmpty();
                if (!$subnet->ddns_conflict_resolution_mode->isEmpty()) {
                    $record['ddns-conflict-resolution-mode'] = $subnet->ddns_conflict_resolution_mode->getValue();
                }
            }
            $result[] = $record;
        }
        return $result;
    }

    private function getConfigClientClasses()
    {
        $result = [];
        foreach ($this->options->option->iterateItems() as $uuid => $option) {
            if ($option->match_code->isEmpty()) {
                continue;
            }
            $result[] = [
                'name' => $uuid,
                'test' => sprintf('option[%d].hex == 0x%s', $option->match_code->asInt(), $option->match_data->encodeValue()),
            ];
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

    public function generateConfig($target = '/usr/local/etc/kea/kea-dhcp6.conf')
    {
        $ddns = new KeaDdns();
        $ddns_enabled = !$ddns->general->enabled->isEmpty();
        $needs_no_leases_class = false;
        $cnf = [
            'Dhcp6' => [
                'valid-lifetime' => $this->general->valid_lifetime->asInt(),
                'decline-probation-period' => $this->general->decline_probation_period->isSet() ?
                                              $this->general->decline_probation_period->asInt() : 600,
                'mac-sources' => $this->general->mac_sources->getValues(),
                'interfaces-config' => [
                    'interfaces' => $this->getConfigPhysicalInterfaces(),
                    /* socket retries are on a per-interface basis, failing to open one won't affect others */
                    'service-sockets-max-retries' => $this->general->service_sockets_max_retries->isSet() ?
                                                     $this->general->service_sockets_max_retries->asInt() : 5,
                    'service-sockets-retry-wait-time' => $this->general->service_sockets_retry_wait_time->isSet() ?
                                                         $this->general->service_sockets_retry_wait_time->asInt() : 5000,
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
                'subnet6' => $this->getConfigSubnets($ddns_enabled, $needs_no_leases_class),
                'hooks-libraries' => [
                    ['library' => '/usr/local/lib/kea/hooks/libdhcp_lease_cmds.so'],
                    ['library' => '/usr/local/lib/kea/hooks/libdhcp_host_cmds.so']
                ],
            ]
        ];
        $client_classes = $this->getConfigClientClasses();

        // Used by temporary dynamic-prefix placeholder subnets.
        // The test can never pass, so subnets using it will not hand out leases.
        if ($needs_no_leases_class) {
            $client_classes[] = [
                'name' => 'NO_LEASES_PLEASE',
                'test' => "not member('ALL')",
            ];
        }

        if (!empty($client_classes)) {
            $cnf['Dhcp6']['client-classes'] = $client_classes;
        }
        $expiredLeasesConfig = $this->getExpiredLeasesProcessingConfig();
        if ($expiredLeasesConfig !== null) {
            $cnf['Dhcp6']['expired-leases-processing'] = $expiredLeasesConfig;
        }
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
            $cnf['Dhcp6']['hooks-libraries'][] = $record;
        }
        if ($ddns_enabled) {
            $cnf['Dhcp6']['dhcp-ddns'] = [
                'enable-updates' => true,
                'server-ip' => $ddns->general->server_ip->getValue(),
                'server-port' => $ddns->general->server_port->asInt(),
            ];
        }
        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0600);
    }
}
