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

namespace OPNsense\Interfaces;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Firewall\Util;

class Gre extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->gre->iterateItems() as $gre) {
            if (!$validateFullModel && !$gre->isFieldChanged()) {
                continue;
            }
            $key = $gre->__reference;
            if (Util::isIpAddress($gre->{'local-addr'})) {
                $proto_local = strpos($gre->{'local-addr'}, ':') === false ? "inet" : "inet6";
                $proto_remote = strpos($gre->{'remote-addr'}, ':') === false ? "inet" : "inet6";
                if ($proto_local != $proto_remote) {
                    $messages->appendMessage(
                        new Message(
                            gettext("The remote address family has to match the one used in local."),
                            $key . ".remote-addr"
                        )
                    );
                }
            }
            $proto_tun_local = strpos($gre->{'tunnel-local-addr'}, ':') === false ? "inet" : "inet6";
            $proto_tun_remote = strpos($gre->{'tunnel-remote-addr'}, ':') === false ? "inet" : "inet6";
            if ($proto_tun_local != $proto_tun_remote) {
                $messages->appendMessage(
                    new Message(
                        gettext("The remote address family has to match the one used in local."),
                        $key . ".tunnel-remote-addr"
                    )
                );
            }
        }
        return $messages;
    }
}
