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
     * hook group interface registration on reconfigure
     * @return bool
     */
    protected function invokeInterfaceRegistration()
    {
        return true;
    }

    /**
     * @return array
     */
    public function reconfigureAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }

        $this->sessionClose();
        $backend = new Backend();
        $backend->configdRun('template reload ' . escapeshellarg(static::$internalServiceTemplate));
        $backend->configdpRun('wireguard configure');

        return ['result' => 'ok'];
    }

    /**
     * show wireguard config
     * XXX: remove in 24.1
     * @return array
     */
    public function showconfAction()
    {
        $response = (new Backend())->configdRun("wireguard showconf");
        return array("response" => $response);
    }

    /**
     * show wireguard handshakes
     * XXX: remove in 24.1
     * @return array
     */
    public function showhandshakeAction()
    {
        $response = (new Backend())->configdRun("wireguard showhandshake");
        return array("response" => $response);
    }

    /**
     * wg show all dump output
     * @return array
     */
    public function showAction()
    {
        $payload = json_decode((new Backend())->configdRun("wireguard show") ?? '', true);
        $records = !empty($payload) && !empty($payload['records']) ? $payload['records'] : [];
        $key_descriptions = [];
        $ifnames = [];
        foreach ((new Client())->clients->client->iterateItems() as $key => $client) {
            $key_descriptions[(string)$client->pubkey] = (string)$client->name;
        }
        foreach ((new Server())->servers->server->iterateItems() as $key => $server) {
            $key_descriptions[(string)$server->pubkey] = (string)$server->name;
            $ifnames[(string)$server->interface] =  (string)$server->name;
        }
        foreach ($records as &$record) {
            if (!empty($record['public-key']) && !empty($key_descriptions[$record['public-key']])) {
                $record['name'] = $key_descriptions[$record['public-key']];
            } else {
                $record['name'] = '';
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
