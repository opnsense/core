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

use OPNsense\Core\Config;
use Phalcon\Mvc\Controller;
use Phalcon\Translate\Adapter\NativeArray;

/**
 * Class ControllerBase implements core controller for OPNsense framework
 * @package OPNsense\Base
 */
class ControllerBase extends Controller
{
    /**
     * translate a text
     * @return NativeArray
     */
    public function getTranslator()
    {
        // TODO: implement language service
        $messages = array();
        return new NativeArray(array(
            "content" => $messages
        ));
    }

    /**
     * Default action. Set the standard layout.
     */
    public function initialize()
    {
        // set base template
        $this->view->setTemplateBefore('default');
    }

    /**
     * shared functionality for all components
     * @param $dispatcher
     * @return bool
     */
    public function beforeExecuteRoute($dispatcher)
    {
        // Authentication
        // - use authentication of legacy OPNsense.
        if ($this->session->has("Username") == false) {
            $this->response->redirect("/", true);
        }
        // check for valid csrf
        if ($this->request->isPost() && !$this->security->checkToken()) {
            // post without csrf, exit.
            return false;
        }

        // include csrf for GET requests.
        if ($this->request->isGet()) {
            // inject csrf information
            $this->view->setVars([
                'csrf_tokenKey' => $this->security->getTokenKey(),
                'csrf_token' => $this->security->getToken()
            ]);
        }

        // Execute before every found action
        $this->view->setVar('lang', $this->getTranslator());

        // link menu system to view, append /ui in uri because of rewrite
        $menu = new Menu\MenuSystem();

        // add interfaces to "Interfaces" menu tab... kind of a hack, may need some improvement.
        $cnf = Config::getInstance();
        $ordid = 0;
        foreach ($cnf->object()->interfaces->children() as $key => $node) {
            $menu->appendItem("Interfaces", $key, array("url"=>"/interfaces.php?if=".$key,"order"=>($ordid++), "visiblename"=>$node->descr));
        }

        $this->view->menuSystem = $menu->getItems("/ui".$this->router->getRewriteUri());

        // append ACL object to view
        $this->view->acl = new \OPNsense\Core\ACL();
    }

    /**
     * @param $dispatcher
     */
    public function afterExecuteRoute($dispatcher)
    {
        // Executed after every found action
        // TODO: implement default behavior
    }
}
