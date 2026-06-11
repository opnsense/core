<?php

/*
 * Copyright (C) 2015-2026 Deciso B.V.
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
use OPNsense\Base\Validators\Regex;

/**
 * Class TextField
 * @package OPNsense\Base\FieldTypes
 */
class TextField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var null|string validation mask (regex)
     */
    protected $internalMask = null;

    /**
     * @var bool allow spaces and tabs
     */
    protected $internalAllowSpaces = true;

    /**
     * @var bool allow newlines
     */
    protected $internalAllowNewlines = true;

    /**
     * @var bool allow special control characters
     */
    protected $internalAllowSpecial = true;

    /**
     * set validation mask
     * @param string $value regexp validation mask
     */
    public function setMask($value)
    {
        $this->internalMask = $value;
    }

    /**
     * @param string $value Y/N
     */
    public function setAllowSpaces($value): void
    {
        $this->internalAllowSpaces = strtoupper(trim($value)) === 'Y';
    }

    /**
     * @param string $value Y/N
     */
    public function setAllowNewlines($value): void
    {
        $this->internalAllowNewlines = strtoupper(trim($value)) === 'Y';
    }

    /**
     * @param string $value Y/N
     */
    public function setAllowSpecial($value): void
    {
        $this->internalAllowSpecial = strtoupper(trim($value)) === 'Y';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Text does not validate.');
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator([
                "callback" => function ($value) {
                    if (!$this->internalAllowSpaces && strpbrk($value, " \t") !== false) {
                        return [gettext("Text may not contain spaces or tabs.")];
                    }

                    if (!$this->internalAllowNewlines && strpbrk($value, "\n\r") !== false) {
                        return [gettext("Text may not contain newlines.")];
                    }

                    if (!$this->internalAllowSpecial && strpbrk($value, "\0\v\f") !== false) {
                        return [gettext("Text may not contain special control characters.")];
                    }

                    return [];
                }
            ]);

            if ($this->internalMask != null) {
                $validators[] = new Regex([
                    'message' => $this->getValidationMessage(),
                    'pattern' => trim($this->internalMask),
                ]);
            }
        }

        return $validators;
    }
}
