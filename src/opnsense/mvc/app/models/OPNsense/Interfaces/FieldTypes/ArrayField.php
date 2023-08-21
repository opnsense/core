<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
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
 */

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField as BaseArrayField;
use OPNsense\Core\Config;

class ArrayField extends BaseArrayField
{
    public function generateUUID()
    {
        return self::encodeUUID($this->nextIfName());
    }

    private function nextIfName()
    {
        $prefix = 'opt';
        $config = Config::getInstance()->toArray();

        for ($i = 1; $i <= count($config['interfaces']); ++$i) {
            if (empty($config['interfaces']["{$prefix}{$i}"])) {
                break;
            }
        }

        return sprintf('%s%d', $prefix, $i);
    }

    /**
     * create a new UUID v4 number from the given string.
     *
     * @param string $data source data for creating UUID, the maximum length is 14 characters
     *
     * @return string uuid v4 number
     */
    public static function encodeUUID($data)
    {
        // thiese positions will be encoded without the ability to decode back
        $data = substr_replace($data, ' ', 6, 0);
        $data = substr_replace($data, ' ', 8, 0);

        $data = str_pad(trim($data), 16, ' ');

        assert(16 == strlen($data));

        $data[6] = chr(ord($data[6]) & 0x0F | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * extract data from UUID v4 number.
     *
     * @param string $uuid encoded data in UUID v4 number representation
     *
     * @return string decoded data
     */
    public static function decodeUUID($uuid)
    {
        if (empty($uuid)) {
            return '';
        }

        $uuid = preg_replace('/[^a-zA-Z0-9]+/', '', $uuid); // remove hyphens
        $data = hex2bin($uuid);

        $data = substr_replace($data, '', 6, 1);
        $data = substr_replace($data, '', 7, 1);

        return preg_replace('/[^a-zA-Z0-9]+/', '', $data); // remove @, _, ' '
    }
}
