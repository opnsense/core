<?php

return new \Phalcon\Config(array(
    'database' => array(
        'adapter'     => 'Mysql',
        'host'        => 'localhost',
        'username'    => 'root',
        'password'    => '',
        'dbname'      => 'test',
    ),
    'application' => array(
        'controllersDir' => __DIR__ . '/../../app/controllers/',
        'modelsDir'      => __DIR__ . '/../../app/models/',
        'viewsDir'       => __DIR__ . '/../../app/views/',
        'pluginsDir'     => __DIR__ . '/../../app/plugins/',
        'libraryDir'     => __DIR__ . '/../../app/library/',
        'cacheDir'       => __DIR__ . '/../../app/cache/',
        'baseUri'        => '/opnsense_gui/',
    ),
    'globals' => array(
        'config_path'    => __DIR__ . '/../../test/conf/',
        'temp_path'      => __DIR__ . '/../../test/tmp/',
        'vardb_path'     => __DIR__ . '/../../test/tmp/',
        'debug'          => true,
        'simulate_mode'  => true

    )
));
