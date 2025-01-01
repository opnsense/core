<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Core\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\FieldTypes\IntegerField;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class TunableField extends ArrayField
{
    protected static $internalStaticChildren = [];

    private static $default_values = null;
    private static $static_entries = [];

    /**
     * {@inheritdoc}
     */
    protected static function getStaticChildren()
    {
        $result = [];
        foreach (self::$static_entries as $key => $item){
            $result[] = [
                'tunable' => $key,
                'value' => $item['value'] ?? '',
                'default_value' => $item['default'],
                'descr' => $item['description'],
                'type' => $item['type'] ?? '',
            ];
        }

        return $result;
    }

    protected function actionPostLoadingEvent()
    {
        if (self::$default_values === null) {
            self::$default_values = json_decode((new Backend())->configdRun('system sysctl gather'), true) ?? [];
            self::$static_entries = json_decode((new Backend())->configdRun('system sysctl defaults'), true) ?? [];
            foreach (self::$static_entries as $key => $item) {
                if (!empty(self::$default_values[$key])) {
                    self::$static_entries[$key]['type'] = self::$default_values[$key]['type'];
                    self::$static_entries[$key]['value'] = self::$default_values[$key]['value'];
                    self::$static_entries[$key]['descr'] = self::$default_values[$key]['description'];
                }
            }
        }
        foreach ($this->iterateItems() as $node) {
            if (isset(self::$static_entries[(string)$node->tunable])) {
                unset(self::$static_entries[(string)$node->tunable]);
            }
            if (isset(self::$default_values[(string)$node->tunable])) {
                $node->default_value->setValue(self::$default_values[(string)$node->tunable]['value']);
                $node->type->setValue(self::$default_values[(string)$node->tunable]['type']);
                if (empty((string)$node->descr)) {
                    $node->descr->setValue(self::$default_values[(string)$node->tunable]['description']);
                }
            }
        }
        parent::actionPostLoadingEvent();
    }
}
