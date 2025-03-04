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

/**
 * Class ClientOverwritesController
 * @package OPNsense\OpenVPN\Api
 */
class ClientOverwritesController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'cso';
    protected static $internalModelClass = 'OPNsense\OpenVPN\OpenVPN';

    public function searchAction()
    {
        return $this->searchBase('Overwrites.Overwrite');
    }
    public function getAction($uuid = null)
    {
        return $this->getBase('cso', 'Overwrites.Overwrite', $uuid);
    }
    public function addAction()
    {
        return $this->addBase('cso', 'Overwrites.Overwrite');
    }
    public function setAction($uuid = null)
    {
        return $this->setBase('cso', 'Overwrites.Overwrite', $uuid);
    }
    public function delAction($uuid)
    {
        return $this->delBase('Overwrites.Overwrite', $uuid);
    }
    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('Overwrites.Overwrite', $uuid, $enabled);
    }
}
