<?php

namespace OPNsense\Phalcon\Filter\Validation;

use Phalcon\Filter\Validation\Exception as PhalconException;
use Phalcon\Validation\Exception as PhalconException4;

if (class_exists("Phalcon\Filter\Validation\Exception", false)) {
    class ExceptionWrapper extends PhalconException
    {
    }
} else {
    class ExceptionWrapper extends PhalconException4
    {
    }
}

class Exception extends ExceptionWrapper
{
}
