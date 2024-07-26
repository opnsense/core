<?php
/*
 * Copyright (C) 2024 Sheridan Computers Limited
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
namespace OPNsense\BootEnvironments\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\BootEnvironments\BootEnvironments;
class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'general';
    protected static $internalModelClass = '\OPNsense\BootEnvironments\General';

    public function getAction()
    {
        // Check if we're only searching for a single record
        $uri = explode("?", $_SERVER["REQUEST_URI"])[0];
        $apiPath = '/api/bootenvironments/general/get/';

        // request for new record?
        if ($apiPath === $uri) {
            $today = date("YmdHis");
            return ['name' => 'BE'.$today];
        } else if (substr($uri, 0, strlen($apiPath)) === $apiPath) {
            // request for specific record?
            $params = ltrim(str_replace($apiPath, '', $uri), '/');
        }

        $backEnd = new BackEnd();
        $backResult = json_decode(trim($backEnd->configdRun('bootenvironments list')), true);
        // only return valid json
        if ($backResult !== null) {
            // search for single record?
            if (! empty($params)) {
                foreach ($backResult as $result) {
                    if ($result['uuid'] === $params) {
                        return $result;
                    }
                }
                return ['message' => 'Boot environment not found'];
            }

            // sort in order of creation
            usort($backResult, function ($a, $b) use ($backResult) {
                return strtotime($a['created']) - strtotime($b['created']);
            });

            // return all
            return [
                'status' => 'ok',
                'path' => $params,
                'rows' => $backResult,
                'current' => 1,
                'total' => count($backResult),
                'rowCount' => 7,
                'searchPhrase' => ''
            ];
        }
        return ['message' => 'Unable to list boot environments'];
    }

    public function setAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('bootenv')) {
            $postVars = $this->request->getPost('bootenv') ?? [];

            $uuid = $postVars['uuid'] ?? null;
            $be = $this->findBootEnvironment($uuid);
            if (empty($be)) {
                return ['status' => 'error', 'message' => 'Boot environment not found'];
            }

            // check boot environment name
            if ($be['name'] !== $postVars['name']) {
                $backEnd = new BackEnd();
                return json_decode(
                    trim($backEnd->configdRun("bootenvironments rename {$be['name']} {$postVars['name']}")),
                    true
                );
            }
        }

        return ['status' => 'error', 'message' => 'Unable to set boot environment'];
    }

    public function addBootEnvAction()
    {
        $response = ['status' => 'failed', 'result' => 'error'];
        if ($this->request->isPost()) {
            if ($this->request->hasPost('bootenv')) {
                $bootenv = $this->request->getPost('bootenv');
                $name = $bootenv['name'] ?? null;
                $uuid = $bootenv['uuid'] ?? null;
            }

            $backEnd = new BackEnd();
            if (empty($name) && empty($uuid)) {
                // no name or uuid - create new environment
                return($backEnd->configdRun("bootenvironments createquick"));
            } else if (!empty($name) && !empty($uuid)) {
                // we have a name and uuid, clone request?
                $be = $this->findBootEnvironment($uuid);
                if (empty($be)) {
                    return ['status' => 'error', 'message' => 'Boot environment not found'];
                }
                if ($be['name'] !== $name) {
                    $cloneFrom = $be['name'];
                    return json_decode(
                        trim($backEnd->configdRun("bootenvironments clone {$name} {$cloneFrom}")),
                        true
                    );
                }
            }
            else {
                // otherwise create as normal
                return($backEnd->configdRun("bootenvironments create {$name}"));
            }
        }
        return ['error' => 'Unable to add boot environment'];
    }

    public function delBootEnvAction($uuid): array
    {
        if (! $this->request->isPost()) {
            $this->response->setStatusCode(405, 'Method Not Allowed');
            $this->response->setHeader('Allow', 'POST');
            return ['message' => 'Method not allowed'];
        }

        $be = $this->findBootEnvironment($uuid);
        if (empty($be)) {
            return ['status' => 'error', 'message' => 'Boot environment not found'];
        }
        $backEnd = new BackEnd();
        return (json_decode(trim($backEnd->configdRun("bootenvironments destroy {$be['name']}")), true));
    }

    public function listAction()
    {
        return $this->getAction();
    }

    public function activateAction()
    {
        $response = ['status' => 'failed', 'result' => 'error'];
        if ($this->request->isPost() && $this->request->hasPost('uuid') && !empty($this->request->getPost('uuid'))) {
            $uuid = $this->request->getPost('uuid');
            $be = $this->findBootEnvironment($uuid);
            if (empty($be)) {
                return ['status' => false, 'message' => 'Boot environment not found'];
            }

            $backEnd = new BackEnd();
            $be_name = $be['name'];
            return json_decode(trim($backEnd->configdRun("bootenvironments activate {$be_name}")), true);
        }
        return $response;
    }

    /**
     * @param string $search uuid of boot environment to find
     * @return array boot environment elements as an array, or null if not found
     */
    private function findBootEnvironment(string $search): array
    {
        $backEnd = new BackEnd();
        $backResult = json_decode(trim($backEnd->configdRun("bootenvironments fetch --uuid {$search}")), true);
        if ($backResult !== null) {
            if ($backResult['uuid'] === $search) {
                return $backResult;
            }
        }
        return [];
    }
}
