<?php

namespace OPNsense\Phalcon\Logger;

use Phalcon\Logger\Logger as PhalconLogger;
use Phalcon\Logger as PhalconLogger4;

if (class_exists("Phalcon\Logger\Logger", false)) {
    class LoggerWrapper extends PhalconLogger
    {
    }
} else {
    class LoggerWrapper extends PhalconLogger4
    {
    }
}

class Logger extends LoggerWrapper
{
}
