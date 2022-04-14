<?php

namespace OPNsense\Phalcon\Filter\Validation;

use Phalcon\Filter\Validation\ValidatorInterface as PhalconValidatorInterface;
use Phalcon\Validation\ValidatorInterface as PhalconValidatorInterface4;

if (interface_exists("Phalcon\Filter\Validation\ValidatorInterface", false)) {
    interface ValidatorInterfaceWrapper extends PhalconValidatorInterface {}
} else {
    interface ValidatorInterfaceWrapper extends PhalconValidatorInterface4 {}
}

interface ValidatorInterface extends ValidatorInterfaceWrapper
{
    
}