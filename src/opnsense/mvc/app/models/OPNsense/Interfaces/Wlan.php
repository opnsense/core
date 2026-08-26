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

class Wlan extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $commons = [];
        foreach ($this->clone->iterateItems() as $clone) {
            if (!isset($commons[$clone->if->getValue()])) {
                $commons[$clone->if->getValue()] = 0;
            }
            if (!$clone->use_common->isEmpty()) {
                $commons[$clone->if->getValue()] += 1;
            }
        }
        foreach ($this->clone->iterateItems() as $clone) {
            $key = $clone->__reference;
            if ($validateFullModel || $clone->isFieldChanged()) {
                if ($clone->if->getInitialValue() != $clone->if->getValue()) {
                    $messages->appendMessage(new Message(
                        gettext("Changing interface binding is not allowed"),
                        $key . ".if"
                    ));
                }
                if ($commons[$clone->if->getValue()] > 1 || $commons[$clone->if->getValue()] == 0) {
                    $messages->appendMessage(new Message(
                        gettext("Exactly one device should be marked as used for common settings."),
                        $key . ".use_common"
                    ));
                }
            }
        }
        return $messages;
    }

    public function getInterface($ifname)
    {
        $result = [];
        foreach ($this->clone->iterateItems() as $clone) {
            if ($clone->cloneif->isEqual($ifname)) {
                $result = $clone->getNodeContent();
            }
        }
        if (empty($result)) {
            return $result;
        }
        foreach ($this->clone->iterateItems() as $clone) {
            if ($clone->if->isEqual($result['if']) && !$clone->use_common->isEmpty()) {
                /* overlay common settings */
                foreach ([
                    'channel',
                    'diversity',
                    'protmode',
                    'regcountry',
                    'regdomain',
                    'reglocation',
                    'rxantenna',
                    'standard',
                    'txantenna',
                    'txpower',
                ] as $fieldname) {
                    $result[$fieldname] = $clone->$fieldname->getValue();
                }
            }
        }
        return $result;
    }
}
