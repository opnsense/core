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

namespace OPNsense\DHCP\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    /**
     * XXX most of this logic can be replaced when appropriate start/stop/restart/status
     * hooks are provided to fit into an ApiMutableServiceControllerBase class. dhcpd being
     * 'enabled' isn't as straight-forward however with current legacy config format.
     */
    public function statusAction()
    {
        $response = trim((new Backend())->configdRun('service status dhcpd'));

        if (strpos($response, 'is running') > 0) {
            $status = 'running';
        } elseif (strpos($response, 'not running') > 0) {
            $status = 'stopped';
        } else {
            $status = 'disabled';
        }

        return [
            'status' => $status,
            'widget' => [
                'caption_stop' => gettext("stop service"),
                'caption_start' => gettext("start service"),
                'caption_restart' => gettext("restart service")
            ]
        ];
    }

    public function startAction()
    {
        $result = ['status' => 'failed'];

        if ($this->request->isPost()) {
            $this->sessionClose();
            $response = trim((new Backend())->configdRun('service start dhcpd'));
            return ['status' => $response];
        }

        return $result;
    }

    public function stopAction()
    {
        $result = ['status' => 'failed'];

        if ($this->request->isPost()) {
            $this->sessionClose();
            $response = trim((new Backend())->configdRun('service stop dhcpd'));
            return ['status' => $response];
        }

        return $result;
    }

    public function restartAction()
    {
        $result = ['status' => 'failed'];

        if ($this->request->isPost()) {
            $this->sessionClose();
            $response = trim((new Backend())->configdRun('service restart dhcpd'));
            return ['status' => $response];
        }

        return $result;
    }
}
