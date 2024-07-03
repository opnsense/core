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

namespace OPNsense\Interfaces;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class Lagg
 * @package OPNsense\Interfaces
 */
class Lagg extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // validate members are uniquely assigned to a single lagg
        $members = [];
        foreach ($this->getFlatNodes() as $key => $node) {
            if ($node->getInternalXMLTagName() == 'members') {
                foreach (explode(',', (string)$node) as $intf) {
                    if (!isset($members[$intf])) {
                        $members[$intf] = [];
                    }
                    $members[$intf][] = $node->getParentNode()->getAttributes()['uuid'];
                }
            }
        }

        foreach ($this->lagg->iterateItems() as $node) {
            if (!$validateFullModel && !$node->isFieldChanged()) {
                continue;
            }
            $uuid = $node->getAttributes()['uuid'];
            $key = $node->__reference;
            $members = explode(',', (string)$node->members);

            $tmp = [];
            foreach ($members as $intf) {
                if (!empty($members[$intf]) && count($members[$intf]) > 1) {
                    $tmp[] = $intf;
                }
            }
            if (!empty($tmp)) {
                $messages->appendMessage(
                    new Message(
                        sprintf(gettext('Members %s are already used in other laggs.'), implode(',', $tmp)),
                        $key . '.members'
                    )
                );
            }
            if (!empty((string)$node->primary_member) && !in_array((string)$node->primary_member, $members)) {
                $messages->appendMessage(
                    new Message(
                        sprintf(gettext('Primary member %s not in member list.'), (string)$node->primary_member),
                        $key . '.primary_member'
                    )
                );
            }
        }
        return $messages;
    }
}
