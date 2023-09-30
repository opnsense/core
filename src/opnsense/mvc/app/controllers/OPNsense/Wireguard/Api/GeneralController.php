<?php

/*
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2022 Patrik Kernstock <patrik@kernstock.net>
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
use OPNsense\Core\Backend;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Wireguard\General';
    protected static $internalModelName = 'general';

    /**
     * XXX: remove in 24.1 unused
     */
    public function getStatusAction()
    {
        // get wireguard configuration
        $config = Config::getInstance()->object();
        $config = $config->OPNsense->wireguard;

        // craft peers array
        $peers = [];
        $peers_uuid_pubkey = [];
        // enabled, name, pubkey
        foreach ($config->client->clients->client as $client) {
            $peerUuid = (string)$client->attributes()['uuid'];
            $peers_uuid_pubkey[$peerUuid] = (string) $client->pubkey;
            $peers[$peerUuid] = [
                "name"      => (string)  $client->name,
                "enabled"   => (int) $client->enabled,
                "publicKey" => (string)  $client->pubkey,
            ];
        }

        // prepare and initialize the server array
        $status = [];
        $peer_pubkey_reference = [];
        foreach ($config->server->servers->server as $server) {
            if ($server->enabled != "1") {
                continue;
            }

            // build basic server array
            $interface = "wg" . $server->instance;
            $status[$interface] = [
                "instance"  => (int) $server->instance,
                "interface" => (string)  $interface,
                "enabled"   => (int) $server->enabled,
                "name"      => (string)  $server->name,
                "peers"     => [],
            ];

            // parse and add peers with initial values to array
            if (strlen($server->peers) > 0) {
                // there is at least one peer defined
                $serverPeers = explode(",", (string) $server->peers);
                // iteriate over each peer uuid
                foreach ($serverPeers as $peerUuid) {
                    // skipping removed peer that is still referenced in server
                    if (!isset($peers[$peerUuid])) {
                        continue;
                    }
                    // remember interface and pubkey <> peer-uuid reference for referencing handshake logic below
                    $peer_pubkey_reference[$interface][$peers_uuid_pubkey[$peerUuid]] = $peerUuid;
                    // merge peer info and initial values for handshake data
                    $status[$interface]["peers"][$peerUuid] = array_merge(
                        $peers[$peerUuid],
                        [
                            "lastHandshake" => "0000-00-00 00:00:00+00:00",
                        ]
                    );
                }
            }
        }

        // Get latest handshakes by running CLI command locally
        $data = (new Backend())->configdRun("wireguard showhandshake");

        // parse and set handshake to status datastructure
        $data = trim($data);
        if (strlen($data) !== 0) {
            $wgHandshakes = explode("\n", $data);
            foreach ($wgHandshakes as $handshake) {
                $item = explode("\t", trim($handshake));

                // set interface name and publickey
                $interface = trim($item[0]);
                $pubkey = trim($item[1]);

                // calculate handshake time based on local timezone
                $epoch = $item[2];
                if ($epoch > 0) {
                    $dt = new \DateTime("@$epoch");
                    $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    $latest = $dt->format("Y-m-d H:i:sP");

                    // set handshake
                    $peerUuid = $peer_pubkey_reference[$interface][$pubkey];
                    if (!empty($peerUuid)) {
                        $status[$interface]["peers"][$peerUuid]["lastHandshake"] = $latest;
                    }
                }
            }
        }

        return [
            "items" => $status
        ];
    }
}
