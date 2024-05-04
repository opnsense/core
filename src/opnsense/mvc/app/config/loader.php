<?php

require_once (__DIR__ . '/../../app/library/OPNsense/Autoload/Loader.php');
use OPNsense\Autoload\Loader;
$loader_paths = [
    $config->application->controllersDir,
    $config->application->modelsDir,
    $config->application->libraryDir
];
(new Loader($loader_paths))->register();
