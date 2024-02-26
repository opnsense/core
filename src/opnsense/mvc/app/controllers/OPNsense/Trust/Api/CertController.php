<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Trust\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class CertController
 * @package OPNsense\Trust\Api
 */
class CertController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'cert';
    protected static $internalModelClass = 'OPNsense\Trust\Cert';

    public function searchAction()
    {
        return $this->searchBase('cert', ['descr', 'caref', 'valid_from', 'valid_to']);
    }
    public function getAction($uuid = null)
    {
        return $this->getBase('cert', 'cert', $uuid);
    }
    public function addAction()
    {
        return $this->addBase('cert', 'cert');
    }
    public function setAction($uuid = null)
    {
        return $this->setBase('cert', 'cert', $uuid);
    }
    public function delAction($uuid)
    {
        return $this->delBase('cert', $uuid);
    }
    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('cert', $uuid, $enabled);
    }
}
