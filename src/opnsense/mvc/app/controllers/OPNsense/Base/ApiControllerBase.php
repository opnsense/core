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
use Phalcon\Mvc\Controller;

/**
 * Class ApiControllerBase, inherit this class to implement API calls
 * @package OPNsense\Base
 */
class ApiControllerBase extends Controller
{
    /**
     * Initialize API controller
     */
    public function initialize()
    {
        // disable view processing
        $this->view->disable();
    }

    /**
     * before routing event
     * @param Dispatcher $dispatcher
     * @return null|bool
     */
    public function beforeExecuteRoute($dispatcher)
    {
        // TODO: implement authentication for api calls, at this moment you need a valid session on the web interface

        // use authentication of legacy OPNsense to validate user.
        if ($this->session->has("Username") == false) {
            $this->response->redirect("/", true);
        }

        // Authorization using legacy acl structure
        $acl = new ACL();
        if (!$acl->isPageAccessible($this->session->get("Username"), $_SERVER['REQUEST_URI'])) {
            $this->response->redirect("/", true);
        }

        // check for valid csrf on post requests
        $csrf_tokenkey = $this->request->getHeader('X_CSRFTOKENKEY');
        $csrf_token =   $this->request->getHeader('X_CSRFTOKEN');
        $csrf_valid = $this->security->checkToken($csrf_tokenkey, $csrf_token);

        if (($this->request->isPost() ||
                $this->request->isPut() ||
                $this->request->isDelete()
            ) && !$csrf_valid
        ) {
            // missing csrf, exit.
            return false;
        }

        // TODO: implement ACL

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
                echo json_encode($data) ;
            }
        }

        return true;
    }
}
