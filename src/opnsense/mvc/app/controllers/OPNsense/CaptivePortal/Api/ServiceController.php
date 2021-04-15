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

namespace OPNsense\CaptivePortal\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\CaptivePortal\CaptivePortal;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;
use Phalcon\Filter\FilterFactory;

/**
 * Class ServiceController
 * @package OPNsense\CaptivePortal
 */
class ServiceController extends ApiControllerBase
{

    /**
     * reconfigure captive portal
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();
            // the ipfw rules need to know about all the zones, so we need to reload ipfw for the portal to work
            $backend->configdRun('template reload OPNsense/IPFW');
            $bckresult = trim($backend->configdRun("ipfw reload"));
            if ($bckresult == "OK") {
                // generate captive portal config
                $bckresult = trim($backend->configdRun('template reload OPNsense/Captiveportal'));
                if ($bckresult == "OK") {
                    $mdlCP = new CaptivePortal();
                    if ($mdlCP->isEnabled()) {
                        $bckresult = trim($backend->configdRun("captiveportal restart"));
                        if ($bckresult == "OK") {
                            $status = "ok";
                        } else {
                            $status = "error reloading captive portal";
                        }
                    } else {
                        $backend->configdRun("captiveportal stop");
                        $status = "ok";
                    }
                } else {
                    $status = "error reloading captive portal template";
                }
            } else {
                $status = "error reloading captive portal rules (" . $bckresult . ")";
            }

            return array("status" => $status);
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * @param null $fileid unique template id (fileid field)
     * @return mixed
     * @throws \Exception
     */
    public function getTemplateAction($fileid = null)
    {
        // get template name
        $paramfilter = (new FilterFactory())->newInstance();
        if ($fileid != null) {
            $templateFileId = $paramfilter->sanitize($fileid, 'alnum');
        } else {
            $templateFileId = 'default';
        }

        // request template data and output result (zipfile)
        $backend = new Backend();
        $response = $backend->configdpRun("captiveportal fetch_template", array($templateFileId));
        $result = json_decode($response, true);
        if ($result != null) {
            $response = $result['payload'];
            $this->response->setRawHeader("Content-Type: application/octet-stream");
            $this->response->setRawHeader("Content-Disposition: attachment; filename=template_" . $templateFileId . ".zip");
            return base64_decode($response);
        } else {
            // return empty response on error
            return "";
        }
    }


    /**
     * save template, updates existing or create new.
     * @return string
     */
    public function saveTemplateAction()
    {
        if ($this->request->isPost() && $this->request->hasPost("name")) {
            $this->sessionClose();
            $templateName = $this->request->getPost("name", "striptags");
            $mdlCP = new CaptivePortal();
            if ($this->request->hasPost("uuid")) {
                $uuid = $this->request->getPost("uuid", "striptags");
                $template = $mdlCP->getNodeByReference('templates.template.' . $uuid);
                if ($template == null) {
                    return array("name" => $templateName, "error" => "node not found");
                }
            } else {
                $template = $mdlCP->getTemplateByName($templateName);
            }

            // cleanse input content, we only want to save changed files into our config
            if (
                strlen($this->request->getPost("content", "striptags", "")) > 20
                || strlen((string)$template->content) == 0
            ) {
                $temp_filename = 'cp_' . (string)$template->getAttributes()['uuid'] . '.tmp';
                file_put_contents('/tmp/' . $temp_filename, $this->request->getPost("content", "striptags", ""));
                // strip defaults and unchanged files from template (standard js libs, etc)
                $backend = new Backend();
                $response = $backend->configdpRun("captiveportal strip_template", array($temp_filename));
                unlink('/tmp/' . $temp_filename);
                $result = json_decode($response, true);
                if ($result != null && !array_key_exists('error', $result)) {
                    $template->content = $result['payload'];
                } else {
                    return array("name" => $templateName, "error" => $result['error']);
                }
            }

            $template->name = $templateName;
            $valMsgs = $mdlCP->performValidation();
            $errorMsg = "";
            foreach ($valMsgs as $field => $msg) {
                if ($errorMsg != "") {
                    $errorMsg .= " , ";
                }
                $errorMsg .= $msg->getMessage();
            }

            if ($errorMsg != "") {
                return array("name" => (string)$template->name, "error" => $errorMsg);
            } else {
                // data is valid, save and return.
                $mdlCP->serializeToConfig();
                Config::getInstance()->save();
                return array("name" => (string)$template->name);
            }
        }
        return null;
    }

    /**
     * delete template by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delTemplateAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new CaptivePortal();
            if ($uuid != null) {
                if ($mdlCP->templates->template->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
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
     * search captive portal zones
     * @return array
     */
    public function searchTemplatesAction()
    {
        $this->sessionClose();
        $mdlCP = new CaptivePortal();
        $grid = new UIModelGrid($mdlCP->templates->template);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "fileid"),
            "name"
        );
    }
}
