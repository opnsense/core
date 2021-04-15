<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

use OPNsense\Core\Config;

/**
 * Class ControllerBase implements core controller for OPNsense framework
 * @package OPNsense\Base
 */
class ControllerBase extends ControllerRoot
{
    /**
     * @var array Content-Security-Policy extensions, when set they will be merged with the defaults
     */
    protected $content_security_policy = array();

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
                    $tab[] = gettext((string)$node->attributes()->description);
                    if (isset($node->subtab)) {
                        $tab["subtabs"] = $this->parseFormNode($node);
                    } else {
                        $tab[] = $this->parseFormNode($node);
                    }
                    $result['tabs'][] = $tab;
                    break;
                case "subtab":
                    $subtab = array();
                    $subtab[] = $node->attributes()->id;
                    $subtab[] = gettext((string)$node->attributes()->description);
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
                    $result[$key] = gettext((string)$node);
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
     * parse an xml type form
     * @param $formname
     * @return array
     * @throws \Exception
     */
    public function getForm($formname)
    {
        $class_info = new \ReflectionClass($this);
        $filename = dirname($class_info->getFileName()) . "/forms/" . $formname . ".xml";
        if (!file_exists($filename)) {
            throw new \Exception('form xml ' . $filename . ' missing');
        }
        $formXml = simplexml_load_file($filename);
        if ($formXml === false) {
            throw new \Exception('form xml ' . $filename . ' not valid');
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
        $this->view->session = $this->session;
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
            if (!$this->doAuth()) {
                return false;
            }

            // check for valid csrf on post requests
            if ($this->request->isPost() && !$this->security->checkToken(null, null, false)) {
                // post without csrf, exit.
                $this->response->setStatusCode(403, "Forbidden");
                return false;
            }

            // REST type calls should be implemented by inheriting ApiControllerBase.
            // because we don't check for csrf on these methods, we want to make sure these aren't used.
            if (
                $this->request->isHead() ||
                $this->request->isPut() ||
                $this->request->isDelete() ||
                $this->request->isPatch() ||
                $this->request->isOptions()
            ) {
                throw new \Exception('request type not supported');
            }
        }

        // link username on successful login
        $this->logged_in_user = $this->session->get("Username");

        // include csrf for volt view rendering.
        $csrf_token = $this->session->get('$PHALCON/CSRF$');
        $csrf_tokenKey = $this->session->get('$PHALCON/CSRF/KEY$');
        if (empty($csrf_token) || empty($csrf_tokenKey)) {
            // when there's no token in our session, request a new one
            $csrf_token = $this->security->getToken();
            $csrf_tokenKey = $this->security->getTokenKey();
        }
        $this->view->setVars(['csrf_tokenKey' => $csrf_tokenKey, 'csrf_token' => $csrf_token]);

        // link menu system to view
        $menu = new Menu\MenuSystem();

        // add interfaces to "Interfaces" menu tab... kind of a hack, may need some improvement.
        $cnf = Config::getInstance();

        $this->view->setVar('lang', $this->translator);
        $rewrite_uri = explode("?", $_SERVER["REQUEST_URI"])[0];
        $this->view->menuSystem = $menu->getItems($rewrite_uri);
        /* XXX generating breadcrumbs requires getItems() call */
        $this->view->menuBreadcrumbs = $menu->getBreadcrumbs();

        // set theme in ui_theme template var, let template handle its defaults (if there is no theme).
        if (
            $cnf->object()->theme->count() > 0 && !empty($cnf->object()->theme) &&
            (
                is_dir('/usr/local/opnsense/www/themes/' . (string)$cnf->object()->theme) ||
                !is_dir('/usr/local/opnsense/www/themes')
            )
        ) {
            $this->view->ui_theme = $cnf->object()->theme;
        }

        // parse product properties, use template (.in) when not found
        $firmware_product_fn =  __DIR__ . '/../../../../../version/core';
        $firmware_product_fn = !is_file($firmware_product_fn) ? $firmware_product_fn . ".in" : $firmware_product_fn;
        $product_vars = json_decode(file_get_contents($firmware_product_fn), true);
        foreach ($product_vars as $product_key => $product_var) {
            $this->view->$product_key = $product_var;
        }

        // info about the current user and box
        $this->view->session_username = !empty($_SESSION['Username']) ? $_SESSION['Username'] : '(unknown)';
        $this->view->system_hostname = $cnf->object()->system->hostname;
        $this->view->system_domain = $cnf->object()->system->domain;

        if (isset($this->view->menuBreadcrumbs[0]['name'])) {
            $output = array();
            foreach ($this->view->menuBreadcrumbs as $crumb) {
                $output[] = gettext($crumb['name']);
            }
            $this->view->title = join(': ', $output);
            $output = array();
            foreach (array_reverse($this->view->menuBreadcrumbs) as $crumb) {
                $output[] = gettext($crumb['name']);
            }
            $this->view->headTitle = join(' | ', $output);
        }

        // append ACL object to view
        $this->view->acl = new \OPNsense\Core\ACL();

        // set security policies
        $policies = array(
            "default-src" => "'self'",
            "img-src" => "'self'",
            "script-src" => "'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src" => "'self' 'unsafe-inline' 'unsafe-eval'");
        foreach ($this->content_security_policy as $policy_name => $policy_content) {
            if (empty($policies[$policy_name])) {
                $policies[$policy_name] = "";
            }
            $policies[$policy_name] .=  " {$policy_content}";
        }
        $csp = "";
        foreach ($policies as $policy_name => $policy) {
            $csp .= $policy_name . " " . $policy . " ;";
        }
        $this->response->setHeader('Content-Security-Policy', $csp);
        $this->response->setHeader('X-Frame-Options', "SAMEORIGIN");
        $this->response->setHeader('X-Content-Type-Options', "nosniff");
        $this->response->setHeader('X-XSS-Protection', "1; mode=block");
        $this->response->setHeader('Referrer-Policy', "same-origin");
    }
}
