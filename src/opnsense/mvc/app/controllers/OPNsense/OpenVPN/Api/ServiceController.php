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

namespace OPNsense\OpenVPN\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\OpenVPN\OpenVPN;

/**
 * Class ServiceController
 * @package OPNsense\OpenVPN
 */
class ServiceController extends ApiControllerBase
{
    private function getConfigs($role)
    {
        $config = Config::getInstance()->object();
        $config_payload = [];
        $cnf_section = 'openvpn-' . $role;
        if (!empty($config->openvpn->$cnf_section)) {
            foreach ($config->openvpn->$cnf_section as $cnf) {
                if (!empty((string)$cnf->vpnid)) {
                    $config_payload[(string)$cnf->vpnid] = [
                        'description' => (string)$cnf->description ?? '',
                        'enabled' => empty((string)$cnf->disable) ? '1' : '0'
                    ];
                }
            }
        }
        foreach ((new OpenVPN())->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if ((string)$node->role == $role) {
                $config_payload[$node_uuid] = [
                    'enabled' => (string)$node->enabled,
                    'description' => (string)$node->description
                ];
            }
        }
        return $config_payload;
    }

    /**
     * Search sessions
     * @return array
     */
    public function searchSessionsAction()
    {
        $data = json_decode((new Backend())->configdRun('openvpn connections client,server'), true) ?? [];
        $records = [];
        $roles = ['client', 'server'];
        if ($this->request->has('type') && is_array($this->request->get('type'))) {
            $roles = array_intersect($this->request->get('type'), $roles);
        }
        foreach ($roles as $role) {
            $config_payload = $this->getConfigs($role);
            $vpnids = [];
            if (!empty($data[$role])) {
                foreach ($data[$role] as $idx => $stats) {
                    $vpnids[] = $idx;
                    $stats['type'] = $role;
                    $stats['id'] = $idx;
                    $stats['description'] =  '';
                    if (!empty($stats['timestamp'])) {
                        $stats['connected_since'] = date('Y-m-d H:i:s', $stats['timestamp']);
                    }
                    if (!empty($config_payload[$idx])) {
                        $stats['description'] = (string)$config_payload[$idx]['description'];
                    }
                    if (!empty($stats['client_list'])) {
                        foreach ($stats['client_list'] as $client) {
                            $tmp = array_merge($stats, $client);
                            $tmp['id'] .= '_' . $client['real_address'];
                            $tmp['is_client'] = true;
                            unset($tmp['client_list']);
                            unset($tmp['routing_table']);
                            $records[] = $tmp;
                        }
                    } else {
                        $records[] = $stats;
                    }
                }
            }
            // add non running enabled servers
            foreach ($config_payload as $idx => $cnf) {
                if (!in_array($idx, $vpnids) && !empty($cnf['enabled'])) {
                    $records[] = [
                        'id' => $idx,
                        'service_id' =>  "openvpn/" . $idx,
                        'type' => $role,
                        'description' => $cnf['description'],
                    ];
                }
            }
        }
        // make sure all records contain the same amount of keys to prevent sorting issues.
        $all_keys = [];
        foreach ($records as $record) {
            $all_keys = array_unique(array_merge(array_keys($record), $all_keys));
        }
        foreach ($records as &$record) {
            foreach ($all_keys as $key) {
                if (!isset($record[$key])) {
                    $record[$key] = null;
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    /**
     * Search routes
     * @return array
     */
    public function searchRoutesAction()
    {
        $records = [];
        $data = json_decode((new Backend())->configdRun('openvpn connections client,server'), true) ?? [];
        $records = [];
        $roles = ['client', 'server'];
        if ($this->request->has('type') && is_array($this->request->get('type'))) {
            $roles = array_intersect($this->request->get('type'), $roles);
        }
        foreach ($roles as $role) {
            if (!empty($data[$role])) {
                $config_payload = $this->getConfigs($role);
                foreach ($data[$role] as $idx => $payload) {
                    if (!empty($payload['routing_table'])) {
                        foreach ($payload['routing_table'] as $route_entry) {
                            $route_entry['type'] = $role;
                            $route_entry['id'] = $idx;
                            $route_entry['description'] =  '';
                            if (!empty($config_payload[$idx])) {
                                $route_entry['description'] = (string)$config_payload[$idx]['description'] ?? '';
                            }
                            $records[] = $route_entry;
                        }
                    }
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    /**
     * kill session by source ip:port or common name
     * @return array
     */
    public function killSessionAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $server_id = $this->request->get('server_id', null);
        $session_id = $this->request->get('session_id', null);
        if ($server_id != null && $session_id != null) {
            $data = json_decode((new Backend())->configdpRun('openvpn kill', [$server_id, $session_id]), true);
            if (!empty($data)) {
                return $data;
            }
            return ['result' => 'failed'];
        } else {
            return ['status' => 'invalid'];
        }
    }

    /**
     * @param int $id server/client id to start
     * @return array
     */
    public function startServiceAction($id = null)
    {
        if (!$this->request->isPost() || $id == null) {
            return ['result' => 'failed'];
        }

        (new Backend())->configdpRun('service start', ['openvpn', $id]);

        return ['result' => 'ok'];
    }

    /**
     * @param int $id server/client id to stop
     * @return array
     */
    public function stopServiceAction($id = null)
    {
        if (!$this->request->isPost() || $id == null) {
            return ['result' => 'failed'];
        }

        (new Backend())->configdpRun('service stop', ['openvpn', $id]);

        return ['result' => 'ok'];
    }

    /**
     * @param int $id server/client id to restart
     * @return array
     */
    public function restartServiceAction($id = null)
    {
        if (!$this->request->isPost() || $id == null) {
            return ['result' => 'failed'];
        }

        (new Backend())->configdpRun('service restart', ['openvpn', $id]);

        return ['result' => 'ok'];
    }

    /**
     * @return array
     */
    public function reconfigureAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }

        $backend = new Backend();
        $backend->configdRun('openvpn configure');
        $backend->configdRun('interface invoke registration');

        return ['result' => 'ok'];
    }
}
