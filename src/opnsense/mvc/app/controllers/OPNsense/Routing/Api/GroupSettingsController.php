<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Routing\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class GroupSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Routing\GatewayGroups';
    protected static $internalModelName = 'gateway_group';

    public function searchAction()
    {
        return $this->searchBase('gateway_group');
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('gateway_group', 'gateway_group', $uuid);
    }

    public function setAction($uuid = null)
    {
        return $this->setBase('gateway_group', 'gateway_group', $uuid);
    }

    public function addAction()
    {
        return $this->addBase('gateway_group', 'gateway_group');
    }

    public function delAction($uuids)
    {
        return $this->delBase('gateway_group', $uuids);
    }
}
