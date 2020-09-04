<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

/**
 * Class TrafficController
 * @package OPNsense\Diagnostics\Api
 */
class TrafficController extends ApiControllerBase
{

    /**
     * retrieve interface traffic stats
     * @return array
     */
    public function InterfaceAction()
    {
        $this->sessionClose(); // long running action, close session
        $response = (new Backend())->configdRun('interface show traffic');
        return json_decode($response, true);
    }

    /**
     * retrieve interface top traffic hosts
     * @param $interfaces string comma separated list of interfaces
     * @return array
     */
    public function TopAction($interfaces)
    {
        $response = [];
        $this->sessionClose(); // long running action, close session
        $config = Config::getInstance()->object();
        $iflist = [];
        $ifmap = [];
        foreach (explode(',', $interfaces) as $intf) {
            if (isset($config->interfaces->$intf) && !empty($config->interfaces->$intf->if)) {
                $iflist[] = (string)$config->interfaces->$intf->if;
                $ifmap[(string)$config->interfaces->$intf->if] = $intf;
            }
        }
        if (count($iflist) > 0) {
            $data = (new Backend())->configdpRun('interface show top', [implode(",", $iflist)]);
            $data = json_decode($data, true);
            foreach ($data as $if => $content) {
                if (isset($ifmap[$if])) {
                    $response[$ifmap[$if]] = $content;
                }
            }
        }
        return $response;

    }
}
