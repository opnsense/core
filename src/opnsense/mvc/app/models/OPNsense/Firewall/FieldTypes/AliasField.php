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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\FieldTypes\IntegerField;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

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
        foreach (glob(__DIR__ . "/../static_aliases/*.json") as $filename) {
            $payload = json_decode(file_get_contents($filename), true);
            if (is_array($payload)) {
                foreach ($payload as $aliasname => $content) {
                    $result[$aliasname] = $content;
                }
            }
        }
        foreach (Config::getInstance()->object()->interfaces->children() as $k => $n) {
            $table_name = sprintf("__%s_network", $k);
            $table_desc = !empty((string)$n->descr) ? (string)$n->descr : $k;
            $result[$table_name] = [
                "enabled" => "1",
                "name" => $table_name,
                "type" => "internal",
                "description" => sprintf("%s %s", $table_desc, gettext("net")),
                "content" => ""
            ];
        }
        return $result;
    }

    protected function actionPostLoadingEvent()
    {
        parent::actionPostLoadingEvent();
        if (self::$current_stats === null) {
            self::$current_stats = [];
            $stats = json_decode((new Backend())->configdRun('filter diag table_size'), true);
            if (!empty($stats) && !empty($stats['details'])) {
                self::$current_stats = $stats['details'];
            }
        }
        foreach ($this->iterateItems() as $node) {
            $current_items = new IntegerField();
            $current_items->setInternalIsVirtual();
            $last_updated = new TextField();
            $last_updated->setInternalIsVirtual();
            if (!empty((string)$node->name) && !empty(self::$current_stats[(string)$node->name])) {
                $current_items->setValue(self::$current_stats[(string)$node->name]['count']);
                $last_updated->setValue(self::$current_stats[(string)$node->name]['updated']);
            }
            $node->addChildNode('current_items', $current_items);
            $node->addChildNode('last_updated', $last_updated);
        }
    }
}
