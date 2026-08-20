<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Interfaces;

use OPNsense\Base\BaseModel;
use OPNsense\Base\FieldTypes\BooleanField;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;
use OPNsense\Core\FileObject;
use OPNsense\Routing\Gateways;


class NetworkInterface extends BaseModel
{
    var $todo_file = '/tmp/.interfaces.todo';

    /**
     * @param array $payload data to store
     */
    private function store_if_todo($id, $payload)
    {
        $fobj = new FileObject($this->todo_file, 'a+', 0600, LOCK_EX);
        $data = $fobj->readJson() ?? [];
        $data[$id] = array_merge($data[$id] ?? [], $payload);
        $fobj->truncate(0)->writeJson($data);
    }

    /**
     * @return itterator yielding interface names and configuration
     */
    private function iterate_assignments()
    {
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            if (!empty((string)$intf->virtual)) {
                continue;
            }
            yield $key => $intf;
        }
    }

    /**
     * fetch all todo items
     * @return array
     */
    public function get_if_todo()
    {
        if (is_file($this->todo_file)) {
            return (new FileObject($this->todo_file, 'r'))->readJson() ?? [];
        } else {
            return [];
        }
    }

    /**
     * remove todo file after processing
     */
    public function flush_todo()
    {
        if (is_file($this->todo_file)) {
            unlink($this->todo_file);
        }
    }

    /**
     * Merge configuration data in "in memory" model on construction
     */
    public function __construct($lazyload = false)
    {
        parent::__construct($lazyload);
        $iftodos = $this->get_if_todo();
        foreach ($this->iterate_assignments() as $key => $intf) {
            $iftodo = $iftodos[$key] ?? [];
            if (($iftodo['pending_action'] ?? '') == 'delete') {
                continue;
            }
            $node = $this->interface->add($key);
            $node->identifier = $key;
            $node->pending_action = $iftodo['pending_action'] ?? '';
            $data = !empty($iftodo['pending']) ? $iftodo['pending'] : [];
            if (empty($data)) {
                /* just copy all legacy formatted data in, we're only mapping what we need */
                foreach ($intf as $akey => $ifvalue) {
                    $data[$akey] = (string)$ifvalue;
                }
            }
            $node->fromLegacy($data); /* map the data */
        }
    }


    /**
     *  Account changes in config.xml when persisting, return "true" so callers know to flush to the configuration
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        /* flush and annotate configuration */
        $interfaces = $this->interface->getNodeContent();
        $existing_ifnames = [];
        /* mark pending actions as we need to wait for "apply" in order to persist them */
        foreach ($this->iterate_assignments() as $key => $intf) {
            if (!isset($interfaces[$key])) {
                $this->store_if_todo($key, ['pending_action' => 'delete']);
            } else {
                $intf->descr = $interfaces[$key]['descr'];
                /* flush actions that need to be applied, for which we need history (config reflects running config) */
                $todo = [
                    'pending' => $this->interface->$key->toLegacy(),
                    'pending_action' => 'update'
                ];
                $changed = false;
                if ($intf->if != $interfaces[$key]['if']) {
                    $todo['pending_action'] = 'relink';
                }
                foreach ($todo['pending'] as $prop => $value) {
                    if ($prop === 'dhcp6_norequest_dns' && !isset($intf->$prop)) {
                        $curval = '0'; /* actually stored as dhcp6_request_dns in our model */
                    } elseif (!isset($intf->$prop)) {
                        $curval = $this->interface->$key->$prop instanceof BooleanField  ? '0' : '';
                    } else {
                        $curval = $intf->$prop;
                    }
                    if ($curval != $value) {
                        $changed = true;
                        break;
                    }
                }
                if ($changed) {
                    $this->store_if_todo($key, $todo);
                }
            }
            $existing_ifnames[] = $key;
        }
        $next_if = 1;
        while (in_array('opt' . $next_if, $existing_ifnames)) {
            $next_if++;
        }

        foreach ($interfaces as $key => $intf) {
            $newIdentifier = 'opt' . $next_if;
            if (!isset(Config::getInstance()->object()->interfaces->$key)) {
                /* register anchor with description, the rest of the data is stored in pending */
                $newif = Config::getInstance()->object()->interfaces->addChild($newIdentifier);
                $newif->if = $intf['if'];
                $newif->descr = $intf['descr'];
                $next_if++;

                $node = $this->interface->$key;
                if ($node !== null) {
                    $this->store_if_todo($newIdentifier, [
                        'pending' => $node->toLegacy(),
                        'pending_action' => 'update'
                    ]);

                    /* We want the node to return the new network identifier as the uuid not
                    the internal random generated uuid by the ArrayField.
                    Assignments use identifier as the uuid in the config.xml */
                    $node->setAttributeValue('uuid', $newIdentifier);
                }
            }
        }
        return true;
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $gateways = new Gateways();
        foreach ($this->interface->iterateItems() as $ifname => $if) {
            if (!$validateFullModel && !$if->isFieldChanged()) {
                continue;
            }
            $key = $if->__reference;
            if (preg_match('/^bridge[0-9]/', $if->if->getValue())) {
                foreach (Config::getInstance()->object()->xpath('/*/bridges/bridged') as $node) {
                    if (in_array($ifname, explode(',', $node->members))) {
                        $msg = sprintf(
                            gettext('You cannot set device %s to interface %s because it cannot be a member of itself.'),
                            $if->if,
                            $ifname
                        );
                        $messages->appendMessage(new Message($msg, $key . ".if"));
                    }
                }
            }
            if ($if->type4->isEqual('staticv4')) {
                if ($if->ipaddr->isEmpty()) {
                    $messages->appendMessage(new Message(
                        gettext('An address is required when configuring static mode'),
                        $key . ".ipaddr"
                    ));
                }
                if (!$if->gateway->isEmpty() && $gateways->getInterfaceName($if->gateway->getValue()) != $ifname) {
                    $messages->appendMessage(new Message(
                        gettext('This gateway belongs to a different interface.'),
                        $key . ".gateway"
                    ));
                }
            }
            if (!empty($if->pppType()) && !in_array($if->type4->getValue(), ['none', $if->pppType()])) {
                $messages->appendMessage(new Message(
                    sprintf(gettext('This device only supports "%s" as type'), $if->pppType()),
                    $key . ".type4"
                ));
            } elseif (empty($if->pppType()) && in_array($if->type4->getValue(), ['ppp', 'pppoe', 'pptp', 'l2tp'])) {
                $messages->appendMessage(new Message(
                    gettext('PPP types belong to their respective devices'),
                    $key . ".type4"
                ));
            }
            if (!empty($if->pppType()) && !in_array($if->type6->getValue(), ['none', 'pppoev6'])) {
                $messages->appendMessage(new Message(
                    sprintf(gettext('This device only supports "%s" as type'), 'pppoev6'),
                    $key . ".type6"
                ));
            } elseif (empty($if->pppType()) && in_array($if->type6->getValue(), ['pppoev6'])) {
                $messages->appendMessage(new Message(
                    gettext('PPP types belong to their respective devices'),
                    $key . ".type6"
                ));
            }

            if ($if->type6->isEqual('dhcp6')) {
                $pdlen = $if->{'dhcp6-ia-pd-len'}->getValue();
                $ipv6_num_prefix_ids = $pdlen < 0 ? 0 : pow(2, $pdlen);
                $dhcp6_prefix_id = intval($if->{'dhcp6-prefix-id'}->getValue(), 16);
                if ($dhcp6_prefix_id < 0 || $dhcp6_prefix_id >= $ipv6_num_prefix_ids) {
                    $messages->appendMessage(new Message(
                        gettext('You specified an IPv6 prefix ID that is out of range.'),
                        $key . ".dhcp6-prefix-id"
                    ));
                }
            } elseif ($if->type6->isEqual('idassoc6') || $if->type6->isEqual('track6')) {
                if ($if->{'track6-interface'}->isEmpty()) {
                    $messages->appendMessage(new Message(
                        gettext('A parent interface is required for this type.'),
                        $key . ".track6-interface"
                    ));
                }
            } elseif ($if->type6->isEqual('staticv6')) {
                if ($if->ipaddrv6->isEmpty()) {
                    $messages->appendMessage(new Message(
                        gettext('An address is required when configuring static mode'),
                        $key . ".ipaddrv6"
                    ));
                }
                if (!$if->gatewayv6->isEmpty() && $gateways->getInterfaceName($if->gatewayv6->getValue()) != $ifname) {
                    $messages->appendMessage(new Message(
                        gettext('This gateway belongs to a different interface.'),
                        $key . ".gatewayv6"
                    ));
                }
            }
        }

        return $messages;
    }
}
