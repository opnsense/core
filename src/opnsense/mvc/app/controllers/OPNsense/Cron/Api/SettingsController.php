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
namespace OPNsense\Cron\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Cron\Cron;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the Cron
 * @package OPNsense\Cron
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve job settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getJobAction($uuid = null)
    {
        $mdlCron = new Cron();
        if ($uuid != null) {
            $node = $mdlCron->getNodeByReference('jobs.job.' . $uuid);
            if ($node != null) {
                // return node
                return array("job" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCron->jobs->job->add();
            return array("job" => $node->getNodes());
        }
        return array();
    }

    /**
     * update job with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setJobAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("job")) {
            $mdlCron = new Cron();
            if ($uuid != null) {
                $node = $mdlCron->getNodeByReference('jobs.job.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $jobInfo = $this->request->getPost("job");
                    if ( $node->origin->__toString() != "cron" ){
                        if ( $jobInfo["command"]!=$node->command->__toString() ) {
                            $result["validations"]["job.command"] = "This item has been created by another service, command and parameter may not be changed.";
                        }
                        if ( $jobInfo["parameters"]!=$node->parameters->__toString() ) {
                            $result["validations"]["job.parameters"] = "This item has been created by another service, command and parameter may not be changed. (was: " . $node->parameters->__toString() . " )";
                        }
                    }

                    $node->setNodes($jobInfo);
                    $valMsgs = $mdlCron->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "job", $msg->getField());
                        if ($fieldnm != $msg->getField()) {
                            // only collect validation errors for the item we're currently editing.
                            $result["validations"][$fieldnm] = $msg->getMessage();
                        }

                    }

                    if (count($result['validations']) == 0) {
                        // we've already performed a validation, prevent issues from other items in the model reflecting back to us.
                        $mdlCron->serializeToConfig($disable_validation = true);

                        // save config if validated correctly
                        Config::getInstance()->save();
                        $result = array("result" => "saved");
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * add new job and set with attributes from post
     * @return array
     */
    public function addJobAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("job")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlCron = new Cron();
            $node = $mdlCron->jobs->job->Add();
            $node->setNodes($this->request->getPost("job"));
            $node->origin = "cron"; // set origin to this component - cron are manually created rules.
            $valMsgs = $mdlCron->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "job", $msg->getField());
                if ($fieldnm != $msg->getField()) {
                    // only collect validation errors for the item we're currently editing.
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }

            }

            if (count($result['validations']) == 0) {
                // we've already performed a validation, prevent issues from other items in the model reflecting back to us.
                $mdlCron->serializeToConfig($disable_validation = true);

                // save config if validated correctly
                Config::getInstance()->save();
                $result = array("result" => "saved");
            }
            return $result;
        }
        return $result;
    }

    /**
     * delete job by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delJobAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlCron = new Cron();
            if ($uuid != null) {
                if ($mdlCron->jobs->job->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCron->serializeToConfig($disable_validation = true);
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle job by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleJobAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlCron = new Cron();
            if ($uuid != null) {
                $node = $mdlCron->getNodeByReference('jobs.job.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlCron->serializeToConfig($disable_validation = true);
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     *
     * search cron jobs
     * @return array
     */
    public function searchJobsAction()
    {
//        if ($this->request->isPost()) {
            $this->sessionClose();
            // fetch query parameters
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);
            $sortBy = array("description");
            $sortDescending = false;

            if ($this->request->hasPost('sort') && is_array($this->request->getPost("sort"))) {
                $sortBy = array_keys($this->request->getPost("sort"));
                if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                    $sortDescending = true;
                }
            }

            $searchPhrase = $this->request->getPost('searchPhrase', 'string', '');

            // create model and fetch query resuls
            $fields = array(
                "enabled",
                "minutes",
                "hours",
                "days",
                "months",
                "weekdays",
                "description",
                "command",
                "origin",
                "cronPermissions"
            );
            $mdlCron = new Cron();
            $grid = new UIModelGrid($mdlCron->jobs->job);
            return $grid->fetch($fields, $itemsPerPage, $currentPage, $sortBy, $sortDescending, $searchPhrase);
//        } else {
//            return array();
//        }

    }
}
