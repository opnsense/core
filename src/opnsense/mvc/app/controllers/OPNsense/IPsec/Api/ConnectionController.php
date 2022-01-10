<?php

/*
 * Copyright (C) 2022 Manuel Faux <mfaux@conf.at>
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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use Phalcon\Filter;

/**
 * Class ConnectionController
 * @package OPNsense\IPsec\Api
 */
class ConnectionController extends ApiControllerBase
{

    public function connectAction($connection)
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend->configdpRun("ipsec connect", [$connection]);
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * retrieve security associations database content
     * @return mixed
     */
    public function disconnectAction($connection)
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend->configdpRun("ipsec disconnect", [$connection]);
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }
}
