<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class LinkAddressField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    private static $known_addresses = null;
    private static $option_groups = [];

    protected function actionPostLoadingEvent()
    {
        if (self::$known_addresses === null) {
            self::$known_addresses = [];
            $cfg = Config::getInstance()->object();
            self::$option_groups['interfaces'] = ['items' => [], 'title' => gettext('Interfaces')];
            self::$option_groups['carp'] = ['items' => [], 'title' => gettext('CARP')];
            self::$option_groups['ipalias'] = ['items' => [], 'title' => gettext('IP Alias')];
            foreach ($cfg->interfaces->children() as $ifname => $node) {
                $descr = !empty((string)$node->descr) ? (string)$node->descr : strtoupper($ifname);
                if (!empty((string)$node->virtual) || empty((string)$node->enable)) {
                    continue;
                }
                self::$known_addresses[$ifname] = $descr;
                self::$option_groups['interfaces']['items'][$ifname] = $descr;
            }
            if (isset($cfg->virtualip)) {
                foreach ($cfg->virtualip->children() as $node) {
                    $descr = sprintf("%s (%s)", $node->subnet, $node->descr);
                    if ($node->mode == 'carp') {
                        $key = $node->interface . "_vip" . $node->vhid;
                    } elseif ((string)$node->mode == 'ipalias') {
                        $key = (string)$node->subnet;
                    } else {
                        continue;
                    }
                    self::$known_addresses[$key] = $descr;
                    self::$option_groups[(string)$node->mode]['items'][$key] = $descr;
                }
            }
            foreach (array_keys(self::$option_groups) as $item) {
                if (empty(self::$option_groups[$item]['items'])) {
                    unset(self::$option_groups[$item]['items']);
                } else {
                    natcasesort(self::$option_groups[$item]['items']);
                }
            }
        }
        return parent::actionPostLoadingEvent();
    }

    public function getPredefinedOptions()
    {
        $this->actionPostLoadingEvent();
        return self::$option_groups;
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                $messages = [];
                if (isset(self::$known_addresses[$data])) {
                    return $messages;
                } elseif (!Util::isIpAddress($data)) {
                    $messages[] = gettext('A valid network address is required.');
                }
                return $messages;
            }]);
        }

        return $validators;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $value = getCurrentValue();

        if (isset(self::$known_addresses[$value])) {
            return self::$known_addresses[$value];
        }

        return $value;
    }

    /**
     * return either ipaddr or if field, only one should be used, addresses are preferred.
     */
    public function getCurrentValue()
    {
        $parent = $this->getParentNode();
        foreach (['ipaddr', 'if'] as $fieldname) {
            if (!empty((string)$parent->$fieldname)) {
                return (string)$parent->$fieldname;
            }
        }
        /* XXX not the current value? */
        return (string)$this->internalValue;
    }

    /**
     * Reflect interface or address choice into their appropriate fields.
     */
    public function setValue($value)
    {
        $value = (string)$value;
        $parent = $this->getParentNode();
        if (Util::isIpAddress($value)) {
            $parent->ipaddr = $value;
            $parent->if = '';
        } else {
            $parent->if = $value;
            $parent->ipaddr = '';
        }
    }
}
