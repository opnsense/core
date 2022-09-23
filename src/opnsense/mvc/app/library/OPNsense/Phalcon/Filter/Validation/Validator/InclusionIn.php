<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\InclusionIn as PhalconInclusionIn;
use Phalcon\Validation\Validator\InclusionIn as PhalconInclusionIn4;

if (class_exists("Phalcon\Filter\Validation\Validator\InclusionIn", false)) {
    class InclusionInWrapper extends PhalconInclusionIn
    {
    }
} else {
    class InclusionInWrapper extends PhalconInclusionIn4
    {
    }
}

class InclusionIn extends InclusionInWrapper
{
}
