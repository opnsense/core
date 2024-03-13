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
        $messages = parent::performValidation($validateFullModel);

        $destinations = [];

        foreach ($this->destinations->getFlatNodes() as $key => $node) {
            $tagName = $node->getInternalXMLTagName();
            $parentNode = $node->getParentNode();

            if ($validateFullModel || $node->isFieldChanged()) {
                $destinations[$parentNode->__reference] = $parentNode;
            }
        }

        // validate all changed destinations
        foreach ($destinations as $key => $node) {
            $v4 = $v6 = false;

            foreach (explode(',', (string)$node->server) as $server) {
                if (strpos($server, '.') !== false) {
                    $v4 = true;
                } else {
                    $v6 = true;
                }
            }

            if ($v4 && $v6) {
                $messages->appendMessage(
                    new Message(gettext('You cannot mix address families for destinations.'), "{$key}.server")
                );
            }
        }

        return $messages;
    }
}
