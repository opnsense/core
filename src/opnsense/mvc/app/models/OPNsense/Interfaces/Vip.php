<?php

/*
 * Copyright (C) 2022-2025 Deciso B.V.
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

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class Vip extends BaseModel
{
    private function getInterfaceDeviceType(string $interface)
    {
        $type = 'mismatch';

        $configHandle = Config::getInstance()->object();
        if (!empty($configHandle->interfaces)) {
            foreach ($configHandle->interfaces->children() as $ifname => $ifnode) {
                if ($ifname != $interface) {
                    continue;
                }
                /* select device names enforcing "l2tp" as well */
                $type = preg_replace('/(?<=..)[0-9].*$/', '', $ifnode->if);
                break;
            }
        }
        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        $virtual_types = ['lo', 'ipsec', 'l2tp', 'ppp', 'pppoe', 'pptp'];
        $unique_addrs = [];
        $carp_vhids = [];
        $vips = [];

        // collect changed VIP entries
        $vip_fields = ['mode', 'subnet', 'subnet_bits', 'password', 'vhid', 'interface', 'peer', 'peer6'];
        foreach ($this->getFlatNodes() as $key => $node) {
            $tagName = $node->getInternalXMLTagName();
            $parentNode = $node->getParentNode();

            if ($validateFullModel || $node->isFieldChanged()) {
                if ($parentNode->getInternalXMLTagName() === 'vip' && in_array($tagName, $vip_fields)) {
                    $vips[$parentNode->__reference] = $parentNode;
                }
            }

            if ($parentNode->getInternalXMLTagName() === 'vip' && $tagName == 'subnet') {
                $addr = (string)$parentNode->subnet;
                if (Util::isLinkLocal($addr)) {
                    $addr .= '@' . (string)$parentNode->interface;
                }
                $unique_addrs[$parentNode->__reference] = $addr;
            }

            $vhid_key = sprintf("%s_%s", $parentNode->interface, $parentNode->vhid);

            if ((string)$parentNode->mode == 'carp' && !isset($carp_vhids[$vhid_key])) {
                $carp_vhids[$vhid_key] = $parentNode;
            }
        }

        // validate all changed VIPs
        foreach ($vips as $key => $node) {
            $subnet_bits = (string)$node->subnet_bits;
            $subnet = (string)$node->subnet;
            if (in_array((string)$node->mode, ['carp', 'ipalias'])) {
                if (Util::isSubnet($subnet . "/" . $subnet_bits) && strpos($subnet, ':') === false && $subnet_bits <= 30) {
                    $sm = 0;
                    for ($i = 0; $i < $subnet_bits; $i++) {
                        $sm >>= 1;
                        $sm |= 0x80000000;
                    }
                    $network_addr = long2ip(ip2long($subnet) & $sm);
                    $broadcast_addr = long2ip((ip2long($subnet) & $sm) | (0xFFFFFFFF ^ $sm));
                    if ($subnet == $network_addr) {
                        $messages->appendMessage(
                            new Message(
                                gettext('You cannot use the network address.'),
                                $key . ".subnet"
                            )
                        );
                    } elseif ($subnet == $broadcast_addr) {
                        $messages->appendMessage(
                            new Message(
                                gettext('You cannot use the broadcast address.'),
                                $key . ".subnet"
                            )
                        );
                    }
                }
            } elseif ((string)$node->mode == 'proxyarp') {
                $net = $subnet . "/" . $subnet_bits;
                if (Util::isSubnet($net) && !Util::isSubnetStrict($net)) {
                    $messages->appendMessage(
                        new Message(
                            gettext("Only strict subnets are allowed for Proxy ARP types" .
                                " (e.g. 192.168.0.0/24, 192.168.1.1/32)."),
                            $key . ".subnet"
                        )
                    );
                }
                if (in_array($this->getInterfaceDeviceType($node->interface), $virtual_types)) {
                    $messages->appendMessage(new Message(
                        gettext('The current interface type does not support this mode.'),
                        $key . '.mode'
                    ));
                }
            }
            $vhid_key = sprintf("%s_%s", $node->interface, $node->vhid);
            if ((string)$node->mode == 'carp') {
                if (in_array($this->getInterfaceDeviceType($node->interface), $virtual_types)) {
                    $messages->appendMessage(new Message(
                        gettext('The current interface type does not support this mode.'),
                        $key . '.mode'
                    ));
                }
                if (empty((string)$node->password)) {
                    $messages->appendMessage(
                        new Message(
                            gettext("You must specify a CARP password that is shared between the two VHID members."),
                            $key . ".password"
                        )
                    );
                }
                if (empty((string)$node->vhid)) {
                    $messages->appendMessage(
                        new Message(
                            gettext('A VHID must be selected for this CARP VIP.'),
                            $key . ".vhid"
                        )
                    );
                } elseif (
                    isset($carp_vhids[$vhid_key]) &&
                    $carp_vhids[$vhid_key]->__reference != $node->__reference
                ) {
                    $errmsg = gettext(
                        "VHID %s is already in use on interface %s. Pick a unique number on this interface."
                    );
                    $messages->appendMessage(
                        new Message(
                            sprintf($errmsg, (string)$node->vhid, (string)$carp_vhids[$vhid_key]->interface),
                            $key . ".vhid"
                        )
                    );
                }
                /* XXX: ideally we shouldn't need the validations below, but when using the same vhid for
                 *      ipv4 and ipv6 one will always flip back to multicast */
                if (strpos($subnet, ':') === false && !empty((string)$node->peer6)) {
                    $messages->appendMessage(
                        new Message(
                            gettext('An (unicast) address can only be configured for the same protocol family.'),
                            $key . ".peer6"
                        )
                    );
                } elseif (strpos($subnet, ':') !== false && !empty((string)$node->peer)) {
                    $messages->appendMessage(
                        new Message(
                            gettext('An (unicast) address can only be configured for the same protocol family.'),
                            $key . ".peer"
                        )
                    );
                }
            } elseif ((string)$node->mode == 'ipalias') {
                if (in_array($this->getInterfaceDeviceType($node->interface), $virtual_types) && !empty((string)$node->vhid)) {
                    $messages->appendMessage(new Message(
                        gettext('The current interface type does not support this mode.'),
                        $key . '.vhid'
                    ));
                } elseif (
                    !empty((string)$node->vhid) && (!isset($carp_vhids[$vhid_key]) ||
                    (string)$carp_vhids[$vhid_key]->interface != (string)$node->interface)
                ) {
                    $errmsg = gettext("VHID %s must be defined on interface %s as a CARP VIP first.");
                    $messages->appendMessage(
                        new Message(
                            sprintf($errmsg, (string)$node->vhid, (string)$node->interface),
                            $key . ".vhid"
                        )
                    );
                }
            }

            /* ensure address is unique; for link-local we test with scope attached */
            $addr = (string)$node->subnet;
            if (Util::isLinkLocal($addr)) {
                $addr .= '@' . (string)$node->interface;
            }
            foreach ($unique_addrs as $refKey => $refAddr) {
                if ($refKey != $key && $refAddr === $addr) {
                    $messages->appendMessage(new Message(gettext('Address already assigned.'), $key . '.subnet'));
                }
            }
        }

        return $messages;
    }

    /**
     * find relevant references to this address which prevent removal or change of this address.
     */
    public function whereUsed($address)
    {
        $relevant_paths = [
          'nat.outbound.rule.' => gettext('Address %s referenced by outbound nat rule "%s"'),
          'nat.rule.' => gettext('Address %s referenced by port forward "%s"'),
        ];
        $usages = [];
        foreach (Config::getInstance()->object()->xpath("//text()[.='{$address}']") as $node) {
            $referring_node = $node->xpath("..")[0];
            $item_path = [$node->getName(), $referring_node->getName()];
            $item_description = "";
            $parent_node = $referring_node;
            while ($parent_node != null && $parent_node->xpath("../..") != null) {
                if (empty($item_description)) {
                    foreach (["description", "descr", "name"] as $key) {
                        if (!empty($parent_node->$key)) {
                            $item_description = (string)$parent_node->$key;
                            break;
                        }
                    }
                }
                $parent_node = $parent_node->xpath("..")[0];
                $item_path[] = $parent_node->getName();
            }
             $item_path = implode('.', array_reverse($item_path)) . "\n";
            foreach ($relevant_paths as $ref => $msg) {
                if (preg_match("/^{$ref}/", $item_path)) {
                    $usages[] = sprintf($msg, $address, $item_description);
                }
            }
        }
        return $usages;
    }

    /**
     * @return bool true if any of the configured vips is a carp type
     */
    public function isCarpEnabled()
    {
        foreach ($this->vip->iterateItems() as $vip) {
            if ($vip->mode == 'carp') {
                return true;
            }
        }
        return false;
    }
}
