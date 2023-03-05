<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Diagnostics\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Firewall\Util;

class HostField extends BaseField
{
    protected $internalIsContainer = false;

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                $messages = [];
                $parts = preg_split('/ /', $data, -1, PREG_SPLIT_NO_EMPTY);
                $tokens = [];
                foreach ($parts as $part) {
                    if (Util::isIpAddress($part) || Util::isSubnet($part) || Util::isMACAddress($part)) {
                        $tokens[] = 'net';
                    } elseif (in_array(strtolower($part), ['and', 'or', 'not'])) {
                        $tokens[] = strtolower($part);
                    } else {
                        // unknown token
                        $messages[] = sprintf(gettext("invalid token %s"), $part);
                    }
                }
                if (count($messages) > 0) {
                    return $messages;
                }
                // language order
                for ($i = 0; $i < count($tokens); $i++) {
                    $this_token = $tokens[$i];
                    $prev_token = $i > 0 ? $tokens[$i - 1] : null;
                    $next_token = isset($tokens[$i + 1]) ? $tokens[$i + 1] : null;
                    if (
                          ($this_token == 'net' && $prev_token == 'net') ||
                          ($this_token == 'not' && $next_token != 'net') ||
                          (in_array($this_token, ['and', 'or']) && $prev_token != 'net') ||
                          (in_array($this_token, ['and', 'or']) && !in_array($next_token, ['net', 'not']))
                    ) {
                        $messages[] = sprintf(gettext("unexpected token at %d (%s)"), $i, $parts[$i]);
                    }
                }
                return $messages;
            }
            ]);
        }
        return $validators;
    }
}
