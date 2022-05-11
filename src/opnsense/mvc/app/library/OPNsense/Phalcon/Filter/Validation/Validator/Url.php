<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\Url as PhalconUrl;
use Phalcon\Validation\Validator\Url as PhalconUrl4;

if (class_exists("Phalcon\Filter\Validation\Validator\Url", false)) {
    class UrlWrapper extends PhalconUrl
    {
    }
} else {
    class UrlWrapper extends PhalconUrl4
    {
    }
}

class Url extends UrlWrapper
{
}
