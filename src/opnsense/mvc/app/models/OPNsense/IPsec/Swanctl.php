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
use OPNsense\Firewall\Util;

/**
 * Class Swanctl
 * @package OPNsense\IPsec
 */
class Swanctl extends BaseModel
{
    /**
     * generate swanctl configuration output, containing "pools" and "connections", locals, remotes and children
     * are treated as children of connection.
     * @return array
     */
    public function getConfig()
    {
        $data = ['connections' => [], 'pools' => []];
        $references = [
            'pools' => 'Pools.Pool',
            'connections' => 'Connections.Connection',
            'locals' => 'locals.local',
            'remotes' => 'remotes.remote',
            'children' => 'children.child',
        ];
        foreach ($references as $key => $ref) {
            foreach ($this->getNodeByReference($ref)->iterateItems() as $node_uuid => $node) {
                if (empty((string)$node->enabled)) {
                    continue;
                }
                $parent = null;
                $thisnode = [];
                foreach ($node->iterateItems() as $attr_name => $attr) {
                    if ($attr_name == 'connection' && isset($data['connections'][(string)$attr])) {
                        $parent = (string)$attr;
                        continue;
                    } elseif ($attr_name == 'pools') {
                        // pools are mapped by name for clearer identification and legacy support
                        if ((string)$attr != '') {
                            $pools = [];
                            foreach (explode(',', (string)$attr) as $pool_id) {
                                $is_uuid = preg_match(
                                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $pool_id
                                ) == 1;
                                if (isset($data['pools'][$pool_id])) {
                                    $pools[] = $data['pools'][$pool_id]['name'];
                                } elseif (!$is_uuid) {
                                    $pools[] = $pool_id;
                                }
                            }
                            if (!empty($pools)) {
                                $thisnode['pools'] = implode(',', $pools);
                            }
                        }
                        continue;
                    } elseif ($attr_name == 'enabled') {
                        if (empty((string)$attr)) {
                            // disabled entity
                            $thisnode = [];
                            break;
                        } else {
                            continue;
                        }
                    } elseif ((string)$attr == '') {
                        continue;
                    } elseif (is_a($attr, 'OPNsense\Base\FieldTypes\BooleanField')) {
                        $thisnode[$attr_name] = (string)$attr == '1' ? 'yes' : 'no';
                    } elseif ($attr_name == 'pubkeys') {
                        $tmp = [];
                        foreach (explode(',', (string)$attr) as $item) {
                            $tmp[] = $item . '.pem';
                        }
                        $thisnode[$attr_name] = implode(',', $tmp);
                    } else {
                        $thisnode[$attr_name] = (string)$attr;
                    }
                }
                if (empty($thisnode)) {
                    continue;
                } elseif (!empty($parent)) {
                    if (!isset($data['connections'][$parent][$key])) {
                        $data['connections'][$parent][$key] = [];
                    }
                    $data['connections'][$parent][$key][] = $thisnode;
                } else {
                    if (!isset($data[$key])) {
                        $data[$key] = [];
                    }
                    $data[$key][$node_uuid] = $thisnode;
                }
            }
        }
        return $data;
    }

    /**
     * return non legacy vti devices formatted like ipsec_get_configured_vtis()
     */
    public function getVtiDevices()
    {
        $result = [];
        foreach ($this->VTIs->VTI->iterateItems() as $node_uuid => $node) {
            if ((string)$node->origin != 'legacy' && (string)$node->enabled == '1') {
                $inet = strpos((string)$node->local_tunnel, ':') > 0 ? 'inet6' : 'inet';
                $result['ipsec' . (string)$node->reqid] = [
                    'reqid' => (string)$node->reqid,
                    'local' => (string)$node->local,
                    'remote' => (string)$node->remote,
                    'descr' => (string)$node->description,
                    'networks' => [
                        [
                            'inet' => $inet,
                            'tunnel_local' => (string)$node->tunnel_local,
                            'tunnel_remote' => (string)$node->tunnel_remote,
                            'mask' => Util::smallestCIDR(
                                [(string)$node->tunnel_local, (string)$node->tunnel_remote],
                                $inet
                            )
                        ]
                    ]
                ];
            }
        }
        return $result;
    }
}
