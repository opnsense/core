<?php

/*
 * Copyright (C) 2025-2026 Deciso B.V.
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

namespace OPNsense\Firewall;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Firewall\Util;

/**
 * Class DNat
 * @package OPNsense\Firewall
 */
class DNat extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $port_protos = ['tcp', 'udp', 'tcp/udp'];

        foreach ($this->rule->iterateItems() as $rule) {
            if ($validateFullModel || $rule->isFieldChanged()) {
                $ports = [$rule->source->port, $rule->destination->port, $rule->{'local-port'}];
                foreach ($ports as $port) {
                    if (!empty($port->getValue()) && !in_array($rule->protocol->getValue(), $port_protos)) {
                        $messages->appendMessage(new Message(
                            gettext("Ports are only valid for TCP or UDP type rules."),
                            $port->__reference
                        ));
                    }
                }

                $addresses = [$rule->source->network, $rule->destination->network, $rule->target];
                foreach ($addresses as $address) {
                    $is_addr = Util::isSubnet($address->getValue()) || Util::isIpAddress($address->getValue());
                    $proto = strpos($address->getValue(), ':') === false ? "inet" : "inet6";

                    if ($is_addr && in_array($rule->ipprotocol->getValue(), ['inet', 'inet6']) && !$rule->ipprotocol->isEqual($proto)) {
                        $messages->appendMessage(new Message(
                            gettext("Address type should match selected TCP/IP protocol version."),
                            $address->__reference
                        ));
                    }
                }
            }
        }

        return $messages;
    }
}
