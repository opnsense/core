<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Proxy\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Proxy\Proxy;

/**
 * Class ServiceController
 * @package OPNsense\Proxy
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Proxy\Proxy';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceTemplate = 'OPNsense/Proxy';
    protected static $internalServiceName = 'proxy';

    protected function reconfigureForceRestart()
    {
        $mdlProxy = new Proxy();

        // some operations can not be performed by a squid -k reconfigure,
        // try to determine if we need a stop/start here
        $prev_sslbump_cert = trim(@file_get_contents('/var/squid/ssl_crtd.id'));
        $prev_cache_active = !empty(trim(@file_get_contents('/var/squid/cache/active')));

        return (((string)$mdlProxy->forward->sslcertificate) != $prev_sslbump_cert) ||
            (!empty((string)$mdlProxy->general->cache->local->enabled) != $prev_cache_active);
    }

    private function hookStartErrorHandler($result)
    {
        if (preg_match('/__ok__$/', $result['response'])) {
            $result['response'] = "ok";
        } else {
            throw new UserException($result['response'], gettext("proxy load error"));
        }
        return $result;
    }

    public function startAction()
    {
        return $this->hookStartErrorHandler(parent::startAction());
    }

    public function restartAction()
    {
        return $this->hookStartErrorHandler(parent::restartAction());
    }

    /**
     * reload template only (for example PAC does not need to change squid configuration)
     * @return array
     */
    public function resetAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            return array('status' => $backend->configdRun('proxy reset'));
        } else {
            return array('error' => 'This API endpoint must be called via POST',
                         'status' => 'error');
        }
    }

    /**
     * reload template only (for example PAC does not need to change squid configuration)
     * @return array
     */
    public function refreshTemplateAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            return array('status' => $backend->configdRun('template reload OPNsense/Proxy'));
        } else {
            return array('error' => 'This API endpoint must be called via POST',
                         'status' => 'error');
        }
    }

    /**
     * fetch acls (download + install)
     * @return array
     */
    public function fetchaclsAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();
            // generate template
            $backend->configdRun('template reload OPNsense/Proxy');

            // fetch files
            $response = $backend->configdRun("proxy fetchacls");
            return array("response" => $response,"status" => "ok");
        } else {
            return array("response" => array());
        }
    }

    /**
     * download (only) acls
     * @return array
     */
    public function downloadaclsAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();
            // generate template
            $backend->configdRun('template reload OPNsense/Proxy');

            // download files
            $response = $backend->configdRun("proxy downloadacls");
            return array("response" => $response,"status" => "ok");
        } else {
            return array("response" => array());
        }
    }
}
