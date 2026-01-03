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

namespace OPNsense\Dnsmasq;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Util;

/**
 * Class Dnsmasq
 * @package OPNsense\Dnsmasq
 */
class Dnsmasq extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->dns_port = strlen($this->port) ? (string)$this->port : '53'; /* port defaults */
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $this->dns_port = strlen($this->port) ? (string)$this->port : '53'; /* port defaults */

        $messages = parent::performValidation($validateFullModel);

        $usedDhcpIpAddresses = [];
        $usedHostFqdns = [];
        $usedHostCnames = [];
        foreach ($this->hosts->iterateItems() as $host) {
            if (!$host->hwaddr->isEmpty() || !$host->client_id->isEmpty()) {
                foreach ($host->ip->getValues() as $ip) {
                    $usedDhcpIpAddresses[$ip] = isset($usedDhcpIpAddresses[$ip]) ? $usedDhcpIpAddresses[$ip] + 1 : 1;
                }
            }

            if (!$host->host->isEmpty()) {
                $fqdn = (string)$host->host;
                if (!$host->domain->isEmpty()) {
                    $fqdn .= '.' . (string)$host->domain;
                }
                $usedHostFqdns[$fqdn] = true;
            }

            foreach ($host->cnames->getValues() as $cname) {
                $usedHostCnames[$cname] = ($usedHostCnames[$cname] ?? 0) + 1;
            }
        }

        $usedDhcpDomains = [];
        foreach ($this->dhcp_ranges->iterateItems() as $range) {
            if ($range->domain->isEmpty()) {
                continue;
            }
            $usedDhcpDomains[(string)$range->domain][] = (string)$range->domain_type;
        }

        foreach ($this->hosts->iterateItems() as $host) {
            if (!$validateFullModel && !$host->isFieldChanged()) {
                continue;
            }
            $key = $host->__reference;
            $is_dhcp = !$host->hwaddr->isEmpty() || !$host->client_id->isEmpty();

            // all dhcp-host IP addresses must be unique, host overrides can have duplicate IP addresses
            if ($is_dhcp) {
                $tmp_ipv4_cnt = 0;
                foreach ($host->ip->getValues() as $ip) {
                    $tmp_ipv4_cnt += (strpos($ip, ':') === false) ? 1 : 0;
                    /* Partial IPv6 addresses can be duplicate */
                    if (str_starts_with($ip, '::')) {
                        continue;
                    }
                    if ($usedDhcpIpAddresses[$ip] > 1) {
                        $messages->appendMessage(
                            new Message(
                                sprintf(gettext("'%s' is already used in another DHCP host entry."), $ip),
                                $key . ".ip"
                            )
                        );
                    }
                }
                if ($tmp_ipv4_cnt > 1) {
                    $messages->appendMessage(
                        new Message(gettext("For IPv4 dhcp reservations, only a single address is allowed."), $key . ".ip")
                    );
                }

                if ($host->host->isEmpty() && $host->ip->isEmpty()) {
                    $messageText = gettext("At least a hostname or IP address must be provided for DHCP reservations.");
                    $messages->appendMessage(new Message($messageText, $key . ".host"));
                    $messages->appendMessage(new Message($messageText, $key . ".ip"));
                }

                if ($host->host == '*') {
                    $messages->appendMessage(
                        new Message(gettext("Wildcard entries are not allowed for DHCP reservations."), $key . ".host")
                    );
                }
            } else {
                if ($host->host->isEmpty() || $host->ip->isEmpty()) {
                    $messageText = gettext("Both hostname and IP address are required for host overrides.");
                    $messages->appendMessage(new Message($messageText, $key . ".host"));
                    $messages->appendMessage(new Message($messageText, $key . ".ip"));
                }
            }

            foreach ($host->cnames->getValues() as $cname) {
                if ($usedHostCnames[$cname] > 1) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(gettext("CNAME '%s' is already in use by a host override."), $cname),
                            $key . ".cnames"
                        )
                    );
                }

                if (isset($usedHostFqdns[$cname])) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(gettext("CNAME '%s' overlaps with a host and domain combination in a host override."), $cname),
                            $key . ".cnames"
                        )
                    );
                }
            }
        }

        foreach ($this->domainoverrides->iterateItems() as $domain) {
            if (!$validateFullModel && !$domain->isFieldChanged()) {
                continue;
            }
            $key = $domain->__reference;

            if ($domain->domain == '*' && !$domain->ipset->isEmpty()) {
                $messages->appendMessage(
                    new Message(gettext("Top level wildcard entries are not allowed for Ipset."), $key . ".domain")
                );
            }
        }

        foreach ($this->dhcp_ranges->iterateItems() as $range) {
            if (!$validateFullModel && !$range->isFieldChanged()) {
                continue;
            }
            $start_inet = strpos($range->start_addr, ':') !== false ? 'inet6' : 'inet';
            $end_inet = strpos($range->end_addr, ':') !== false ? 'inet6' : 'inet';
            $key = $range->__reference;
            if (!$range->domain->isEmpty()) {
                if ((string)$range->domain_type === 'range' && $range->end_addr->isEmpty()) {
                    $messages->appendMessage(
                        new Message(
                            gettext("Can only configure a domain of type 'Range' when a full range (including end) is specified."),
                            $key . ".end_addr"
                        )
                    );
                }

                if ((string)$range->domain_type === 'interface' && $range->interface->isEmpty()) {
                    $messages->appendMessage(
                        new Message(
                            gettext("A domain of type 'Interface' requires an interface to be selected."),
                            $key . ".interface"
                        )
                    );
                }
            }

            if (!$range->domain->isEmpty() && isset($usedDhcpDomains[(string)$range->domain])) {
                $typesUsed = array_unique($usedDhcpDomains[(string)$range->domain]);

                if (in_array('interface', $typesUsed) && in_array('range', $typesUsed)) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(
                                gettext("The domain '%s' cannot be used with both types 'Interface' and 'Range'."),
                                (string)$range->domain
                            ),
                            $key . ".domain"
                        )
                    );
                }
            }

            if ($start_inet != $end_inet && !$range->end_addr->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("Protocol family doesn't match."),
                        $key . ".end_addr"
                    )
                );
            }

            if (!$range->constructor->isEmpty()) {
                if ($start_inet == 'inet') {
                    $messages->appendMessage(
                        new Message(
                            gettext("A constructor can only be configured for ipv6."),
                            $key . ".constructor"
                        )
                    );
                }
                if (!str_starts_with($range->start_addr, '::')) {
                    $messages->appendMessage(
                        new Message(
                            gettext("A constructor expects a partial address (e.g. ::1)."),
                            $key . ".start_addr"
                        )
                    );
                }
                if (!$range->end_addr->isEmpty() && !str_starts_with($range->end_addr, '::')) {
                    $messages->appendMessage(
                        new Message(
                            gettext("A constructor expects a partial address (e.g. ::1)."),
                            $key . ".end_addr"
                        )
                    );
                }
            }

            if (
                $range->constructor->isEmpty() &&
                (str_starts_with($range->start_addr, '::') || str_starts_with($range->end_addr, '::'))
            ) {
                $messages->appendMessage(
                    new Message(
                        gettext("Partial addresses can only be used with a constructor."),
                        $key . ".start_addr"
                    )
                );
            }

            if (!$range->prefix_len->isEmpty() && $start_inet != 'inet6') {
                $messages->appendMessage(
                    new Message(
                        gettext("Prefix length can only be used for IPv6."),
                        $key . ".prefix_len"
                    )
                );
            }

            $is_static = in_array('static', $range->mode->getValues());
            if (!$range->end_addr->isEmpty() && $is_static) {
                $messages->appendMessage(
                    new Message(
                        gettext("Static only accepts a starting address."),
                        $key . ".end_addr"
                    )
                );
            } elseif ($range->end_addr->isEmpty() && !$is_static && $start_inet == 'inet') {
                $messages->appendMessage(
                    new Message(
                        gettext("End address may only be left empty for static ipv4 ranges."),
                        $key . ".end_addr"
                    )
                );
            }

            if (!$range->subnet_mask->isEmpty() && $is_static) {
                $messages->appendMessage(
                    new Message(
                        gettext("Static only accepts a starting address."),
                        $key . ".subnet_mask"
                    )
                );
            }

            if ($range->interface->isEmpty() && !$range->ra_mode->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("Selecting an RA Mode requires an interface."),
                        $key . ".interface"
                    )
                );
            }

            if (!$range->ra_mode->isEmpty() && (!$range->prefix_len->isEmpty() && $range->prefix_len->asFloat() < 64)) {
                $messages->appendMessage(
                    new Message(
                        gettext("Prefix length must be at least 64 when router advertisements are used."),
                        $key . ".prefix_len"
                    )
                );
            }

            // Validate RA mode combinations
            $valid_ra_mode_combinations = [
                ['ra-names', 'slaac'],
                ['ra-names', 'ra-stateless'],
                ['slaac', 'ra-stateless'],
                ['ra-names', 'slaac', 'ra-stateless']
            ];

            $selected_ra_modes = $range->ra_mode->getValues();

            // If only one mode is selected, it is always valid
            if (count($selected_ra_modes) > 1) {
                $is_ra_mode_valid = false;
                foreach ($valid_ra_mode_combinations as $ra_mode_combination) {
                    // Ensure order independant comparing
                    if (
                        empty(array_diff($selected_ra_modes, $ra_mode_combination)) &&
                        empty(array_diff($ra_mode_combination, $selected_ra_modes))
                    ) {
                        $is_ra_mode_valid = true;
                        break;
                    }
                }

                if (!$is_ra_mode_valid) {
                    $messages->appendMessage(
                        new Message(
                            gettext("Invalid RA mode combination."),
                            $key . ".ra_mode"
                        )
                    );
                }
            }
        }

        foreach ($this->dhcp_options->iterateItems() as $option) {
            if (!$validateFullModel && !$option->isFieldChanged()) {
                continue;
            }
            $key = $option->__reference;

            if (!$option->option->isEmpty() && !$option->option6->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("'Option' and 'Option6' cannot be selected at the same time."),
                        $key . ".option"
                    )
                );
            }

            if ($option->option->isEmpty() && $option->option6->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("Either 'Option' or 'Option6' is required."),
                        $key . ".option"
                    )
                );
            }

            if ($option->type == 'match' && $option->set_tag->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("When type is 'Match', a tag must be set."),
                        $key . ".set_tag"
                    )
                );
            }

            if (
                !$option->value->isEmpty() &&
                !$option->option6->isEmpty()
            ) {
                $values = array_map('trim', $option->value->getValues());
                foreach ($values as $value) {
                    if (
                        Util::isIpv6Address(trim($value, '[]')) &&
                        !(str_starts_with($value, '[') && str_ends_with($value, ']'))
                    ) {
                        $messages->appendMessage(
                            new Message(
                                gettext("Each IPv6 address must be wrapped inside square brackets '[fe80::]'."),
                                $key . ".value"
                            )
                        );
                    }
                }
            }
        }

        if (
            ($validateFullModel || $this->enable->isFieldChanged() || $this->port->isFieldChanged()) &&
            !empty((string)$this->enable)
        ) {
            foreach (json_decode((new Backend())->configdpRun('service list'), true) as $service) {
                if (empty($service['dns_ports'])) {
                    continue;
                }
                if (!is_array($service['dns_ports'])) {
                    syslog(LOG_ERR, sprintf('Service %s (%s) reported a faulty "dns_ports" entry.', $service['description'], $service['name']));
                    continue;
                }
                if ($service['name'] != 'dnsmasq' && in_array((string)$this->dns_port, $service['dns_ports'])) {
                    $messages->appendMessage(new Message(
                        sprintf(gettext('%s is currently using this port.'), $service['description']),
                        $this->port->getInternalXMLTagName()
                    ));
                    break;
                }
            }
        }

        return $messages;
    }

    public function getDhcpInterfaces()
    {
        $result = [];
        if (!empty($this->dhcp_ranges->iterateItems()->current())) {
            $exclude = [];
            foreach ($this->dhcp->no_interface->getValues() as $item) {
                $exclude[] = $item;
            }
            if ($this->interface->isEmpty()) {
                /* All -- use interfaces from ranges */
                foreach ($this->dhcp_ranges->iterateItems() as $node) {
                    $item = $node->interface->getValue();
                    if (!in_array($item, $result) && !in_array($item, $exclude)) {
                        $result[] = $item;
                    }
                }
            } else {
                /* specific interfaces */
                foreach ($this->interface->getValues() as $item) {
                    if (!empty($item) && !in_array($item, $exclude)) {
                        $result[] = $item;
                    }
                }
            }
        }
        return $result;
    }
}
