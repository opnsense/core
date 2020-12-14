<?php

/*
 * Copyright (C) 2015-2019 Deciso B.V.
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

/**
 * Class VirtualIPField field type to select virtual ip's (such as carp)
 * @package OPNsense\Base\FieldTypes
 */
class VirtualIPField extends BaseListField
{

    /**
     * @var string virtual ip type
     */
    private $vipType = "*";

    /**
     * @var array cached collected certs
     */
    private static $internalStaticOptionList = array();

    /**
     * set virtual ip type (carp, proxyarp, ..)
     * @param $value string vip type
     */
    public function setType($value)
    {
        $this->vipType = $value;
    }

    /**
     * generate validation data (list of virtual ips)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList[$this->vipType])) {
            self::$internalStaticOptionList[$this->vipType] = array();
            $configObj = Config::getInstance()->object();
            if (!empty($configObj->virtualip) && !empty($configObj->virtualip->vip)) {
                $filter_types = explode(',', $this->vipType);
                foreach ($configObj->virtualip->vip as $vip) {
                    if ($this->vipType == '*' || in_array($vip->mode, $filter_types)) {
                        if (isset($configObj->{$vip->interface}->descr)) {
                            $intf_name = $configObj->{$vip->interface}->descr;
                        } else {
                            $intf_name = $vip->interface;
                        }
                        if (!empty($vip->vhid)) {
                            $caption = sprintf(
                                gettext("[%s] %s on %s (vhid %s)"),
                                $vip->subnet,
                                $vip->descr,
                                $intf_name,
                                $vip->vhid
                            );
                        } else {
                            $caption = sprintf(gettext("[%s] %s on %s"), $vip->subnet, $vip->descr, $intf_name);
                        }
                        self::$internalStaticOptionList[$this->vipType][(string)$vip->subnet] = $caption;
                    }
                }
                natcasesort(self::$internalStaticOptionList[$this->vipType]);
            }
        }
        $this->internalOptionList = self::$internalStaticOptionList[$this->vipType];
    }
}
