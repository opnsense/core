<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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
 * Class PreSharedKeysController
 * @package OPNsense\IPsec\Api
 */
class PreSharedKeysController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ipsec';
    protected static $internalModelClass = 'OPNsense\IPsec\IPsec';

    /**
     * Search preSharedKeys
     * @return array
     * @throws \ReflectionException
     */
    public function searchItemAction()
    {
        return $this->searchBase('preSharedKeys.preSharedKey', ['ident', 'remote_ident', 'keyType']);
    }

    /**
     * Update preSharedKey with given properties
     * @param $uuid
     * @return array
     * @throws \OPNsense\Base\UserException
     * @throws \ReflectionException
     */
    public function setItemAction($uuid = null)
    {
        return $this->setBase('preSharedKey', 'preSharedKeys.preSharedKey', $uuid);
    }

    /**
     * Add new preSharedKey with given properties
     * @return array
     * @throws \OPNsense\Base\UserException
     * @throws \ReflectionException
     */
    public function addItemAction()
    {
        return $this->addBase('preSharedKey', 'preSharedKeys.preSharedKey');
    }

    /**
     * Retrieve key pair or return defaults for new one
     * @param $uuid
     * @return array
     * @throws \ReflectionException
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase('preSharedKey', 'preSharedKeys.preSharedKey', $uuid);
    }

    /**
     * Delete preSharedKey by UUID
     * @param $uuid
     * @return array
     * @throws \OPNsense\Base\UserException
     * @throws \ReflectionException
     */
    public function delItemAction($uuid)
    {
        return $this->delBase('preSharedKeys.preSharedKey', $uuid);
    }
}
