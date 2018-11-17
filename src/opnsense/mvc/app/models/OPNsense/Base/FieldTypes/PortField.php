<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\Base\FieldTypes;

use Phalcon\Validation\Validator\InclusionIn;

/**
 * Class PortField field type for ports, includes validation for services in /etc/services or valid number ranges.
 * @package OPNsense\Base\FieldTypes
 */
class PortField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var array list of well known services
     */
    private static $wellknownservices = array(
        'cvsup',
        'domain',
        'ftp',
        'hbci',
        'http',
        'https',
        'aol',
        'auth',
        'imap',
        'imaps',
        'ipsec-msft',
        'isakmp',
        'l2f',
        'ldap',
        'ms-streaming',
        'afs3-fileserver',
        'microsoft-ds',
        'ms-wbt-server',
        'wins',
        'msnp',
        'nntp',
        'ntp',
        'netbios-dgm',
        'netbios-ns',
        'netbios-ssn',
        'openvpn',
        'pop3',
        'pop3s',
        'pptp',
        'radius',
        'radius-acct',
        'avt-profile-1',
        'sip',
        'smtp',
        'igmpv3lite',
        'urd',
        'snmp',
        'snmptrap',
        'ssh',
        'nat-stun-port',
        'submission',
        'teredo',
        'telnet',
        'tftp',
        'rfb'
    );

    /**
     * @var bool enable well known ports
     */
    private $enableWellKown = false;

    /**
     * @var array collected options
     */
    private static $internalOptionList = null;

    /**
     * generate validation data (list of port numbers and well know ports)
     */
    protected function actionPostLoadingEvent()
    {
        if (!is_array(self::$internalOptionList)) {
            if ($this->enableWellKown) {
                self::$internalOptionList = array("any") + self::$wellknownservices;
            }

            for ($port=1; $port <= 65535; $port++) {
                self::$internalOptionList[] = (string)$port;
            }
        }
    }

    /**
     * setter for maximum value
     * @param integer $value
     */
    public function setEnableWellKnown($value)
    {
        if (strtoupper(trim($value)) == "Y") {
            $this->enableWellKown = true;
        } else {
            $this->enableWellKown = false;
        }
    }

    /**
     * always lowercase portnames
     * @param string $value
     */
    public function setValue($value)
    {
        parent::setValue(trim(strtolower($value)));
    }

    /**
     * retrieve field validators for this field type
     * @return array returns InclusionIn validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValidationMessage == null) {
            $msg = "please specify a valid port number (1-65535) or name (" . implode(",", self::$wellknownservices) .
                ")";
        } else {
            $msg = $this->internalValidationMessage;
        }

        if (($this->internalIsRequired == true || $this->internalValue != null) &&
            count(self::$internalOptionList) > 0) {
            $validators[] = new InclusionIn(array('message' => $msg,'domain'=>self::$internalOptionList));
        }
        return $validators;
    }
}
