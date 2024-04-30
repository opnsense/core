<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Core;

class AppConfig
{
    /**
     * @var application config data
     */
    private static $data = [];

    /**
     * @var self::$data as StdClass
     */
    private static $obj = null;

    /**
     * construct new application config object, keep $data when not offered.
     */
    public function __construct($new_data = null)
    {
        if ($new_data != null) {
            self::$data = $new_data;
            // simple conversion from array to StdClass container, current representation of self::$data.
            self::$obj = json_decode(json_encode(self::$data));
        }
    }

    /**
     * @param string $name
     * @return StdClass or simple type
     */
    public function __get($name)
    {
        if (isset(self::$obj->$name)) {
            return self::$obj->$name;
        }
        return null;
    }

    /**
     * @param array $cnf configuration data to merge into the app config container
     */
    public function merge($cnf)
    {
        self::$data = array_merge_recursive(self::$data, $cnf);
        // simple conversion from array to StdClass container, current representation of self::$data.
        self::$obj = json_decode(json_encode(self::$data));
    }

    /**
     * update a property inside the container
     * @param string $path in dot notation a.b.c
     * @param mixed $value
     * @return bool true when found and updated
     */
    public function update($path, $value)
    {
        $tmp = &self::$data;
        foreach (explode('.', $path) as $key) {
            if (isset($tmp[$key])) {
                $tmp = &$tmp[$key];
            } else {
                return false;
            }
        }
        $tmp = $value;
        self::$obj = json_decode(json_encode(self::$data));
        return true;
    }
}
