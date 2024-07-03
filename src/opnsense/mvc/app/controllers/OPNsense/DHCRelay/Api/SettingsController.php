<?php

/*
 * Copyright (C) 2023-2024 Deciso B.V.
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

namespace OPNsense\DHCRelay\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\DHCRelay\DHCRelay';
    protected static $internalModelUseSafeDelete = true;
    protected static $internalModelName = 'dhcrelay';

    public function searchRelayAction()
    {
        $search_result = $this->searchBase('relays', ['enabled', 'interface', 'destination', 'agent_info'], 'interface');
        $status_by_uuid = [];

        foreach (json_decode((new Backend())->configdpRun('service list', [self::$internalModelName]), true) as $service) {
            $status_by_uuid[$service['id']] = strpos($service['status'], 'not running') > 0 ? 'red' : 'green';
        }

        foreach ($search_result['rows'] as &$row) {
            $row['status'] = !empty($status_by_uuid[$row['uuid']]) ? $status_by_uuid[$row['uuid']] : 'grey';
        }

        return $search_result;
    }

    public function getRelayAction($uuid = null)
    {
        return $this->getBase('relay', 'relays', $uuid);
    }

    public function addRelayAction()
    {
        return $this->addBase('relay', 'relays');
    }

    public function delRelayAction($uuid)
    {
        return $this->delBase('relays', $uuid);
    }

    public function setRelayAction($uuid)
    {
        return $this->setBase('relay', 'relays', $uuid);
    }

    public function toggleRelayAction($uuid, $enabled = null)
    {
        return $this->toggleBase('relays', $uuid, $enabled);
    }

    public function searchDestAction()
    {
        return $this->searchBase('destinations', ['name', 'server'], 'name');
    }

    public function getDestAction($uuid = null)
    {
        return $this->getBase('destination', 'destinations', $uuid);
    }

    public function addDestAction()
    {
        return $this->addBase('destination', 'destinations');
    }

    public function delDestAction($uuid)
    {
        return $this->delBase('destinations', $uuid);
    }

    public function setDestAction($uuid)
    {
        return $this->setBase('destination', 'destinations', $uuid);
    }
}
