<?php

/*
 * Copyright (C) 2017-2018 EURO-LOG AG
 * Copyright (c) 2019 Deciso B.V.
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

namespace OPNsense\Monit\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\Monit
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Monit\Monit';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceTemplate = 'OPNsense/Monit';
    protected static $internalServiceName = 'monit';

    /**
     * test monit configuration
     * @return array
     */
    public function checkAction()
    {
        if ($this->request->isPost()) {
            $result['status'] = 'ok';
            $backend = new Backend();
            $result['function'] = 'check';
            $result['template'] = trim($backend->configdRun('template reload OPNsense/Monit'));
            if ($result['template'] != 'OK') {
                $result['result'] = "Template error: " . $result['template'];
                return $result;
            }
            $result['result'] = trim($backend->configdRun('monit check'));
            return $result;
        } else {
            return array('status' => 'failed');
        }
    }

    /**
     * reconfigure monit
     * @return array
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $status = $this->statusAction();
            /* renders the template and runs "monit -t", its output is only used as a message */
            $result = $this->checkAction();
            $result['function'] = 'reconfigure';
            $action = null;
            if (!$this->serviceEnabled()) {
                if ($status['status'] == 'running') {
                    $action = 'stop';
                }
            } elseif ($result['template'] == 'OK') {
                /*
                 * rc(8) runs monit_setup and, for a reload, "monit -t" first; monit itself refuses
                 * to start with an invalid control file. Their exit status is the verdict.
                 */
                $action = $status['status'] == 'running' ? 'reload' : 'start';
            }
            $response = $action !== null ? trim($backend->configdRun('monit ' . $action)) : 'OK';
            $result['status'] = $result['template'] == 'OK' && $response == 'OK' ? 'ok' : 'failed';
            if ($result['status'] != 'ok') {
                $result['status_msg'] = $result['result'];
                if ($response != 'OK') {
                    $result['status_msg'] .= "\n" . sprintf(gettext('"monit %s" returned: %s'), $action, $response);
                }
            }
            return $result;
        } else {
            return array('status' => 'failed');
        }
    }

     /**
      * avoid restarting Monit on reconfigure
      */
    protected function reconfigureForceRestart()
    {
        return 0;
    }
}
