<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use Phalcon\Filter;

/**
 * @inherit
 */
class LogController extends ApiControllerBase
{
    public function __call($name, $arguments)
    {
        $module = substr($name, 0, strlen($name) - 6);
        $scope = count($arguments) > 0 ? $arguments[0] : "";
        $action = count($arguments) > 1 ? $arguments[1] : "";
        $searchPhrase = '';
        // create filter to sanitize input data
        $filter = new Filter([
            'query' => function($value){
                return preg_replace("/[^0-9,a-z,A-Z, ,*,\-,_,.,\#]/", "", $value);
            }
        ]);

        $backend = new Backend();
        if ($this->request->isPost() && substr($name, -6) == 'Action') {
            $this->sessionClose();
            if ($action == "clear") {
                $backend->configdpRun("system clear log", array($module, $scope));
                return ["status" => "ok"];
            } else {
                // fetch query parameters (limit results to prevent out of memory issues)
                $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
                $currentPage = $this->request->getPost('current', 'int', 1);

                if ($this->request->getPost('searchPhrase', 'string', '') != "") {
                    $searchPhrase = $filter->sanitize($this->request->getPost('searchPhrase'), "query");
                }

                $response = $backend->configdpRun("system diag log", array($itemsPerPage,
                    ($currentPage - 1) * $itemsPerPage, $searchPhrase, $module, $scope));
                $result = json_decode($response, true);
                if ($result != null) {
                    $result['rowCount'] = count($result['rows']);
                    $result['total'] = $result['total_rows'];
                    $result['current'] = (int)$currentPage;
                    return $result;
                }
            }
        } elseif ($this->request->isGet() && substr($name, -6) == 'Action') {
            if ($action == "export") {
                if ($this->request->get('searchPhrase', 'string', '') != "") {
                    $searchPhrase = $filter->sanitize($this->request->get('searchPhrase'), "query");
                }
                $response = $backend->configdpRun("system diag log", array(0, 0, $searchPhrase, $module, $scope));
                $this->response->setRawHeader("Content-Type: text/csv");
                $this->response->setRawHeader("Content-Disposition: attachment; filename=" . $scope . ".log");
                foreach (json_decode($response, true)['rows'] as $row) {
                    printf("%s\t%s\t%s\n", $row['timestamp'], $row['process_name'], $row['line']);
                }
                return;
            }
        }
        return array();
    }
}
