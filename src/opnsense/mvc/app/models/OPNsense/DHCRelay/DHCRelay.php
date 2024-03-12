<?php

/*
 * Copyright (C) 2023-2024 Deciso B.V.
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

namespace OPNsense\DHCRelay;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class DHCRelay extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $dst4 = $dst6 = [];
        $ints = [];

        $messages = parent::performValidation($validateFullModel);

        foreach ($this->destinations->getFlatNodes() as $key => $node) {
            if ($node->getInternalXMLTagName() != 'server') {
                continue;
            }

            /* single pass lookup for each special IP delimiter beats looping over entries */
            $v4 = strpos((string)$node, '.') !== false;
            $v6 = strpos((string)$node, ':') !== false;

            if ($validateFullModel || $node->isFieldChanged()) {
                if ($v4 && $v6) {
                    $messages->appendMessage(new Message(gettext('You cannot mix address families for destinations.'), $key));
                }
            }

            $uuid = $node->getParentNode()->getAttribute('uuid');

            if ($v4) {
                $dst4[$uuid] = true;
            } elseif ($v6) {
                $dst6[$uuid] = true;
            }
        }

        foreach ($this->relays->getFlatNodes() as $key => $node) {
            if ($node->getInternalXMLTagName() == 'interface' && ($validateFullModel || $node->isFieldChanged())) {
                /* collect changed interfaces first to confirm their association afterwards */
                $ints[$node->getParentNode()->__reference] = (string)$node;
            }
        }

        foreach ($ints as $intkey => $int) {
            $has_v4 = $has_v6 = 0;

            foreach ($this->relays->iterateItems() as $key => $node) {
                if ((string)$node->interface == $int) {
                    if (array_key_exists((string)$node->destination, $dst4)) {
                        $has_v4 += 1;
                    }
                    if (array_key_exists((string)$node->destination, $dst6)) {
                        $has_v6 += 1;
                    }
                }
            }

            if ($has_v4 > 1) {
                $messages->appendMessage(new Message(gettext('An IPv4 destination for this relay is already set.'), "{$intkey}.destination"));
            } elseif ($has_v6 > 1) {
                $messages->appendMessage(new Message(gettext('An IPv6 destination for this relay is already set.'), "{$intkey}.destination"));
            }
        }

        return $messages;
    }
}
