<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Core;

/**
 * Class Singleton provides singleton pattern, be careful with usage.
 * @package Core
 */
abstract class Singleton
{
    /**
     * holder for singleton objects
     * @var static
     */
    private static $instances;

    /**
     * Cannot construct Singleton, use getInstance instead
     */
    final private function __construct()
    {
        // call init on newly created object
        static::init();
    }

    /**
     * Do not clone
     * @throws \Exception
     */
    private function __clone()
    {
        throw new \Exception("An instance of " . get_called_class() . " cannot be cloned.");
    }

    /**
     * get (new) instance
     * @return object
     */
    public static function getInstance()
    {
        $className = get_called_class();

        if (isset(self::$instances[$className]) == false) {
            self::$instances[$className] = new static();
        }
        return self::$instances[$className];
    }


    /**
     * by default there must be an inherited init method
     * so an extended class could simply
     * specify its own init
     */
    protected function init()
    {
    }
}
