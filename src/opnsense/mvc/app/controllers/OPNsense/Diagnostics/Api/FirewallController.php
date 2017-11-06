<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Diagnostics\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

/**
 * Class FirewallController
 * @package OPNsense\Diagnostics\Api
 */
class FirewallController extends ApiControllerBase
{

    /**
     * retrieve firewall log
     * @return array
     */
    public function logAction()
    {
        if ($this->request->isGet()) {
            $this->sessionClose(); // long running action, close session
            $digest = empty($this->request->get('digest')) ? "" : $this->request->get('digest');
            $limit = empty($this->request->get('limit')) ? 1000 : $this->request->get('limit');
            $backend = new Backend();
            $response = $backend->configdpRun("filter read log", array($limit, $digest));
            $logoutput = json_decode($response, true);
            return $logoutput;
        } else {
            return null;
        }
    }
}
