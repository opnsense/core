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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Core\Config;

/**
 * Class GroupNameField
 * @package OPNsense\Base\FieldTypes
 */
class GroupNameField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * retrieve field validators for this field type
     * @return array returns list of validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        $validators[] = new CallbackValidator(
            [
                "callback" => function ($value) {
                    $result = [];
                    if (preg_match('/[^a-zA-Z0-9_]+/', $value, $match)) {
                        $result[] = gettext('Only letters, digits and underscores are allowed as the group name.');
                    }
                    if (!empty($value) && strlen($value) > 15) {
                        $result[] = gettext('The group name shall not be longer than 15 characters.');
                    }
                    if (preg_match('/[0-9]$/', $value, $match)) {
                        $result[] = gettext('The group name shall not end in a digit.');
                    }
                    $cnf = Config::getInstance()->object();
                    if (!empty($cnf->$value) && empty($cnf->$value->virtual)) {
                        $result[] = gettext(
                            "The specified group name is already used by an interface. Please choose another name."
                        );
                    }
                    return $result;
                }
            ]
        );
        return $validators;
    }
}
