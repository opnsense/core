<?php

namespace OPNsense\Phalcon\Di;

use Phalcon\Di\Di as PhalconDi;
use Phalcon\Di as PhalconDi4;

if (class_exists("Phalcon\Di\Di", false)) {
    class DiWrapper extends PhalconDi
    {
    }
} else {
    class DiWrapper extends PhalconDi4
    {
    }
}

class Di extends DiWrapper
{
}
