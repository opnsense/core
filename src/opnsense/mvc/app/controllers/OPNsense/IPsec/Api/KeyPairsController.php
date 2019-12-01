<?php

/*
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
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

namespace OPNsense\IPsec\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class KeyPairsController
 * @package OPNsense\IPsec\Api
 */
class KeyPairsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ipsec';
    protected static $internalModelClass = 'OPNsense\IPsec\IPsec';

    /**
     * Search key pairs
     * @return array
     * @throws \ReflectionException
     */
    public function searchItemAction()
    {
        return $this->searchBase(
            'keyPairs.keyPair',
            ['name', 'keyType', 'keySize', 'keyFingerprint']
        );
    }

    /**
     * Update key pair with given properties
     * @param $uuid
     * @return array
     * @throws \OPNsense\Base\UserException
     * @throws \ReflectionException
     */
    public function setItemAction($uuid = null)
    {
        $response = $this->setBase('keyPair', 'keyPairs.keyPair', $uuid);
        if (!empty($response['result']) && $response['result'] === 'saved') {
            touch('/tmp/ipsec.dirty'); // mark_subsystem_dirty('ipsec')
        }

        return $response;
    }

    /**
     * Add new key pair with given properties
     * @return array
     * @throws \OPNsense\Base\UserException
     * @throws \ReflectionException
     */
    public function addItemAction()
    {
        $response = $this->addBase('keyPair', 'keyPairs.keyPair');
        if (!empty($response['result']) && $response['result'] === 'saved') {
            touch('/tmp/ipsec.dirty'); // mark_subsystem_dirty('ipsec')
        }

        return $response;
    }

    /**
     * Retrieve key pair or return defaults for new one
     * @param $uuid
     * @return array
     * @throws \ReflectionException
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase('keyPair', 'keyPairs.keyPair', $uuid);
    }

    /**
     * Delete key pair by UUID
     * @param $uuid
     * @return array
     * @throws \OPNsense\Base\UserException
     * @throws \ReflectionException
     */
    public function delItemAction($uuid)
    {
        $response = $this->delBase('keyPairs.keyPair', $uuid);
        if (!empty($response['result']) && $response['result'] === 'deleted') {
            touch('/tmp/ipsec.dirty'); // mark_subsystem_dirty('ipsec')
        }

        return $response;
    }
}
