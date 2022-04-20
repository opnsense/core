<?php

namespace OPNsense\Phalcon\Filter;

use Phalcon\Filter\Filter as PhalconFilter;
use Phalcon\Filter as PhalconFilter4;

if (class_exists("Phalcon\Filter\Filter", false)) {
    class FilterWrapper extends PhalconFilter
    {
    }
} else {
    class FilterWrapper extends PhalconFilter4
    {
    }
}

class Filter extends FilterWrapper
{
}
