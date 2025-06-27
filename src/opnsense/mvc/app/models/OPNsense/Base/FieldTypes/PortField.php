<?php

/*
 * Copyright (C) 2015-2020 Deciso B.V.
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
    private static $wellknownservices = [
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
     * generate validation data (list of port numbers and well know ports)
     */
    protected function actionPostLoadingEvent()
    {
        $setid = $this->enableWellKnown ? "1" : "0";
        $setid .= $this->enableAlias ? "1" : "0";
        if (empty(self::$internalCacheOptionList[$setid])) {
            self::$internalCacheOptionList[$setid] = [];
            if ($this->enableWellKnown) {
                foreach (["any"] + self::$wellknownservices as $wellknown) {
                    self::$internalCacheOptionList[$setid][(string)$wellknown] = $wellknown;
                }
            }
            if ($this->enableAlias) {
                foreach (self::getArrayReference(Alias::getCachedData(), 'aliases.alias') as $uuid => $alias) {
                    if (strpos($alias['type'], "port") !== false) {
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
        if ($this->enableWellKnown && in_array($tmp, ["any"] + self::$wellknownservices)) {
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
            $msg .= ' ' . sprintf(gettext('A service name is also possible (%s).'), implode(', ', self::$wellknownservices));
        }
        return $msg;
    }

    /**
     * @return array|string|null
     */
    public function getNodeData()
    {
        // XXX: although it's not 100% clean,
        //      when using a selector we generally would expect to return a (appendable) list of options.
        if ($this->internalMultiSelect) {
            return parent::getNodeData();
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
