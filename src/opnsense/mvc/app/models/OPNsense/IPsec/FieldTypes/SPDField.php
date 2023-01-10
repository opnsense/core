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
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class SPDField extends ArrayField
{
    private static $legacyItems = [];

    public function __construct($ref = null, $tagname = null)
    {
        if (empty(self::$legacyItems)) {
            $config = Config::getInstance()->object();
            $phase1s = [];
            $legacy_spds = [];
            if (!empty($config->ipsec->phase1)) {
                foreach ($config->ipsec->phase1 as $p1) {
                    if (!empty((string)$p1->ikeid)) {
                        $phase1s[(string)$p1->ikeid] = $p1;
                    }
                }
            }
            if (!empty($config->ipsec->phase2)) {
                $idx = 0;
                foreach ($config->ipsec->phase2 as $p2) {
                    ++$idx;
                    if (!empty((string)$p2->spd) && !empty($phase1s[(string)$p2->ikeid])) {
                        $reqid = !empty((string)$p2->reqid) ? (string)$p2->reqid : '0';
                        foreach (explode(',', (string)$p2->spd) as $idx2 => $spd) {
                            $spdkey = 'spd_' . (string)$p2->ikeid . '_' . (string)$idx . '_' . $idx2;
                            self::$legacyItems[$spdkey] = [
                                'enabled' => '1',
                                'reqid' => $reqid,
                                'source' => $spd,
                                'description' => (string)$p2->descr
                            ];
                        }
                    }
                }
            }
        }
        parent::__construct($ref, $tagname);
    }

    /**
     * create virtual SPD nodes
     */
    private function createReservedNodes()
    {
        $result = [];
        foreach (self::$legacyItems as $vtiName => $spdContent) {
            $container_node = $this->newContainerField($this->__reference . "." . $vtiName, $this->internalXMLTagName);
            $container_node->setAttributeValue("uuid", $vtiName);
            $container_node->setInternalIsVirtual();
            foreach ($this->getTemplateNode()->iterateItems() as $key => $value) {
                $node = clone $value;
                $node->setInternalReference($container_node->__reference . "." . $key);
                if (isset($spdContent[$key])) {
                    $node->setValue($spdContent[$key]);
                }
                $node->markUnchanged();
                $container_node->addChildNode($key, $node);
            }
            $type_node = new TextField();
            $type_node->setInternalIsVirtual();
            $type_node->setValue('legacy');
            $container_node->addChildNode('origin', $type_node);
            $result[$vtiName] = $container_node;
        }
        return $result;
    }

    protected function actionPostLoadingEvent()
    {
        foreach ($this->internalChildnodes as $node) {
            if (!$node->getInternalIsVirtual()) {
                $type_node = new TextField();
                $type_node->setInternalIsVirtual();
                $type_node->setValue('spd');
                $node->addChildNode('origin', $type_node);
            }
        }
        return parent::actionPostLoadingEvent();
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
