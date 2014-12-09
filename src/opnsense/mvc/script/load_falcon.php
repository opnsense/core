<?php
/**
 * User: ad
 * Date: 08-12-14
 * Time: 20:23
 */
use Phalcon\DI\FactoryDefault;
use Phalcon\Loader;

$di = new FactoryDefault();
$phalcon_config = include_once(__DIR__."/../app/config/config.php");

$loader = new Loader();
$loader->registerDirs(
    array(
        $phalcon_config->application->controllersDir,
        $phalcon_config->application->modelsDir
    )
)->register();

$di->set('config',$phalcon_config);

unset($phalcon_config);
