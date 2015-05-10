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
use OPNsense\Core\ACL;
use Phalcon\Mvc\Controller;
use Phalcon\Translate\Adapter\Gettext;

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
        if (function_exists("gettext")) {
            // gettext installed, return gettext translator
            return new Gettext(array(
                "locale" => locale_get_default(),
                "directory" => "/usr/local/share/locale/",
                'file' => 'LC_MESSAGES/OPNsense.pot',
            ));
        } else {
            // no gettext installed, return original content
            return new Gettext(array(
                "content" => array()
            ));
        }
    }

    /**
     * convert xml form definition to simple data structure to use in our Volt templates
     *
     * @param $xmlNode
     * @return array
     */
    private function parseFormNode($xmlNode)
    {
        $result = array();
        foreach ($xmlNode as $key => $node) {
            switch ($key) {
                case "tab":
                    if (!array_key_exists("tabs", $result)) {
                        $result['tabs'] = array();
                    }
                    $tab = array();
                    $tab[] = $node->attributes()->id;
                    $tab[] = $node->attributes()->description;
                    if (isset($node->subtab)) {
                        $tab["subtabs"] = $this->parseFormNode($node);
                    } else {
                        $tab[] = $this->parseFormNode($node);
                    }
                    $result['tabs'][] = $tab ;
                    break;
                case "subtab":
                    $subtab = array();
                    $subtab[] = $node->attributes()->id;
                    $subtab[] = $node->attributes()->description;
                    $subtab[] = $this->parseFormNode($node);
                    $result[] = $subtab;
                    break;
                case "field":
                    // field type, containing attributes
                    $result[] = $this->parseFormNode($node);
                    break;
                case "help":
                case "hint":
                case "label":
                    // translate text items if gettext is enabled
                    if (function_exists("gettext")) {
                        $result[$key] = gettext((string)$node);
                    } else {
                        $result[$key] = (string)$node;
                    }

                    break;
                default:
                    // default behavior, copy in value as key/value data
                    $result[$key] = (string)$node;
                    break;
            }
        }

        return $result;
    }

    /**
     * @param $formname
     * @return array
     * @throws \Exception
     */
    public function getForm($formname)
    {
        $class_info = new \ReflectionClass($this);
        $filename = dirname($class_info->getFileName()) . "/forms/".$formname.".xml" ;
        if (!file_exists($filename)) {
            throw new \Exception('form xml '.$filename.' missing') ;
        }
        $formXml = simplexml_load_file($filename);
        if ($formXml === false) {
            throw new \Exception('form xml '.$filename.' not valid') ;
        }

        return $this->parseFormNode($formXml);
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
     * @throws \Exception
     */
    public function beforeExecuteRoute($dispatcher)
    {
        // only handle input validation on first request.
        if (!$dispatcher->wasForwarded()) {
            // Authentication
            // - use authentication of legacy OPNsense.
            if ($this->session->has("Username") == false) {
                $this->response->redirect("/", true);
            }

            // Authorization using legacy acl structure
            $acl = new ACL();
            if (!$acl->isPageAccessible($this->session->get("Username"), $_SERVER['REQUEST_URI'])) {
                $this->response->redirect("/", true);
            }


            // check for valid csrf on post requests
            if ($this->request->isPost() && !$this->security->checkToken()) {
                // post without csrf, exit.
                return false;
            }

            // REST type calls should be implemented by inheriting ApiControllerBase.
            // because we don't check for csrf on these methods, we want to make sure these aren't used.
            if ($this->request->isHead() ||
                $this->request->isPut() ||
                $this->request->isDelete() ||
                $this->request->isPatch() ||
                $this->request->isOptions()) {
                throw new \Exception('request type not supported');
            }
        }

        // include csrf for volt view rendering.
        $this->view->setVars([
            'csrf_tokenKey' => $this->security->getTokenKey(),
            'csrf_token' => $this->security->getToken()
        ]);

        // set translator
        $this->view->setVar('lang', $this->getTranslator());

        // link menu system to view, append /ui in uri because of rewrite
        $menu = new Menu\MenuSystem();

        // add interfaces to "Interfaces" menu tab... kind of a hack, may need some improvement.
        $cnf = Config::getInstance();
        $ordid = 0;
        foreach ($cnf->object()->interfaces->children() as $key => $node) {
            $menu->appendItem("Interfaces", $key, array("url"=>"/interfaces.php?if=".$key,"order"=>($ordid++),
                "visiblename"=>$node->descr?$node->descr:strtoupper($key)));
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
