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

                if (!empty($rule->source->port->getValue()) && !in_array($rule->protocol->getValue(), $port_protos)) {
                    $messages->appendMessage(new Message(
                        gettext("Ports are only valid for TCP or UDP type rules."),
                        $rule->source->port->__reference
                    ));
                }

                if (!empty($rule->destination->port->getValue()) && !in_array($rule->protocol->getValue(), $port_protos)) {
                    $messages->appendMessage(new Message(
                        gettext("Ports are only valid for TCP or UDP type rules."),
                        $rule->destination->port->__reference
                    ));
                }

                if (!empty($rule->{'local-port'}->getValue()) && !in_array($rule->protocol->getValue(), $port_protos)) {
                    $messages->appendMessage(new Message(
                        gettext("Ports are only valid for TCP or UDP type rules."),
                        $rule->{'local-port'}->__reference
                    ));
                }

                $src_is_addr = Util::isSubnet($rule->source->network->getValue()) || Util::isIpAddress($rule->source->network->getValue());
                $src_proto = strpos($rule->source->network->getValue(), ':') === false ? "inet" : "inet6";

                if ($src_is_addr && in_array($rule->ipprotocol->getValue(), ['inet', 'inet6']) && !$rule->ipprotocol->isEqual($src_proto)) {
                    $messages->appendMessage(new Message(
                        gettext("Address type should match selected TCP/IP protocol version."),
                        $rule->source->network->__reference
                    ));
                }

                $dest_is_addr = Util::isSubnet($rule->destination->network->getValue()) || Util::isIpAddress($rule->destination->network->getValue());
                $dest_proto = strpos($rule->destination->network->getValue(), ':') === false ? "inet" : "inet6";

                if ($dest_is_addr && in_array($rule->ipprotocol->getValue(), ['inet', 'inet6']) && !$rule->ipprotocol->isEqual($dest_proto)) {
                    $messages->appendMessage(new Message(
                        gettext("Address type should match selected TCP/IP protocol version."),
                        $rule->destination->network->__reference
                    ));
                }

                $target_is_addr = Util::isSubnet($rule->target->getValue()) || Util::isIpAddress($rule->target->getValue());
                $target_proto = strpos($rule->target->getValue(), ':') === false ? "inet" : "inet6";

                if ($target_is_addr && in_array($rule->ipprotocol->getValue(), ['inet', 'inet6']) && !$rule->ipprotocol->isEqual($target_proto)) {
                    $messages->appendMessage(new Message(
                        gettext("Address type should match selected TCP/IP protocol version."),
                        $rule->target->__reference
                    ));
                }
            }
        }

        return $messages;
    }
}
