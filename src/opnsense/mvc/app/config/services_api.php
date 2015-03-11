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

// Service definition for API
use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Session\Adapter\Files as SessionAdapter;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();

$di->set('view', function () use ($config) {
    // return a empty view
    $view = new View();
    return $view;
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->set('url', function () use ($config) {
    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
}, true);

/**
 * Start the session the first time some component request the session service
 */
$di->set('session', function () {
    $session = new SessionAdapter();
    $session->start();

    return $session;
});


$di->set('config', $config);


/**
 * Setup router
 */
$di->set('router', function() {

    $router = new \Phalcon\Mvc\Router(false);

    $router->setDefaultController('index');
    $router->setDefaultAction('index');
    $router->setDefaultNamespace('OPNsense\Sample\Api');

    $router->add('/', array(
        "controller" => 'index',
        "action" => 'index'
    ));

    //
    // probe registered API modules and create a namespace map
    // for example, OPNsense\Core\Api will be mapped at http(s):\\host\core\..
    // module names should be unique in the application, unless you want to
    // overwrite functionality (check glob's sorting).
    //
    // if the glob for probing the directories turns out to be too slow,
    // we should consider some kind of caching here
    //
    $registered_modules = array();
    $controller_dir = __DIR__."/../controllers/";
    foreach (glob($controller_dir."*", GLOB_ONLYDIR) as $namespace_base) {
        foreach (glob($namespace_base."/*", GLOB_ONLYDIR) as $module_base) {
            if (strpos($module_base, 'OPNsense/Base') === false) {
                foreach (glob($module_base."/*", GLOB_ONLYDIR) as $api_base) {
                    $namespace_name = str_replace('/', '\\', str_replace($controller_dir, '', $api_base));
                    $registered_modules[strtolower(basename($module_base))] = $namespace_name;
                }
            }
        }
    }

    // add routing for all controllers, using the following convention:
    // \module\controller\action\params
    // where module is mapped to the corresponding namespace
    foreach ($registered_modules as $module_name => $namespace_name) {
        $router->add("/".$module_name."/", array(
            "namespace" => $namespace_name
        ));

        $router->add("/".$module_name."/:controller/", array(
            "namespace" => $namespace_name,
            "controller" => 1
        ));

        $router->add("/".$module_name."/:controller/:action/", array(
            "namespace" =>  $namespace_name,
            "controller" => 1,
            "action" => 2
        ));


        $router->add("/".$module_name."/:controller/:action/:params", array(
            "namespace" => $namespace_name,
            "controller" => 1,
            "action" => 2,
            "params" => 3
        ));

    }

    $router->handle();

    return $router;

});
