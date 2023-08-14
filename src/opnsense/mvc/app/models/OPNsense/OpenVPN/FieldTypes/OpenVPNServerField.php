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

namespace OPNsense\OpenVPN\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Config;

/**
 * @package OPNsense\Base\FieldTypes
 */
class OpenVPNServerField extends BaseListField
{
    private static $internalCacheOptionList = [];

    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalCacheOptionList)) {
            $ref = 'openvpn-server';
            if (
                isset(Config::getInstance()->object()->openvpn) &&
                isset(Config::getInstance()->object()->openvpn->$ref)
            ) {
                foreach (Config::getInstance()->object()->openvpn->$ref as $server) {
                    $label = (string)$server->description ?? '';
                    $label .= ' (' . (string)$server->local_port . ' / ' . (string)$server->protocol . ')';
                    self::$internalCacheOptionList[(string)$server->vpnid] = $label;
                }
            }
            foreach ($this->getParentModel()->Instances->Instance->iterateItems() as $node_uuid => $node) {
                if ((string)$node->role == 'server') {
                    self::$internalCacheOptionList[$node_uuid] = (string)$node->description . ' (' . (!empty((string)$node->port) ? (string)$node->port : '1194') . ' / ' . strtoupper((string)$node->proto) . ')';
                }
            }
            natcasesort(self::$internalCacheOptionList);
        }
        $this->internalOptionList = self::$internalCacheOptionList;
    }
}
