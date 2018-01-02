<?php

/**
 *    Copyright (C) 2017 Smart-Soft
 *    Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
 *    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
 *    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

use \Net_IPv6;

class Util
{
    /* returns true if $hostname is a valid hostname */
    public static function is_hostname($hostname)
    {
        return is_string($hostname) && preg_match('/^(?:(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])\.)*(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i',
                $hostname);
    }

    /* Convert long int to IP address, truncating to 32-bits. */
    public static function long2ip32($ip)
    {
        return long2ip($ip & 0xFFFFFFFF);
    }

    /* returns true if $ipaddr is a valid dotted IPv4 address */
    public static function is_ipaddrv4($ipaddr)
    {
        return is_string($ipaddr) && !empty($ipaddr) && $ipaddr == self::long2ip32(ip2long($ipaddr));
    }

    /* returns true if $ipaddr is a valid IPv6 address */
    public static function is_ipaddrv6($ipaddr)
    {
        if (!is_string($ipaddr) || empty($ipaddr)) {
            return false;
        }
        if (strstr($ipaddr, "%") && is_linklocal($ipaddr)) {
            $tmpip = explode("%", $ipaddr);
            $ipaddr = $tmpip[0];
        }

        return strpos($ipaddr, ":") !== false && strpos($ipaddr, "/") === false && Net_IPv6::checkIPv6($ipaddr);
    }

    /* returns true if $ipaddr is a valid dotted IPv4 address or a IPv6 */
    public static function is_ipaddr($ipaddr)
    {
        return self::is_ipaddrv4($ipaddr) || self::is_ipaddrv6($ipaddr);
    }

    public static function is_URL($url)
    {
        return preg_match("'\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))'", $url);
    }
}
