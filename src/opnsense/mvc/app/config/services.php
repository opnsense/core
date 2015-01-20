<?php

use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Session\Adapter\Files as SessionAdapter;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault();

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->set('url', function () use ($config) {
    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
}, true);

/**
 * Setting up the view component
 */
$di->set('view', function () use ($config) {

    $view = new View();

    $view->setViewsDir($config->application->viewsDir);

    $view->registerEngines(array(
        '.volt' => function ($view, $di) use ($config) {

            $volt = new VoltEngine($view, $di);

            $volt->setOptions(array(
                'compiledPath' => $config->application->cacheDir,
                'compiledSeparator' => '_'
            ));

            return $volt;
        },
        '.phtml' => 'Phalcon\Mvc\View\Engine\Php'
    ));

    return $view;
}, true);

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->set('db', function () use ($config) {
    return new DbAdapter(array(
        'host' => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname' => $config->database->dbname
    ));
});

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->set('modelsMetadata', function () {
    return new MetaDataAdapter();
});

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
    $router->setDefaultNamespace('OPNsense\Core');

    $router->add('/', array(
        "controller" => 'index',
        "action" => 'index'
    ));


    // probe registered modules and create a namespace map
    // for example, OPNsense\Core will be mapped at http(s):\\host\core\..
    // module names should be unique in the application, unless you want to overwrite functionality (check glob's sorting).
    //
    // if the glob for probing the directories turns out to be too slow, we should consider some kind of caching here
    //
    $registered_modules = array();
    $controller_dir = __DIR__."/../controllers/";
    foreach (glob($controller_dir."*", GLOB_ONLYDIR) as $namespace_base) {
        foreach (glob($namespace_base."/*", GLOB_ONLYDIR) as $module_name) {
            if (strpos($module_name, 'OPNsense/Base') === false) {
                $namespace_name = str_replace('/', '\\', str_replace($controller_dir, '', $module_name));
                $registered_modules[strtolower(basename($module_name))]= $namespace_name;
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
