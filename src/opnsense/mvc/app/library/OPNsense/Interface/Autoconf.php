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

namespace OPNsense\Interface;

class Autoconf
{
    /**
     * @return string fetch ifctl collected data
     */
    private static function get($if, $type, $ipproto = 'inet')
    {
        $fsuffix = $ipproto == 'inet6' ? 'v6' : '';
        foreach (['', ':slaac'] as $isuffix) {
            $file = "/tmp/{$if}{$isuffix}_{$type}{$fsuffix}";
            if (file_exists($file)) {
                return trim(@file_get_contents($file));
            }
        }
        return null;
    }

    /**
     * @return string nameserver when offered
     */
    public static function getNameserver($if, $ipproto = 'inet')
    {
        return self::get($if, 'nameserver', $ipproto);
    }

    /**
     * @return string router when offered
     */
    public static function getRouter($if, $ipproto = 'inet')
    {
        return self::get($if, 'router', $ipproto);
    }

    /**
     * @return string prefix when offered
     */
    public static function getPrefix($if, $ipproto = 'inet')
    {
        return self::get($if, 'prefix', $ipproto);
    }

    /**
     * @return string search domain when offered
     */
    public static function getSearchdomain($if, $ipproto = 'inet')
    {
        return self::get($if, 'searchdomain', $ipproto);
    }

    /**
     * fetch all collected dynamic interface properties
     * @return array
     */
    public static function all($if)
    {

        $result = [];
        foreach (['inet', 'inet6'] as $ipproto) {
            $map = [
                'nameserver' => self::getNameserver($if, $ipproto),
                'prefix' => self::getPrefix($if, $ipproto),
                'router' => self::getRouter($if, $ipproto),
                'searchdomain' => self::getSearchdomain($if, $ipproto)
            ];
            foreach ($map as $key => $content) {
                if ($content !== null) {
                    if (!isset($result[$key])) {
                        $result[$key] = [];
                    }
                    $result[$key][] = $content;
                }
            }
        }
        return $result;
    }
}
