<?php

namespace OPNsense\Phalcon\Config;

use Phalcon\Config\Config as PhalconConfig;
use Phalcon\Config as PhalconConfig4;

if (class_exists("Phalcon\Config\Config", false)) {
    class ConfigWrapper extends PhalconConfig
    {
    }
} else {
    class ConfigWrapper extends PhalconConfig4
    {
    }
}

class Config extends ConfigWrapper
{
}
