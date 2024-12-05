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

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Wireguard\General;
use OPNsense\Wireguard\Client;
use OPNsense\Wireguard\Server;

/**
 * Class ServiceController
 * @package OPNsense\Wireguard
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Wireguard\General';
    protected static $internalServiceTemplate = 'OPNsense/Wireguard';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'wireguard';

    /**
     * @return array
     */
    public function reconfigureAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }

        $backend = new Backend();
        $backend->configdRun('interface invoke registration');
        $backend->configdRun('template reload ' . escapeshellarg(static::$internalServiceTemplate));
        $backend->configdpRun('wireguard configure');

        return ['result' => 'ok'];
    }

    /**
     * wg show all dump output
     * @return array
     */
    public function showAction()
    {
        $payload = json_decode((new Backend())->configdRun("wireguard show") ?? '', true);
        $records = !empty($payload) && !empty($payload['records']) ? $payload['records'] : [];
        $key_descriptions = []; /* descriptions per interface + pub-key */
        $ifnames = []; /* interface / instance names */
        $peers = [];
        foreach ((new Client())->clients->client->iterateItems() as $key => $client) {
            $peers[$key] = ['name' => (string)$client->name , 'pubkey' =>  (string)$client->pubkey];
        }
        foreach ((new Server())->servers->server->iterateItems() as $key => $server) {
            $if = (string)$server->interface;
            $key_descriptions[$if . '-' . $server->pubkey] = (string)$server->name;
            foreach (explode(',', (string)$server->peers) as $peer) {
                if (!empty($peers[$peer])) {
                    $key_descriptions[$if . '-' . $peers[$peer]['pubkey']] = $peers[$peer]['name'];
                }
            }
            $ifnames[$if] =  (string)$server->name;
        }
        foreach ($records as &$record) {
            $record['name'] = '';
            if (!empty($record['public-key'])) {
                $key = $record['if'] . '-' . $record['public-key'];
                if (!empty($key_descriptions[$key])) {
                    $record['name'] = $key_descriptions[$key];
                }
            }
            $record['ifname'] = $ifnames[$record['if']];
        }
        $filter_funct = null;
        $types = $this->request->get('type');
        if (!empty($types)) {
            $filter_funct = function ($record) use ($types) {
                return in_array($record['type'], $types);
            };
        }
        return $this->searchRecordsetBase($records, null, null, $filter_funct);
    }
}
