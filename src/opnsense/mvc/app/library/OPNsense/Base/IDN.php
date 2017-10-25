<?php

/**
 *    Copyright (C) 2017 Smart-Soft
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

namespace OPNsense\Base;

include_once('/usr/local/opnsense/contrib/simplepie/idn/idna_convert.class.php');

/**
 * Class IDN contains function for decode and encode internationalize domains
 * @package OPNsense\Base
 */
class IDN
{
    /**
     * Encode a given UTF-8 domain name
     * @param    string   Domain name (UTF-8 or UCS-4)
     * @return   string   Encoded Domain name (ACE string)
     */
    public static function encode($domains)
    {
        $IDN = new \idna_convert();
        $result = [];
        foreach (explode(",", $domains) as $domain)
            if ($domain != "")
                $result[] = ($domain[0] == "." ? "." : "") . $IDN->encode($domain);
        return implode(",", $result);
    }

    /**
     * Decode a given ACE domain name
     * @param    string   Domain name (ACE string)
     * @return   string   Decoded Domain name (UTF-8 or UCS-4)
     */
    public static function decode($domains)
    {
        $IDN = new \idna_convert();
        $result = [];
        foreach (explode(",", $domains) as $domain)
            if ($domain != "")
                $result[] = $IDN->decode($domain);
        return implode(",", $result);
    }
}