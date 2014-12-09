<?php
/**
 * User: ad
 * Date: 08-12-14
 * Time: 20:23
 */
use Phalcon\DI\FactoryDefault;
use Phalcon\Loader;

$di = new FactoryDefault();
$config = include_once(__DIR__."/../app/config/config.php");

$loader = new Loader();
$loader->registerDirs(
    array(
        $config->application->controllersDir,
        $config->application->modelsDir
    )
)->register();

$di->set('config',$config);
