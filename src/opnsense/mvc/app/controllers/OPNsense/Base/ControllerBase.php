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
            $element = array();
            $nodes = $node->children();
            $nodes_count = $nodes->count();
            $attributes = $node->attributes();

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
                case "help":
                case "hint":
                case "label":
                    $result[$key] = gettext((string)$node);
                    break;
                default:

                // There's primarily two structures we need to build here.
                //
                // The first is very simple, and consists of a single
                // key/value pair which is the name of the XML element, and
                // the value of that XML element.
                //
                // XML:
                // <model>settings</model>
                //
                // This gets translated into a PHP array.
                //
                // PHP Array:
                // ["model"]=> string(8) "settings"
                //
                // The second is a more complex structure which consists of
                // nested elements with attributes (which can include single
                // elements with attributes).
                //
                // XML:
                // <columns>
                //   <select>true</select>
                //   <column id="expression" width="" size="" type="string" visible="true" data-formatter="">
                //     Expression
                //   </column>
                //   <column id="schedule" width="" size="" type="string" visible="true" data-formatter="">
                //     Schedule
                //   </column>
                //   <column id="comment" width="" size="" type="string" visible="true" data-formatter="">
                //     Comment
                //   </column>
                // </columns>
                //
                // This gets translated into a PHP array.
                //
                // PHP Array:
                // ["columns"]=> array(2) {
                //     ["select"]=> string(4) "true"
                //     ["column"]=> array(3) {
                //         [0]=> array(2) {
                //             ["@attributes"]=> array(6) {
                //                 ["id"]=>             string(10) "expression"
                //                 ["width"]=>          string(0) ""
                //                 ["size"]=>           string(0) ""
                //                 ["type"]=>           string(6) "string"
                //                 ["visible"]=>        string(4) "true"
                //                 ["data-formatter"]=> string(0) ""
                //             }
                //             [0]=> string(10) "Expression"
                //         }
                //         [1]-> ...
                //     }
                // }
                //
                // The array named "columns" is equivalent to the XML columns element.
                // The string named "select" is the single XML element (with no children).
                // The array named "column" is the collection of same named XML elements
                // interpreted as an array.
                // Each of the attributes of each XML element is stored as an array named "@attributes".
                // And the XML value is stored as a string in the first index.
                //
                // Here's another nested structure example with attirbutes at multiple levels:
                // XML:
                //  <button type="group" icon="fa fa-floppy-o" label="Save Basic Settings" id="save_actions">
                //      <dropdown action="save" icon="fa fa-floppy-o">Save Only</dropdown>
                //      <dropdown action="save_apply" icon="fa fa-floppy-o">Save and Apply</dropdown>
                //  </button>
                //
                // This converts to a PHP array.
                //
                // PHP Array:
                // ["button"]=> array(2) {
                //     ["dropdown"]=> array(2) {
                //         [0]=> array(2) {
                //             ["@attributes"]=> array(2) {
                //                 ["action"]=>             string(4) "save"
                //                 ["icon"]=>               string(14) "fa fa-floppy-o"
                //           }
                //           [0]=>                          string(9) "Save Only"
                //         }
                //         [1]=> array(2) {
                //             ["@attributes"]=> array(2) {
                //                 ["action"]=>             string(10) "save_apply"
                //                 ["icon"]=>               string(14) "fa fa-floppy-o"
                //             }
                //             [0]=>                        string(14) "Save and Apply"
                //         }
                //     }
                //     ["@attributes"]=> array(4) {
                //         ["type"]=>                       string(5) "group"
                //         ["icon"]=>                       string(14) "fa fa-floppy-o"
                //         ["label"]=>                      string(19) "Save Basic Settings"
                //         ["id"]=>                         string(11) "save_actions"
                //     }
                // }

                // If there are attributes, let's grab them.
                if (count($attributes) !== 0) {
                    foreach ($attributes as $attr_name => $attr_value) {
                    // Create an array with each key named after the attribute name, and store its value accordingly.
                    $my_attributes[$attr_name] = $attr_value->__tostring();
                }
                // Store the attributes to a named index in the element array.
                    $element['@attributes'] = $my_attributes;
                }

                // If there are no children, then we've reached the end of this branch.
                if ($nodes_count === 0) {
                    if ($node->attributes()) {
                        // Using xpath to look at the parent node,
                        // if there are other nodes that have the same key name,
                        // then we need to put this node into an array of the same key name,
                        // mimicing that structure.
                        // It will be one of the indexes in that array.
                        if (count($node->xpath('../' . $key)) > 1) {
                            $element[] = $node->__toString();
                            $result[$key][] = $element;
                        } else {
                            // Since this is the only key with this name then we're not
                            // creating an array, we're just naming the key after it,
                            // and setting the value of that key.
                            $element[] = $node->__toString();
                            $result[$key] = $element;
                        }
                    } else {
                        // We have no attributes to attach,
                        // Using xpath to look at the parent node,
                        // if there are other nodes that have the same key name,
                        // then we need to put this node into an array of the same key name,
                        // mimicing that structure.
                        if (count($node->xpath('../' . $key)) > 1) {
                            $result[$key][] = $node->__toString();
                        } else {
                            // No multiple nodes, and no attributes,
                            // just set the key/value pair.
                            $result[$key] = $node->__toString();
                        }
                    }

                    break;
                }

                // If we have 1 key, then lets set the value,
                // but merge it with $element to include any attributes.
                if (count($node->xpath('../' . $key)) < 2) {
                    $result[$key] = array_merge($this->parseFormNode($node), $element);
                    break;
                }
                // Nothing else to do, so let's recurse,
                // but also merge with $element to include any attributes,
                // adding it as a new index in the array.
                $result[$key][] = array_merge($this->parseFormNode($node), $element);
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
