<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Wireguard\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Wireguard\Server;

class ClientField extends ArrayField
{
    /**
     * backreference servers
     */
    protected function actionPostLoadingEvent()
    {
        $peers = [];
        foreach ((new Server())->servers->server->iterateItems() as $key => $node) {
            if (!empty((string)$node->peers)) {
                foreach (explode(',', (string)$node->peers) as $peer) {
                    if (!isset($peers[$peer])) {
                        $peers[$peer] = [];
                    }
                    $peers[$peer][] = $key;
                }
            }
        }
        foreach ($this->internalChildnodes as $key => $node) {
            if (isset($peers[$key])) {
                $node->servers->setValue(implode(',', $peers[$key]));
            }
        }
        return parent::actionPostLoadingEvent();
    }
}
