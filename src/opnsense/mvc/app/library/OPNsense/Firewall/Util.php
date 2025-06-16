<?php

/*
 * Copyright (C) 2017-2023 Deciso B.V.
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

namespace OPNsense\Firewall;

use OPNsense\Core\Config;
use OPNsense\Firewall\Alias;

/**
 * Class Util, common static firewall support functions
 * @package OPNsense\Firewall
 */
class Util
{
    /**
     * @var null|Alias reference to alias object
     */
    private static $aliasObject = null;

    /**
     * @var null|array cached alias descriptions
     */
    private static $aliasDescriptions = [];

    /**
     * @var array cached getservbyname results
     */
    private static $servbynames = [];

    /**
     * is provided address an ip address.
     * @param string $address network address
     * @return boolean
     */
    public static function isIpAddress($address)
    {
        return !empty(filter_var($address, FILTER_VALIDATE_IP));
    }

    public static function isIpv4Address($address)
    {
        return !empty(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }

    public static function isIpv6Address($address)
    {
        return !empty(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6));
    }

    /**
     * is provided address an link-locak IPv6 address.
     * @param string $address network address
     * @return boolean
     */
    public static function isLinkLocal($address)
    {
        return !!preg_match('/^fe[89ab][0-9a-f]:/i', $address);
    }

    /**
     * is provided address a mac address.
     * @param string $address network address
     * @return boolean
     */
    public static function isMACAddress($address)
    {
        return !empty(filter_var($address, FILTER_VALIDATE_MAC));
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
            if (self::isIpAddress($tmp[0]) && ctype_digit($tmp[1]) && abs($tmp[1]) == $tmp[1]) {
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
     * is provided network strict (host bits not set)
     * @param string $network network
     * @return boolean
     */
    public static function isSubnetStrict($network)
    {
        if (self::isSubnet($network)) {
            list($net, $mask) = explode('/', $network);
            $ip_net = inet_pton($net);
            $bits = (strpos($net, ":") !== false && $mask <= 128) ? 128 : 32;

            $ip_mask = "";
            $significant_bits = $mask;
            for ($i = 0; $i < $bits / 8; $i++) {
                if ($significant_bits >= 8) {
                    $ip_mask .= chr(0xFF);
                    $significant_bits -= 8;
                } else {
                    $ip_mask .= chr(~(0xFF >> $significant_bits));
                    $significant_bits = 0;
                }
            }

            return $ip_net == ($ip_net & $ip_mask);
        }

        return false;
    }

    /**
     * is provided network a valid wildcard (https://en.wikipedia.org/wiki/Wildcard_mask)
     * @param string $network network
     * @return boolean
     */
    public static function isWildcard($network)
    {
        $tmp = explode('/', $network);
        if (count($tmp) == 2) {
            if (self::isIpAddress($tmp[0]) && self::isIpAddress($tmp[1])) {
                if (strpos($tmp[0], ':') !== false && strpos($tmp[1], ':') !== false) {
                    return true;
                } elseif (strpos($tmp[0], ':') === false && strpos($tmp[1], ':') === false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * use provided alias object instead of creating one. When modifying multiple aliases referencing each other
     * we need to use the same object for validations.
     * @param Alias $alias object to link
     */
    public static function attachAliasObject($alias)
    {
        self::$aliasObject = $alias;
        if ($alias != null) {
            $alias->flushCache();
        }
    }

    /**
     * fetch alias by name from model
     * @param string $name name
     * @return Alias|null
     */
    private static function getAliasByName($name)
    {
        if (self::$aliasObject == null) {
            // Cache the alias object to avoid object creation overhead.
            self::$aliasObject = new Alias(true);
            self::$aliasObject->flushCache();
        }
        if (!empty($name)) {
            return self::$aliasObject->getByName($name);
        }
        return null;
    }

    /**
     * check if name exists in alias config section
     * @param string $name name
     * @param boolean $valid check if the alias can safely be used
     * @return boolean
     * @throws \OPNsense\Base\ModelException
     */
    public static function isAlias($name, $valid = false)
    {
        $alias = self::getAliasByName($name);
        if ($alias != null) {
            if ($valid) {
                // check validity for port type aliases
                if ((string)$alias->type == 'port' && empty((string)$alias->content)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * @return boolean true when alias exists and is a port type
     */
    public static function isPortAlias($name)
    {

        $alias = self::getAliasByName($name);
        if ($alias != null) {
            // check validity for port type aliases
            if ((string)$alias->type == 'port' && !empty((string)$alias->content)) {
                return true;
            }
        }
        return false;
    }

    /**
     * return alias descriptions
     * @param string $name name
     * @return string
     */
    public static function aliasDescription($name)
    {
        if (empty(self::$aliasDescriptions)) {
            // read all aliases at once, and cache descriptions.
            foreach ((new Alias(true))->aliasIterator() as $alias) {
                if (empty(self::$aliasDescriptions[$alias['name']])) {
                    if (!empty($alias['description'])) {
                        self::$aliasDescriptions[$alias['name']] = '<strong>' . $alias['description'] . '</strong><br/>';
                    } else {
                        self::$aliasDescriptions[$alias['name']] = "";
                    }

                    if (!empty($alias['content'])) {
                        $tmp = array_slice($alias['content'], 0, 10);
                        asort($tmp, SORT_NATURAL | SORT_FLAG_CASE);
                        if (count($alias['content']) > 10) {
                            $tmp[] = '[...]';
                        }
                        self::$aliasDescriptions[$alias['name']] .= implode("<br/>", $tmp);
                    }
                }
            }
        }
        if (!empty(self::$aliasDescriptions[$name])) {
            return self::$aliasDescriptions[$name];
        } else {
            return null;
        }
    }

    /**
     * Fetch port alias contents, other alias types are handled using tables so there usually no need
     * to know the contents within any of the scripts.
     * @param string $name name
     * @param array $aliases aliases already parsed (prevent deadlock)
     * @return array containing all ports or addresses
     * @throws \OPNsense\Base\ModelException when unable to create alias model
     */
    public static function getPortAlias($name, $aliases = array())
    {
        if (self::$aliasObject == null) {
            // Cache the alias object to avoid object creation overhead.
            self::$aliasObject = new Alias(true);
        }
        $result = array();
        foreach (self::$aliasObject->aliasIterator() as $node) {
            if (!empty($name) && (string)$node['name'] == $name && $node['type'] == 'port') {
                $aliases[] = $name;
                foreach ($node['content'] as $address) {
                    if (Util::isAlias($address)) {
                        if (!in_array($address, $aliases)) {
                            foreach (Util::getPortAlias($address, $aliases) as $port) {
                                if (!in_array($port, $result)) {
                                    $result[] = $port;
                                }
                            }
                        }
                    } elseif (!in_array($address, $result)) {
                        $result[] = $address;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * cached version of getservbyname() existence check
     * @param string $service service name
     * @return boolean
     */
    public static function getservbyname($service)
    {
        if (empty(self::$servbynames)) {
            foreach (explode("\n", file_get_contents('/etc/services')) as $line) {
                if (strlen($line) > 1 && $line[0] != '#') {
                    foreach (preg_split('/\s+/', $line) as $idx => $tmp) {
                        if ($tmp[0] == '#') {
                            break;
                        } elseif ($idx != 1) {
                            self::$servbynames[$tmp] = true;
                        }
                    }
                }
            }
        }
        return isset(self::$servbynames[$service]);
    }

    /**
     * check if name exists in alias config section
     * @param string $number port number or range
     * @param boolean $allow_range ranges allowed
     * @return boolean
     */
    public static function isPort($number, $allow_range = true)
    {
        $tmp = $number !== null ? explode(':', $number) : [];
        foreach ($tmp as $port) {
            if (
                (filter_var($port, FILTER_VALIDATE_INT, array(
                  "options" => array("min_range" => 1, "max_range" => 65535))) === false || !ctype_digit($port)) &&
                !self::getservbyname($port)
            ) {
                return false;
            }
        }
        if (($allow_range && count($tmp) <= 2) || count($tmp) == 1) {
            return true;
        }
        return false;
    }

    /**
     * Check if provided string is a valid domain name
     * @param string $domain
     * @return false|int
     */
    public static function isDomain($domain)
    {
        $pattern = '/^(?:(?:[a-z\pL0-9]|[a-z\pL0-9][a-z\pL0-9\-]*[a-z\pL0-9])\.)*(?:[a-z\pL0-9]|[a-z\pL0-9][a-z\pL0-9\-]*[a-z\pL0-9])$/iu';
        $parts = explode(".", $domain);
        if (ctype_digit($parts[0]) && ctype_digit($parts[count($parts) - 1])) {
            // according to rfc1123 2.1
            //   a valid host name can never have the dotted-decimal form #.#.#.#, since at least the highest-level
            //   component label will be alphabetic.
            return false;
        } elseif (preg_match($pattern, $domain)) {
            return true;
        }
        return false;
    }

    /**
     * calculate rule hash value
     * @param array $rule
     * @return string
     */
    public static function calcRuleHash($rule)
    {
        // remove irrelevant fields
        foreach (array('updated', 'created', 'descr') as $key) {
            unset($rule[$key]);
        }
        ksort($rule);
        foreach ($rule as &$value) {
            if (is_array($value)) {
                ksort($value);
            }
        }
        return md5(json_encode($rule));
    }

    private static function isIPv4InCIDR($ip, $cidr)
    {
        list ($subnet, $bits) = explode('/', $cidr);
        if ($bits === null) {
            $bits = 32;
        }
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }

    private static function isIPv6InCIDR($ip, $cidr)
    {
        $inet_to_bits = function ($ip) {
            $split = str_split($ip);
            $bin_ip = '';
            foreach ($split as $char) {
                $bin_ip .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
            }
            return $bin_ip;
        };

        $in_addr = inet_pton($ip);
        $bin_ip = $inet_to_bits($in_addr);

        list ($net, $maskbits) = explode('/', $cidr);
        $net = inet_pton($net);
        $bin_net = $inet_to_bits($net);

        $ip_net_bits = substr($bin_ip, 0, $maskbits);
        $net_bits = substr($bin_net, 0, $maskbits);

        return $ip_net_bits === $net_bits;
    }

    /**
     * returns whether a given IP (v4 or v6) is in a CIDR block
     */
    public static function isIPInCIDR($ip, $cidr)
    {
        if (!self::isIpAddress($ip) || !self::isSubnet($cidr)) {
            return false;
        } elseif (str_contains($ip, ':') && str_contains($cidr, ':')) {
            return self::isIPv6InCIDR($ip, $cidr);
        } elseif (!str_contains($ip, ':') && !str_contains($cidr, ':')) {
            return self::isIPv4InCIDR($ip, $cidr);
        }
        return false;
    }

    /**
     * convert ipv4 cidr to netmask e.g. 24 --> 255.255.255.0
     * @param int $bits ipv4 bits
     * @return string netmask
     */
    public static function CIDRToMask($bits)
    {
        return long2ip(0xFFFFFFFF << (32 - $bits));
    }

    /**
     * Find the smallest possible subnet mask for given IP range
     * @param array $ips (start, end)
     * @param string $family inet6 or inet
     * @return int smallest mask
     */
    public static function smallestCIDR($ips, $family = 'inet')
    {
        if ($family == 'inet6') {
            foreach ($ips as $id => $ip) {
                $ips[$id] = unpack('N*', inet_pton($ip));
            }

            for ($bits = 0; $bits <= 128; $bits += 1) {
                $mask1 = (0xffffffff << max($bits - 96, 0)) & 0xffffffff;
                $mask2 = (0xffffffff << max($bits - 64, 0)) & 0xffffffff;
                $mask3 = (0xffffffff << max($bits - 32, 0)) & 0xffffffff;
                $mask4 = (0xffffffff << $bits) & 0xffffffff;
                $test = [];
                foreach ($ips as $ip) {
                    $test[sprintf('%032b%032b%032b%032b', $ip[1] & $mask1, $ip[2] & $mask2, $ip[3] & $mask3, $ip[4] & $mask4)] = true;
                }
                if (count($test) == 1) {
                    /* one element means CIDR size matches all */
                    break;
                }
            }

            return 128 - $bits;
        } else {
            foreach ($ips as $id => $ip) {
                $ips[$id] = ip2long($ip);
            }

            for ($bits = 0; $bits <= 32; $bits += 1) {
                $mask = (0xffffffff << $bits) & 0xffffffff;
                $test = [];
                foreach ($ips as $ip) {
                    $test[$ip & $mask] = true;
                }
                if (count($test) == 1) {
                    /* one element means CIDR size matches all */
                    break;
                }
            }

            return 32 - $bits;
        }
    }

    /**
     * convert cidr to array containing a start and end address
     * @param array $cidr ipv4/6 cidr range definition
     * @return array|bool address range or false when not a valid cidr
     */
    public static function cidrToRange($cidr)
    {
        if (!self::isSubnet($cidr)) {
            return false;
        }
        list ($ipaddr, $bits) = explode('/', $cidr);
        $inet_ip = inet_pton($ipaddr);
        if (str_contains($ipaddr, ':')) {
            /* IPv6 */
            $size = 128 - (int)$bits;
            $mask = [
                1 => (0xffffffff << max($size - 96, 0)) & 0xffffffff,
                2 => (0xffffffff << max($size - 64, 0)) & 0xffffffff,
                3 => (0xffffffff << max($size - 32, 0)) & 0xffffffff,
                4 => (0xffffffff << $size) & 0xffffffff,
            ];

            $netmask_parts = [];
            for ($pos = 1; $pos <= 4; $pos += 1) {
                $netmask_parts = array_merge($netmask_parts, str_split(sprintf('%08x', $mask[$pos]), 4));
            }
            $inet_mask = inet_pton(implode(':', $netmask_parts));
        } else {
            /* IPv4 */
            $size = 32 - (int)$bits;
            $inet_mask = inet_pton(long2ip((0xffffffff << $size) & 0xffffffff));
        }
        $inet_start = $inet_ip & $inet_mask;
        $inet_end = $inet_ip | ~$inet_mask;
        return [inet_ntop($inet_start), inet_ntop($inet_end)];
    }

    /**
     * @param array $cidr ipv4/6 cidr range definition
     * @return Generator yielding usable addresses in cidr range (max size for ipv6 is 32 bits)
     */
    public static function cidrRangeIterator($cidr)
    {
        $range = self::cidrToRange($cidr);
        if ($range) {
            $bits = explode('/', $cidr)[1];
            $inet_start = inet_pton($range[0]);
            if (str_contains($range[0], ':')) {
                for ($i = 0; $i < pow(2, min(128 - (int)$bits, 32)); ++$i) {
                    yield inet_ntop($inet_start | inet_pton('0000::' . dechex($i)));
                }
            } else {
                /**
                 * For ipv4, skip network and broadcast addresses,
                 * unless size is >= 31 in which case these may be omitted acording to RFC3021
                 */
                $range_start = (int)$bits >= 31 ? 0 : 1;
                $range_stop =  (int)$bits >= 31 ? pow(2, 32 - (int)$bits) : pow(2, 32 - (int)$bits) - 1;
                for ($i = $range_start; $i < $range_stop; ++$i) {
                    yield inet_ntop($inet_start | inet_pton(long2ip($i)));
                }
            }
        }
    }
}
