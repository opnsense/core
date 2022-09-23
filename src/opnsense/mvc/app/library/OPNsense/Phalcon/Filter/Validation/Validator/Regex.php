<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\Regex as PhalconRegex;
use Phalcon\Validation\Validator\Regex as PhalconRegex4;

if (class_exists("Phalcon\Filter\Validation\Validator\Regex", false)) {
    class RegexWrapper extends PhalconRegex
    {
    }
} else {
    class RegexWrapper extends PhalconRegex4
    {
    }
}

class Regex extends RegexWrapper
{
}
