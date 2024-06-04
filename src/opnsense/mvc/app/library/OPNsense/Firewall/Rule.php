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

/**
 * Class Rule basic rule parsing logic
 * @package OPNsense\Firewall
 */
abstract class Rule
{
    protected $rule = array();
    protected $interfaceMapping = array();
    protected $ruleDebugInfo = array();

    /* ease the reuse of parsing for pf keywords by using class constants */
    const PARSE_PROTO = 'parseReplaceSimple,tcp/udp:{tcp udp}|a/n:"a/n",proto ';

    /**
     * init Rule
     * @param array $interfaceMapping internal interface mapping
     * @param array $conf rule configuration
     */
    public function __construct(&$interfaceMapping, $conf)
    {
        $this->interfaceMapping = $interfaceMapping;
        $this->rule = $conf;
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
        $interfaces = empty($this->rule['interface']) ? array(null) : explode(',', $this->rule['interface']);
        foreach ($interfaces as $interface) {
            if (isset($this->rule['ipprotocol']) && $this->rule['ipprotocol'] == 'inet46') {
                $ipprotos = array('inet', 'inet6');
            } elseif (isset($this->rule['ipprotocol'])) {
                $ipprotos = array($this->rule['ipprotocol']);
            } elseif (!empty($type) && $type = 'npt') {
                $ipprotos = array('inet6');
            } else {
                $ipprotos = array(null);
            }

            foreach ($ipprotos as $ipproto) {
                $rule = $this->rule;
                if ($ipproto == 'inet6' && !empty($this->interfaceMapping[$interface]['IPv6_override'])) {
                    $rule['interface'] = $this->interfaceMapping[$interface]['IPv6_override'];
                } else {
                    $rule['interface'] = $interface;
                }
                $rule['ipprotocol'] = $ipproto;
                $this->convertAddress($rule);
                // disable rule when interface not found
                if (!empty($interface) && empty($this->interfaceMapping[$interface]['if'])) {
                    $this->log("Interface {$interface} not found");
                    $rule['disabled'] = true;
                }
                yield $rule;
            }
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
        } else {
            $debugTxt = "";
        }
        return $debugTxt . $ruleTxt;
    }

    /**
     * convert source/destination address entries as used by the gui
     * @param array $rule rule
     */
    protected function convertAddress(&$rule)
    {
        $fields = array();
        $fields['source'] = 'from';
        $fields['destination'] = 'to';
        $interfaces = $this->interfaceMapping;
        foreach ($fields as $tag => $target) {
            if (!empty($rule[$tag])) {
                if (isset($rule[$tag]['any'])) {
                    $rule[$target] = 'any';
                } elseif (!empty($rule[$tag]['network'])) {
                    $network_name = $rule[$tag]['network'];
                    $matches = '';
                    if ($network_name == '(self)') {
                        $rule[$target] = $network_name;
                    } elseif (preg_match("/^(wan|lan|opt[0-9]+)ip$/", $network_name, $matches)) {
                        if (!empty($interfaces[$matches[1]]['if'])) {
                            $rule[$target] = "({$interfaces[$matches[1]]['if']})";
                        }
                    } elseif (!empty($interfaces[$network_name]['if'])) {
                        $rule[$target] = "({$interfaces[$network_name]['if']}:network)";
                        if ($rule['ipprotocol'] == 'inet6' && $this instanceof FilterRule && $rule['interface'] == $network_name) {
                            /* historically pf(4) excludes link-local on :network to avoid anti-spoof overlap */
                            $rule[$target] .= ',fe80::/10';
                        }
                    } elseif (Util::isIpAddress($rule[$tag]['network']) || Util::isSubnet($rule[$tag]['network'])) {
                        $rule[$target] = $rule[$tag]['network'];
                    } elseif (Util::isAlias($rule[$tag]['network'])) {
                        $rule[$target] = '$' . $rule[$tag]['network'];
                    } elseif ($rule[$tag]['network'] == 'any') {
                        $rule[$target] = $rule[$tag]['network'];
                    }
                } elseif (!empty($rule[$tag]['address'])) {
                    if (
                        Util::isIpAddress($rule[$tag]['address']) || Util::isSubnet($rule[$tag]['address']) ||
                        Util::isPort($rule[$tag]['address'])
                    ) {
                        $rule[$target] = $rule[$tag]['address'];
                    } elseif (Util::isAlias($rule[$tag]['address'])) {
                        $rule[$target] = '$' . $rule[$tag]['address'];
                    }
                }
                if (!empty($rule[$target]) && $rule[$target] != 'any' && isset($rule[$tag]['not'])) {
                    $rule[$target] = "!" . $rule[$target];
                }
                if (isset($rule['protocol']) && in_array(strtolower($rule['protocol']), array("tcp","udp","tcp/udp"))) {
                    $port = !empty($rule[$tag]['port']) ? str_replace('-', ':', $rule[$tag]['port']) : null;
                    if ($port == null || $port == 'any') {
                        $port = null;
                    } elseif (strpos($port, ':any') !== false xor strpos($port, 'any:') !== false) {
                        // convert 'any' to upper or lower bound when provided in range. e.g. 80:any --> 80:65535
                        $port = str_replace('any', strpos($port, ':any') !== false ? '65535' : '1', $port);
                    }
                    if (Util::isPort($port)) {
                        $rule[$target . "_port"] = $port;
                    } elseif (Util::isAlias($port)) {
                        $rule[$target . "_port"] = '$' . $port;
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
