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
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\ExclusionIn;
use Phalcon\Validation\Validator\Callback;
use Phalcon\Validation\Message;
use OPNsense\Firewall\Util;


/**
 * Class AliasContentField
 * @package OPNsense\Base\FieldTypes
 */
class AliasContentField extends BaseField
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
     * item separator
     * @var string
     */
    private $separatorchar = "\n";

    /**
     * retrieve data as options
     * @return array
     */
    public function getNodeData()
    {
        $result = array ();
        $selectlist = explode($this->separatorchar, (string)$this);
        foreach ($selectlist as $optKey) {
            $result[$optKey] = array("value"=>$optKey, "selected" => 1);
        }
        return $result;
    }

    /**
     * Validate port alias options
     * @param array $data to validate
     * @return bool|Callback
     */
    private function validatePort($data)
    {
        $message = array();
        foreach ($data as $key => $value){
            foreach (explode($this->separatorchar, $value) as $key => $value){
                if (!Util::isAlias($value) && !Util::isPort($value, true)){
                    $message[] = $value;
                }
            }
        }
        if (!empty($message)) {
            // When validation fails use a callback to return the message so we can add the failed items
            return new Callback([
                "message" =>sprintf(gettext('Entry "%s" is not a valid port number.'), implode("|", $message)),
                "callback" => function() {return false;}]
            );
        }
        return true;
    }

    /**
     * retrieve field validators for this field type
     * @return array
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $alias_type = (string)$this->getParentNode()->type;

            switch ($alias_type) {
                case "port":
                    $validators[] = new Callback(["callback" => function ($data) {
                        return $this->validatePort($data);}
                    ]);
                    break;
                default:
                    break;
            }
        }
        return $validators;
    }
}
