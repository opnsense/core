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

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

class LaggInterfaceField extends BaseListField
{
    private static $parent_interfaces = null;

    protected function actionPostLoadingEvent()
    {
        if (self::$parent_interfaces === null) {
            self::$parent_interfaces = [];
            $skip = [];
            foreach (Config::getInstance()->object()->interfaces->children() as $ifname => $node) {
                if (!empty((string)$node->if)) {
                    $skip[] = (string)$node->if;
                }
            }
            $itfs = json_decode((new Backend())->configdRun("interface list ifconfig") ?? '', true) ?? [];
            foreach ($itfs as $ifname => $ifinfo) {
                if (in_array($ifname, $skip) || !$ifinfo['is_physical']) {
                    continue;
                }
                self::$parent_interfaces[$ifname] = sprintf("%s (%s)", $ifname, $ifinfo['macaddr'] ?? '');
            }
        }
        $this->internalOptionList = self::$parent_interfaces;
        return parent::actionPostLoadingEvent();
    }
}
