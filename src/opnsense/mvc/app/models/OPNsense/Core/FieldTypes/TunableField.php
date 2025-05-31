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

namespace OPNsense\Core\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\FieldTypes\IntegerField;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class TunableField extends ArrayField
{
    protected static $internalStaticChildren = [];

    private static $current_values = null;
    private static $static_entries = [];

    /**
     * {@inheritdoc}
     */
    protected static function getStaticChildren()
    {
        $result = [];
        foreach (self::$static_entries as $key => $item) {
            if (!empty($item['optional'])) {
                continue;
            }

            /* md5($key) ensures static keys identifiable as static options  */
            $result[md5($key)] = [
                'tunable' => $key,
                'value' => $item['value'] ?? '',
                'default_value' => $item['default'] ?? '',
                'descr' => $item['description'] ?? '',
                'type' => $item['type'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        if (self::$current_values === null && !$this->getParentModel()->isLazyLoaded()) {
            self::$current_values = json_decode((new Backend())->configdRun('system sysctl gather'), true) ?? [];
            self::$static_entries = json_decode((new Backend())->configdRun('system sysctl defaults'), true) ?? [];
            foreach (self::$static_entries as $key => $item) {
                if (!empty(self::$current_values[$key])) {
                    foreach (['type', 'value', 'description'] as $value) {
                        /* fill these when currently found but not preset in static */
                        if (!isset(self::$static_entries[$key][$value])) {
                            self::$static_entries[$key][$value] = self::$current_values[$key][$value];
                        }
                    }
                }
            }
        } elseif (self::$current_values === null) {
            self::$current_values = [];
        }
        foreach ($this->iterateItems() as $node) {
            if ($node->value == 'default') {
                /* deprecate 'default', the model uses empty value to signal this */
                $node->value = '';
            }
            if (isset(self::$current_values[(string)$node->tunable])) {
                /* fill current information from the system */
                if (empty((string)$node->type)) {
                    $node->type->setValue(self::$current_values[(string)$node->tunable]['type'] ?? '');
                }
                if (empty((string)$node->descr)) {
                    $node->descr->setValue(self::$current_values[(string)$node->tunable]['description'] ?? '');
                }
            }
            if (isset(self::$static_entries[(string)$node->tunable])) {
                /* fill static information if set explicitly */
                $node->default_value->setValue(self::$static_entries[(string)$node->tunable]['default'] ?? '');
                if (empty((string)$node->type)) {
                    $node->type->setValue(self::$static_entries[(string)$node->tunable]['type'] ?? '');
                }
                if (empty((string)$node->descr)) {
                    $node->descr->setValue(self::$static_entries[(string)$node->tunable]['description'] ?? '');
                }
                /* set static entry to invisible */
                self::$static_entries[(string)$node->tunable]['optional'] = true;
            }
        }
        parent::actionPostLoadingEvent();
    }
}
