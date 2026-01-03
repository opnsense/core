<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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

namespace OPNsense\CaptivePortal\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\SanitizeFilter;

/**
 * Class ServiceController
 * @package OPNsense\CaptivePortal
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\CaptivePortal\CaptivePortal';
    protected static $internalServiceTemplate = 'OPNsense/Captiveportal';
    protected static $internalServiceName = 'captiveportal';

    protected function serviceEnabled()
    {
        return  $this->getModel()->isEnabled();
    }

    protected function invokeFirewallReload()
    {
        return true;
    }

    /**
     * @param null $fileid unique template id (fileid field)
     * @return mixed
     * @throws \Exception
     */
    public function getTemplateAction($fileid = null)
    {
        // get template name
        $templateFileId = $fileid != null ? (new SanitizeFilter())->sanitize($fileid, 'alnum') : 'default';

        // request template data and output result (zipfile)
        $response = (new Backend())->configdpRun('captiveportal fetch_template', [$templateFileId]);
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
            Config::getInstance()->lock();
            $content = $this->request->getPost("content", "striptags", "");
            $templateName = $this->request->getPost("name", "striptags");
            if ($this->request->hasPost("uuid")) {
                $uuid = $this->request->getPost("uuid", "striptags");
                $template = $this->getModel()->getNodeByReference('templates.template.' . $uuid);
                if ($template == null) {
                    return ["name" => $templateName, "error" => "node not found"];
                }
            } else {
                $template = $this->getModel()->getTemplateByName($templateName);
            }

            // cleanse input content, we only want to save changed files into our config
            if (strlen($content) > 20 || strlen((string)$template->content) == 0) {
                $temp_filename = (new AppConfig())->application->tempDir;
                $temp_filename .= '/cp_' . $template->getAttributes()['uuid'] . '.tmp';
                file_put_contents($temp_filename, $content);
                // strip defaults and unchanged files from template (standard js libs, etc)
                $response = (new Backend())->configdpRun('captiveportal strip_template', [$temp_filename]);
                unlink($temp_filename);
                $result = json_decode($response, true);
                if (is_array($result) && !array_key_exists('error', $result)) {
                    $template->content = $result['payload'];
                } else {
                    return ["name" => $templateName, "error" => $result['error']];
                }
            }

            $template->name = $templateName;
            $errorMsg = [];
            foreach ($this->getModel()->performValidation() as $validation_message) {
                $errorMsg[] = (string)$validation_message;
            }

            if (!empty($errorMsg)) {
                return ["name" => (string)$template->name, "error" => implode("\n", $errorMsg)];
            } else {
                // data is valid, save and return.
                $this->getModel()->serializeToConfig();
                Config::getInstance()->save();
                return ["name" => (string)$template->name];
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
        $result = ["result" => "failed"];
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            if ($uuid != null) {
                if ($this->getModel()->templates->template->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $this->getModel()->serializeToConfig();
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
        $grid = new UIModelGrid($this->getModel()->templates->template);
        return $grid->fetchBindRequest($this->request, ["name", "fileid"], "name");
    }
}
