<?php

/*
 * Copyright (C) 2020-2026 Deciso B.V.
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
     * @return string|null
     */
    protected function getNodeOptions()
    {
        // XXX: don't use as list, only for validation
        return (string)$this;
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return sprintf(gettext("%s is not a valid source IP address or alias."), (string)$this);
    }

    /**
     * generate validation data (list of protocols)
     */
    protected function actionPostLoadingEvent()
    {
        if ($this->hasStaticOptions()) {
            $this->internalOptionList = $this->getStaticOptions();
            return;
        }
        // static nets
        $data = [
            'any' => gettext('any'),
            '(self)' => gettext("This Firewall")
        ];
        // interface nets and addresses
        foreach (Config::getInstance()->object()->interfaces->children() as $ifname => $ifdetail) {
            $descr = htmlspecialchars(!empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($ifname));
            $data[$ifname] = $descr . " " . gettext("net");
            if (!isset($ifdetail->virtual)) {
                $data[$ifname . "ip"] = $descr . " " . gettext("address");
            }
        }
        // aliases
        foreach (self::getArrayReference(Alias::getCachedData(), 'aliases.alias') as $uuid => $alias) {
            if ($alias['type'] != 'port') {
                $data[$alias['name']] = $alias['name'];
            }
        }
        $this->internalOptionList = $this->setStaticOptions($data);
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
