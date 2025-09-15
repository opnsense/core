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

use OPNsense\Firewall\Alias;

/**
 * Class Rule basic rule parsing logic
 * @package OPNsense\Firewall
 */
abstract class Rule
{
    protected $rule = [];
    protected $interfaceMapping = [];
    protected $ruleDebugInfo = [];
    protected static $aliasMap = [];

    /* ease the reuse of parsing for pf keywords by using class constants */
    const PARSE_PROTO = 'parseReplaceSimple,tcp/udp:{tcp udp}|a/n:"a/n"|skip:"skip",proto ';

    protected function loadAliasMap()
    {
        if (empty(static::$aliasMap)) {
            static::$aliasMap['any'] = 'any';
            static::$aliasMap['(self)'] = '(self)';
            foreach ($this->interfaceMapping as $ifname => $payload) {
                if (!empty($payload['if'])) {
                    static::$aliasMap[$ifname] = sprintf("(%s:network)", $payload['if']);
                    if (preg_match("/^(wan|lan|opt[0-9]+)$/", $ifname, $matches)) {
                        static::$aliasMap[$ifname . 'ip'] = sprintf("(%s)", $payload['if']);
                    }
                }
            }
            foreach ((new Alias())->aliases->alias->iterateItems() as $alias) {
                if (preg_match("/port/i", (string)$alias->type)) {
                    continue;
                }
                static::$aliasMap[(string)$alias->name] = sprintf('$%s', $alias->name);
            }
        }
    }

    /**
     *  maps address definitions into tags our pf(4) ruleset understands
     */
    protected function mapAddressInfo(&$rule)
    {
        foreach (['from', 'to'] as $tag) {
            /* always strip placeholders (e.g. <alias>) so we validate them as we would for an ordinary alias */
            $content = !empty($rule[$tag]) ? trim($rule[$tag], '<>') : '';
            if (empty($rule[$tag]) && $rule[$tag] != '0') {
                /* any source/destination (omit value) */
                null;
            } elseif (str_starts_with($rule[$tag], '<') && str_ends_with($rule[$tag], '>')) {
                /* literal alias by automated rules, unvalidated, might want to change the callers some day */
                null;
            } elseif (str_starts_with($rule[$tag], '(') && str_ends_with($rule[$tag], ')')) {
                /* literal interface by automated rules, unvalidated, might want to change the callers some day */
                null;
            } elseif (isset(static::$aliasMap[$rule[$tag]])) {
                $is_interface = isset($this->interfaceMapping[$rule[$tag]]);
                $rule[$tag] = static::$aliasMap[$rule[$tag]];
                /* historically pf(4) excludes link-local on :network to avoid anti-spoof overlap */
                if ($rule['ipprotocol'] == 'inet6' && $is_interface && $this instanceof FilterRule) {
                    $rule[$tag] .= ',fe80::/10';
                }
            } elseif (!Util::isIpAddress($rule[$tag]) && !Util::isSubnet($rule[$tag])) {
                $rule['disabled'] = true;
                $rule[$tag] = json_encode($rule[$tag]);
                $this->log("Unable to convert address, see {$tag} for details");
            }
            if (!empty($rule[$tag . '_not']) && !empty($rule[$tag]) && $rule[$tag] != 'any') {
                $rule[$tag] = '!' . $rule[$tag];
            }

            /* port handling */
            $pfield = sprintf('%s_port', $tag);
            if (isset($rule[$pfield])) {
                $port = str_replace('-', ':', $rule[$pfield]);
                if (strpos($port, ':any') !== false xor strpos($port, 'any:') !== false) {
                    // convert 'any' to upper or lower bound when provided in range. e.g. 80:any --> 80:65535
                    $port = str_replace('any', strpos($port, ':any') !== false ? '65535' : '1', $port);
                }
                if ($port == 'any') {
                    $rule[$pfield] = null;
                } elseif (Util::isPort($port)) {
                    $rule[$pfield] = $port;
                } elseif (Util::isAlias($port)) {
                    $rule[$pfield] = '$' . $port;
                    if (!Util::isAlias($port, true)) {
                        // unable to map port
                        $rule['disabled'] = true;
                        $this->log("Unable to map port {$port}, empty?");
                    }
                } elseif (!empty($port)) {
                    $rule['disabled'] = true;
                    $this->log("Unable to map port {$port}, config error?");
                }
            }
        }
    }

    /**
     * init Rule
     * @param array $interfaceMapping internal interface mapping
     * @param array $conf rule configuration
     */
    public function __construct(&$interfaceMapping, $conf)
    {
        $this->interfaceMapping = $interfaceMapping;
        $this->rule = $conf;
        $this->loadAliasMap();
    }

    /**
     * send text to debug log
     * @param string $line debug log info
     */
    protected function log($line)
    {
        $this->ruleDebugInfo[] = $line;
    }

    /**
     * output parsing
     * @param string $value field value
     * @return string
     */
    protected function parseIsComment($value)
    {
        return !empty($value) ? "#" : "";
    }

    /**
     * parse comment
     * @param string $value field value
     * @return string
     */
    protected function parseComment($value)
    {
        return !empty($value) ? "# " . preg_replace("/\r|\n/", "", $value) : "";
    }

    /**
     * parse static text
     * @param string $value static text
     * @param string $text
     * @return string
     */
    protected function parseStaticText($value, $text)
    {
        return $text;
    }

    /**
     * parse boolean, return text from $valueTrue / $valueFalse
     * @param string $value field value
     * @param $valueTrue
     * @param string $valueFalse
     * @return string
     */
    protected function parseBool($value, $valueTrue, $valueFalse = "")
    {
        if (!empty($value)) {
            return !empty($valueTrue) ? $valueTrue . " " : "";
        } else {
            return !empty($valueFalse) ? $valueFalse . " " : "";
        }
    }

    /**
     * parse plain data
     * @param string $value field value
     * @param string $prefix prefix when $value is provided
     * @param string $suffix suffix when $value is provided
     * @param int $maxsize maximum size, cut when longer
     * @return string
     */
    protected function parsePlain($value, $prefix = "", $suffix = "", $maxsize = null)
    {
        if (!empty($maxsize) && strlen($value) > $maxsize) {
            $value = substr($value, 0, $maxsize);
        }
        return $value == null || $value === '' ? '' : $prefix . $value . $suffix . ' ';
    }

    /**
     * parse plain data
     * @param string $value field value
     * @param string $prefix prefix when $value is provided
     * @param string $suffix suffix when $value is provided
     * @return string
     */
    protected function parsePlainCurly($value, $prefix = "", $suffix = "")
    {
        if ($value !== null && strpos($value, '$') === false) {
            // don't wrap aliases in curly brackets
            $prefix = $prefix . "{";
            $suffix = "}" . $suffix;
        }
        return $value == null || $value === '' ? '' : $prefix . $value . $suffix . ' ';
    }

    /**
     * parse data, use replace map
     * @param string $value field value
     * @param string $map
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    protected function parseReplaceSimple($value, $map, $prefix = "", $suffix = "")
    {
        $retval = $value;
        foreach (explode('|', $map) as $item) {
            $tmp = explode(':', $item);
            if ($tmp[0] == $value) {
                $retval = $tmp[1] . " ";
                break;
            }
        }
        if (!empty($retval)) {
            return $prefix . $retval . $suffix . " ";
        } else {
            return "";
        }
    }

    /**
     * rule reader, applies standard rule patterns
     * @param string $type type of rule to be read
     * @return \Iterator rules to generate
     */
    protected function reader($type = null)
    {
        $rule = array_replace([], $this->rule); /* deep copy before use */
        $this->legacyMoveAddressFields($rule);
        $interfaces = empty($rule['interface']) ? [null] : explode(',', $rule['interface']);
        $froms = empty($rule['from']) ? [null] : explode(',', $rule['from']);
        $tos = empty($rule['to']) ? [null] : explode(',', $rule['to']);
        if (isset($rule['ipprotocol']) && $rule['ipprotocol'] == 'inet46') {
            $ipprotos = ['inet', 'inet6'];
        } elseif (isset($rule['ipprotocol'])) {
            $ipprotos = [$rule['ipprotocol']];
        } elseif (!empty($type) && $type = 'npt') {
            $ipprotos = ['inet6'];
        } else {
            $ipprotos = [null];
        }
        /* generate cartesian product of fields that may contain multiple options */
        $meta_rules = [];
        foreach ($froms as $from) {
            foreach ($tos as $to) {
                foreach ($interfaces as $interface) {
                    foreach ($ipprotos as $ipproto) {
                        $meta_rules[] = [
                            'ipprotocol' => $ipproto,
                            'interface' => $interface,
                            'from' => $from,
                            'to' => $to
                        ];
                    }
                }
            }
        }
        foreach ($meta_rules as $meta_rule) {
            $rulecpy = array_merge($rule, $meta_rule);
            $this->mapAddressInfo($rulecpy);
            if (!empty($rulecpy['protocol'])) {
                /* lowercase to avoid mismatching lookups */
                $rulecpy['protocol'] = strtolower($rulecpy['protocol']);
            }
            $interface = $rulecpy['interface'];
            if ($rulecpy['ipprotocol'] == 'inet6' && !empty($this->interfaceMapping[$interface]['IPv6_override'])) {
                $rulecpy['interface'] = $this->interfaceMapping[$interface]['IPv6_override'];
            }
            // disable rule when interface not found
            if (!empty($interface) && empty($this->interfaceMapping[$interface]['if'])) {
                $this->log("Interface {$interface} not found");
                $rulecpy['disabled'] = true;
            }
            yield $rulecpy;
        }
    }

    /**
     * parse rule to text using processing parameters in $procorder
     * @param array $procorder conversion properties (rule keys and methods to execute)
     * @param array $rule rule to parse
     * @return string
     */
    protected function ruleToText(&$procorder, &$rule)
    {
        $ruleTxt = '';
        foreach ($procorder as $tag => $handle) {
            // support reuse of the same fieldname
            $tag = explode(".", $tag)[0];
            $tmp = explode(',', $handle);
            $method = $tmp[0];
            $args = array(isset($rule[$tag]) ? $rule[$tag] : null);
            if (count($tmp) > 1) {
                array_shift($tmp);
                $args = array_merge($args, $tmp);
            }
            $cmdout = trim(call_user_func_array(array($this,$method), $args));
            $ruleTxt .= !empty($cmdout) && !empty($ruleTxt) ? " "  : "";
            $ruleTxt .= $cmdout;
        }
        if (!empty($this->ruleDebugInfo)) {
            $debugTxt = "#debug:" . implode("|", $this->ruleDebugInfo) . "\n";
            $this->ruleDebugInfo = []; /* flush */
        } else {
            $debugTxt = "";
        }
        return $debugTxt . $ruleTxt;
    }

    /**
     * convert source/destination structures as used by the gui into simple flat structures.
     * @param array $rule rule
     */
    protected function legacyMoveAddressFields(&$rule)
    {
        $fields = [];
        $fields['source'] = 'from';
        $fields['destination'] = 'to';
        $interfaces = $this->interfaceMapping;
        foreach ($fields as $tag => $target) {
            if (!empty($rule[$tag])) {
                if (isset($rule[$tag]['any'])) {
                    $rule[$target] = 'any';
                } elseif (!empty($rule[$tag]['network'])) {
                    $rule[$target] = $rule[$tag]['network'];
                } elseif (!empty($rule[$tag]['address'])) {
                    $rule[$target] = $rule[$tag]['address'];
                }
                $rule[$target . '_not'] = isset($rule[$tag]['not']); /* to be used in mapAddressInfo() */

                if (
                    isset($rule['protocol']) &&
                    in_array(strtolower($rule['protocol']), ["tcp", "udp", "tcp/udp"]) &&
                    !empty($rule[$tag]['port'])
                ) {
                    $rule[$target . "_port"] = $rule[$tag]['port'];
                }
                if (!isset($rule[$target])) {
                    // couldn't convert address, disable rule
                    // dump all tag contents in target (from/to) for reference
                    $rule['disabled'] = true;
                    $this->log("Unable to convert address, see {$target} for details");
                    $rule[$target] = json_encode($rule[$tag]);
                }
            }
        }
    }

    /**
     * parse interface (name to interface)
     * @param string|array $value field value
     * @param string $prefix prefix interface tag
     * @param string $suffix suffix interface tag
     * @return string
     */
    protected function parseInterface($value, $prefix = "on ", $suffix = "")
    {
        if (!empty($this->rule['interfacenot'])) {
            $prefix = "{$prefix} ! ";
        }
        if (empty($value)) {
            return "";
        } elseif (empty($this->interfaceMapping[$value]['if'])) {
            return "{$prefix}##{$value}##{$suffix} ";
        } else {
            return "{$prefix}" . $this->interfaceMapping[$value]['if'] . "{$suffix} ";
        }
    }

    /**
     * Validate if the provided rule looks like an ipv4 address.
     * This method isn't bulletproof (if only aliases are used and we don't know the protocol, this might fail to
     * tell the truth)
     * @param array $rule parsed rule info
     * @return bool
     */
    protected function isIpV4($rule)
    {
        if (!empty($rule['ipprotocol'])) {
            return $rule['ipprotocol'] == 'inet';
        } else {
            // check fields which are known to contain addresses and search for an ipv4 address
            foreach (array('from', 'to', 'external', 'target') as $fieldname) {
                if (
                    (Util::isIpAddress($rule[$fieldname]) || Util::isSubnet($rule[$fieldname]))
                        && strpos($rule[$fieldname], ":") === false
                ) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * return label
     * @return string
     */
    public function getLabel()
    {
        return !empty($this->rule['label']) ? $this->rule['label'] : "";
    }

    /**
     * return #ref
     * @return string
     */
    public function getRef()
    {
        return !empty($this->rule['#ref']) ? $this->rule['#ref'] : "";
    }

    /**
     * return description
     * @return string
     */
    public function getDescr()
    {
        return !empty($this->rule['descr']) ? $this->rule['descr'] : "";
    }

    /**
     * return interface
     */
    public function getInterface()
    {
        return !empty($this->rule['interface']) ? $this->rule['interface'] : "";
    }

    /**
     * is rule enabled
     */
    public function isEnabled()
    {
        return empty($this->rule['disabled']);
    }

    public function ruleOrigin()
    {
        if ($this->rule['#priority'] < 200000) {
            return 'internal';  // early
        } elseif ($this->rule['#priority'] >= 200000 && $this->rule['#priority'] < 300000) {
            return 'floating';
        } elseif ($this->rule['#priority'] >= 300000 && $this->rule['#priority'] < 400000) {
            return 'group';
        } elseif ($this->rule['#priority'] >= 400000 && $this->rule['#priority'] < 500000) {
            return isset($this->rule['seq']) ? 'interface' : 'automation';
        }
        return 'internal2'; // late
    }

    /**
     * return raw rule
     */
    public function getRawRule()
    {
        return $this->rule;
    }
}
