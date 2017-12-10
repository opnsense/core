<?php

/*
 *  Copyright (C) 2015 Deciso B.V.
 *  Copyright (C) 2017 Fabian Franz
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *
 *  THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *  AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Routes\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;
use \OPNsense\Routes\Route;

/**
 * @package OPNsense\Routes
 */
class RoutesController extends ApiControllerBase
{
    public function searchrouteAction()
    {
        $this->sessionClose();
        $mdlRoute = new Route();
        $grid = new UIModelGrid($mdlRoute->route);
        return $grid->fetchBindRequest(
            $this->request,
            array('disabled', 'network', 'gateway', 'descr'),
            'description'
        );
    }

    public function setrouteAction($uuid)
    {
        $result = array('result'=>'failed');
        if ($this->request->isPost() && $this->request->hasPost('route')) {
            $mdlRoute = new Route();
            if ($uuid != null) {
                $node = $mdlRoute->getNodeByReference('route.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost('route'));
                    $validations = $mdlRoute->validate($node->__reference, 'route');
                    if (count($validations)) {
                        $result['validations'] = $validations;
                    } else {
                        // serialize model to config and save
                        $mdlRoute->serializeToConfig();
                        Config::getInstance()->save();
                        $result['result'] = 'saved';
                    }
                }
            }
        }
        return $result;
    }

    public function addrouteAction()
    {
        $result = array('result'=>'failed');
        if ($this->request->isPost() && $this->request->hasPost('route')) {
            $mdlRoute = new Route();
            $node = $mdlRoute->route->Add();
            $node->setNodes($this->request->getPost('route'));
            $validations = $mdlRoute->validate($node->__reference, 'route');
            if (count($validations)) {
                $result['validations'] = $validations;
            } else {
                // serialize model to config and save
                $mdlRoute->serializeToConfig();
                Config::getInstance()->save();
                $result['result'] = 'saved';
            }
        }
        return $result;
    }

    public function getrouteAction($uuid = null)
    {
        $mdlRoute = new Route();
        if ($uuid != null) {
            $node = $mdlRoute->getNodeByReference('route.'.$uuid);
            if ($node != null) {
                // return node
                return array('route' => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlRoute->route->add();
            return array('route' => $node->getNodes());
        }
        return array();
    }

    public function delrouteAction($uuid)
    {
        $result = array('result'=>'failed');
        if ($this->request->isPost() && $uuid != null) {
            $mdlRoute = new Route();
            $node = $mdlRoute->getNodeByReference('route.'.$uuid);
            if ($mdlRoute->route->del($uuid)) {
                // if item is removed, serialize to config and save
                $mdlRoute->serializeToConfig();
                // we don't know for sure if this route was already removed, flush to disk to remove on apply
                file_put_contents("/tmp/delete_route_{$uuid}.todo", (string)$node->network);
                Config::getInstance()->save();
                $result['result'] = 'deleted';
            } else {
                $result['result'] = 'not found';
            }
        }
        return $result;
    }

    public function togglerouteAction($uuid, $disabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $uuid != null) {
            $mdlRoute = new Route();
            $node = $mdlRoute->getNodeByReference('route.' . $uuid);
            if ($node != null) {
                if ($disabled == '0' || $disabled == '1') {
                    $node->disabled = (string)$disabled;
                } elseif ($node->disabled->__toString() == '1') {
                    $node->disabled = '0';
                } else {
                    $node->disabled = '1';
                }
                $result['result'] = (string)$node->disabled == '1' ? 'Disabled' : 'Enabled';
                // if item has toggled, serialize to config and save
                $mdlRoute->serializeToConfig();
                Config::getInstance()->save();
            }
        }
        return $result;
    }

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
