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

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;

class LegalHostnameField extends BaseField
{
    /**
     * @var bool not a container
     */
    protected $internalIsContainer = false;

    /**
     * @var bool validate as a full domain (default: false = hostname)
     */
    private $internalIsDomain = false;

    /**
     * @param string $value Y/N
     */
    public function setIsDomain($value): void
    {
        $this->internalIsDomain = strtoupper(trim($value)) === 'Y';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext(
            "Labels must be 1–63 characters, start with a letter or digit, "
            . "and may contain only letters, digits, '-' or '_'. "

        );
    }

    /**
     * https://github.com/imp/dnsmasq/blob/770bce967cfc9967273d0acfb3ea018fb7b17522/src/util.c#L191
     *
     * @return array list of validators for this field
     */
    public function getValidators()
    {
        $validators = parent::getValidators();

        $validators[] = new CallbackValidator([
            "callback" => function ($value) {
                $isDomain = $this->internalIsDomain;

                // Skip validation if empty
                if ($value === '') {
                    return [];
                }

                // single wildcard allowed in Host field
                if (!$isDomain && $value === '*') {
                    return [];
                } elseif ($isDomain && $value === '*') {
                    return [
                        gettext("Wildcards are not allowed in domain names.")
                    ];
                }

                // RFC1035 2.3.1 applies here
                if ($isDomain && strlen($value) > 255) {
                    return [
                        gettext("All labels combined may not exceed 255 characters.")
                    ];
                }

                // hostname = label, domain = label.label...
                foreach ($isDomain ? explode('.', $value) : [$value] as $label) {
                    // RFC1035 2.3.1 applies here
                    if (!preg_match('/^(?![_-])[A-Za-z0-9_-]{1,63}$/', $label)) {
                        return [$this->getValidationMessage()];
                    }
                }

                // validation succeeded
                return [];
            }
        ]);

        return $validators;
    }

}
