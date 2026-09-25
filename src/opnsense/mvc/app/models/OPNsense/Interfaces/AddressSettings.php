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
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;
use OPNsense\Core\FileObject;
use OPNsense\Routing\Gateways;

class AddressSettings extends BaseModel
{
    private $todo_file = '/tmp/.interfaces.todo';

    public function get_if_todo()
    {
        if (is_file($this->todo_file)) {
            return (new FileObject($this->todo_file, 'r'))->readJson() ?? [];
        }
        return [];
    }

    public function flush_todo()
    {
        if (is_file($this->todo_file)) {
            unlink($this->todo_file);
        }
    }

    private function store_if_todo($id, $payload)
    {
        $fobj = new FileObject($this->todo_file, 'a+e', 0600, LOCK_EX);
        $data = $fobj->readJson() ?? [];
        $data[$id] = array_merge($data[$id] ?? [], $payload);
        $fobj->truncate(0)->writeJson($data);
    }

    private function nodeToArray($node)
    {
        $result = [];
        foreach ($node->children() as $key => $value) {
            $result[$key] = (string)$value;
        }
        return $result;
    }

    private function pendingConfig($ifname)
    {
        $todos = $this->get_if_todo();
        if (!empty($todos[$ifname]['pending'])) {
            return $todos[$ifname]['pending'];
        }
        $node = Config::getInstance()->object()->interfaces->$ifname ?? null;
        return $node !== null ? $this->nodeToArray($node) : [];
    }

    private function iterateInterfaces()
    {
        $seen = [];
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            if (!empty((string)$intf->virtual)) {
                continue;
            }
            $seen[$key] = true;
            yield $key => $this->pendingConfig($key);
        }
        foreach ($this->get_if_todo() as $key => $todo) {
            if (!isset($seen[$key]) && !empty($todo['pending'])) {
                yield $key => $todo['pending'];
            }
        }
    }

    public function __construct($lazyload = false)
    {
        parent::__construct($lazyload);
        foreach ($this->iterateInterfaces() as $key => $intf) {
            $node = $this->settings->add($key);
            $node->identifier = $key;
            $node->descr = $intf['descr'] ?? '';
            $node->if = $intf['if'] ?? '';
            $node->enable = !empty($intf['enable']) ? '1' : '0';
            if (empty($intf['ipaddr'])) {
                $node->type = 'none';
            } elseif (filter_var($intf['ipaddr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $node->type = 'staticv4';
                $node->ipaddr = $intf['ipaddr'];
                $node->subnet = $intf['subnet'] ?? '';
                $node->gateway = $intf['gateway'] ?? 'none';
            } else {
                $node->type = $intf['ipaddr'];
            }
            if (empty($intf['ipaddrv6'])) {
                $node->type6 = 'none';
            } elseif (filter_var($intf['ipaddrv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $node->type6 = 'staticv6';
                $node->ipaddrv6 = $intf['ipaddrv6'];
                $node->subnetv6 = $intf['subnetv6'] ?? '';
                $node->gatewayv6 = $intf['gatewayv6'] ?? 'none';
            } else {
                $node->type6 = $intf['ipaddrv6'];
            }
        }
    }

    private function interfaceAddressInUse($address, $ifname, $field)
    {
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            if ((string)$key !== (string)$ifname && (string)$intf->$field === (string)$address) {
                return true;
            }
        }
        return false;
    }

    private function networkAddress($address, $bits)
    {
        $addr = ip2long($address);
        $mask = $bits == 0 ? 0 : (-1 << (32 - $bits));
        return long2ip($addr & $mask);
    }

    private function broadcastAddress($address, $bits)
    {
        $addr = ip2long($address);
        $mask = $bits == 0 ? 0 : (-1 << (32 - $bits));
        return long2ip(($addr & $mask) | (~$mask & 0xffffffff));
    }

    private function staticRouteConflict($address, $bits)
    {
        $static_routes = Config::getInstance()->object()->staticroutes ?? null;
        if ($static_routes === null) {
            return false;
        }
        foreach ($static_routes->route as $route) {
            $network = (string)$route->network;
            if (strpos($network, '/') === false) {
                continue;
            }
            [$route_address, $route_bits] = explode('/', $network, 2);
            if ((string)$route_bits === (string)$bits && $route_address === $this->networkAddress($address, (int)$bits)) {
                return true;
            }
        }
        return false;
    }

    private function gatewayExists($name, $ipprotocol)
    {
        if (empty($name) || $name === 'none') {
            return true;
        }
        foreach ((new Gateways())->gatewayIterator() as $gateway) {
            if (($gateway['name'] ?? '') === $name && ($gateway['ipprotocol'] ?? '') === $ipprotocol) {
                return true;
            }
        }
        return false;
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->settings->iterateItems() as $ifname => $settings) {
            if (!$validateFullModel && !$settings->isFieldChanged()) {
                continue;
            }
            $ref = $settings->__reference;
            if ((string)$settings->type === 'staticv4') {
                $address = (string)$settings->ipaddr;
                $bits = (string)$settings->subnet;
                if (empty($address)) {
                    $messages->appendMessage(new Message(gettext('A valid IPv4 address must be specified.'), $ref . '.ipaddr'));
                } elseif ($this->interfaceAddressInUse($address, $ifname, 'ipaddr')) {
                    $messages->appendMessage(new Message(gettext('This IPv4 address is being used by another interface.'), $ref . '.ipaddr'));
                } elseif ($bits !== '' && (int)$bits < 31) {
                    if ($address === $this->networkAddress($address, (int)$bits)) {
                        $messages->appendMessage(new Message(gettext('This IPv4 address is the network address and cannot be used'), $ref . '.ipaddr'));
                    } elseif ($address === $this->broadcastAddress($address, (int)$bits)) {
                        $messages->appendMessage(new Message(gettext('This IPv4 address is the broadcast address and cannot be used'), $ref . '.ipaddr'));
                    }
                }
                if ($bits === '') {
                    $messages->appendMessage(new Message(gettext('A valid subnet bit count must be specified.'), $ref . '.subnet'));
                } elseif (!empty($address) && $this->staticRouteConflict($address, (int)$bits)) {
                    $messages->appendMessage(new Message(gettext('This IPv4 address conflicts with a Static Route.'), $ref . '.ipaddr'));
                }
                if (!$this->gatewayExists((string)$settings->gateway, 'inet')) {
                    $messages->appendMessage(new Message(gettext('A valid gateway must be specified.'), $ref . '.gateway'));
                }
            }
            if ((string)$settings->type6 === 'staticv6') {
                if (empty((string)$settings->ipaddrv6)) {
                    $messages->appendMessage(new Message(gettext('A valid IPv6 address must be specified.'), $ref . '.ipaddrv6'));
                } elseif ($this->interfaceAddressInUse((string)$settings->ipaddrv6, $ifname, 'ipaddrv6')) {
                    $messages->appendMessage(new Message(gettext('This IPv6 address is being used by another interface.'), $ref . '.ipaddrv6'));
                }
                if ((string)$settings->subnetv6 === '') {
                    $messages->appendMessage(new Message(gettext('A valid subnet bit count must be specified.'), $ref . '.subnetv6'));
                }
                if (!$this->gatewayExists((string)$settings->gatewayv6, 'inet6')) {
                    $messages->appendMessage(new Message(gettext('A valid gateway must be specified.'), $ref . '.gatewayv6'));
                }
            }
        }
        return $messages;
    }

    private function buildPendingConfig($ifname, $settings)
    {
        $pending = $this->pendingConfig($ifname);
        $pending['descr'] = (string)$settings->descr;
        if ((string)$settings->enable === '1') {
            $pending['enable'] = '1';
        } else {
            unset($pending['enable']);
        }
        unset($pending['ipaddr'], $pending['subnet'], $pending['gateway']);
        if ((string)$settings->type === 'staticv4') {
            $pending['ipaddr'] = (string)$settings->ipaddr;
            $pending['subnet'] = (string)$settings->subnet;
            if ((string)$settings->gateway !== '' && (string)$settings->gateway !== 'none') {
                $pending['gateway'] = (string)$settings->gateway;
            }
        } elseif ((string)$settings->type !== 'none') {
            $pending['ipaddr'] = (string)$settings->type;
        }
        unset($pending['ipaddrv6'], $pending['subnetv6'], $pending['gatewayv6']);
        if ((string)$settings->type6 === 'staticv6') {
            $pending['ipaddrv6'] = (string)$settings->ipaddrv6;
            $pending['subnetv6'] = (string)$settings->subnetv6;
            if ((string)$settings->gatewayv6 !== '' && (string)$settings->gatewayv6 !== 'none') {
                $pending['gatewayv6'] = (string)$settings->gatewayv6;
            }
        } elseif ((string)$settings->type6 !== 'none') {
            $pending['ipaddrv6'] = (string)$settings->type6;
        }
        return $pending;
    }

    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        foreach ($this->settings->iterateItems() as $ifname => $settings) {
            if (!$validateFullModel && !$settings->isFieldChanged()) {
                continue;
            }
            $this->store_if_todo($ifname, [
                'pending_action' => 'configure',
                'enable' => (string)$settings->enable,
                'pending' => $this->buildPendingConfig($ifname, $settings),
            ]);
        }
        return false;
    }
}
