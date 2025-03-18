<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

use OPNsense\Core\Config;
use OPNsense\Firewall\Group;
use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;

/**
 * Class FilterRuleContainerField
 * @package OPNsense\Firewall\FieldTypes
 */
class FilterRuleContainerField extends ContainerField
{
    /**
     * Interface group weight
     */
    private static $ifgroups = null;

    /**
     * map rules
     * @return array
     */
    public function serialize()
    {
        $result = [
            'label' => $this->getAttribute('uuid')
        ];
        $map_manual = ['source_net', 'source_not', 'source_port', 'destination_net', 'destination_not',
            'destination_port', 'enabled', 'description', 'sequence', 'action', 'replyto'];
        // 1-on-1 map (with type conversion if needed)
        foreach ($this->iterateItems() as $key => $node) {
            if (!in_array($key, $map_manual)) {
                if (is_a($node, "OPNsense\\Base\\FieldTypes\\BooleanField")) {
                    $result[$key] = !empty((string)$node);
                } elseif (is_a($node, "OPNsense\\Base\\FieldTypes\\ProtocolField")) {
                    if ((string)$node != 'any') {
                        $result[$key] = (string)$node;
                    }
                } else {
                    $result[$key] = (string)$node;
                }
            }
        }
        // source / destination mapping
        if (!empty((string)$this->source_net)) {
            $result['from'] = (string)$this->source_net;
            if (!empty((string)$this->source_not)) {
                $result['from_not'] = true;
            }
            if (!empty((string)$this->source_port)) {
                $result['from_port'] = (string)$this->source_port;
            }
        }
        if (!empty((string)$this->destination_net)) {
            $result['to'] = (string)$this->destination_net;
            if (!empty((string)$this->destination_not)) {
                $result['to_not'] = true;
            }
            if (!empty((string)$this->destination_port)) {
                $result['to_port'] = (string)$this->destination_port;
            }
        }
        // field mappings and differences
        $result['disabled'] = empty((string)$this->enabled);
        $result['descr'] = (string)$this->description;
        $result['type'] = (string)$this->action;
        $result['reply-to'] = (string)$this->replyto;
        if (strpos((string)$this->interface, ",") !== false) {
            $result['floating'] = true;
        }
        return $result;
    }

    /**
     * rule priority is treated equally to the legacy rules, first "floating" then groups and single interface
     * rules are handled last
     * @return int priority in the ruleset, sequence should determine sort order.
     */
    public function getPriority()
    {
        $configObj = Config::getInstance()->object();
        $interface = (string)$this->interface;
        if (strpos($interface, ",") !== false || empty($interface)) {
            // floating (multiple interfaces involved)
            return 200000;
        } elseif (
            !empty($configObj->interfaces) &&
            !empty($configObj->interfaces->$interface) &&
            !empty($configObj->interfaces->$interface->type) &&
            $configObj->interfaces->$interface->type == 'group'
        ) {
            if (static::$ifgroups === null) {
                static::$ifgroups = [];
                foreach ((new Group())->ifgroupentry->iterateItems() as $node) {
                    if (!empty((string)$node->sequence)) {
                        static::$ifgroups[(string)$node->ifname] =  (int)((string)$node->sequence);
                    }
                }
            }
            if (!isset(static::$ifgroups[$interface])) {
                static::$ifgroups[$interface] = 0;
            }
            // group type
            return 300000 + static::$ifgroups[$interface];
        } else {
            // default
            return 400000;
        }
    }
}

/**
 * Class FilterRuleField
 * @package OPNsense\Firewall\FieldTypes
 */
class FilterRuleField extends ArrayField
{
    /**
     * @inheritDoc
     */
    public function newContainerField($ref, $tagname)
    {
        $container_node = new FilterRuleContainerField($ref, $tagname);
        $parentmodel = $this->getParentModel();
        $container_node->setParentModel($parentmodel);
        return $container_node;
    }

    protected function actionPostLoadingEvent()
    {
        foreach ($this->internalChildnodes as $node) {
            /**
             * Evaluation order consists of a priority group and a sequence within the set,
             * prefixed with 0 as these precede legacy rules
             **/
            $node->sort_order = sprintf("%d.0%06d", $node->getPriority(), (string)$node->sequence);
        }
        return parent::actionPostLoadingEvent();
    }
}
