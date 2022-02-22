<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class VlanInterfaceField extends BaseListField
{
    private static $interface_devices = null;

    protected function actionPostLoadingEvent()
    {
        if (self::$interface_devices === null) {
            $configHandle = Config::getInstance()->object();
            $ifnames = [];
            if (!empty($configHandle->interfaces)) {
                foreach ($configHandle->interfaces->children() as $ifname => $node) {
                    $ifnames[(string)$node->if] = !empty((string)$node->descr) ? (string)$node->descr : strtoupper($ifname);
                }
            }
            self::$interface_devices = [];
            $ifconfig = json_decode((new Backend())->configdRun('interface list ifconfig'), true);
            if (!empty($ifconfig)) {
                foreach ($ifconfig as $ifname => $details) {
                    // XXX: skip same interface types as legacy, may need to revise later
                    if (
                        strpos($ifname, "_vlan") > 1 || strpos($ifname, "lo") === 0 || strpos($ifname, "enc") === 0 ||
                        strpos($ifname, "pflog") === 0 || strpos($ifname, "pfsync") === 0 ||
                        strpos($ifname, "bridge") === 0 ||
                        strpos($ifname, "gre") === 0 || strpos($ifname, "gif") === 0 || strpos($ifname, "ipsec") === 0
                    ) {
                        continue;
                    }
                    self::$interface_devices[$ifname] = sprintf(
                        "%s (%s) [%s]",
                        $ifname,
                        $details['macaddr'],
                        !empty($ifnames[$ifname]) ? $ifnames[$ifname] : ""
                    );
                }
            }
        }
        $this->internalOptionList = self::$interface_devices;
        return parent::actionPostLoadingEvent();
    }
}
