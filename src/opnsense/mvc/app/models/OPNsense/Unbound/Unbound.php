<?php

/*
 * Copyright (C) 2023 Deciso B.V.
 * Copyright (C) 2021 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Unbound;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Util;

class Unbound extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if (
            ($validateFullModel || $this->general->enabled->isFieldChanged() || $this->general->port->isFieldChanged()) &&
            !empty((string)$this->general->enabled)
        ) {
            foreach (json_decode((new Backend())->configdpRun('service list'), true) as $service) {
                if (empty($service['dns_ports'])) {
                    continue;
                }
                if (!is_array($service['dns_ports'])) {
                    syslog(LOG_ERR, sprintf('Service %s (%s) reported a faulty "dns_ports" entry.', $service['description'], $service['name']));
                    continue;
                }
                if ($service['name'] != 'unbound' && in_array((string)$this->general->port, $service['dns_ports'])) {
                    $messages->appendMessage(new Message(
                        sprintf(gettext('%s is currently using this port.'), $service['description']),
                        'general.' . $this->general->port->getInternalXMLTagName()
                    ));
                    break;
                }
            }
        }
        foreach ($this->dnsbl->blocklist->iterateItems() as $node) {
            if ($node->isFieldChanged() || $validateFullModel) {
                /* Extract all subnets (eg x.x.x.x/24 --> 24) and protocol families */
                $sizes = array_unique(
                    array_map(fn($x) => explode("/", $x)[1] ?? null, explode(",", $node->source_nets))
                );
                $ipproto = array_unique(
                    array_map(fn($x) => strpos($x, ':') == false ? 'inet' : 'inet6', explode(",", $node->source_nets))
                );
                if (count($sizes) > 1) {
                    $messages->appendMessage(new Message(
                        gettext('All offered networks should be equally sized to avoid overlaps.'),
                        $node->source_nets->__reference
                    ));
                }
                if (count($ipproto) > 1) {
                    $messages->appendMessage(new Message(
                        gettext('All offered networks should use the same IP protocol.'),
                        $node->source_nets->__reference
                    ));
                }
            }
        }


        // Validate Split DNS subnet overlaps
        if ($validateFullModel || $this->split_dns->view_subnets->isFieldChanged()) {
            $subnets = [];
            foreach ($this->split_dns->view_subnets->subnet->iterateItems() as $uuid => $subnet) {
                if (!empty((string)$subnet->network) && !empty((string)$subnet->enabled)) {
                    $currentNetwork = (string)$subnet->network;

                    // Check for overlaps with existing subnets
                    foreach ($subnets as $existingUuid => $existingNetwork) {
                        if ($this->subnetsOverlap($currentNetwork, $existingNetwork)) {
                            $messages->appendMessage(new Message(
                                sprintf(gettext('Subnet %s overlaps with existing subnet %s'), $currentNetwork, $existingNetwork),
                                'split_dns.view_subnets.subnet.' . $uuid . '.network'
                            ));
                            break;
                        }
                    }

                    $subnets[$uuid] = $currentNetwork;
                }
            }
        }

        // Validate Split DNS host IP address versions match record types
        if ($validateFullModel || $this->split_dns->view_hosts->isFieldChanged()) {
            foreach ($this->split_dns->view_hosts->host->iterateItems() as $uuid => $host) {
                if (!empty((string)$host->enabled) && !empty((string)$host->rr) && !empty((string)$host->server)) {
                    $recordType = (string)$host->rr;
                    $ipAddress = (string)$host->server;

                    if ($recordType === 'A') {
                        // Validate IPv4 for A records
                        if (!Util::isIpv4Address($ipAddress)) {
                            $messages->appendMessage(new Message(
                                gettext('IPv4 address required for A record type'),
                                'split_dns.view_hosts.host.' . $uuid . '.server'
                            ));
                        }
                    } elseif ($recordType === 'AAAA') {
                        // Validate IPv6 for AAAA records
                        if (!Util::isIpv6Address($ipAddress)) {
                            $messages->appendMessage(new Message(
                                gettext('IPv6 address required for AAAA record type'),
                                'split_dns.view_hosts.host.' . $uuid . '.server'
                            ));
                        }
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * Check if two CIDR subnets overlap
     * @param string $cidr1 First CIDR (e.g., "192.168.1.0/24" or "2001:db8::/32")
     * @param string $cidr2 Second CIDR (e.g., "192.168.0.0/16" or "2001:db8:1::/48")
     * @return bool True if subnets overlap
     */
    private function subnetsOverlap($cidr1, $cidr2)
    {
        if (empty($cidr1) || empty($cidr2)) {
            return false;
        }

        // Use existing OPNsense function to get network ranges (handles both IPv4 and IPv6)
        $range1 = Util::cidrToRange($cidr1);
        $range2 = Util::cidrToRange($cidr2);

        if (!$range1 || !$range2) {
            return false;
        }

        // Check if either network address falls within the other CIDR
        return Util::isIPInCIDR($range1[0], $cidr2) || Util::isIPInCIDR($range2[0], $cidr1);
    }
}
