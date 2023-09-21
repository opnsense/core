<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\IPsec\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Core\Backend;

class VTIField extends ArrayField
{
    protected static $internalStaticChildren = [];

    /**
     * {@inheritdoc}
     */
    protected static function getStaticChildren()
    {
        $result = [];
        $legacy_vtis = json_decode((new Backend())->configdRun('ipsec list legacy_vti'), true);
        if (!empty($legacy_vtis)) {
            foreach ($legacy_vtis as $vti) {
                $vti['enabled'] = '1';
                $result['ipsec' . $vti['reqid']] = $vti;
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
            $type_node->setValue(strpos($key, 'ipsec') === 0 ? 'legacy' : 'vti');
            $node->addChildNode('origin', $type_node);
        }
    }
}
