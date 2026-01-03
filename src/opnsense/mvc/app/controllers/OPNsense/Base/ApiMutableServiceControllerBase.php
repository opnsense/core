<?php

/*
 * Copyright (C) 2017-2024 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2016 IT-assistans Sverige AB
 * Copyright (C) 2015-2016 Deciso B.V.
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

namespace OPNsense\Base;

use OPNsense\Core\Backend;

/**
 * Class ApiMutableServiceControllerBase, inherit this class to implement
 * an API that exposes a service controller with start, stop, restart,
 * reconfigure and status actions.
 * @package OPNsense\Base
 */
abstract class ApiMutableServiceControllerBase extends ApiControllerBase
{
    /**
     * @var string this implementations internal model service to use
     */
    protected static $internalServiceName = null;

    /**
     * @var string model class name to use
     */
    protected static $internalServiceClass = null;

    /**
     * @var string model template name to use
     */
    protected static $internalServiceTemplate = null;

    /**
     * @var string model enabled xpath to use
     */
    protected static $internalServiceEnabled = null;

    /**
     * @var null|BaseModel model object to work on
     */
    private $modelHandle = null;

    /**
     * validate on initialization
     * @throws \Exception when not bound to internal service
     */
    public function initialize()
    {
        parent::initialize();
        if (empty(static::$internalServiceName)) {
            throw new \Exception('cannot instantiate without internalServiceName defined.');
        }
    }

    /**
     * @return null|BaseModel
     * @throws \ReflectionException when unable to instantiate model class
     */
    protected function getModel()
    {
        if ($this->modelHandle == null) {
            if (empty(static::$internalServiceClass)) {
                throw new \Exception('cannot get model without internalServiceClass defined.');
            }
            $this->modelHandle = (new \ReflectionClass(static::$internalServiceClass))->newInstance();
        }

        return $this->modelHandle;
    }

    /**
     * start service
     * @return array
     * @throws \Exception when configd action fails
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = trim($backend->configdRun(escapeshellarg(static::$internalServiceName) . ' start'));
            return ['response' => $response];
        }

        return ['response' => []];
    }

    /**
     * stop service
     * @return array
     * @throws \Exception when configd actions fails
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = trim($backend->configdRun(escapeshellarg(static::$internalServiceName) . ' stop'));
            return ['response' => $response];
        }

        return ['response' => []];
    }

    /**
     * restart service
     * @return array
     * @throws \Exception
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = trim($backend->configdRun(escapeshellarg(static::$internalServiceName) . ' restart'));
            return ['response' => $response];
        }

        return ['response' => []];
    }

    /**
     * reconfigure force restart check, return zero and define 'reload' backend action for soft-reload
     */
    protected function reconfigureForceRestart()
    {
        return 1;
    }

    /**
     * invoke interface registration check, return true to invoke configd action
     */
    protected function invokeInterfaceRegistration()
    {
        return false;
    }

    /**
     * invoke firewall reload check, return true to invoke configd action
     */
    protected function invokeFirewallReload()
    {
        return false;
    }

    /**
     * check if service is enabled according to model
     */
    protected function serviceEnabled()
    {
        if (empty(static::$internalServiceEnabled)) {
            throw new \Exception('cannot check if service is enabled without internalServiceEnabled defined.');
        }

        return (string)($this->getModel())->getNodeByReference(static::$internalServiceEnabled) == '1';
    }

    /**
     * reconfigure with optional stop, generate config and start / reload
     * @return array response message
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when model can't be instantiated
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $restart = $this->reconfigureForceRestart();
            $enabled = $this->serviceEnabled();
            $backend = new Backend();

            if ($restart || !$enabled) {
                $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' stop');
            }

            if ($this->invokeInterfaceRegistration()) {
                $backend->configdRun('interface invoke registration');
            }

            if ($this->invokeFirewallReload()) {
                $backend->configdRun('filter reload skip_alias');
            }

            if (!empty(static::$internalServiceTemplate)) {
                $result = trim($backend->configdpRun('template reload', [static::$internalServiceTemplate]));
                if ($result !== 'OK') {
                    throw new UserException(sprintf(
                        gettext('Template generation failed for internal service "%s". See backend log for details.'),
                        static::$internalServiceName
                    ), gettext('Configuration exception'));
                }
            }

            if ($enabled) {
                if ($restart || $this->statusAction()['status'] != 'running') {
                    $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' start');
                } else {
                    $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' reload');
                }
            }

            return ['status' => 'ok'];
        }

        return ['status' => 'failed'];
    }

    /**
     * retrieve status of service
     * @return array response message
     * @throws \Exception when configd action fails
     */
    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' status');

        if (strpos($response, 'not running') > 0) {
            if ($this->serviceEnabled()) {
                $status = 'stopped';
            } else {
                $status = 'disabled';
            }
        } elseif (strpos($response, 'is running') > 0) {
            $status = 'running';
        } elseif (!$this->serviceEnabled()) {
            $status = 'disabled';
        } else {
            $status = 'unknown';
        }

        return [
            'status' => $status,
            'widget' => [
                'caption_restart' => gettext('Restart'),
                'caption_start' => gettext('Start'),
                'caption_stop' => gettext('Stop'),
            ],
        ];
    }
}
