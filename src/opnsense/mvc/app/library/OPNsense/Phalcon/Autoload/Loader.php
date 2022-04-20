<?php

namespace OPNsense\Phalcon\Autoload;

use Phalcon\Autoload\Loader as PhalconLoader;
use Phalcon\Loader as PhalconLoader4;

if (class_exists("Phalcon\Autoload\Loader", false)) {
    class LoaderWrapper extends PhalconLoader
    {
    }
} else {
    class LoaderWrapper extends PhalconLoader4
    {
    }
}

class Loader extends LoaderWrapper
{
    public function __call($fName, $args)
    {
        if (method_exists($this, $fName)) {
            return $this->fName(...$args);
        } elseif ($fName == 'setDirectories') {
            /* Phalcon5 renamed registerDirs to setDirectories */
            return $this->registerDirs(...$args);
        }
    }
}
