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
namespace OPNsense\Base;

use OPNsense\Core\ACL;
use OPNsense\Auth\AuthenticationFactory;
/**
 * Class ApiControllerBase, inherit this class to implement API calls
 * @package OPNsense\Base
 */
class ApiControllerBase extends ControllerRoot
{
    /**
     * @var bool cleanse output before sending to client, be very careful to disable this (XSS).
     */
    private $cleanseOutput = true;

    /**
     * disable output cleansing.
     * Prevents the framework from executing automatic XSS protection on all delivered json data.
     * Be very careful to disable this, if content can't be guaranteed you might introduce XSS vulnerabilities.
     */
    protected function disableOutputCleansing()
    {
        $this->cleanseOutput = false;
    }

    /**
     * Initialize API controller
     */
    public function initialize()
    {
        // disable view processing
        $this->view->disable();
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
        if (!empty($this->request->getHeader('Authorization'))) {
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
                            $acl = new ACL();
                            if (!$acl->isPageAccessible($authResult['username'], $_SERVER['REQUEST_URI'])) {
                                $this->getLogger()->error("uri ".$_SERVER['REQUEST_URI'].
                                    " not accessible for user ".$authResult['username'] . " using api key ".
                                    $apiKey
                                );
                            } else {
                                // authentication + authorization successful.
                                // pre validate request and communicate back to the user on errors
                                $callMethodName = $dispatcher->getActionName().'Action';
                                $dispatchError = null;
                                if (!method_exists($this, $callMethodName)) {
                                    // can not execute, method not found
                                    $dispatchError = 'action ' . $dispatcher->getActionName() . ' not found';
                                } else {
                                    // check number of parameters using reflection
                                    $object_info = new \ReflectionObject($this);
                                    $req_c = $object_info->getMethod($callMethodName)->getNumberOfRequiredParameters();
                                    if ($req_c > count($dispatcher->getParams())) {
                                        $dispatchError = 'action ' . $dispatcher->getActionName() .
                                          ' expects at least '. $req_c . ' parameter(s)';
                                    }
                                }
                                if ($dispatchError != null) {
                                    $this->response->setStatusCode(400, "Bad Request");
                                    $this->response->setContentType('application/json', 'UTF-8');
                                    $this->response->setJsonContent(
                                        array('message' => $dispatchError,
                                              'status'  => 400
                                    ));
                                    $this->response->send();
                                    return false;
                                }

                                return true;
                            }
                        }
                    }
                }
            }
            // not authenticated
            $this->response->setStatusCode(401, "Unauthorized");
            $this->response->setContentType('application/json', 'UTF-8');
            $this->response->setJsonContent(array(
                'status'  => 401,
                'message' => 'Authentication Failed',
            ));
            $this->response->send();
            return false;
        } else {
            // handle UI ajax reuests
            // use session data and ACL to validate request.
            if (!$this->doAuth()) {
                return false;
            }

            // check for valid csrf on post requests
            $csrf_tokenkey = $this->request->getHeader('X_CSRFTOKENKEY');
            $csrf_token =   $this->request->getHeader('X_CSRFTOKEN');
            $csrf_valid = $this->security->checkToken($csrf_tokenkey, $csrf_token, false);

            if (($this->request->isPost() ||
                    $this->request->isPut() ||
                    $this->request->isDelete()
                ) && !$csrf_valid
            ) {
                // missing csrf, exit.
                $this->getLogger()->error("no matching csrf found for request");
                return false;
            }
        }
    }

    /**
     * process API results, serialize return data to json.
     * @param $dispatcher
     * @return string json data
     */
    protected function afterExecuteRoute($dispatcher)
    {
        // exit when reponse headers are already set
        if ($this->response->getHeaders()->get("Status") != null) {
            return false;
        } else {
            // process response, serialize to json object
            $data = $dispatcher->getReturnedValue();
            if (is_array($data)) {
                $this->response->setContentType('application/json', 'UTF-8');
                if ($this->cleanseOutput) {
                    echo htmlspecialchars(json_encode($data), ENT_NOQUOTES);
                } else {
                    echo json_encode($data);
                }

            } else {
                // output raw data
                echo $data;
            }
        }

        return true;
    }
}
