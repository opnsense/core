<?php

/*
 * Copyright (C) 2017 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2017 Michael Muenz
 * Copyright (C) 2016 IT-assistans Sverige AB
 * Copyright (C) 2016 Deciso B.V.
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
    static protected $internalModelService = null;

    /**
     * @var string model class name to use
     */
    static protected $internalModelClass = null;

    /**
     * @var string model template name to use
     */
    static protected $internalModelTemplate = null;

    /**
     * @var string model enabled xpath to use
     */
    static protected $internalModelEnabled = null;

    /**
     * @var null|BaseModel model object to work on
     */
    private $modelHandle = null;

    /**
     * validate on initialization
     * @throws Exception
     */
    public function initialize()
    {
        parent::initialize();
        if (empty(static::$internalModelClass)) {
            throw new \Exception('cannot instantiate without internalModelClass defined.');
        }
        if (empty(static::$internalModelService)) {
            throw new \Exception('cannot instantiate without internalModelService defined.');
        }
        if (empty(static::$internalModelTemplate)) {
            throw new \Exception('cannot instantiate without internalModelTemplate defined.');
        }
        if (empty(static::$internalModelEnabled)) {
            throw new \Exception('cannot instantiate without internalModelEnabled defined.');
        }
    }

    /**
     * @return null|BaseModel
     */
    protected function getModel()
    {
        if ($this->modelHandle == null) {
            $this->modelHandle = (new \ReflectionClass(static::$internalModelClass))->newInstance();
        }

        return $this->modelHandle;
    }

    /**
     * start service
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun(escapeshellarg(static::$internalModelService) . ' start');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * stop service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun(escapeshellarg(static::$internalModelService) . ' stop');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * restart service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun(escapeshellarg(static::$internalModelService) . ' restart');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * reconfigure, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $model = $this->getModel();
            $backend = new Backend();

            $this->stopAction();

            // generate template
            $backend->configdRun('template reload ' . escapeshellarg(static::$internalModelTemplate));

            // (re)start daemon
            if ((string)$model->getNodeByReference(static::$internalModelEnabled) == '1') {
                $this->startAction();
            }

            return array('status' => 'ok');
        } else {
            return array('status' => 'failed');
        }
    }

    /**
     * retrieve status of service
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $model = $this->getModel();
        $response = $backend->configdRun(escapeshellarg(static::$internalModelService) . ' status');

        if (strpos($response, 'not running') > 0) {
            if ((string)$model->getNodeByReference(static::$internalModelEnabled) == 1) {
                $status = 'stopped';
            } else {
                $status = 'disabled';
            }
        } elseif (strpos($response, 'is running') > 0) {
            $status = 'running';
        } elseif ((string)$model->getNodeByReference(static::$internalModelEnabled) == 0) {
            $status = 'disabled';
        } else {
            $status = 'unknown';
        }

        return array('status' => $status);
    }
}
