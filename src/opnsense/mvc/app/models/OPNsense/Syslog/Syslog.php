<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Syslog;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Firewall\Util;

/**
 * Class Syslog
 * @package OPNsense\Routes
 */
class Syslog extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        // standard model validations
        $messages = parent::performValidation($validateFullModel);
        // extended validations
        foreach ($this->getFlatNodes() as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                $parentNode = $node->getParentNode();
                $ptagname = $parentNode->getInternalXMLTagName();
                $tagname = $node->getInternalXMLTagName();
                if ($ptagname == 'destination' && in_array($tagname, array('hostname', 'transport'))) {
                    // protocol family check
                    if (in_array((string)$parentNode->transport, array('udp4', 'udp6', 'tcp4', 'tcp6'))) {
                        $ipproto = ((string)$parentNode->transport)[3];
                        $hostproto = strpos((string)$parentNode->hostname, ":") === false ? "4" : "6";
                        if (Util::isIpAddress((string)$parentNode->hostname) && $ipproto != $hostproto) {
                            $messages->appendMessage(new Message(
                                gettext("Transport protocol does not match address in hostname"),
                                $key
                            ));
                        }
                    }
                }
            }
        }
        return $messages;
    }
}
