<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\IPsec\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;

class ConnnectionField extends ArrayField
{
    private static $child_attrs = ['local_ts', 'remote_ts'];
    private static $child_data = null;

    /**
     * Add child attributes (virtual / read-only) to connection for query purposes
     */
    protected function actionPostLoadingEvent()
    {
        if (self::$child_data === null) {
            self::$child_data = [];
            foreach ($this->getParentModel()->children->child->iterateItems() as $node_uuid => $node) {
                if (empty((string)$node->enabled)) {
                    continue;
                }
                $conn_uuid = (string)$node->connection;
                if (!isset(self::$child_data[$conn_uuid])) {
                    self::$child_data[$conn_uuid] = [];
                }
                foreach (self::$child_attrs as $key) {
                    if (!isset(self::$child_data[$conn_uuid][$key])) {
                        self::$child_data[$conn_uuid][$key] = [];
                    }
                    self::$child_data[$conn_uuid][$key][] = (string)$node->$key;
                }
            }
        }
        foreach ($this->internalChildnodes as $node) {
            if (!$node->getInternalIsVirtual()) {
                $extra_attr = ['local_ts' => '', 'remote_ts' => ''];
                $conn_uuid = (string)$node->getAttribute('uuid');
                foreach (self::$child_attrs as $key) {
                    $child_node = new TextField();
                    $child_node->setInternalIsVirtual();
                    if (isset(self::$child_data[$conn_uuid]) && !empty(self::$child_data[$conn_uuid][$key])) {
                        $child_node->setValue(implode(',', array_unique(self::$child_data[$conn_uuid][$key])));
                    }
                    $node->addChildNode($key, $child_node);
                }
            }
        }
        return parent::actionPostLoadingEvent();
    }
}
