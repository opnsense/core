<?php

require_once __DIR__ . '/../../../app/config/AppConfig.php';

return new OPNsense\Core\AppConfig([
    'application' => [
        'controllersDir' => __DIR__ . '/../../../app/controllers/',
        'modelsDir' => __DIR__ . '/../../../app/models/',
        'viewsDir' => __DIR__ . '/../../../app/views/',
        'pluginsDir' => __DIR__ . '/../../../app/plugins/',
        'libraryDir' => __DIR__ . '/../../../app/library/',
        'cacheDir' => __DIR__ . '/../../../app/cache/',
        'baseUri' => '/opnsense_gui/',
    ],
    'globals' => [
        'config_path'    => '/conf/',
        'temp_path'      => '/tmp/',
        'debug'          => false,
        'simulate_mode'  => false,
    ],
]);
