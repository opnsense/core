<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;

class TosField extends BaseListField
{
    private static $tos_values = [];

    protected function actionPostLoadingEvent()
    {
        if (empty(self::$tos_values)) {
            self::$tos_values = [
                '' => gettext('Any'),
                'lowdelay' => gettext('lowdelay'),
                'critical' => gettext('critical'),
                'inetcontrol' => gettext('inetcontrol'),
                'netcontrol' => gettext('netcontrol'),
                'throughput' => gettext('throughput'),
                'reliability' => gettext('reliability'),
                'ef' => 'EF',
            ];

            foreach (array(11, 12, 13, 21, 22, 23, 31, 32, 33, 41 ,42, 43) as $val) {
                self::$tos_values["af$val"] = "AF$val";
            }

            foreach (range(0, 7) as $val) {
                self::$tos_values["cs$val"] = "CS$val";
            }

            foreach (range(0, 255) as $val) {
                self::$tos_values['0x' . dechex($val)] = sprintf('0x%02X', $val);
            }
        }
        $this->internalOptionList = self::$tos_values;
        return parent::actionPostLoadingEvent();
    }
}
