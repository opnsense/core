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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class PingController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ping';
    protected static $internalModelClass = 'OPNsense\Diagnostics\Ping';
    private static $ping_dir = '/tmp/ping';

    /**
     * set / create ping job
     */
    public function setAction()
    {
        $result = parent::setAction();
        if ($result['result'] != 'failed') {
            $mdl = $this->getModel();
            $result['result'] = 'ok';
            $result['uuid'] = $mdl->settings->generateUUID();
            @mkdir(self::$ping_dir);
            $nodes = $mdl->settings->getNodes();
            foreach ($nodes as $key => $value) {
                if (is_array($value)) {
                    $items = [];
                    foreach ($value as $itemkey => $itemval) {
                        if (!empty($itemval['selected'])) {
                            $items[] = $itemkey;
                        }
                    }
                    $nodes[$key] = implode(',', $items);
                }
            }
            file_put_contents(
                sprintf('%s/%s.json', self::$ping_dir, $result['uuid']),
                json_encode($nodes)
            );
        }
        return $result;
    }

    /**
     * start ping job
     */
    public function startAction($jobid)
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost()) {
            $payload = json_decode((new Backend())->configdpRun('interface ping start', [$jobid]) ?? '', true);
            if (!empty($payload)) {
                $result = $payload;
            }
        }
        return $result;
    }

    /**
     * stop ping job
     */
    public function stopAction($jobid)
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost()) {
            $payload = json_decode((new Backend())->configdpRun('interface ping stop', [$jobid]) ?? '', true);
            if (!empty($payload)) {
                $result = $payload;
            }
        }
        return $result;
    }

    /**
     * remove ping job
     */
    public function removeAction($jobid)
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost()) {
            $payload = json_decode((new Backend())->configdpRun('interface ping remove', [$jobid]) ?? '', true);
            if (!empty($payload)) {
                $result = $payload;
            }
        }
        return $result;
    }

    /**
     * search current ping jobs
     */
    public function searchJobsAction()
    {
        $data = json_decode((new Backend())->configdRun('interface ping list') ?? '', true);
        $records = (!empty($data) && !empty($data['jobs'])) ? $data['jobs'] : [];
        return $this->searchRecordsetBase($records);
    }
}
