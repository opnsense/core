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

/**
 * Class Dnsmasq
 * @package OPNsense\Dnsmasq
 */
class Dnsmasq extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->hosts->iterateItems() as $host) {
            if (!$validateFullModel && !$host->isFieldChanged()) {
                continue;
            }
            $key = $host->__reference;
            if (!$host->hwaddr->isEmpty() && strpos($host->ip->getCurrentValue(), ':') !== false) {
                $messages->appendMessage(
                    new Message(
                        gettext("Only IPv4 reservations are currently supported"),
                        $key . ".ip"
                    )
                );
            }

            if ($host->host->isEmpty() && $host->hwaddr->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("Hostnames my only be omitted when a hardware address is offered."),
                        $key . ".host"
                    )
                );
            }
        }

        foreach ($this->dhcp_ranges->iterateItems() as $range) {
            if (!$validateFullModel && !$range->isFieldChanged()) {
                continue;
            }
            $key = $range->__reference;
            if (!$range->domain->isEmpty() && $range->end_addr->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("Can only configure a domain when a full range (including end) is specified."),
                        $key . ".domain"
                    )
                );
            }
        }

        return $messages;
    }

    public function getDhcpInterfaces()
    {
        $result = [];
        if (!empty($this->dhcp_ranges->iterateItems()->current())) {
            $exclude = [];
            foreach (explode(',', $this->no_dhcp_interface) as $item) {
                $exclude[] = $item;
            }
            foreach (explode(',', $this->interface) as $item) {
                if (!empty($item) && !in_array($item, $exclude)){
                    $result[] = $item;
                }
            }
        }
        return $result;
    }
}
