<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\IDS\Api;

use \Phalcon\Filter;
use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\IDS\IDS;

/**
 * Class ServiceController
 * @package OPNsense\IDS
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start ids service
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = trim($backend->configdRun("ids start"));
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop ids service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = trim($backend->configdRun("ids stop"));
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart ids service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("ids restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of squid proxy
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $mdlIDS = new IDS();
        $response = $backend->configdRun("ids status");

        if (strpos($response, "not running") > 0) {
            if ((string)$mdlIDS->general->enabled == 1) {
                $status = "stopped";
            } else {
                $status = "disabled";
            }
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } elseif ((string)$mdlIDS->general->enabled == 0) {
            $status = "disabled";
        } else {
            $status = "unkown";
        }

        return array("status" => $status);
    }

    /**
     * reconfigure IDS
     */
    public function reconfigureAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $mdlIDS = new IDS();
            $runStatus = $this->statusAction();

            if ($runStatus['status'] == "running" && (string)$mdlIDS->general->enabled == 0) {
                $this->stopAction();
            }

            $backend = new Backend();
            $bckresult = trim($backend->configdRun("template reload OPNsense.IDS"));

            if ($bckresult == "OK") {
                if ((string)$mdlIDS->general->enabled == 1) {
                    $bckresult = trim($backend->configdRun("ids install rules"));
                    if ($bckresult == "OK") {
                        if ($runStatus['status'] == 'running') {
                            $status = $this->restartAction()['response'];
                        } else {
                            $status = $this->startAction()['response'];
                        }
                    } else {
                        $status = "error installing ids rules (".$bckresult.")";
                    }
                } else {
                    $status = "OK";
                }
            } else {
                $status = "error generating ids template (".$bckresult.")";
            }

        }
        return array("status" => $status);
    }

    /**
     * query suricata alerts
     * @return array
     */
    public function queryAlertsAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();

            // fetch query parameters
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);

            if ($this->request->getPost('searchPhrase', 'string', '') != "") {
                $searchPhrase = 'alert,src_ip/"*'.$this->request->getPost('searchPhrase', 'string', '').'*"';
            } else {
                $searchPhrase = '';
            }

            $backend = new Backend();
            $response = $backend->configdpRun("ids query alerts", array($itemsPerPage,
                ($currentPage-1)*$itemsPerPage, $searchPhrase));
            $result = json_decode($response, true);
            if ($result != null) {
                $result['rowCount'] = count($result['rows']);
                $result['total'] = $result['total_rows'];
                $result['current'] = (int)$currentPage;
                return $result;
            }
        }
        return array();
    }

    /**
     * fetch alert detailed info
     * @param $alertId alert id, position in log file
     * @return array alert info
     */
    public function getAlertInfoAction($alertId)
    {
        $backend = new Backend();
        $filter = new Filter();
        $id = $filter->sanitize($alertId, "int");
        $response = $backend->configdpRun("ids query alerts", array(1, 0, "filepos/".$id));
        $result = json_decode($response, true);
        if ($result != null && count($result['rows']) > 0) {
            return $result['rows'][0];
        } else {
            return array();
        }

    }
}
