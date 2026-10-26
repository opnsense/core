<?php

/*
 * Copyright (C) 2026 Greelan
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

namespace OPNsense\Ntpd\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class SettingsController
 * @package OPNsense\Ntpd
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ntpd';
    protected static $internalModelClass = '\OPNsense\Ntpd\Ntpd';

    public function searchServerAction()
    {
        return $this->searchBase('timeservers.server');
    }

    public function getServerAction($uuid = null)
    {
        return $this->getBase('server', 'timeservers.server', $uuid);
    }

    public function addServerAction()
    {
        return $this->addBase('server', 'timeservers.server');
    }

    public function setServerAction($uuid)
    {
        return $this->setBase('server', 'timeservers.server', $uuid);
    }

    public function delServerAction($uuid)
    {
        return $this->delBase('timeservers.server', $uuid);
    }

    public function toggleServerAction($uuid, $enabled = null)
    {
        return $this->toggleBase('timeservers.server', $uuid, $enabled);
    }
}
