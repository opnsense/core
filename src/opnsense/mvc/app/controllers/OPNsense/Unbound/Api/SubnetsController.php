<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SubnetsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'subnet';
    protected static $internalModelClass = '\OPNsense\Unbound\Unbound';

    public function searchSubnetAction()
    {
        return $this->searchBase('split_dns.view_subnets.subnet', null, null);
    }

    public function getSubnetAction($uuid = null)
    {
        return $this->getBase('subnet', 'split_dns.view_subnets.subnet', $uuid);
    }

    public function addSubnetAction($uuid = null)
    {
        return $this->addBase('subnet', 'split_dns.view_subnets.subnet', $uuid);
    }

    public function delSubnetAction($uuid)
    {
        return $this->delBase('split_dns.view_subnets.subnet', $uuid);
    }

    public function setSubnetAction($uuid = null)
    {
        return $this->setBase('subnet', 'split_dns.view_subnets.subnet', $uuid);
    }

    public function toggleSubnetAction($uuid)
    {
        return $this->toggleBase('split_dns.view_subnets.subnet', $uuid);
    }
}
