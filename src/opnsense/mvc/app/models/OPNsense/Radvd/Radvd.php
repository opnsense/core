<?php

/*
 * Copyright (C) 2025-2026 Deciso B.V.
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

namespace OPNsense\Radvd;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class Radvd
 * @package OPNsense\Radvd
 */
class Radvd extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        foreach ($this->entries->iterateItems() as $entry) {
            if (!$validateFullModel && !$entry->isFieldChanged()) {
                continue;
            }

            $key = $entry->__reference;

            $raMin = $entry->MinRtrAdvInterval->asInt();
            $raMax = $entry->MaxRtrAdvInterval->asInt();
            $raMinAllowed = (int)floor($raMax * 0.75);

            if ($raMin > $raMinAllowed) {
                $messages->appendMessage(
                    new Message(
                        sprintf(
                            gettext('Value cannot be greater than %s.'),
                            (string)$raMinAllowed
                        ),
                        $key . '.MinRtrAdvInterval'
                    )
                );
            }

            if (!$entry->AdvDefaultLifetime->isEmpty()) {
                $defaultLifetime = $entry->AdvDefaultLifetime->asInt();
                $defaultLifetimeMax = (int)9000;

                if ($defaultLifetime < $raMax || $defaultLifetime > $defaultLifetimeMax) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(
                                gettext('AdvDefaultLifetime must be between %s and %s seconds.'),
                                (string)$raMax,
                                (string)$defaultLifetimeMax
                            ),
                            $key . '.AdvDefaultLifetime'
                        )
                    );
                }
            }
        }

        return $messages;
    }
}
