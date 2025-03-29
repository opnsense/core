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
use OPNsense\Interfaces\Vip;

/**
 * Translation Network field type supporting aliases, virtual IP's and special nets
 * @package OPNsense\Base\FieldTypes
 */
class TranslationField extends BaseListField
{
    /**
     * @var array cached collected translation networks
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
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return sprintf(gettext("%s is not a valid translation IP address or alias."), (string)$this);
    }

    /**
     * generate validation data (list of translation networks)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList)) {
            self::$internalStaticOptionList = array();
        }
        if (empty(self::$internalStaticOptionList)) {
            self::$internalStaticOptionList = array();
            // interface nets and addresses
            $configObj = Config::getInstance()->object();
            foreach ($configObj->interfaces->children() as $ifname => $ifdetail) {
                if (!isset($ifdetail->virtual)) {
                    $descr = htmlspecialchars(!empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($ifname));        
                    self::$internalStaticOptionList[$ifname . "ip"] = $descr . " " . gettext("address");
                }
            }
            // virtual IP's
            $vipObj = new Vip();
            foreach ($vipObj->vip->iterateItems() as $vip) {
                if (empty((string)$vip->noexpand)) {
                    if ((string)$vip->mode == "proxyarp") {
                        $start = Util::ip2long32(Util::genSubnet((string)$vip->subnet, (string)$vip->subnet_bits));
                        $end = Util::ip2long32(Util::genSubnetMax((string)$vip->subnet, (string)$vip->subnet_bits));
                        $len = $end - $start;
                        self::$internalStaticOptionList[(string)$vip->subnet.'/'.(string)$vip->subnet_bits] = "Subnet: ".(string)$vip->subnet."/".(string)$vip->subnet_bits." (".(string)$vip->descr.")";
                        for ($i = 0; $i <= $len; $i++) {
                            $snip = Util::long2ip32($start+$i);
                            self::$internalStaticOptionList[$snip] = $snip." (".(string)$vip->descr.")";
                        }
                    } else {
                        self::$internalStaticOptionList[(string)$vip->subnet] = (string)$vip->subnet." (".(string)$vip->descr.")";
                    }
                }
            }
            // aliases
            $aliasObj = new Alias();
            foreach ($aliasObj->aliases->alias->iterateItems() as $alias) {
                if ((string)$alias->type == "host") {
                    self::$internalStaticOptionList[(string)$alias->name] = (string)$alias->name;
                }
            }
        }
        $this->internalOptionList = self::$internalStaticOptionList;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        if (isset($this->internalOptionList[(string)$this])) {
            return $this->internalOptionList[(string)$this];
        }
        return (string)$this;
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
