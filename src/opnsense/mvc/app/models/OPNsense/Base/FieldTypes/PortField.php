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

use OPNsense\Firewall\Alias;

/**
 * Class PortField field type for ports, includes validation for services in /etc/services or valid number ranges.
 * @package OPNsense\Base\FieldTypes
 */
class PortField extends BaseListField
{
    /**
     * @var array list of well known services
     */
    protected static $wellknownservices = [
        'afs3-fileserver' => 7000,
        'aol' => 5190,
        'auth' => 113,
        'avt-profile-1' => 5004,
        'cvsup' => 5999,
        'domain' => 53,
        'ftp' => 21,
        'hbci' => 3000,
        'http' => 80,
        'https' => 443,
        'igmpv3lite' => 465,
        'imap' => 143,
        'imaps' => 993,
        'ipsec-msft' => 10000,
        'ipsec-nat-t' => 4500,
        'isakmp' => 500,
        'l2f' => 1701,
        'ldap' => 389,
        'microsoft-ds' => 445,
        'ms-streaming' => 1755,
        'ms-wbt-server' => 3389,
        'msnp' => 1863,
        'nat-stun-port' => 3478,
        'netbios-dgm' => 138,
        'netbios-ns' => 137,
        'netbios-ssn' => 139,
        'nntp' => 119,
        'ntp' => 123,
        'openvpn' => 1194,
        'pop3' => 110,
        'pop3s' => 995,
        'pptp' => 1723,
        'radius' => 1812,
        'radius-acct' => 1813,
        'rfb' => 5900,
        'sip' => 5060,
        'smtp' => 25,
        'snmp' => 161,
        'snmptrap' => 162,
        'ssh' => 22,
        'submission' => 587,
        'telnet' => 23,
        'teredo' => 3544,
        'tftp' => 69,
        'urd' => 465,
        'wins' => 1512,
    ];

    /**
     * @var array cached collected ports
     */
    private static $internalCacheOptionList = [];

    /**
     * @var bool enable well known ports
     */
    private $enableWellKnown = false;

    /**
     * @var bool enable port ranges
     */
    private $enableRanges = false;

    /**
     * @var bool enable aliases
     */
    private $enableAlias = false;

    /**
     * get the list of well known services
     * @var ?string ask for specific service
     * @return array service names
     */
    public static function getWellKnown(?string $search = null)
    {
        if (!is_null($search)) {
            return isset(self::$wellknownservices[$search]) ?
                [$search => self::$wellknownservices[$search]] : [];
        }

        return self::$wellknownservices;
    }

    /**
     * generate validation data (list of port numbers and well know ports)
     */
    protected function actionPostLoadingEvent()
    {
        $setid = $this->enableWellKnown ? "1" : "0";
        $setid .= $this->enableAlias ? "1" : "0";
        if (empty(self::$internalCacheOptionList[$setid])) {
            self::$internalCacheOptionList[$setid] = [];
            if ($this->enableWellKnown) {
                foreach (['any'] + array_keys(self::$wellknownservices) as $wellknown) {
                    self::$internalCacheOptionList[$setid][(string)$wellknown] = $wellknown;
                }
            }
            if ($this->enableAlias) {
                foreach (self::getArrayReference(Alias::getCachedData(), 'aliases.alias') as $uuid => $alias) {
                    if ($alias['type'] == 'port') {
                        self::$internalCacheOptionList[$setid][$alias['name']] = $alias['name'];
                    }
                }
            }
            for ($port = 1; $port <= 65535; $port++) {
                self::$internalCacheOptionList[$setid][(string)$port] = (string)$port;
            }
        }
        $this->internalOptionList = self::$internalCacheOptionList[$setid];
    }

    /**
     * setter for maximum value
     * @param integer $value
     */
    public function setEnableWellKnown($value)
    {
        $this->enableWellKnown = strtoupper(trim($value)) == 'Y';
    }

    /**
     * setter for maximum value
     * @param integer $value
     */
    public function setEnableAlias($value)
    {
        $this->enableAlias = strtoupper(trim($value)) == 'Y';
    }

    /**
     * setter for maximum value
     * @param integer $value
     */
    public function setEnableRanges($value)
    {
        $this->enableRanges = strtoupper(trim($value)) == 'Y';
    }

    /**
     * always lowercase known portnames
     * @param string $value
     */
    public function setValue($value)
    {
        $tmp = trim(strtolower($value));
        if ($this->enableWellKnown && in_array($tmp, ['any'] + array_keys(self::$wellknownservices))) {
            return parent::setValue($tmp);
        } else {
            return parent::setValue($value);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        $msg = gettext('Please specify a valid port number (1-65535).');
        if ($this->enableWellKnown) {
            $msg .= ' ' . sprintf(gettext('A service name is also possible (%s).'), implode(', ', array_keys(self::$wellknownservices)));
        }
        return $msg;
    }

    /**
     * @return array|string|null
     */
    protected function getNodeOptions()
    {
        // XXX: although it's not 100% clean,
        //      when using a selector we generally would expect to return a (appendable) list of options.
        if ($this->internalMultiSelect) {
            return parent::getNodeOptions();
        } else {
            return (string)$this;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        if ($this->enableRanges) {
            // add valid ranges to options
            foreach (explode(",", $this->internalValue) as $data) {
                if (strpos($data, "-") !== false) {
                    $tmp = explode('-', $data);
                    if (count($tmp) == 2) {
                        if (
                            filter_var(
                                $tmp[0],
                                FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => 1, 'max_range' => 65535]]
                            ) !== false &&
                            filter_var(
                                $tmp[1],
                                FILTER_VALIDATE_INT,
                                ['options' => ['min_range' => 1, 'max_range' => 65535]]
                            ) !== false &&
                            $tmp[0] < $tmp[1]
                        ) {
                            $this->internalOptionList[$data] = $data;
                        }
                    }
                }
            }
        }
        return parent::getValidators();
    }
}
