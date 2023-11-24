<?php

/*
 * Copyright (C) 2023 Deciso B.V.
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Wireguard\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Wireguard\Server;

class ClientController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'client';
    protected static $internalModelClass = '\OPNsense\Wireguard\Client';

    public function searchClientAction()
    {
        return $this->searchBase(
            'clients.client',
            ["enabled", "name", "pubkey", "tunneladdress", "serveraddress", "serverport"]
        );
    }

    public function getClientAction($uuid = null)
    {
        $result = $this->getBase('client', 'clients.client', $uuid);
        if (!empty($result['client'])) {
            $result['client']['servers'] = [];
            foreach ((new Server())->servers->server->iterateItems() as $key => $node) {
                $result['client']['servers'][$key] = [
                    'value' => (string)$node->name,
                    'selected' => in_array($uuid, explode(',', (string)$node->peers)) ? '1' : '0'
                ];
            }
        }
        return $result;
    }

    public function addClientAction()
    {
        return $this->setClientAction(null);
    }

    public function delClientAction($uuid)
    {
        return $this->delBase('clients.client', $uuid);
    }

    public function setClientAction($uuid)
    {
        $add_uuid = null;
        if (!empty($this->request->getPost(static::$internalModelName)) && $this->request->isPost()) {
            $servers = [];
            if (!empty($this->request->getPost(static::$internalModelName)['servers'])) {
                $servers = explode(',', $this->request->getPost(static::$internalModelName)['servers']);
            }
            Config::getInstance()->lock();
            $mdl = new Server();
            if (empty($uuid)) {
                // add new client, generate uuid
                $uuid = $mdl->servers->generateUUID();
                $add_uuid = $uuid;
            }
            foreach ($mdl->servers->server->iterateItems() as $key => $node) {
                $peers = array_filter(explode(',', (string)$node->peers));
                if (in_array($uuid, $peers) && !in_array($key, $servers)) {
                    $node->peers = implode(',', array_diff($peers, [$uuid]));
                } elseif (!in_array($uuid, $peers) && in_array($key, $servers)) {
                    $node->peers = implode(',', array_merge($peers, [$uuid]));
                }
            }
            /**
             * Save to in memory model.
             * Ignore validations as $uuid might be new or trigger an existing validation issue.
             * Persisting the data is handled by setBase()
             */
            $mdl->serializeToConfig(false, true);
        }
        $result = $this->setBase('client', 'clients.client', $uuid);
        if (!empty($add_uuid) && $result['result'] == 'saved') {
            $result['uuid'] = $add_uuid;
        }
        return $result;
    }

    public function toggleClientAction($uuid)
    {
        return $this->toggleBase('clients.client', $uuid);
    }
}
