<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

use OPNsense\Core\Config;
use OPNsense\Firewall\Util;
use OPNsense\Firewall\Alias;
use OPNsense\Base\Validators\CallbackValidator;

/**
 * Network field type supporting aliases and special nets
 * @package OPNsense\Base\FieldTypes
 */
class NetworkAliasField extends BaseListField
{

    /**
     * @var array cached collected protocols
     */
    private static $internalStaticOptionList = array();

    /**
     * @return string|null
     */
    public function getNodeData()
    {
        // XXX: don't use as list, only for validation
        return (string)$this;
    }

    /**
     * @inheritDoc
     */
    protected function getValidationMessage()
    {
        if ($this->internalValidationMessage == null) {
            return sprintf(
                gettext("%s is not a valid source IP address or alias."),
                (string)$this
            );
        } else {
            return $this->internalValidationMessage;
        }
    }

    /**
     * generate validation data (list of protocols)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList)) {
            self::$internalStaticOptionList = array();
        }
        if (empty(self::$internalStaticOptionList)) {
            self::$internalStaticOptionList = array();
            // static nets
            self::$internalStaticOptionList['any'] = gettext('any');
            self::$internalStaticOptionList['(self)'] = gettext("This Firewall");
            // interface nets and addresses
            $configObj = Config::getInstance()->object();
            foreach ($configObj->interfaces->children() as $ifname => $ifdetail) {
                $descr = htmlspecialchars(!empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($ifname));
                self::$internalStaticOptionList[$ifname] = $descr . " " . gettext("net");
                if (!isset($ifdetail->virtual)) {
                    self::$internalStaticOptionList[$ifname . "ip"] = $descr . " " . gettext("address");
                }
            }
            // aliases
            $aliasMdl = new Alias();
            foreach ($aliasMdl->aliases->alias->iterateItems() as $alias) {
                if (strpos((string)$alias->type, "port") === false) {
                    self::$internalStaticOptionList[(string)$alias->name] = (string)$alias->name;
                }
            }
        }
        $this->internalOptionList = self::$internalStaticOptionList;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($value)
    {
        // XXX: only single items are supported
        throw new \Exception("Unsupported feature setMultiple()");
    }

    /**
     * retrieve field validators for this field type
     * @return array
     */
    public function getValidators()
    {
        if (Util::isIpAddress((string)$this) || Util::isSubnet((string)$this)) {
            // add to option list if input is a valid network or host
            $this->internalOptionList[(string)$this] = (string)$this;
        }
        return parent::getValidators();
    }
}
