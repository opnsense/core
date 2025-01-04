<?php

/*
 * Copyright (C) 2017 Fabian Franz
 * Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\Routes;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;

/**
 * Class Route
 * @package OPNsense\Routes
 */
class Route extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        // perform standard validations
        $result = parent::performValidation($validateFullModel);
        foreach ($this->route->iterateItems() as $route) {
            if (!$validateFullModel && !$route->isFieldChanged()) {
                continue;
            }
            $proto_net = strpos($route->network, ':') === false ? "inet" : "inet6";
            // Gateway addresses are stored in the result list received from configd.
            // Unfortunately we can't trust the config here, so we use the list results here.
            $gateways = $route->gateway->getNodeData();
            if (!isset($gateways[(string)$route->gateway])) {
                /* an invalid gateway ends up with another validation error so skip below */
                continue;
            }
            $gateway = $gateways[(string)$route->gateway];
            $tmp = explode("-", $gateway['value']);
            $gateway_ip = !empty($tmp) ? trim(end($tmp)) : "";
            if (in_array($gateway_ip, ['inet', 'inet6'])) {
                $gateway_proto = $gateway_ip;
            } else {
                $gateway_proto = strpos($gateway_ip, ":") !== false ? "inet6" : "inet";
            }
            if ($gateway_proto == 'inet6' && $proto_net == 'inet') {
                /* allow rfc5549, sends ipv4 traffic to ipv6 next hop hardware addresss */
                continue;
            } elseif (empty($gateway_ip) || $gateway_proto != $proto_net) {
                // When protocols don't match, add a message for this field to the validation result.
                $result->appendMessage(new Message(
                    gettext('Specify a valid gateway from the list matching the networks ip protocol.'),
                    $route->gateway->__reference
                ));
            }
        }
        return $result;
    }
}
