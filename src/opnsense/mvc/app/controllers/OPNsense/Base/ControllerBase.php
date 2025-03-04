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

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Mvc\Dispatcher;

/**
 * Class ControllerBase implements core controller for OPNsense framework
 * @package OPNsense\Base
 */
class ControllerBase extends ControllerRoot
{
    public View $view;

    /**
     * @var array Content-Security-Policy extensions, when set they will be merged with the defaults
     */
    protected array $content_security_policy = [];

    private array $volt_functions = [
        'theme_file_or_default' => 'view_fetch_themed_filename',
        'file_exists' => 'view_file_exists',
        'cache_safe' => 'view_cache_safe'
    ];

    /**
     *  @return array list of javascript files to be included in default.volt
     */
    protected function templateJSIncludes()
    {
        return [
          // legacy browser functions
          '/ui/js/polyfills.js',
          // JQuery
          '/ui/js/jquery-3.5.1.min.js',
          // JQuery Tokenize2 (https://zellerda.github.io/Tokenize2/)
          '/ui/js/tokenize2.js',
          // Bootgrid (grid system from http://www.jquery-bootgrid.com/ )
          '/ui/js/jquery.bootgrid.js',
          // Bootstrap type ahead
          '/ui/js/bootstrap3-typeahead.min.js',
          // OPNsense standard toolkit
          '/ui/js/opnsense.js',
          '/ui/js/opnsense_theme.js',
          '/ui/js/opnsense_ui.js',
          '/ui/js/opnsense_bootgrid_plugin.js',
          '/ui/js/opnsense_status.js',
          // bootstrap script
          '/ui/js/bootstrap.min.js',
          '/ui/js/bootstrap-select.min.js',
          // bootstrap dialog
          '/ui/js/bootstrap-dialog.min.js'
        ];
    }

    /**
     *  @return array list of css files to be included in default.volt (used themed versions if available)
     */
    protected function templateCSSIncludes()
    {
        return [
            // default theme
            'css/main.css',
            // Stylesheet for fancy select/dropdown
            '/css/bootstrap-select.css',
            // bootstrap dialog
            '/css/bootstrap-dialog.css',
            // Font awesome
            '/ui/assets/fontawesome/css/all.min.css',
            '/ui/assets/fontawesome/css/v4-shims.min.css',
            // JQuery Tokenize2 (https://zellerda.github.io/Tokenize2/)
            '/css/tokenize2.css',
            // Bootgrid (grid system from http://www.jquery-bootgrid.com/ )
            '/css/jquery.bootgrid.css'
        ];
    }

    /**
     * Construct a view to render Volt templates
     */
    public function __construct()
    {
        $volt_functions = $this->volt_functions;
        $appcfg = new AppConfig();
        $this->view = new View();
        $viewDirs = [];
        foreach ((array)$appcfg->application->viewsDir as $viewDir) {
            $viewDirs[] = $viewDir;
        }
        $this->view->setViewsDir($viewDirs);
        $this->view->setDI(new FactoryDefault());
        $this->view->registerEngines([
            '.volt' => function ($view) use ($appcfg, $volt_functions) {
                $volt = new VoltEngine($view);
                $volt->setOptions([
                    'path' => $appcfg->application->cacheDir,
                    'separator' => '_'
                ]);
                foreach ($volt_functions as $func_name => $function) {
                    $volt->getCompiler()->addFunction($func_name, $function);
                }
                $volt->getCompiler()->addFilter('safe', 'view_html_safe');
                return $volt;
            }]);
    }

    /**
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function afterExecuteRoute(Dispatcher $dispatcher)
    {
        $this->view->start();
        $this->view->processRender('', '');
        $this->view->finish();

        $this->response->setContent($this->view->getContent());
    }

    /**
     * convert xml form definition to simple data structure to use in our Volt templates
     *
     * @param $xmlNode
     * @return array
     */
    private function parseFormNode($xmlNode)
    {
        $result = [];
        foreach ($xmlNode as $key => $node) {
            switch ($key) {
                case "tab":
                    if (!array_key_exists("tabs", $result)) {
                        $result['tabs'] = [];
                    }
                    $tab = [];
                    $tab[] = (string)$node->attributes()->id;
                    $tab[] = gettext((string)$node->attributes()->description);
                    if (isset($node->subtab)) {
                        $tab["subtabs"] = $this->parseFormNode($node);
                    } else {
                        $tab[] = $this->parseFormNode($node);
                    }
                    $result['tabs'][] = $tab;
                    break;
                case "subtab":
                    $subtab = [];
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
     * fetch form xml
     * @return SimpleXMLElement
     */
    private function getFormXML($formname)
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
        return $formXml;
    }

    /**
     * parse an xml type form
     * @param $formname
     * @return array
     * @throws \Exception
     */
    public function getForm($formname)
    {
        return $this->parseFormNode($this->getFormXML($formname));
    }

    /**
     * Extract grid fields from form definition
     * @return array
     */
    public function getFormGrid($formname, $grid_id = null, $edit_alert_id = null)
    {
        /* collect all fields, sort by sequence */
        $all_data = [];
        $idx = 0;
        foreach ($this->getFormXML($formname) as $rootkey => $rootnode) {
            if ($rootkey == 'field' && !empty((string)$rootnode->id)) {
                $record = [
                    'column-id' => '',
                    'label' => '',
                    'visible' => 'true',
                    'sortable' => 'true',
                    'identifier' => 'false',
                    'type' => 'string' /* XXX: convert to type + formatter using source type? */
                ];
                foreach ($rootnode as $key => $item) {
                    switch ($key) {
                        case 'label':
                            $record['label'] = gettext((string)$item);
                            break;
                        case 'id':
                            $tmp = explode('.', (string)$item);
                            $record['column-id'] = end($tmp);
                            break;
                    }
                }
                /* iterate field->grid_view items */
                $this_sequence = '9999999';
                if (isset($rootnode->grid_view)) {
                    foreach ($rootnode->grid_view->children() as $key => $item) {
                        if ($key == 'ignore' && $item != 'false') {
                            /* ignore field as requested */
                            continue 2;
                        } elseif ($key == 'sequence') {
                            $this_sequence = (string)$item;
                        } else {
                            $record[$key] = (string)$item;
                        }
                    }
                }
                $all_data[sprintf("%010d.%03d", $this_sequence, $idx++)] = $record;
            }
        }
        /* prepend identifier */
        $all_data[sprintf("%010d.%03d", 0, 0)] = [
            'column-id' => 'uuid',
            'label' => gettext('ID'),
            'type' => 'string',
            'identifier' => 'true',
            'visible' => 'false'
        ];
        ksort($all_data);
        $basename = $grid_id ?? $formname;
        return [
            'table_id' => $basename,
            'edit_dialog_id' => 'dialog_' . $basename,
            'edit_alert_id' => $edit_alert_id == null ? 'change_message_base_form' : $edit_alert_id,
            'fields' => array_values($all_data)
        ];
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
    public function beforeExecuteRoute(Dispatcher $dispatcher)
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
        $this->view->setVar('langcode', str_replace('_', '-', $this->langcode));

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
        $firmware_product_fn = __DIR__ . '/../../../../../version/core';
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
            $output = [];
            foreach ($this->view->menuBreadcrumbs as $crumb) {
                $output[] = gettext($crumb['name']);
            }
            $this->view->title = join(': ', $output);
            $output = [];
            foreach (array_reverse($this->view->menuBreadcrumbs) as $crumb) {
                $output[] = gettext($crumb['name']);
            }
            $this->view->headTitle = join(' | ', $output);
        }

        // append ACL object to view
        $this->view->acl = new \OPNsense\Core\ACL();

        // Javascript includes
        $this->view->javascript_files = $this->templateJSIncludes();
        $this->view->css_files = $this->templateCSSIncludes();

        // set security policies
        $policies = array(
            "default-src" => "'self'",
            "img-src" => "'self' data: blob:",
            "script-src" => "'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src" => "'self' 'unsafe-inline' 'unsafe-eval'");
        foreach ($this->content_security_policy as $policy_name => $policy_content) {
            if (empty($policies[$policy_name])) {
                $policies[$policy_name] = "";
            }
            $policies[$policy_name] .= " {$policy_content}";
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
