<?php

/*
 * Copyright (C) 2021 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Unbound;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

class Unbound extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if ($validateFullModel || $this->general->enabled->isFieldChanged() || $this->general->port->isFieldChanged()) {
            $config = Config::getInstance()->object();

            $dnsmasq_port = !empty((string)$config->dnsmasq->port) ? (string)$config->dnsmasq->port : '53';
            $unbound_port = (string)$this->general->port;

            if (!empty((string)$this->general->enabled) && !empty((string)$config->dnsmasq->enable) && $unbound_port == $dnsmasq_port) {
                $messages->appendMessage(new Message(
                    gettext('Dnsmasq is currently using this port.'),
                    'general.' . $this->general->port->getInternalXMLTagName()
                ));
            }
        }

        return $messages;
    }
}
