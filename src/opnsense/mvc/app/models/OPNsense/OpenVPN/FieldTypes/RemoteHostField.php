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

namespace OPNsense\OpenVPN\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;

/**
 * @package OPNsense\Base\FieldTypes
 */
class RemoteHostField extends BaseField
{
    protected $internalIsContainer = false;

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        $result = [];
        foreach (explode(',', $this->internalValue) as $opt) {
            $result[$opt] = array("value" => $opt, "selected" => 1);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(
                [
                    "callback" => function ($value) {
                        $errors = [];
                        foreach (explode(',', $value) as $this_remote) {
                            $parts = [];
                            if (substr_count($this_remote, ':') > 1) {
                                foreach (explode(']', $this_remote) as $part) {
                                    $parts[] = ltrim($part, '[:');
                                }
                            } else {
                                $parts = explode(':', $this_remote);
                            }
                            if (
                                filter_var($parts[0], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false &&
                                filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) === false
                            ) {
                                $errors[] = sprintf(gettext("hostname %s is not a valid hostname."), $parts[0]);
                            } elseif (
                                isset($parts[1]) &&
                                filter_var(
                                    $parts[1],
                                    FILTER_VALIDATE_INT,
                                    ['options' => ['min_range' => 1, 'max_range' => 65535]]
                                ) === false
                            ) {
                                $errors[] = sprintf(gettext("port %s not valid."), $parts[1]);
                            }
                        }
                        return $errors;
                    }
                ]
            );
        }
        return $validators;
    }
}
