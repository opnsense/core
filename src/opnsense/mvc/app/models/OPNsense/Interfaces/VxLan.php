<?php

/*
 * Copyright (C) 2019-2023 Deciso B.V.
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

class VxLan extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        // Initialize variables
        foreach ($this->vxlan->iterateItems() as $vxlan) {
            $key = $vxlan->__reference;
            $vxlangroup = (string) $vxlan->vxlangroup;
            $vxlanremote = (string) $vxlan->vxlanremote;
            $vxlandev = (string) $vxlan->vxlandev;

            // Validate that values in Fields have been changed, prevents configuration save lockout when invalid data is present.
            if ($validateFullModel || $vxlan->isFieldChanged()) {
                // Validation 1: At least one of vxlangroup and vxlanremote must be populated, but not both.
                if (
                    (!empty($vxlangroup) && !empty($vxlanremote)) ||
                    (empty($vxlangroup) && empty($vxlanremote))
                ) {
                    $messages->appendMessage(new Message(
                        gettext("Remote address -or- Multicast group has to be specified"),
                        $key . ".vxlanremote",
                        "GroupOrRemote"
                    ));
                    $messages->appendMessage(new Message(
                        gettext("Multicast group -or- Remote address has to be specified"),
                        $key . ".vxlangroup",
                        "GroupOrRemote"
                    ));
                }

                // Validation 2: If vxlanremote is populated, vxlandev must be an empty string.
                if (!empty($vxlanremote) && !empty($vxlandev)) {
                    $messages->appendMessage(new Message(
                        gettext("Remote address is specified, Device must be None"),
                        $key . ".vxlandev",
                        "DeviceRequirementForRemote"
                    ));
                }

                // Validation 3: If vxlangroup is populated, vxlandev must not be an empty string.
                if (!empty($vxlangroup) && empty($vxlandev)) {
                    $messages->appendMessage(new Message(
                        gettext("Multicast group is specified, a Device must also be specified"),
                        $key . ".vxlandev",
                        "DeviceRequirementForGroup"
                    ));
                }
            }
        }

        return $messages;
    }
}
