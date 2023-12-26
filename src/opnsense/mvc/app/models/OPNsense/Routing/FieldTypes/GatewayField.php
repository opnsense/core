<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Routing\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;

class GatewayField extends ArrayField
{
    /* XXX: make private when legacy cruft is removed*/
    public static function getDpingerDefaults()
    {
        return [
            'data_length' => 1,
            'interval' => 1,
            'latencyhigh' => 500,
            'latencylow' => 200,
            'loss_interval' => 4,
            'losshigh' => 20,
            'losslow' => 10,
            'time_period' => 60,
        ];
    }

    /**
     * add or update "current_" values for fields defined in getDpingerDefaults()
     */
    public function calculateCurrent($node)
    {
        foreach ($this->getDpingerDefaults() as $property => $default) {
            if (!is_a($node->getParentNode(), 'OPNsense\Routing\FieldTypes\GatewayField')) {
                continue;
            }
            $targetfield = sprintf("current_%s", $property);
            if (!isset($node->$targetfield)) {
                $new_item = new TextField();
                $new_item->setInternalIsVirtual();
                $node->addChildNode($targetfield, $new_item);
            }
            $value = !empty((string)$node->$property) ? (string)$node->$property : (string)$default;
            $node->$targetfield = $value;
        }
    }

    /**
     * push virtual fields for current (calculated) values
     */
    protected function actionPostLoadingEvent()
    {
        foreach ($this->internalChildnodes as $node) {
            $this->calculateCurrent($node);
        }
        return parent::actionPostLoadingEvent();
    }
}
