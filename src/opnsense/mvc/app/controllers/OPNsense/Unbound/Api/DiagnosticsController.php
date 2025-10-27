<?php

/**
 *    Copyright (C) 2017 Fabian Franz
 *
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
 *
 */

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Class DiagnosticsextensionController
 * @package OPNsense\Unbound\Api
 */
class DiagnosticsController extends ApiControllerBase
{
    /**
     * reconfigure return the stats
     */
    public function statsAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode($backend->configdRun('unbound stats'), true);
        if ($result != null) {
            $ret['status'] = "ok";
            $ret['data'] = $result;
        }
        return $ret;
    }

    /**
     * return the entries of the cache
     */
    public function dumpcacheAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("unbound dumpcache")), true);
        if ($result !== null) {
            $ret['data'] = $result;
            $ret['status'] = 'ok';
        }
        return $ret;
    }
    public function dumpinfraAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("unbound dumpinfra")), true);
        if ($result !== null) {
            $ret['data'] = $result;
            $ret['status'] = 'ok';
        }
        return $ret;
    }
    public function listlocaldataAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("unbound listlocaldata")), true);
        if ($result !== null) {
            $ret['data'] = $result;
            $ret['status'] = 'ok';
        }
        return $ret;
    }
    public function listlocalzonesAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("unbound listlocalzones")), true);
        if ($result !== null) {
            $ret['data'] = $result;
            $ret['status'] = 'ok';
        }
        return $ret;
    }
    public function listinsecureAction()
    {
        $ret['status'] = "failed";
        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("unbound listinsecure")), true);
        if ($result !== null) {
            $ret['data'] = $result;
            $ret['status'] = 'ok';
        }
        return $ret;
    }

    public function testBlocklistAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('domain')) {
            $src = $this->request->getPost('src', null, '127.0.0.1');
            $backend = new Backend();
            $response = json_decode($backend->configdpRun('unbound domain test', [
                $this->request->getPost('domain'), $src
            ]), true);

            if (!empty($response)) {
                return $response;
            }
        }
        return ["status" => "error"];
    }
}
