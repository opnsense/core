<?php

/*
 * Copyright (C) 2015-2018 Deciso B.V.
 * Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\Routes\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\FileObject;
use OPNsense\Routes\Route;

/**
 * @package OPNsense\Routes
 */
class RoutesController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'route';
    protected static $internalModelClass = '\OPNsense\Routes\Route';
    var $todo_file = '/tmp/.static_routes.todo';

    /**
     * @param array $payload data to store
     */
    private function store_todo($payload)
    {
        $fobj = new FileObject($this->todo_file, 'a+', 0600, LOCK_EX);
        $data = $fobj->readJson() ?? [];
        $data[] = $payload;
        $fobj->truncate(0)->writeJson($data);
    }

    /**
     * search routes
     * @return array search results
     * @throws \ReflectionException
     */
    public function searchrouteAction()
    {
        return $this->searchBase("route", null, "description");
    }

    /**
     * Update route with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setrouteAction($uuid)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('route.' . $uuid);
        $to_store = null;
        if ($node !== null) {
            $to_store = ['network' => (string)$node->network, 'gateway' => (string)$node->gateway];
        }
        $result =  $this->setBase("route", "route", $uuid);
        if ($result['result'] == 'saved' && !empty($to_store)) {
            $this->store_todo($to_store);
        }
        return $result;
    }

    /**
     * Add new route and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException
     */
    public function addrouteAction()
    {
        return $this->addBase("route", "route");
    }

    /**
     * Retrieve route settings or return defaults for new one
     * @param $uuid item unique id
     * @return array route content
     * @throws \ReflectionException when not bound to model
     */
    public function getrouteAction($uuid = null)
    {
        return $this->getBase("route", "route", $uuid);
    }

    /**
     * Delete route by uuid, save contents to tmp for removal on apply
     * @param string $uuid internal id
     * @return array save status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\ModelException when not bound to model
     */
    public function delrouteAction($uuid)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('route.' . $uuid);
        $response = $this->delBase("route", $uuid);
        if (!empty($response['result']) && $response['result'] == 'deleted') {
            // we don't know for sure if this route was already removed, flush to disk to remove on apply
            $this->store_todo(['network' => (string)$node->network, 'gateway' => (string)$node->gateway]);
        }
        return $response;
    }

    /**
     * @param string $uuid id to toggled
     * @param string|null $disabled set disabled by default
     * @return array status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\ModelException when not bound to model
     */
    public function togglerouteAction($uuid, $enabled = null)
    {
        return $this->toggleBase("route", $uuid, $enabled);
    }

    /**
     * reconfigure routes
     * @return array reconfigure status
     * @throws \Exception when unable to execute configd command
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $bckresult = trim($backend->configdRun('interface routes configure'));
            if ($bckresult == 'OK') {
                $status = 'ok';
            } else {
                $status = "error reloading routes ($bckresult)";
            }

            return array('status' => $status);
        } else {
            return array('status' => 'failed');
        }
    }
}
