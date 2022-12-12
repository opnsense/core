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
 * Class VtiController
 * @package OPNsense\IPsec\Api
 */
class VtiController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'swanctl';
    protected static $internalModelClass = 'OPNsense\IPsec\Swanctl';

    public function searchAction()
    {
        return $this->searchBase(
            'VTIs.VTI',
            ['enabled', 'description', 'origin', 'reqid', 'local', 'remote', 'tunnel_local', 'tunnel_remote']
        );
    }

    public function setAction($uuid = null)
    {
        return $this->setBase('vti', 'VTIs.VTI', $uuid);
    }

    public function addAction()
    {
        return $this->addBase('vti', 'VTIs.VTI');
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('vti', 'VTIs.VTI', $uuid);
    }

    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('VTIs.VTI', $uuid, $enabled);
    }

    public function delAction($uuid)
    {
        return $this->delBase('VTIs.VTI', $uuid);
    }
}
