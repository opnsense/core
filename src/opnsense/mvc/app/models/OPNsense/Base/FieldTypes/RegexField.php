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

namespace OPNsense\Base\FieldTypes;

use OPNsense\Base\Validators\CallbackValidator;

class RegexField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var bool whether PHP-style delimiters are required
     */
    private $internalRequireDelimiters = false;

    /**
     * @param string $value Y/N
     */
    public function setRequireDelimiters($value): void
    {
        $this->internalRequireDelimiters = strtoupper(trim($value)) === 'Y';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext("Invalid regular expression pattern.");
    }

    /**
     * @return array list of validators for this field
     */
    public function getValidators()
    {
        $validators = parent::getValidators();

        $validators[] = new CallbackValidator([
            "callback" => function ($value) {
                $item = (string)$value;

                // Skip validation if empty
                if ($item === '') {
                    return [];
                }

                // Try using the pattern as-is to check if it has delimiters
                $hasDelimiters = @preg_match($item, '') !== false;

                if ($this->internalRequireDelimiters) {
                    // Delimiters are required
                    if (!$hasDelimiters) {
                        return [$this->getValidationMessage()];
                    }
                } else {
                    // No delimiters expected
                    if ($hasDelimiters) {
                        return [$this->getValidationMessage()];
                    }

                    // Wrap pattern with delimiter for validation
                    $delimiter = chr(1);
                    $testPattern = $delimiter . $item . $delimiter;

                    if (@preg_match($testPattern, '') === false) {
                        return [$this->getValidationMessage()];
                    }
                }

                return [];
            }
        ]);

        return $validators;
    }
}
