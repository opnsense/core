<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\Email as PhalconEmail;
use Phalcon\Validation\Validator\Email as PhalconEmail4;

if (class_exists("Phalcon\Filter\Validation\Validator\Email", false)) {
    class EmailWrapper extends PhalconEmail
    {
    }
} else {
    class EmailWrapper extends PhalconEmail4
    {
    }
}

class Email extends EmailWrapper
{
}
