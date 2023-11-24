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
    protected static $internalStaticChildren = [];

    /**
     * {@inheritdoc}
     */
    protected static function getStaticChildren()
    {
        $result = [];
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
                        $result[$spdkey] = [
                            'enabled' => '1',
                            'reqid' => $reqid,
                            'source' => $spd,
                            'description' => (string)$p2->descr
                        ];
                    }
                }
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
        foreach ($this->iterateItems() as $key => $node) {
            $type_node = new TextField();
            $type_node->setInternalIsVirtual();
            $type_node->setValue(strpos($key, 'spd') === 0 ? 'legacy' : 'spd');
            $node->addChildNode('origin', $type_node);
        }
    }
}
