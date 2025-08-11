<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\OpenVPN\FieldTypes;

use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Base\FieldTypes\IntegerField;

/**
 * @package OPNsense\Base\FieldTypes
 */
class VPNIdField extends IntegerField
{
    private static $internalLegacyVPNids = [];

    /**
     * fetch (legacy) vpn id's as these are reserved
     */
    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalLegacyVPNids)) {
            self::$internalLegacyVPNids = $this->getParentModel()->usedVPNIds();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if ($value == '') {
            // enforce default when not set
            for ($i = 1; true; $i++) {
                if (!in_array($i, self::$internalLegacyVPNids)) {
                    $this->internalValue = (string)$i;
                    $this_uuid = $this->getParentNode()->getAttributes()['uuid'] ?? (string)$i;
                    self::$internalLegacyVPNids[$this_uuid] = $i;
                    break;
                }
            }
        } else {
            parent::setValue($value);
        }
    }

    /**
     * retrieve field validators for this field type
     * @return array returns list of validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        $vpnids = self::$internalLegacyVPNids;
        $this_uuid = $this->getParentNode()->getAttributes()['uuid'];

        $validators[] = new CallbackValidator(
            [
                "callback" => function ($value) use ($vpnids, $this_uuid) {
                    foreach ($vpnids as $key => $vpnid) {
                        if ($vpnid == $value && $key != $this_uuid) {
                            return [gettext('Value should be unique')];
                        }
                    }
                    return [];
                }
            ]
        );

        return $validators;
    }
}
