<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Backend;


class DeviceField extends BaseListField
{
    private static $interfaces = [];

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        if (empty(self::$interfaces)) {
            /* pass backend call, but push through gettext() for group names */
            $response = (new Backend())->configdRun('interface list assign-opts', false, 20);
            $response = json_decode($response, true) ?? [];
            foreach ($response as $key => $value) {
                if (!empty($value['optgroup'])) {
                    $value['optgroup'] = gettext($value['optgroup']);
                }
                self::$interfaces[$key] = $value;
            }
        }
        $this->internalOptionList = self::$interfaces;
        return parent::actionPostLoadingEvent();
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        $this->getParentNode()->icon = 'fa fa-plug text-muted';
        if (isset(static::$interfaces[$value])) {
            $this->getParentNode()->optgroup = static::$interfaces[$value]['optgroup'] ?? '';
            if (!empty(static::$interfaces[$value]['data']) && !empty(static::$interfaces[$value]['data']['icon'])) {
                $this->getParentNode()->icon = static::$interfaces[$value]['data']['icon'];
            }
        }
        parent::setValue($value);
    }
}
