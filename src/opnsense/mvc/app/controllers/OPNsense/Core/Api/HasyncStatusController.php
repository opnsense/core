<?php

/*
 * Copyright (c) 2024 Deciso B.V.
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

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Class HasyncStatusController
 * @package OPNsense\Core
 */
class HasyncStatusController extends ApiControllerBase
{
    private function remoteServiceAction($action, $service, $service_id)
    {
        $backend = new Backend();
        $backend->configdRun('system ha exec exec_sync');
        $backend->configdRun('system ha exec reload_templates');
        return json_decode($backend->configdpRun('system ha exec', [$action, $service, $service_id]), true);
    }

    public function versionAction()
    {
        return json_decode((new Backend())->configdRun('system ha exec version'), true);
    }

    public function servicesAction()
    {
        $data = json_decode((new Backend())->configdRun('system ha services_cached'), true);
        $records = !empty($data['response']) ? $data['response'] : [];
        return $this->searchRecordsetBase($records, null, null, function (&$record) {
            $record['uid'] = $record['name'] ?? '';
            if (!empty($record['id'])) {
                $record['uid'] .= '_' . $record['id'];
            }
            return true;
        });
    }

    public function stopAction($service = null, $service_id = null)
    {
        if ($this->request->isPost()) {
            return $this->remoteServiceAction('stop', $service, $service_id);
        }
        return ["status" => "failed"];
    }

    public function startAction($service = null, $service_id = null)
    {
        if ($this->request->isPost()) {
            return $this->remoteServiceAction('start', $service, $service_id);
        }
        return ["status" => "failed"];
    }

    public function restartAction($service = null, $service_id = null)
    {
        if ($this->request->isPost()) {
            return $this->remoteServiceAction('restart', $service, $service_id);
        }
        return ["status" => "failed"];
    }

    public function restartAllAction($service = null, $service_id = null)
    {
        if ($this->request->isPost()) {
            $backend = new Backend();

            $services = json_decode((new Backend())->configdRun('system ha exec services'), true);
            if (!empty($services['response'])) {
                $backend->configdRun('system ha exec exec_sync');
                $backend->configdRun('system ha exec reload_templates');
                foreach ($services['response'] as $service) {
                    $backend->configdpRun('system ha exec', ['restart', $service['name'], $service['id'] ?? '']);
                }
                return ["status" => "ok", "count" =>  count($services['response'])];
            }

            return $this->remoteServiceAction('restart', $service, $service_id);
        }
        return ["status" => "failed"];
    }
}
