<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\IDS\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Cron\Cron;
use OPNsense\IDS\IDS;
use Phalcon\Filter;
use Phalcon\Filter\FilterFactory;

/**
 * Class ServiceController
 * @package OPNsense\IDS
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\IDS\IDS';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceTemplate = 'OPNsense/IDS';
    protected static $internalServiceName = 'ids';

    /**
     * Reconfigure IDS
     * @return array result status
     * @throws \Exception when configd action fails
     * @throws \OPNsense\Base\ModelException when unable to construct model
     * @throws \Phalcon\Validation\Exception when one or more model validations fail
     */
    public function reconfigureAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $mdlIDS = new IDS();
            $runStatus = $this->statusAction();
            // we should always have a cron item configured for IDS, let's create one upon first reconfigure.
            if ((string)$mdlIDS->general->UpdateCron == "") {
                $mdlCron = new Cron();
                // update cron relation (if this doesn't break consistency)
                $mdlIDS->general->UpdateCron = $mdlCron->newDailyJob("IDS", "ids update", "ids rule updates", "*", "0");

                if ($mdlCron->performValidation()->count() == 0) {
                    $mdlCron->serializeToConfig();
                    // save data to config, do not validate because the current in memory model doesn't know about the
                    // cron item just created.
                    $mdlIDS->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                }
            }

            if ($runStatus['status'] == "running" && (string)$mdlIDS->general->enabled == 0) {
                $this->stopAction();
            }

            $backend = new Backend();
            $bckresult = trim($backend->configdRun('template reload OPNsense/IDS'));

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
                        $status = "error installing ids rules (" . $bckresult . ")";
                    }
                } else {
                    $status = "OK";
                }
            } else {
                $status = "error generating ids template (" . $bckresult . ")";
            }
        }
        return array("status" => $status);
    }

    /**
     * Download and update rules
     * @param null|string $wait wait for update to complete (default) or run in background and return message id
     * @return array result status
     * @throws \Exception when configd action fails
     */
    public function updateRulesAction($wait = null)
    {
        $status = "failed";
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            // we have to trigger a template reload to be sure we have the right download configuration
            // ideally we should only regenerate the download config, but that's not supported at the moment.
            // (not sure if it should be supported)
            $bckresult = trim($backend->configdRun('template reload OPNsense/IDS'));

            if ($bckresult == "OK") {
                if ($wait != null) {
                    $detach = true;
                } else {
                    $detach = false;
                }

                $status = $backend->configdRun("ids update", $detach);
            } else {
                $status = "template error";
            }
        }

        return array("status" => $status);
    }

    /**
     * Flush rule configuration to config and reload suricata ruleset (graceful restart)
     * @return array
     * @throws \Exception when configd action fails
     */
    public function reloadRulesAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            // flush rule configuration
            $bckresult = trim($backend->configdRun('template reload OPNsense/IDS'));
            if ($bckresult == "OK") {
                $status = $backend->configdRun("ids reload");
            } else {
                $status = "template error";
            }
        }
        return array("status" => $status);
    }

    /**
     * Query suricata alerts
     * @return array
     * @throws \Exception when configd action fails
     */
    public function queryAlertsAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            // create filter to sanitize input data
            $filter = new Filter([
                'query' => function($value){
                    return preg_replace("/[^0-9,a-z,A-Z, ,*,\-,_,.,\#]/", "", $value);
                }
            ]);

            // fetch query parameters (limit results to prevent out of memory issues)
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);

            if ($this->request->getPost('searchPhrase', 'string', '') != "") {
                $filterTag = $filter->sanitize($this->request->getPost('searchPhrase'), "query");
                $searchPhrase = 'alert,alert_action,src_ip,dest_ip/"*' . $filterTag . '*"';
            } else {
                $searchPhrase = '';
            }


            if ($this->request->getPost('fileid', 'string', '') != "") {
                $fileid = $this->request->getPost('fileid', 'int', -1);
            } else {
                $fileid = null;
            }

            $backend = new Backend();
            $response = $backend->configdpRun("ids query alerts", array($itemsPerPage,
                ($currentPage - 1) * $itemsPerPage, $searchPhrase, $fileid));
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
     * Fetch alert detailed info
     * @param string $alertId alert id, position in log file
     * @param string $fileid log file id number (empty for standard)
     * @return array alert info
     * @throws \Exception when configd action fails
     */
    public function getAlertInfoAction($alertId, $fileid = "")
    {
        $this->sessionClose();
        $backend = new Backend();
        $filter = (new FilterFactory())->newInstance();
        $id = $filter->sanitize($alertId, "int");
        $response = $backend->configdpRun("ids query alerts", array(1, 0, "filepos/" . $id, $fileid));
        $result = json_decode($response, true);
        if ($result != null && count($result['rows']) > 0) {
            return $result['rows'][0];
        } else {
            return array();
        }
    }

    /**
     * List all available logs
     * @return array list of alert logs
     * @throws \Exception when configd action fails
     */
    public function getAlertLogsAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $response = $backend->configdRun("ids list alertlogs");
        $result = json_decode($response, true);
        if ($result != null) {
            $logs = array();
            foreach ($result as $log) {
                $log['modified'] = date('Y/m/d G:i', $log['modified']);
                $logs[] = $log;
            }
            return $logs;
        } else {
            return array();
        }
    }

    /**
     * Drop alert log
     * @return array result status
     * @throws \Exception when configd action fails
     */
    public function dropAlertLogAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $filename = $this->request->getPost('filename', 'string', null);
            if ($filename != null) {
                $filename = basename($filename);
                $backend->configdpRun("ids drop alertlog", array($filename));
                return array("status" => "ok");
            }
        }
        return array("status" => "failed");
    }
}
