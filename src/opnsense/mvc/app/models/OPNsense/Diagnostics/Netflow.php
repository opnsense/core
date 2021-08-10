<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Diagnostics;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;

/**
 * Class Netflow
 * @package OPNsense\Netflow
 */
class Netflow extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        // standard model validations
        $messages = parent::performValidation($validateFullModel);

        // extended validations
        $intf_list = $egress_list = $missing = [];

        foreach ($this->getFlatNodes() as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                $parentNode = $node->getParentNode();
                $ptagname = $parentNode->getInternalXMLTagName();
                $tagname = $node->getInternalXMLTagName();
                if ($ptagname == 'capture' && in_array($tagname, array('interfaces', 'egress_only'))) {
                    $intf_list = explode(',', (string)$parentNode->interfaces);
                    $egress_list  = explode(',', (string)$parentNode->egress_only);
                }
            }
        }

        foreach ($egress_list as $egress_item) {
            if (!in_array($egress_item, $intf_list)) {
                $missing[] = $egress_item;
            }
        }

        if (count($missing)) {
            $messages->appendMessage(new Message(
                sprintf(
                    gettext('WAN interfaces missing in listening interfaces: %s'),
                    implode(', ', $missing)
                ),
                'capture.interfaces'
            ));
        }

        return $messages;
    }
}
