<?php

namespace OPNsense\Phalcon\Filter\Validation\Validator;

use Phalcon\Filter\Validation\Validator\Numericality as PhalconNumericality;
use Phalcon\Validation\Validator\Numericality as PhalconNumericality4;

if (class_exists("Phalcon\Filter\Validation\Validator\Numericality", false)) {
    class NumericalityWrapper extends PhalconNumericality
    {
    }
} else {
    class NumericalityWrapper extends PhalconNumericality4
    {
    }
}

class Numericality extends NumericalityWrapper
{
}
