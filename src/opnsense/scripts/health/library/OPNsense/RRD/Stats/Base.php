<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\RRD\Stats;

use OPNsense\Core\Config;

/**
 * Stats collection
 */
abstract class Base
{
    static array $metadata = [];

    /**
     * run simple shell command, expected to return json output
     */
    protected function jsonShellCmd($cmd)
    {
        exec($cmd . '  2>&1', $payload, $returncode);
        if ($returncode == 0 && !empty($payload[0])) {
            return json_decode($payload[0], true) ?? [];
        }
        return null;
    }

    /**
     * run simple shell command
     * @param string $cmd command to execute
     * @return array output lines when returnvalue equals 0
     */
    protected function shellCmd(string $cmd)
    {
        exec($cmd . '  2>&1', $payload, $returncode);
        if ($returncode == 0 && !empty($payload[0])) {
            return $payload;
        }
        return [];
    }

    /**
     * collect static generic metadata on init
     */
    public function __construct()
    {
        if (empty(self::$metadata)) {
            self::$metadata['interfaces'] = [];

            self::$metadata['interfaces']['ipsec'] = ['name' => 'IPsec', 'if' => 'enc0'];
            foreach (Config::getInstance()->object()->interfaces->children() as $ifname => $node) {
                if (isset($node->enable)) {
                    self::$metadata['interfaces'][$ifname] = [
                        'name' => !empty((string)$node->descr) ? (string)$node->descr : $ifname,
                        'if' => (string)$node->if
                    ];
                    /* relevant parts from get_real_interface() using in old rrd.inc  */
                    if (isset($node->wireless) && !strstr((string)$node->if, '_wlan')) {
                        self::$metadata['interfaces'][$ifname]['if'] .= '_wlan0';
                    }
                }
            }
            foreach ((new \OPNsense\OpenVPN\OpenVPN())->serverDevices() as $ifname => $data) {
                self::$metadata['interfaces'][$ifname] = [
                    'name' => $data['descr'],
                    'if' => $ifname,
                    'openvpn_socket' => $data['sockFilename']
                ];
            }
            self::$metadata['ntp_statsgraph'] = empty((string)Config::getInstance()->object()->ntp->statsgraph);
        }
    }

    /**
     * run
     */
    public function run()
    {
        throw new \Exception("Need to implement run()");
    }
}
