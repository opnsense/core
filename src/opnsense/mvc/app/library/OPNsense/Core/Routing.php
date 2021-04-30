<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Core;

use Phalcon\Mvc\Router;

/**
 * Class Routing handles OPNsense ui/api routing
 * @package OPNsense\Core
 */
class Routing
{
    /**
     * @var Router Phalcon router
     */
    private $router;

    /**
     * @var string controller root directory
     */
    private $rootDir;

    /**
     * @var string one of ui, api
     */
    private $type = "ui";

    /**
     * @var string path suffix (e.g. Api or empty)
     */
    private $suffix = "";

    /**
     * Routing constructor.
     * @param string $root root controller directory
     * @param string $type routing type ui,api
     * @throws \Exception
     */
    public function __construct($root, $type = "ui")
    {
        $this->rootDir = $root;
        $this->router = new Router(false);
        // defaults
        $this->router->setDefaultController('index');
        $this->router->setDefaultAction('index');
        $this->router->add('/', array("controller" => 'index',"action" => 'index'));

        $this->type = $type;
        switch ($this->type) {
            case "ui":
                $this->router->setDefaultNamespace('OPNsense\Core');
                break;
            case "api":
                $this->router->setDefaultNamespace('OPNsense\Core\Api');
                $this->suffix = "/Api";
                break;
            default:
                throw new \Exception("Invalid type " . $type);
        }

        $this->setup();
    }

    /**
     * setup routing
     */
    private function setup()
    {
        //
        // probe registered API modules and create a namespace map
        // for example, OPNsense\Core\Api will be mapped at http(s):\\host\core\..
        //
        // if the glob for probing the directories turns out to be too slow,
        // we should consider some kind of caching here
        //
        $registered_modules = array();
        $rootDirs = is_object($this->rootDir) || is_array($this->rootDir) ? $this->rootDir : array($this->rootDir);
        foreach ($rootDirs as $rootDir) {
            foreach (glob($rootDir . "*", GLOB_ONLYDIR) as $namespace_base) {
                foreach (glob($namespace_base . "/*", GLOB_ONLYDIR) as $module_base) {
                    if (is_dir($module_base . $this->suffix)) {
                        $basename = strtolower(basename($module_base));
                        $api_base = $module_base . $this->suffix;
                        $namespace_name = str_replace('/', '\\', str_replace($rootDir, '', $api_base));
                        if (empty($registered_modules[$basename])) {
                            $registered_modules[$basename] = array();
                        }
                        // always place OPNsense components on top
                        $sortOrder = stristr($module_base, '/OPNsense/') ? "0" : count($registered_modules[$basename]) + 1;
                        $registered_modules[$basename][$sortOrder] = array();
                        $registered_modules[$basename][$sortOrder]['namespace'] = $namespace_name;
                        $registered_modules[$basename][$sortOrder]['path'] = $api_base;
                        ksort($registered_modules[$basename]);
                    }
                }
            }
        }

        // add routing for all controllers, using the following convention:
        // \module\controller\action\params
        // where module is mapped to the corresponding namespace
        foreach ($registered_modules as $module_name => $module_configs) {
            $namespace = array_shift($module_configs)['namespace'];
            $this->router->add("/{$this->type}/" . $module_name, array(
                "namespace" => $namespace,
            ));

            $this->router->add("/{$this->type}/" . $module_name . "/:controller", array(
                "namespace" => $namespace,
                "controller" => 1
            ));

            $this->router->add("/{$this->type}/" . $module_name . "/:controller/:action", array(
                "namespace" => $namespace,
                "controller" => 1,
                "action" => 2
            ));


            $this->router->add("/{$this->type}/" . $module_name . "/:controller/:action/:params", array(
                "namespace" => $namespace,
                "controller" => 1,
                "action" => 2,
                "params" => 3
            ));

            // In case we have overlapping modules, map additional controllers on top.
            // This can normally only happens with 3rd party plugins hooking into standard functionality
            if (count($module_configs) > 0) {
                foreach ($module_configs as $module_config) {
                    foreach (glob($module_config['path'] . "/*.php") as $filename) {
                        // extract controller name and bind static in routing table
                        $controller = strtolower(str_replace('Controller.php', '', basename($filename)));
                        $this->router->add("/{$this->type}/{$module_name}/{$controller}/:action", array(
                            "namespace" => $module_config['namespace'],
                            "controller" => $controller,
                            "action" => 1
                        ));
                        $this->router->add("/{$this->type}/{$module_name}/{$controller}/:action/:params", array(
                            "namespace" => $module_config['namespace'],
                            "controller" => $controller,
                            "action" => 1,
                            "params" => 2
                        ));
                    }
                }
            }
            $this->router->removeExtraSlashes(true);
        }
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }
}
