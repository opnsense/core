<?php

namespace OPNsense\Phalcon\Encryption\Security;

use Phalcon\Encryption\Security\Random as PhalconRandom;
use Phalcon\Security\Random as PhalconRandom4;

if (class_exists("Phalcon\Encryption\Security\Random", false)) {
    class RandomWrapper extends PhalconRandom
    {
    }
} else {
    class RandomWrapper extends PhalconRandom4
    {
    }
}

class Random extends RandomWrapper
{
}
