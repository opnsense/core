<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
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
 * Class Rule basic rule parsing logic
 * @package OPNsense\Firewall
 */
abstract class Rule
{
    protected $rule = array();
    protected $interfaceMapping = array();

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
     * output parsing
     * @param string $value field value
     * @return string
     */
    protected function parseIsComment($value)
    {
        return !empty($value) ? "#" : "";
    }


    /**
     * parse boolean, return text from $valueTrue / $valueFalse
     * @param string $value field value
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
                    $matches = "";
                    if ($network_name == '(self)') {
                        $rule[$target] = "(self)";
                    } elseif (preg_match("/^(wan|lan|opt[0-9]+)ip$/", $network_name, $matches)) {
                        if (!empty($interfaces[$matches[1]]['if'])) {
                            $rule[$target] = "({$interfaces["{$matches[1]}"]['if']})";
                        }
                    } else {
                        if (!empty($interfaces[$network_name]['if'])) {
                            $rule[$target] = "({$interfaces[$network_name]['if']}:network)";
                        }
                    }
                } elseif (!empty($rule[$tag]['address'])) {
                    if (Util::isIpAddress($rule[$tag]['address']) || Util::isSubnet($rule[$tag]['address']) ||
                      Util::isPort($rule[$tag]['address'])
                    ) {
                        $rule[$target] = $rule[$tag]['address'];
                    } elseif (Util::isAlias($rule[$tag]['address'])) {
                        $rule[$target] = '$'.$rule[$tag]['address'];
                    }
                }
                if (!empty($rule[$target]) && $rule[$target] != 'any' && isset($rule[$tag]['not'])) {
                    $rule[$target] = "!" . $rule[$target];
                }
                if (isset($rule['protocol']) && in_array(strtolower($rule['protocol']), array("tcp","udp","tcp/udp"))) {
                    $port = str_replace('-', ':', $rule[$tag]['port']);
                    if (Util::isPort($port)) {
                        $rule[$target."_port"] = $port;
                    } elseif (Util::isAlias($port)) {
                        $rule[$target."_port"] = '$'.$port;
                    }
                }
                if (!isset($rule[$target])) {
                    // couldn't convert address, disable rule
                    // dump all tag contents in target (from/to) for reference
                    $rule['disabled'] = true;
                    $rule[$target] = json_encode($rule[$tag]);
                }
            }
        }
    }

    /**
     * parse interface (name to interface)
     * @param string $value field value
     * @return string
     */
    protected function parseInterface($value)
    {
        if (empty($value)) {
            return "";
        } elseif (empty($this->interfaceMapping[$value]['if'])) {
            return "on ##{$value}## ";
        } else {
            return "on ". $this->interfaceMapping[$value]['if']." ";
        }
    }
}
