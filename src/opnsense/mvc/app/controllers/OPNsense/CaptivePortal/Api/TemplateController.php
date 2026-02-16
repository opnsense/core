<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\SanitizeFilter;

class TemplateController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'template';
    protected static $internalModelClass = '\OPNsense\CaptivePortal\CaptivePortal';

    public function searchTemplatesAction()
    {
        return $this->searchBase('templates.template', ['name', 'fileid'], 'name');
    }

    public function delTemplateAction($uuid)
    {
        return $this->delBase('templates.template', $uuid);
    }

    /**
     * Download template as zip
     */
    public function getTemplateAction($fileid = null)
    {
        $templateFileId = $fileid !== null
            ? (new SanitizeFilter())->sanitize($fileid, 'alnum')
            : 'default';

        $response = (new Backend())->configdpRun('captiveportal fetch_template', [$templateFileId]);

        $result = json_decode($response, true);

        if ($result !== null) {
            $payload = base64_decode($result['payload']);

            $this->response->setRawHeader(
                "Content-Type: application/octet-stream"
            );
            $this->response->setRawHeader(
                "Content-Disposition: attachment; filename=template_{$templateFileId}.zip"
            );

            return $payload;
        }

        return "";
    }

    /**
     * Save (create or update) template
     */
    public function saveTemplateAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost("name")) {
            return null;
        }

        Config::getInstance()->lock();

        $content = $this->request->getPost("content", "striptags", "");
        $templateName = $this->request->getPost("name", "striptags");

        if ($this->request->hasPost("uuid")) {
            $uuid = $this->request->getPost("uuid", "striptags");
            $template = $this->getModel()->getNodeByReference('templates.template.' . $uuid);

            if ($template === null) {
                return ["error" => "node not found"];
            }
        } else {
            $template = $this->getModel()->getTemplateByName($templateName);
        }

        // cleanse input content, we only want to save changed files into our config
        if (strlen($content) > 20 || strlen((string)$template->content) === 0) {
            $temp_filename = (new AppConfig())->application->tempDir .
                '/cp_' . $template->getAttributes()['uuid'] . '.tmp';

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

        $validation = $this->validate();

        if (!empty($validation['validations'])) {
            return $validation;
        }

        $this->save(false, true);

        return ["result" => "saved"];
    }

}
