<?php

namespace OPNsense\Phalcon\Filter;

use Phalcon\Filter\Validation as PhalconValidation;
use Phalcon\Validation as PhalconValidation4;

if (class_exists("Phalcon\Filter\Validation", false)) {
    class ValidationWrapper extends PhalconValidation {}
} else {
    class ValidationWrapper extends PhalconValidation4 {}
}

class Validation extends ValidationWrapper
{
    
}