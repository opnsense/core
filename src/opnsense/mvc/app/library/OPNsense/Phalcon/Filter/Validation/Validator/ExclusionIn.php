<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\ExclusionIn as PhalconExclusionIn;
use Phalcon\Validation\Validator\ExclusionIn as PhalconExclusionIn4;

if (class_exists("Phalcon\Filter\Validation\Validator\ExclusionIn", false)) {
    class ExclusionInWrapper extends PhalconExclusionIn
    {
    }
} else {
    class ExclusionInWrapper extends PhalconExclusionIn4
    {
    }
}

class ExclusionIn extends ExclusionInWrapper
{
}
