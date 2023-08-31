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

namespace OPNsense\Gateways;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Util;
use Phalcon\Messages\Message;
use OPNsense\Core\Config;

class Gateway extends BaseModel
{

    public static function getDpingerDefaults()
    {
        return [
            'alert_interval' => 1,
            'data_length' => 0,
            'interval' => 1,
            'latencyhigh' => 500,
            'latencylow' => 200,
            'loss_interval' => 2,
            'losshigh' => 20,
            'losslow' => 10,
            'time_period' => 60,
        ];
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $defaults = self::getDpingerDefaults();

        foreach ($this->getFlatNodes() as $node) {
            $tagName = $node->getInternalXMLTagName();
            if (array_key_exists($tagName, $defaults)) {
                if (empty((string)$node)) {
                    // Since dpinger values are not required in the model,
                    // we set them to the defaults here if they're empty.
                    $node->setValue($defaults[$tagName]);
                }
            }
        }

        foreach ($this->getFlatNodes() as $key => $node) {
            $tagName = $node->getInternalXMLTagName();
            $parent = $node->getParentNode();
            $ref = $parent->__reference;
            if ($validateFullModel || $node->isFieldChanged()) {
                if ($tagName === 'name') {
                    $this->validateNameChange($parent, $messages, $ref);
                }
                if (in_array($tagName, ['ipprotocol', 'gateway', 'monitor'])) {
                    $this->validateProtocolMatch($parent, $messages, $ref);
                }
                if (in_array($tagName, ['ipprotocol', 'gateway'])) {
                    $this->validateDynamicMatch($parent, $messages, $ref);
                }
                if (in_array($tagName, ['latencylow', 'latencyhigh', 'losslow', 'losshigh', 'time_period', 'interval'])) {
                    $this->validateDpingerSettings($tagName, $parent, $messages, $ref);
                }
            }
        }

        return $messages;
    }

    private function validateDpingerSettings($tag, $parent, $messages, $ref)
    {
        switch ($tag) {
            case 'latencylow':
            case 'latencyhigh':
                if ((string)$parent->latencylow > (string)$parent->latencyhigh) {
                    $messages->appendMessage(new Message("The high latency threshold needs to be higher than the low latency threshold.", $ref.".".$tag));
                }
                break;
            case 'losslow':
            case 'losshigh':
                if ((string)$parent->losslow > (string)$parent->losshigh) {
                    $messages->appendMessage(new Message("The high Packet Loss threshold needs to be higher than the low Packet Loss threshold.", $ref.".".$tag));
                }
                break;
            case 'time_period':
            case 'interval':
                if ((string)$parent->time_period < (2.1 * (intval((string)$parent->interval)))) {
                    $messages->appendMessage(new Message("The time period needs to be at least 2.1 times that of the probe interval.", $ref.".".$tag));
                }
                break;
        }
    }

    private function validateNameChange($node, $messages, $ref)
    {
        if (empty($ref)) {
            return;
        }

        $new = (string)$node->name;
        $cfg = Config::getInstance()->object();
        if (!empty($cfg->OPNsense->Gateways) && !empty($cfg->OPNsense->Gateways->gateway_item)) {
            /* Exclude legacy components from validation */
            foreach ($cfg->OPNsense->Gateways->gateway_item as $item) {
                $uuid = (string)$item->attributes()->uuid;
                if ($uuid === explode('.', $ref)[1]) {
                    $old = (string)$item->name;
                    if ($old !== $new) {
                        $messages->appendMessage(new Message("Changing name on a gateway is not allowed.", $ref.".name"));
                    }
                }
            }
        }
    }

    private function validateProtocolMatch($node, $messages, $ref)
    {
        $ipproto = (string)$node->ipprotocol;
        $gateway = (string)$node->gateway;
        $monitor = (string)$node->monitor;

        foreach ([$gateway, $monitor] as $value) {
            $tag = $value === $gateway ? 'gateway' : 'monitor';

            if ($value === 'dynamic' || empty($value)) {
                continue;
            }

            if ($ipproto === 'inet' && !Util::isIpv4Address($value)) {
                $messages->appendMessage(new Message(
                    "Address Family is specified as IPv4, but ". $value ." is not an IPv4 address", $ref .'.'.$tag));
            }

            if ($ipproto === 'inet6' && !Util::isIpv6Address($value)) {
                $messages->appendMessage(new Message(
                    "Address Family is specified as IPv6, but the ". $tag ." is not an IPv6 address", $ref .'.'.$tag));
            }
        }
    }

    private function validateDynamicMatch($node, $messages, $ref)
    {
        $gateway = (string)$node->gateway;
        $ipproto = (string)$node->ipprotocol === 'inet' ? 'ipaddr' : 'ipaddrv6';

        if (Util::isIpAddress($gateway)) {
            // not dynamic, so no validation needed. protocol validation is handled earlier in the chain
            return;
        }

        $cfg = Config::getInstance()->object();

        $if = (string)$node->interface;
        $ifcfg = $cfg->interfaces->$if;
        if (!empty((string)$ifcfg->$ipproto) && Util::isIpAddress((string)$ifcfg->$ipproto)) {
            $ipFormat = $ipproto === 'ipaddr' ? 'IPv4' : 'IPv6';
            $messages->appendMessage(new Message(
                "Dynamic gateway values cannot be specified for interfaces with a static ".$ipFormat." configuration.",
                $ref.".gateway"
            ));
        }
    }

    public function gatewayIterator()
    {
        $use_legacy = true;
        foreach ($this->gateway_item->iterateItems() as $gateway) {
            $record = [];
            foreach ($gateway->iterateItems() as $key => $value) {
                $record[(string)$key] = (string)$value;
            }
            $record['uuid'] = (string)$gateway->getAttributes()['uuid'];
            yield $record;
            $use_legacy = false;
        }

        if ($use_legacy) {
            $config = Config::getInstance()->object();
            if (!empty($config->gateways) && !empty($config->gateways->gateway_item)) {
                foreach ($config->gateways->children() as $tag => $gateway) {
                    if ($tag == 'gateway_item') {
                        $record = [];
                        // iterate over the individual nodes since empty nodes still return a
                        // SimpleXMLObject when the container is converted to an array
                        foreach ($gateway->children() as $node) {
                            $record[$node->getName()] = (string)$node;
                        }
                        foreach ($this->getDpingerDefaults() as $key => $value) {
                            if (!array_key_exists($key, $record)) {
                                $record[$key] = $value;
                            }
                        }
                        $record['uuid'] = '';
                        yield $record;
                    }
                }
            }
        }
    }
}
