<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Dnsmasq\FieldTypes;

use OPNsense\Base\FieldTypes\BaseSetField;
use OPNsense\Base\Validators\CallbackValidator;

class LegalHostnameField extends BaseSetField
{
    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext("Please enter a valid hostname.");
    }

    /**
     * Dnsmasq only processes the first label in a hostname (separated by dots).
     * That is why we do not allow dots at all. We consider "label = hostname".
     * IP addresses are not allowed implicitely as "." and ":" are not valid.
     * https://github.com/imp/dnsmasq/blob/770bce967cfc9967273d0acfb3ea018fb7b17522/src/util.c#L191
     *
     * @return array list of validators for this field
     */
    public function getValidators()
    {
        $sender = $this;

        return [
            new CallbackValidator([
                "callback" => function ($data) use ($sender) {
                    $response = [];

                    foreach ($sender->iterateInput($data) as $value) {
                        // Allow empty label (valid in some cases)
                        if ($value === '') {
                            continue;
                        }

                        // Allow host wildcard
                        if ($value === '*') {
                            continue;
                        } elseif (str_contains($value, '*')) {
                            $response[] = gettext("Hostname wildcard '*' must be a single character.");
                        }

                        // First character must be alphanumeric
                        if (!ctype_alnum($value[0])) {
                            $response[] = gettext("Hostname must start with a letter or digit.");
                        }

                        // Remaining characters only alphanumeric and some special
                        $alnumTail = str_replace(['-', '_'], '', substr($value, 1));

                        if ($alnumTail !== '' && !ctype_alnum($alnumTail)) {
                            $response[] = gettext("Hostname may only contain letters, digits, '-' and '_'.");
                        }

                        // RFC1035 2.3.1 applies here
                        if (strlen($value) > 63) {
                            $response[] = gettext("Hostname must not exceed 63 characters.");
                        }
                    }

                    return $response;
                }
            ])
        ];
    }
}
