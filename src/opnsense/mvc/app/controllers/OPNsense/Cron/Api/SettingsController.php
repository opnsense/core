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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Cron\Cron;

/**
 * Class SettingsController Handles settings related API actions for the Cron
 * @package OPNsense\Cron
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'job';
    protected static $internalModelClass = '\OPNsense\Cron\Cron';

    /**
     * retrieve job settings or return defaults
     * @param $uuid item unique id
     * @return array job contents
     * @throws \ReflectionException when not bound to model
     */
    public function getJobAction($uuid = null)
    {
        return $this->getBase("job", "jobs.job", $uuid);
    }


    /**
     * update job with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setJobAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("job")) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('jobs.job.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $jobInfo = $this->request->getPost("job");
                    if ((string)$node->origin != "cron") {
                        if ($jobInfo["command"] != (string)$node->command) {
                            $result["validations"]["job.command"] = gettext("This item has been created by " .
                                "another service, command and parameter may not be changed.");
                        }
                        if ($jobInfo["parameters"] != (string)$node->parameters) {
                            $result["validations"]["job.parameters"] = sprintf(
                                gettext("This item has been created by " .
                                "another service, command and parameter may not be changed. (was: %s)"),
                                (string)$node->parameters
                            );
                        }
                    }

                    $node->setNodes($jobInfo);
                    $valMsgs = $this->getModel()->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "job", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        $result = $this->save();
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * add new job and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException
     */
    public function addJobAction()
    {
        return $this->addBase("job", "jobs.job");
    }


    /**
     * delete job by uuid ( only if origin is cron)
     * @param string $uuid item unique id
     * @return array status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\ModelException when not bound to model
     */
    public function delJobAction($uuid)
    {
        if ($uuid != null) {
            $node = (new Cron())->getNodeByReference('jobs.job.' . $uuid);
            if ($node->origin == "cron") {
                return $this->delBase("jobs.job", $uuid);
            }
        }
        return array("result" => "failed");
    }

    /**
     * toggle job by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleJobAction($uuid, $enabled = null)
    {
        return $this->toggleBase("jobs.job", $uuid, $enabled);
    }


    /**
     * search cron jobs
     * @return array search results
     * @throws \ReflectionException
     */
    public function searchJobsAction()
    {
        return $this->searchBase(
            "jobs.job",
            array("enabled", "minutes","hours", "days", "months", "weekdays", "description", "command", "origin"),
            "description"
        );
    }
}
