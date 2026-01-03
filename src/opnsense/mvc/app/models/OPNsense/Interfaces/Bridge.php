<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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
use OPNsense\Firewall\Util;

class Bridge extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->bridged->iterateItems() as $bridge) {
            if (!$validateFullModel && !$bridge->isFieldChanged()) {
                continue;
            }
            $key = $bridge->__reference;
            $members = $bridge->members->getValues();
            if (!$bridge->span->isEmpty() && in_array($bridge->span->getValue(), $members)) {
                $messages->appendMessage(
                    new Message(
                        gettext("Span interface cannot be part of the bridge."),
                        $key . ".span"
                    )
                );
            }
            foreach (['stp', 'edge', 'autoedge', 'ptp', 'autoptp', 'static', 'private'] as $section) {
                if ($bridge->$section->isEmpty()) {
                    continue;
                }
                foreach ($bridge->$section->getValues() as $if) {
                    if (!in_array($if, $members)) {
                        $messages->appendMessage(
                            new Message(
                                gettext("Contains non bridge members."),
                                $key . "." . $section
                            )
                        );
                        break;
                    }
                }
            }
        }
        return $messages;
    }
}
