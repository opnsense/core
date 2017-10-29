<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
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
namespace OPNsense\Firewall;

use \OPNsense\Core\Config;

/**
 * Class Util, common static firewall support functions
 * @package OPNsense\Firewall
 */
class Util
{
    /**
     * is provided address an ip address.
     * @param string $network address
     * @return boolean
     */
    public static function isIpAddress($address)
    {
        return !empty(filter_var($address, FILTER_VALIDATE_IP));
    }

    /**
     * is provided network valid
     * @param string $network network
     * @return boolean
     */
    public static function isSubnet($network)
    {
        $tmp = explode('/', $network);
        if (count($tmp) == 2) {
            if (self::isIpAddress($tmp[0]) && abs($tmp[1]) == $tmp[1]) {
                if (strpos($tmp[0], ':') !== false && $tmp[1] <= 128) {
                    // subnet v6
                    return true;
                } elseif ($tmp[1] <= 32) {
                    // subnet v4
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * check if name exists in alias config section
     * @param string $name name
     * @return boolean
     */
    public static function isAlias($name)
    {
        if (!empty($name) && !empty(Config::getInstance()->object()->aliases)) {
            foreach (Config::getInstance()->object()->aliases->children() as $node) {
                if ($node->name == $name) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * check if name exists in alias config section
     * @param string $number port number or range
     * @param boolean $allow_range ranges allowed
     * @return boolean
     */
    public function isPort($number, $allow_range = true)
    {
        $tmp = explode(':', $number);
        foreach ($tmp as $port) {
            if (!getservbyname($port, "tcp") && !getservbyname($port, "udp")
                && filter_var($port, FILTER_VALIDATE_INT, array(
                    "options" => array("min_range"=>1, "max_range"=>65535))) === false
            ) {
                return false;
            }
        }
        if (($allow_range && count($tmp) <=2) || count($tmp) == 1) {
            return true;
        }
        return false;
    }
}
