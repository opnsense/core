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

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\Validators\CallbackValidator;

class DUIDField extends TextField
{
    /* Note: function is public for use in SettingsController */
    public function isValidDuid($duid)
    {
        // Duid's can be any length. Just check the format is correct.
        $values = explode(":", $duid);

        // need to get the DUID type. There are four types, in the
        // first three the type number is in byte[2] in the fourth it's
        // in byte[4]. Offset is either 0 or 2 depending if it's the read #
        // from file duid or the user input.

        $valid_duid = false;

        $duid_length = count($values);
        $test1 = hexdec($values[1]);
        $test2 = hexdec($values[3]);
        if (($test1 == 1 && $test2 == 1 ) || ($test1 == 3 && $test2 == 1 ) || ($test1 == 0 && $test2 == 4 ) || ($test1 == 2)) {
            $valid_duid = true;
        }

        /* max DUID length is 128, but with the separators it could be up to 254 */
        if ($duid_length < 6 || $duid_length > 254) {
            $valid_duid = false;
        }

        if ($valid_duid == false) {
            return false;
        }

        for ($i = 0; $i < count($values); $i++) {
            if (ctype_xdigit($values[$i]) == false) {
                return false;
            }
            if (hexdec($values[$i]) < 0 || hexdec($values[$i]) > 255) {
                return false;
            }
        }

        return true;
    }

    public function getValidators()
    {
        $validators = parent::getValidators();

        $validators[] = new CallbackValidator(["callback" => function ($data) {
            $messages = [];
            if (!empty($data) && !$this->isValidDuid($data)) {
                $messages[] = gettext('A valid DUID must be specified.');
            }
            return $messages;
        }]);

        return $validators;
    }
}
