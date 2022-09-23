<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\PresenceOf as PhalconPresenceOf;
use Phalcon\Validation\Validator\PresenceOf as PhalconPresenceOf4;

if (class_exists("Phalcon\Filter\Validation\Validator\PresenceOf", false)) {
    class PresenceOfWrapper extends PhalconPresenceOf
    {
    }
} else {
    class PresenceOfWrapper extends PhalconPresenceOf4
    {
    }
}

class PresenceOf extends PresenceOfWrapper
{
}
