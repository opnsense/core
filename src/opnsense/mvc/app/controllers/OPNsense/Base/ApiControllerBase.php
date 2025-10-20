<?php

/*
 * Copyright (C) 2015-2022 Deciso B.V.
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

namespace OPNsense\Base;

use OPNsense\Auth\AuthenticationFactory;
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Mvc\Security;

/**
 * Class ApiControllerBase, inherit this class to implement API calls
 * @package OPNsense\Base
 */
class ApiControllerBase extends ControllerRoot
{
    /***
     * Recordset (array in array) search wrapper
     * @param string $path path to search, relative to this model
     * @param array $fields fieldnames to search through in result
     * @param string|null $defaultSort default sort field name
     * @param null|function $filter_funct additional filter callable
     * @param int $sort_flags sorting behavior
     * @param array|null $search_clauses optional overwrite to pass clauses to search instead of using searchPhrase
     * @return array
     */
    protected function searchRecordsetBase(
        $records,
        $fields = null,
        $defaultSort = null,
        $filter_funct = null,
        $sort_flags = SORT_NATURAL | SORT_FLAG_CASE,
        $search_clauses = null
    ) {
        $records = is_array($records) ? $records : []; // safeguard input, we are only able to search arrays.
        $itemsPerPage = intval($this->request->getPost('rowCount', 'int', 9999));
        $itemsPerPage = $itemsPerPage == -1 ? count($records) : $itemsPerPage;
        $currentPage = intval($this->request->getPost('current', 'int', 1));
        $offset = ($currentPage - 1) * $itemsPerPage;
        $entry_keys = array_keys($records);
        if (!is_array($search_clauses)) {
            /* default behavior, extract clauses to search from post */
            $searchPhrase = (string)$this->request->getPost('searchPhrase', null, '');
            $search_clauses = preg_split('/\s+/', $searchPhrase);
        }

        $sortOrder = SORT_ASC;
        $sortKey = $defaultSort;
        if (
            $this->request->hasPost('sort') &&
            is_array($this->request->getPost('sort')) &&
            !empty($this->request->getPost('sort'))
        ) {
            $keys = array_keys($this->request->getPost('sort'));
            $sortOrder = $this->request->getPost('sort')[$keys[0]] == 'asc' ? SORT_ASC : SORT_DESC;
            $sortKey = $keys[0];
        }
        if (!empty($sortKey) && !empty($records)) {
            // make sure the sort key exists in the recordset to prevent "sizes are inconsistent"
            foreach ($records as &$record) {
                if (!isset($record[$sortKey])) {
                    $record[$sortKey] = null;
                }
            }
            $keys = array_column($records, $sortKey);
            array_multisort($keys, $sortOrder, $sort_flags, $records);
        }


        $entry_keys = array_filter($entry_keys, function ($key) use ($search_clauses, $filter_funct, $fields, &$records) {
            if (is_callable($filter_funct) && !$filter_funct($records[$key])) {
                // not applicable according to $filter_funct()
                return false;
            } elseif (!empty($search_clauses)) {
                foreach ($search_clauses as $clauses) {
                    $matches = false;
                    foreach ($records[$key] as $itemkey => $itemval) {
                        if (!empty($fields) && !in_array($itemkey, $fields)) {
                            continue;
                        }

                        if (is_array($itemval)) {
                            $tmp = [];
                            array_walk_recursive($itemval, function ($a) use (&$tmp) {
                                $tmp[] = $a;
                            });
                            $itemval = implode(' ', $tmp);
                        }
                        /**
                         *
                         * Usually "clauses" are singular, in which case all clauses together act as an "AND"
                         * When a "clauses" item is actually an array, all items in the list act as aliases for the same
                         * phrase (OR)
                         **/
                        foreach ((array)$clauses as $clause) {
                            if (stripos((string)$itemval, $clause) !== false) {
                                $matches = true;
                            }
                        }
                    }
                    if (!$matches) {
                        return $matches;
                    }
                }
                return true;
            } else {
                return true;
            }
        });

        $formatted = array_map(function ($value) use (&$records) {
            foreach ($records[$value] as $ekey => $evalue) {
                $item[$ekey] = $evalue;
            }
            return $item;
        }, array_slice($entry_keys, $offset, $itemsPerPage));

        return [
           'total' => count($entry_keys),
           'rowCount' => count($formatted),
           'current' => $currentPage,
           'rows' => $formatted,
        ];
    }

    /**
     * passtru recordset (key value store) as csv output
     * @param array $records dataset to export (e.g. [['field' => 'value'], ['field' => 'value']])
     */
    protected function exportCsv(
        $records,
        $headers = [
            'Content-Type: text/csv', 'Content-Transfer-Encoding: binary', 'Pragma: no-cache', 'Expires: 0'
        ]
    ) {
        $records = is_array($records) ? $records : [];
        $stream = fopen('php://temp', 'rw+');
        if (isset($records[0])) {
            fputcsv($stream, array_keys($records[0]));
        }
        foreach ($records as $record) {
            fputcsv($stream, $record);
        }
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            $this->response->setHeader($parts[0], ltrim($parts[1]));
        }
        rewind($stream);
        $this->response->setContent($stream);
    }

    /**
     * passtru configd stream
     * @param string $action configd action to perform
     * @param array $params list of parameters
     * @param array $headers http headers to send before pushing data
     * @param int $poll_timeout poll timeout after connect
     * @param bool $safe when safe, no escaping will be applied
     */
    protected function configdStream(
        $action,
        $params = [],
        $headers = [
            'Content-Type: application/json', 'Content-Transfer-Encoding: binary', 'Pragma: no-cache', 'Expires: 0'
        ],
        $poll_timeout = 2,
        $safe = false
    ) {
        $response = (new Backend())->configdpStream($action, $params, $poll_timeout);

        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            $this->response->setHeader($parts[0], ltrim($parts[1]));
        }
        $this->response->setContent($response, $safe || $this->isExternalClient());
    }

    /**
     * parse raw json type content to POST data depending on content type
     * (only for api calls)
     * @return string
     */
    private function parseJsonBodyData()
    {
        switch (strtolower(str_replace(' ', '', $this->request->getHeader('CONTENT_TYPE')))) {
            case 'application/json':
            case 'application/json;charset=utf-8':
                $jsonRawBody = $this->request->getJsonRawBody();
                if (empty($this->request->getRawBody()) && empty($jsonRawBody)) {
                    return "Invalid JSON syntax";
                }
                $_POST = is_array($jsonRawBody) ? $jsonRawBody : [];
                foreach ($_POST as $key => $value) {
                    $_REQUEST[$key] = $value;
                }
                break;
            case 'application/x-www-form-urlencoded':
            case 'application/x-www-form-urlencoded;charset=utf-8':
                // valid non parseable content
                break;
            default:
                if (!empty($this->request->getRawBody())) {
                    $this->getLogger()->warning('unparsable Content-Type:' . $this->request->getHeader('CONTENT_TYPE') . ' received');
                }
                break;
        }
        return null;
    }

    /**
     * is external client (other then session authenticated)
     * @return bool
     */
    protected function isExternalClient()
    {
        return !empty($this->request->getHeader('Authorization'));
    }

    /**
     * before routing event.
     * Handles authentication and authentication of user requests
     * In case of API calls, also prevalidates if request can be executed to return a more readable response
     * to the user.
     * @param Dispatcher $dispatcher
     * @return null|bool
     */
    public function beforeExecuteRoute($dispatcher)
    {
        // handle authentication / authorization
        if ($this->isExternalClient()) {
            // Authorization header send, handle API request
            $authHeader = explode(' ', $this->request->getHeader('Authorization'));
            if (count($authHeader) > 1) {
                $key_secret_hash = $authHeader[1];
                $key_secret = explode(':', base64_decode($key_secret_hash));
                if (count($key_secret) > 1) {
                    $apiKey = $key_secret[0];
                    $apiSecret = $key_secret[1];

                    $authFactory = new AuthenticationFactory();
                    $authenticator = $authFactory->get("Local API");
                    if ($authenticator->authenticate($apiKey, $apiSecret)) {
                        $authResult = $authenticator->getLastAuthProperties();
                        if (array_key_exists('username', $authResult)) {
                            // check ACL if user is returned by the Authenticator object
                            $acl = new ACL();
                            if (!$acl->isPageAccessible($authResult['username'], $_SERVER['REQUEST_URI'])) {
                                $this->getLogger()->error("uri " . $_SERVER['REQUEST_URI'] .
                                    " not accessible for user " . $authResult['username'] . " using api key " .
                                    $apiKey);
                                // not authenticated
                                $this->response->setStatusCode(403, "Forbidden");
                                $this->response->setContentType('application/json', 'UTF-8');
                                $this->response->setContent(['status'  => 403,'message' => 'Forbidden']);
                                $this->response->send();
                                return false;
                            } else {
                                // link username on successful login
                                $this->logged_in_user = $authResult['username'];
                                // if body is send as json data, parse to $_POST first
                                $dispatchError = $this->parseJsonBodyData();
                                if ($dispatchError != null) {
                                    $this->response->setStatusCode(400, "Bad Request");
                                    $this->response->setContentType('application/json', 'UTF-8');
                                    $this->response->setContent(['status'  => 400, 'message' => $dispatchError]);
                                    $this->response->send();
                                    return false;
                                }

                                // pass revision context to config object
                                Config::getInstance()->setRevisionContext([
                                    'username' => $authResult['username'],
                                    'user_apitoken' => $apiKey
                                ]);
                                return true;
                            }
                        }
                    } else {
                        $this->getLogger()->error("uri " . $_SERVER['REQUEST_URI'] .
                            " authentication failed for api key " . $apiKey);
                    }
                }
            }
            // not authenticated
            $this->response->setStatusCode(401, "Unauthorized");
            $this->response->setContentType('application/json', 'UTF-8');
            $this->response->setContent(['status'  => 401, 'message' => 'Authentication Failed']);
            $this->response->send();
            return false;
        } else {
            // handle UI ajax requests
            // use session data and ACL to validate request.
            if (!$this->doAuth()) {
                if (!$this->session->has("Username")) {
                    $this->response->setStatusCode(401, "Unauthorized");
                } else {
                    $this->response->setStatusCode(403, "Forbidden");
                }
                return false;
            }

            // check for valid csrf on post requests
            $csrf_valid = (new Security($this->session, $this->request))->checkToken(
                null,
                $this->request->getHeader('X_CSRFTOKEN')
            );

            if (
                ($this->request->isPost() ||
                    $this->request->isPut() ||
                    $this->request->isDelete()
                ) && !$csrf_valid
            ) {
                // missing csrf, exit.
                $this->getLogger()->error("no matching csrf found for request");
                $this->response->setStatusCode(403, "Forbidden");
                return false;
            }
            // when request is using a json body (based on content type), parse it first
            $this->parseJsonBodyData();

            // link username on successful login
            $this->logged_in_user = $this->session->get("Username");
        }
    }

    /**
     * process API results, serialize return data to json.
     * @param $dispatcher
     * @return string json data
     */
    public function afterExecuteRoute($dispatcher)
    {
        // process response, serialize to json object
        $data = $dispatcher->getReturnedValue();
        if (is_array($data)) {
            $this->response->setContentType('application/json', 'UTF-8');
            $this->response->setContent($data, $this->isExternalClient());
        } elseif (is_string($data)) {
            // XXX: fallback, controller returned data as string. a deprecation message might be an option here.
            $this->response->setContent($data);
        }

        return $this->response->send();
    }
}
