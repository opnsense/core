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
 * Class ConnectionsController
 * @package OPNsense\IPsec\Api
 */
class ConnectionsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'swanctl';
    protected static $internalModelClass = 'OPNsense\IPsec\Swanctl';

    public function searchConnectionAction()
    {
        return $this->searchBase('Connections.Connection', ['description']);
    }

    public function setConnectionAction($uuid = null)
    {
        return $this->setBase('connection', 'Connections.Connection', $uuid);
    }

    public function addConnectionAction()
    {
        return $this->addBase('connection', 'Connections.Connection');
    }

    public function getConnectionAction($uuid = null)
    {
        return $this->getBase('connection', 'Connections.Connection', $uuid);
    }

    public function delConnectionAction($uuid)
    {
        return $this->delBase('Connections.Connection', $uuid);
    }
}
