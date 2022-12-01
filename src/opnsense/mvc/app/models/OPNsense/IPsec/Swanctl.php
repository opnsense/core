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

namespace OPNsense\IPsec;

use OPNsense\Base\BaseModel;

/**
 * Class Swanctl
 * @package OPNsense\IPsec
 */
class Swanctl extends BaseModel
{
    public function getConfig()
    {
        $data = [];
        $references = [
            'connections' => 'Connections.Connection',
            'locals' => 'locals.local',
            'remotes' => 'remotes.remote',
            'children' => 'children.child'
        ];
        foreach ($references as $key => $ref) {
            $data[$key] = [];
            foreach ($this->getNodeByReference($ref)->iterateItems() as $node_uuid => $node) {
                if (empty((string)$node->enabled)) {
                    continue;
                }
                $data[$key][$node_uuid] = [];
                foreach ($node->iterateItems() as $attr_name => $attr) {
                    if (in_array($attr_name, ['connection', 'enabled']) || (string)$attr == '') {
                        continue;
                    } elseif (is_a($attr, 'OPNsense\Base\FieldTypes\BooleanField')) {
                        $data[$key][$node_uuid][$attr_name] = (string)$attr == '1' ? 'yes' : 'no';
                    } elseif ($attr_name == 'pubkeys') {
                        $tmp = [];
                        foreach (explode(',', (string)$attr) as $item) {
                            $tmp[] = $item . '.pem';
                        }
                        $data[$key][$node_uuid][$attr_name] = implode(',', $tmp);
                    } else {
                        $data[$key][$node_uuid][$attr_name] = (string)$attr;
                    }
                }
            }
        }
        print_r($data);
        return [];
    }
}
