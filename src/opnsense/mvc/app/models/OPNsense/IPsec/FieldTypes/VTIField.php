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

class VTIField extends ArrayField
{
    private static $legacyItems = [];

    public function __construct($ref = null, $tagname = null)
    {
        if (empty(self::$legacyItems)) {
            // query legacy VTI devices, valid for the duration of this script execution
            $legacy_vtis = json_decode((new Backend())->configdRun('ipsec list legacy_vti'), true);
            if (!empty($legacy_vtis)) {
                foreach ($legacy_vtis as $vti) {
                    $vti['enabled'] = '1';
                    self::$legacyItems['ipsec' . $vti['reqid']] = $vti;
                }
            }
        }
        parent::__construct($ref, $tagname);
    }

    /**
     * create virtual VTI nodes
     */
    private function createReservedNodes()
    {
        $result = [];
        foreach (self::$legacyItems as $vtiName => $vtiContent) {
            $container_node = $this->newContainerField($this->__reference . "." . $vtiName, $this->internalXMLTagName);
            $container_node->setAttributeValue("uuid", $vtiName);
            $container_node->setInternalIsVirtual();
            foreach ($this->getTemplateNode()->iterateItems() as $key => $value) {
                $node = clone $value;
                $node->setInternalReference($container_node->__reference . "." . $key);
                if (isset($vtiContent[$key])) {
                    $node->setValue($vtiContent[$key]);
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
                $type_node->setValue('vti');
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
