<?php

/**
 *    Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\ExclusionIn;

/**
 * Class AliasNameField
 * @package OPNsense\Base\FieldTypes
 */
class AliasNameField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "alias name required";

    /**
     * retrieve field validators for this field type
     * @return array returns list of validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        $reservedwords = array(
            'all', 'pass', 'block', 'out', 'queue', 'max', 'min', 'pptp', 'pppoe', 'L2TP', 'OpenVPN', 'IPsec', 'log'
        );
        if ($this->internalValue != null) {
            // add validations to deny reserved keywords, service/protocol names and invalid characters
            $validators[] = new ExclusionIn(array(
                'message' => sprintf(
                    gettext('The name cannot be the internally reserved keyword "%s".'),
                    (string)$this
                ),
                'domain' => $reservedwords));
            $validators[] = new Regex(array(
                'message' => sprintf(gettext(
                    'The name must be less than 32 characters long and may only consist of the following characters: %s'
                ), 'a-z, A-Z, 0-9, _'),
                'pattern' => '/[_0-9a-zA-z]{1,31}/'));
            $validators[] = new CallbackValidator(
                [
                    "callback" => function ($value) {
                        if (
                            getservbyname($value, 'tcp') ||
                            getservbyname($value, 'udp') || getprotobyname($value)
                        ) {
                            return array(gettext('Reserved protocol or service names may not be used'));
                        }
                        return array();
                    }
                ]
            );
        }
        return $validators;
    }
}
