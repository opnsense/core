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
        foreach ($this->hosts->iterateItems() as $host) {
            if (!$host->hwaddr->isEmpty() || !$host->client_id->isEmpty()) {
                foreach (explode(',', (string)$host->ip) as $ip) {
                    $usedDhcpIpAddresses[$ip] = isset($usedDhcpIpAddresses[$ip]) ? $usedDhcpIpAddresses[$ip] + 1 : 1;
                }
            }
        }

        foreach ($this->hosts->iterateItems() as $host) {
            if (!$validateFullModel && !$host->isFieldChanged()) {
                continue;
            }
            $key = $host->__reference;

            // all dhcp-host IP addresses must be unique, host overrides can have duplicate IP addresses
            if (!$host->hwaddr->isEmpty() || !$host->client_id->isEmpty()) {
                foreach (explode(',', (string)$host->ip) as $ip) {
                    if ($usedDhcpIpAddresses[$ip] > 1) {
                        $messages->appendMessage(
                            new Message(
                                sprintf(gettext("'%s' is already used in another DHCP host entry."), $ip),
                                $key . ".ip"
                            )
                        );
                    }
                }
            }

            if (
                $host->host->isEmpty() &&
                $host->hwaddr->isEmpty() &&
                $host->client_id->isEmpty()
            ) {
                $messages->appendMessage(
                    new Message(
                        gettext(
                            "Hostnames may only be omitted when either a hardware address " .
                            "or a client identifier is provided."
                        ),
                        $key . ".host"
                    )
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
            if (!$range->domain->isEmpty() && $range->end_addr->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("Can only configure a domain when a full range (including end) is specified."),
                        $key . ".domain"
                    )
                );
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

            $is_static = in_array('static', explode(',', $range->mode));
            if (!$range->end_addr->isEmpty() && $is_static) {
                $messages->appendMessage(
                    new Message(
                        gettext("Static only accepts a starting address."),
                        $key . ".end_addr"
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

            // Validate RA mode combinations
            $valid_ra_mode_combinations = [
                ['ra-names', 'slaac'],
                ['ra-names', 'ra-stateless'],
                ['slaac', 'ra-stateless'],
                ['ra-names', 'slaac', 'ra-stateless']
            ];

            $selected_ra_modes = explode(',', $range->ra_mode);

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
                $values = array_map('trim', explode(',', (string)$option->value));
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
            foreach (explode(',', $this->dhcp->no_interface) as $item) {
                $exclude[] = $item;
            }
            foreach (explode(',', $this->interface) as $item) {
                if (!empty($item) && !in_array($item, $exclude)) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }
}
