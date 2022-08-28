<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class PacketCaptureController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'packetcapture';
    protected static $internalModelClass = 'OPNsense\Diagnostics\PacketCapture';
    private static $capture_dir = "/tmp/captures";

    /**
     * set / create capture job
     */
    public function setAction()
    {
        $result = parent::setAction();
        if (empty($result['validations'])) {
            $mdl = $this->getModel();
            $result['result'] = 'ok';
            $result['uuid'] = $mdl->settings->generateUUID();
            @mkdir(self::$capture_dir);
            $nodes = $mdl->settings->getNodes();
            foreach ($nodes as $key => $value) {
                if (is_array($value)) {
                    $items = [];
                    foreach ($value as $itemkey => $itemval) {
                        if (!empty($itemval['selected'])) {
                            $items[] = $itemkey;
                        }
                    }
                    $nodes[$key] = $items;
                }
            }
            file_put_contents(
                sprintf("%s/%s.json", self::$capture_dir, $result['uuid']),
                json_encode($nodes)
            );
        }
        return $result;
    }

    /**
     * start capture job
     */
    public function startAction($jobid)
    {
        $result = ["status" => "failed"];
        if ($this->request->isPost()) {
            $this->sessionClose(); // long running action, close session
            $payload = json_decode((new Backend())->configdpRun("interface capture start", [$jobid]), true);
            if (!empty($payload)) {
                $result = $payload;
            }
        }
        return $result;
    }

    /**
     * stop capture job
     */
    public function stopAction($jobid)
    {
        $result = ["status" => "failed"];
        if ($this->request->isPost()) {
            $this->sessionClose(); // long running action, close session
            $payload = json_decode((new Backend())->configdpRun("interface capture stop", [$jobid]), true);
            if (!empty($payload)) {
                $result = $payload;
            }
        }
        return $result;
    }

    /**
     * search current capture jobs
     */
    public function searchJobsAction()
    {
        $this->sessionClose();
        $data = json_decode((new Backend())->configdRun('interface capture list'), true);
        $records = (!empty($data) && !empty($data['jobs'])) ? $data['jobs'] : [];
        return $this->searchRecordsetBase($records);
    }
}
