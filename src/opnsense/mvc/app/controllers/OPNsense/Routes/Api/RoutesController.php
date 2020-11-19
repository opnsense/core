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
use OPNsense\Routes\Route;

/**
 * @package OPNsense\Routes
 */
class RoutesController extends ApiMutableModelControllerBase
{

    protected static $internalModelName = 'route';
    protected static $internalModelClass = '\OPNsense\Routes\Route';

    /**
     * search routes
     * @return array search results
     * @throws \ReflectionException
     */
    public function searchrouteAction()
    {
        return $this->searchBase(
            "route",
            array('disabled', 'network', 'gateway', 'descr'),
            "description"
        );
    }

    /**
     * Update route with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setrouteAction($uuid)
    {
        return $this->setBase("route", "route", $uuid);
    }

    /**
     * Add new route and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \Phalcon\Validation\Exception when field validations fail
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
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\ModelException when not bound to model
     */
    public function delrouteAction($uuid)
    {
        $node = (new Route())->getNodeByReference('route.' . $uuid);
        $response = $this->delBase("route", $uuid);
        if (!empty($response['result']) && $response['result'] == 'deleted') {
            // we don't know for sure if this route was already removed, flush to disk to remove on apply
            file_put_contents("/tmp/delete_route_{$uuid}.todo", (string)$node->network);
        }
        return $response;
    }

    /**
     * toggle, we can not use our default action here since enabled/disabled are swapped
     * @param string $uuid id to toggled
     * @param string|null $disabled set disabled by default
     * @return array status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\ModelException when not bound to model
     */
    public function togglerouteAction($uuid, $disabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $uuid != null) {
            $node = $this->getModel()->getNodeByReference('route.' . $uuid);
            if ($node != null) {
                if ($disabled == '0' || $disabled == '1') {
                    $node->disabled = (string)$disabled;
                } elseif ((string)$node->disabled == '1') {
                    $node->disabled = '0';
                } else {
                    $node->disabled = '1';
                }
                $result['result'] = (string)$node->disabled == '1' ? 'Disabled' : 'Enabled';
                $this->save();
            }
        }
        return $result;
    }

    /**
     * reconfigure routes
     * @return array reconfigure status
     * @throws \Exception when unable to execute configd command
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

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
