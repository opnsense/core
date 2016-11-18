<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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
namespace OPNsense\Firewall;

/**
 * Class FilterRule
 * @package OPNsense\Firewall
 */
class FilterRule
{
    private $rule = array();
    private $interfaceMapping = array();

    private $procorder = array(
        'disabled' => 'parseIsComment',
        'type' => 'parseType',
        'direction' => 'parseReplaceSimple,any:',
        'log' => 'parseBool,log',
        'quick' => 'parseBool,quick',
        'interface' => 'parseInterface',
        'ipprotocol' => 'parsePlain',
        'protocol' => 'parseReplaceSimple,tcp/udp:{tcp udp},proto ',
        'from' => 'parsePlain,from {,}',
        'from_port' => 'parsePlain, port {,}',
        'to' => 'parsePlain,to {,}',
        'to_port' => 'parsePlain, port {,}',
        'icmp6-type' => 'parsePlain,icmp6-type {,}',
        'flags' => 'parsePlain, flags ',
        'state' => 'parseState',
        'allowopts' => 'parseBool,allow-opts',
        'label' => 'parsePlain,label ",",63'
    );

    /**
     * output parsing
     * @param string $value field value
     * @return string
     */
    private function parseIsComment($value)
    {
        return !empty($value) ? "#" : "";
    }

    /**
     * parse plain data
     * @param string $value field value
     * @param string $prefix prefix when $value is provided
     * @param string $suffix suffix when $value is provided
     * @param int $maxsize maximum size, cut when longer
     * @return string
     */
    private function parsePlain($value, $prefix = "", $suffix = "", $maxsize = null)
    {
        if (!empty($maxsize) && strlen($value) > $maxsize) {
            $value = substr($value, 0, $maxsize);
        }
        return $value == '' ? "" : $prefix . $value . $suffix . " ";
    }

    /**
     * parse data, use replace map
     * @param string $value field value
     * @param string $map
     * @return string
     */
    private function parseReplaceSimple($value, $map, $prefix = "", $suffix = "")
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
     * parse type
     * @param string $value field value
     * @return string
     */
    private function parseType($value)
    {
        switch ($value) {
            case 'reject':
                $type = 'block return';
                break;
            default:
                $type = $value;
        }
        return empty($type) ? "pass " : $type . " ";
    }

    /**
     * parse interface (name to interface)
     * @param string $value field value
     * @return string
     */
    private function parseInterface($value)
    {
        if (empty($value)) {
            return "";
        } elseif (empty($this->interfaceMapping[$value]['if'])) {
            return "on ##{$value}## ";
        } else {
            return "on ". $this->interfaceMapping[$value]['if']." ";
        }
    }

    /**
     * parse boolean, return text from $valueTrue / $valueFalse
     * @param string $value field value
     * @return string
     */
    private function parseBool($value, $valueTrue, $valueFalse = "")
    {
        if (!empty($value)) {
            return !empty($valueTrue) ? $valueTrue . " " : "";
        } else {
            return !empty($valueFalse) ? $valueFalse . " " : "";
        }
    }

    /**
     * parse state settings
     * @param array $value state option
     * @return string
     */
    private function parseState($value)
    {
        $retval = "";
        if (!empty($value)) {
            $retval .= $value['type'] . " state ";
            if (count($value['options'])) {
                $retval .= "( " . implode(' ', $value['options']) .  " ) ";
            }
        }
        return $retval;
    }

    /**
     * preprocess internal rule data to detail level of actual ruleset
     * handles shortcuts, like inet46 and multiple interfaces
     * @return array
     */
    private function fetchActualRules()
    {
        $result = array();
        $interfaces = empty($this->rule['interface']) ? array(null) : explode(',', $this->rule['interface']);
        foreach ($interfaces as $interface) {
            if (isset($this->rule['ipprotocol']) && $this->rule['ipprotocol'] == 'inet46') {
                $ipprotos = array('inet', 'inet6');
            } elseif (isset($this->rule['ipprotocol'])) {
                $ipprotos = array($this->rule['ipprotocol']);
            } else {
                $ipprotos = array(null);
            }

            foreach ($ipprotos as $ipproto) {
                $tmp = $this->rule;
                $tmp['interface'] = $interface;
                $tmp['ipprotocol'] = $ipproto;
                $tmp['from'] = empty($tmp['from']) ? "any" : $tmp['from'];
                $tmp['to'] = empty($tmp['to']) ? "any" : $tmp['to'];
                // disable rule when interface not found
                if (!empty($interface) && empty($this->interfaceMapping[$interface]['if'])) {
                    $tmp['disabled'] = true;
                }
                if (!isset($tmp['quick'])) {
                    // all rules are quick by default except floating
                    $tmp['quick'] = !isset($rule['floating']) ? true : false;
                }
                // restructure state settings for easier output parsing
                if (!empty($tmp['statetype'])) {
                    $tmp['state'] = array('type' => 'keep', 'options' => array());
                    switch ($tmp['statetype']) {
                        case 'none':
                            $tmp['state']['type'] = 'no';
                            break;
                        case 'sloppy state':
                        case 'sloppy':
                            $tmp['state']['type'] = 'keep';
                            $tmp['state']['options'][] = "sloppy ";
                            break;
                        default:
                            $tmp['state']['type'] = explode(' ', $tmp['statetype'])[0];
                    }
                    if (!empty($tmp['nopfsync'])) {
                        $tmp['state']['options'][] = "no-sync ";
                    }
                    foreach (array('max', 'max-src-nodes', 'max-src-conn', 'max-src-states') as $state_tag) {
                        if (!empty($tmp[$state_tag])) {
                            $tmp['state']['options'][] = $state_tag . " " . $tmp[$state_tag];
                        }
                    }
                    if (!empty($tmp['statetimeout'])) {
                        $tmp['state']['options'][] = "tcp.established " . $tmp['statetimeout'];
                    }
                    if (!empty($tmp['max-src-conn-rate']) && !empty($tmp['max-src-conn-rates'])) {
                        $tmp['state']['options'][] = "max-src-conn-rate " . $tmp['max-src-conn-rate'] . " " .
                                              "/" . $tmp['max-src-conn-rates'] . ", overload <virusprot> flush global ";
                    }
                }
                // icmpv6
                if ($ipproto == 'inet6' && !empty($tmp['protocol']) && $tmp['protocol'] == "icmp") {
                    $tmp['protocol'] = 'ipv6-icmp';
                }
                $result[] = $tmp;
            }
        }
        return $result;
    }

    /**
     * init FilterRule
     * @param array $interfaceMapping internal interface mapping
     * @param array $conf rule configuration
     */
    public function __construct(&$interfaceMapping, $conf)
    {
        $this->interfaceMapping = $interfaceMapping;
        $this->rule = $conf;
    }

    /**
     * output rule as string
     * @return string ruleset
     */
    public function __toString()
    {
        $ruleTxt = '';
        foreach ($this->fetchActualRules() as $rule) {
            foreach ($this->procorder as $tag => $handle) {
                $tmp = explode(',', $handle);
                $method = $tmp[0];
                $args = array(isset($rule[$tag]) ? $rule[$tag] : null);
                if (count($tmp) > 1) {
                    array_shift($tmp);
                    $args = array_merge($args, $tmp);
                }
                $ruleTxt .= call_user_func_array(array($this,$method), $args);
            }
            $ruleTxt .= "\n";
        }
        return $ruleTxt;
    }
}
