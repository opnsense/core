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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

/**
 * Class InstancesController
 * @package OPNsense\OpenVPN\Api
 */
class InstancesController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'instance';
    protected static $internalModelClass = 'OPNsense\OpenVPN\OpenVPN';

    public function searchAction()
    {
        return $this->searchBase('Instances.Instance');
    }
    public function getAction($uuid = null)
    {
        $result = $this->getBase('instance', 'Instances.Instance', $uuid);
        if (!empty($result['instance'])) {
            $fetchmode = $this->request->has("fetchmode") ? $this->request->get("fetchmode") : null;
            if ($fetchmode == 'copy') {
                $result['instance']['vpnid'] = null;
            }
        }
        return $result;
    }
    public function addAction()
    {
        return $this->addBase('instance', 'Instances.Instance');
    }
    public function setAction($uuid = null)
    {
        return $this->setBase('instance', 'Instances.Instance', $uuid);
    }
    public function delAction($uuid)
    {
        return $this->delBase('Instances.Instance', $uuid);
    }
    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('Instances.Instance', $uuid, $enabled);
    }

    /**
     * static key administration
     */
    public function searchStaticKeyAction()
    {
        return $this->searchBase('StaticKeys.StaticKey', ['description']);
    }
    public function getStaticKeyAction($uuid = null)
    {
        return $this->getBase('statickey', 'StaticKeys.StaticKey', $uuid);
    }
    public function addStaticKeyAction()
    {
        return $this->addBase('statickey', 'StaticKeys.StaticKey');
    }
    public function setStaticKeyAction($uuid = null)
    {
        return $this->setBase('statickey', 'StaticKeys.StaticKey', $uuid);
    }
    public function delStaticKeyAction($uuid)
    {
        return $this->delBase('StaticKeys.StaticKey', $uuid);
    }

    public function genKeyAction($type = 'secret')
    {
        if (in_array($type, ['secret', 'auth-token'])) {
            $key = (new Backend())->configdpRun("openvpn genkey", [$type]);
            if (strpos($key, '-----BEGIN') !== false) {
                return [
                    'result' => 'ok',
                    'key' => trim($key)
                ];
            }
        }
        return ['result' => 'failed'];
    }
}
