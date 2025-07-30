<?php

/*
 * Copyright (C) 2020-2025 Deciso B.V.
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

use ReflectionClass;
use ReflectionException;
use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Core\Backend;

class AliasField extends ArrayField
{
    private static $current_stats = null;

    /**
     * {@inheritdoc}
     */
    protected static $internalStaticChildren = [];

    /**
     * {@inheritdoc}
     */
    protected static function getStaticChildren()
    {
        $result = [];
        foreach (glob(__DIR__ . "/../DynamicAliases/*.php") as $filename) {
            $origin = explode('.', basename($filename))[0];
            $classname = 'OPNsense\\Firewall\\DynamicAliases\\' . $origin;
            try {
                $obj = (new ReflectionClass($classname))->newInstance();
                $payload = $obj->collect();
                if (is_array($payload)) {
                    foreach ($payload as $aliasname => $content) {
                        /* XXX: will overwrite when exists */
                        $result[$aliasname] = $content;
                    }
                }
            } catch (\Error | \Exception | ReflectionException $e) {
                syslog(LOG_ERR, sprintf(
                    "Invalid DynamicAliases object %s in %s (%s)",
                    $classname,
                    realpath($filename),
                    $e->getMessage()
                ));
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        parent::actionPostLoadingEvent();
        if ($this->getParentModel()->isLazyLoaded()) {
            /* skip dynamic content */
            return;
        }
        if (self::$current_stats === null) {
            self::$current_stats = [];
            $stats = json_decode((new Backend())->configdRun('filter diag table_size'), true);
            if (!empty($stats) && !empty($stats['details'])) {
                self::$current_stats = $stats['details'];
            }
        }
        foreach ($this->iterateItems() as $node) {
            if (!empty((string)$node->name) && !empty(self::$current_stats[(string)$node->name])) {
                $node->current_items->setValue(self::$current_stats[(string)$node->name]['count']);
                $node->last_updated->setValue(self::$current_stats[(string)$node->name]['updated']);
                foreach (
                    [
                    'eval_nomatch', 'eval_match', 'in_block_p', 'in_block_b', 'in_pass_p',
                    'in_pass_b', 'out_block_p', 'out_block_b', 'out_pass_p', 'out_pass_b'
                    ] as $prop
                ) {
                    $node->$prop->setValue(self::$current_stats[(string)$node->name][$prop] ?? '');
                }
            }
        }
    }
}
