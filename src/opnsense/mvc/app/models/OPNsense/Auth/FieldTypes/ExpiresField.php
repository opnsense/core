<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Auth\FieldTypes;

use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\Validators\CallbackValidator;

class ExpiresField extends TextField
{
    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if (!is_a($value, 'SimpleXMLElement') && !empty($value)) {
            try {
                /* backwards compatibility, we do accept inputs as +1 day */
                parent::setValue((new \DateTime($value))->format("m/d/Y"));
            } catch (\Exception $ex) {
                parent::setValue($value);
            }
        } else {
            parent::setValue($value);
        }
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                /*
                * Check for a valid expirationdate if one is set at all (valid means,
                * DateTime puts out a time stamp so any DateTime compatible time
                * format may be used. to keep it simple for the enduser, we only
                * claim to accept MM/DD/YYYY as inputs. Advanced users may use inputs
                * like "+1 day", which will be converted to MM/DD/YYYY based on "now".
                * Otherwise such an entry would lead to an invalid expiration data.
                */
                try {
                    (new \DateTime($data))->format("m/d/Y");
                } catch (\Exception $ex) {
                    return [gettext("Invalid expiration date format; use MM/DD/YYYY instead.")];
                }
            }
            ]);
        }
        return $validators;
    }
}
