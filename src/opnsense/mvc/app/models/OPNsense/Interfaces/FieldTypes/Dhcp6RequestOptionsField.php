<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

class Dhcp6RequestOptionsField extends TextField
{
    static $validoptions = [
        'domain-name-servers' => '/^$/D',
        'domain-name' => '/^$/D',
        'ntp-servers' => '/^$/D',
        'refreshtime' => '/^$/D',
        'sip-server-address' => '/^$/D',
        'sip-server-domain-name' => '/^$/D',
        'nis-server-address' => '/^$/D',
        'nis-domain-name' => '/^$/D',
        'nisp-server-address' => '/^$/D',
        'nisp-domain-name' => '/^$/D',
        'bcmcs-server-address' => '/^$/D',
        'bcmcs-server-domain-name' => '/^$/D',
        'refreshtime' => '/^$/D',
    ];

    public function getValidators()
    {

        $validators = parent::getValidators();
        $validators[] = new CallbackValidator(["callback" => function ($data) {
            $options = self::$validoptions;
            $messages = [];
            if (empty($data)) {
                return $messages;
            }
            foreach (explode("\n", $data) as $line) {
                $parts = explode(" ", $line, 2);
                if (!empty($parts)) {
                    if (!isset($options[$parts[0]]) || !preg_match($options[$parts[0]], $parts[1] ?? '')) {
                        $messages[] = sprintf(
                            gettext('Invalid option or parameters "%s".'),
                            $line
                        );
                    }
                }
            }
            return $messages;
        }
        ]);

        return $validators;
    }
}





