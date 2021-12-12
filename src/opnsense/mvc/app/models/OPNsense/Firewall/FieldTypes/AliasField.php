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

class AliasField extends ArrayField
{
    private static $reservedItems = [
        "bogons" => [
            "enabled" => "1",
            "name" => "bogons",
            "type" => "external",
            "description" => "bogon networks (internal)",
            "content" => ""
        ],
        "bogonsv6" => [
            "enabled" => "1",
            "name" => "bogonsv6",
            "type" => "external",
            "description" => "bogon networks IPv6 (internal)",
            "content" => ""
        ],
        "virusprot" => [
            "enabled" => "1",
            "name" => "virusprot",
            "type" => "external",
            "description" => "overload table for rate limiting (internal)",
            "content" => ""
        ],
        "sshlockout" => [
            "enabled" => "1",
            "name" => "sshlockout",
            "type" => "external",
            "description" => "abuse lockout table (internal)",
            "content" => ""
        ]
    ];

    private static $current_stats = null;

    protected function actionPostLoadingEvent()
    {
        if (self::$current_stats === null) {
            self::$current_stats = [];
            $stats = json_decode((new Backend())->configdRun('filter diag table_size'), true);
            if (!empty($stats) && !empty($stats['details'])) {
                self::$current_stats = $stats['details'];
            }
        }
        foreach ($this->internalChildnodes as $node) {
            if (!$node->getInternalIsVirtual()) {
                // generate new unattached fields, which are only usable to read data from (not synched to config.xml)
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
        return parent::actionPostLoadingEvent();
    }

    /**
     * create virtual alias nodes
     */
    private function createReservedNodes()
    {
        $result = [];
        foreach (self::$reservedItems as $aliasName => $aliasContent) {
            $container_node = $this->newContainerField($this->__reference . "." . $aliasName, $this->internalXMLTagName);
            $container_node->setAttributeValue("uuid", $aliasName);
            $container_node->setInternalIsVirtual();
            foreach ($this->getTemplateNode()->iterateItems() as $key => $value) {
                $node = clone $value;
                $node->setInternalReference($container_node->__reference . "." . $key);
                if (isset($aliasContent[$key])) {
                    $node->setValue($aliasContent[$key]);
                }
                $container_node->addChildNode($key, $node);
            }
            $result[$aliasName] = $container_node;
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function hasChild($name)
    {
        if (isset(self::$reservedItems[$name])) {
            return true;
        } else {
            return parent::hasChild($name);
        }
    }

    /**
     * @inheritdoc
     */
    public function getChild($name)
    {
        if (isset(self::$reservedItems[$name])) {
            return $this->createReservedNodes()[$name];
        } else {
            return parent::getChild($name);
        }
    }

    /**
     * @inheritdoc
     */
    public function iterateItems()
    {
        foreach (parent::iterateItems() as $key => $value) {
            yield $key => $value;
        }
        foreach ($this->createReservedNodes() as $key => $node) {
            yield $key => $node;
        }
    }
}
