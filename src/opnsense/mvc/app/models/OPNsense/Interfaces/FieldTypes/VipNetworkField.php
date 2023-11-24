<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class VipNetworkField extends TextField
{
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->getInternalXMLTagName() == 'subnet_bits' && $this->getParentNode()->subnet->isFieldChanged()) {
            // no need to validate on subnet_bits as subnet is changed as well, let's trigger on subnet only
            return $validators;
        }
        $validators[] = new CallbackValidator(["callback" => function ($data) {
            $parent = $this->getParentNode();
            $messages = [];
            $network = implode('/', [(string)$parent->subnet, (string)$parent->subnet_bits]);
            if (!strlen((string)$parent->subnet) || !Util::isIpAddress((string)$parent->subnet)) {
                $messages[] = gettext('A valid network address is required.');
            } elseif (!strlen((string)$parent->subnet_bits) || !Util::isSubnet($network)) {
                $messages[] = gettext('A valid network subnet is required.');
            }
            return $messages;
        }
        ]);

        return $validators;
    }
}
