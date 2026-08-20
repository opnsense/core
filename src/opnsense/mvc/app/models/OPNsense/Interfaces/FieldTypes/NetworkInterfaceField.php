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

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\BooleanField;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class NetworkInterfaceContainerField extends ContainerField
{
    static $pppDevices = null;

    public function toLegacy()
    {
        $result = [];
        if ($this->type4->isEqual('staticv4')) {
            $result['ipaddr'] = explode('/', $this->ipaddr->getValue())[0];
            $result['subnet'] = explode('/', $this->ipaddr->getValue())[1];
        } else {
            $result['ipaddr'] = $this->type4->getValue();
        }
        if ($this->type6->isEqual('staticv6')) {
            $result['ipaddrv6'] = explode('/', $this->ipaddrv6->getValue())[0];
            $result['subnetv6'] = explode('/', $this->ipaddrv6->getValue())[1];
        } else {
            $result['ipaddrv6'] = $this->type6->getValue();
        }
        /* standard copy->paste */
        $skiplist = [
            'type6',
            'type4',
            'ipaddr',
            'ipaddrv6',
            'identifier',
            'pending_action',
            'icon',
            'optgroup',
            'dhcp6_request_dns',
            'dhcp6-prefix-id',
            'dhcp6_ifid'
        ];
        foreach ($this->iterateItems() as $key => $node) {
            if (in_array($key, $skiplist)) {
                continue; /* skip otherwise processed */
            }
            $result[$key] = $node->getValue();
        }
        $result['dhcp6_norequest_dns'] = $this->dhcp6_request_dns->isEmpty() ? '1' : '0';
        foreach (['dhcp6-prefix-id', 'dhcp6_ifid', 'track6-prefix-id', 'track6_ifid'] as $fld) {
            if (!$this->$fld->isEmpty()) {
                $result[$fld] = intval($this->$fld->getValue(), 16);
            }
        }
        return $result;
    }

    public function fromLegacy($data)
    {
        foreach ($this->iterateItems() as $key => $node) {
            if ($node instanceof BooleanField) {
                $this->$key = empty($data[$key]) ? '0' : '1';
            } elseif (in_array($key, ['ipaddr', 'ipaddrv6'])) {
                /* ignore addresses, processed below, only ensure fields exist */
                $data[$key] = !empty($data[$key]) ? $data[$key] : '';
            } elseif (isset($data[$key])) {
                $this->$key = $data[$key];
            }
        }
        $this->type4 = Util::isIpv4Address($data['ipaddr']) ? 'staticv4' : $data['ipaddr'];
        $this->type6 = Util::isIpv6Address($data['ipaddrv6']) ? 'staticv6' : $data['ipaddrv6'];
        if (Util::isIpv4Address($data['ipaddr'])) {
            $this->ipaddr = $data['ipaddr'] . '/' . $data['subnet'];
        }
        if (Util::isIpv6Address($data['ipaddrv6'])) {
            $this->ipaddrv6 = $data['ipaddrv6'] . '/' . $data['subnetv6'];
        }
        $this->dhcp6_request_dns = empty($data['dhcp6_norequest_dns']) ? '1' : '0';
        foreach (['dhcp6-prefix-id', 'dhcp6_ifid', 'track6-prefix-id', 'track6_ifid'] as $fld) {
            if (!empty($data[$fld])) {
                $this->$fld = sprintf("0x%x", $data[$fld]);
            }
        }
    }

    /**
     * PPP interfaces should be constraint to their configured type
     */
    public function pppType()
    {
        if (self::$pppDevices === null) {
            self::$pppDevices = [];
            foreach (Config::getInstance()->object()->ppps->children() as $intf) {
                if (!empty((string)$intf->if)) {
                    self::$pppDevices[(string)$intf->if] = (string)$intf->type;
                }
            }
        }

        if (isset(self::$pppDevices[$this->if->getValue()])) {
            return self::$pppDevices[$this->if->getValue()];
        }
        return null;
    }
}


class NetworkInterfaceField extends ArrayField
{
    /**
     * @inheritDoc
     */
    public function newContainerField($ref, $tagname)
    {
        $container_node = new NetworkInterfaceContainerField($ref, $tagname);
        $pmodel = $this->getParentModel();
        $container_node->setParentModel($pmodel);
        return $container_node;
    }
}
