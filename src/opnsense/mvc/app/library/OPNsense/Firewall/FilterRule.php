<?php

/*
 * Copyright (C) 2016-2017 Deciso B.V.
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
 * Class FilterRule
 * @package OPNsense\Firewall
 */
class FilterRule extends Rule
{
    private $gatewayMapping = [];
    /* dummynet [shaper] targets */
    private static $dntargets = null;

    private $procorder = array(
        'disabled' => 'parseIsComment',
        'type' => 'parseType',
        'direction' => 'parseReplaceSimple,any:|:in',
        'log' => 'parseBool,log',
        'quick' => 'parseBool,quick',
        'interface' => 'parseInterface',
        'gateway' => 'parseRoute',
        'reply' =>  'parsePlain',
        'ipprotocol' => 'parsePlain',
        'protocol' => self::PARSE_PROTO,
        'from' => 'parsePlainCurly,from ',
        'from_port' => 'parsePlainCurly, port ',
        'os' => 'parsePlain, os {","}',
        'to' => 'parsePlainCurly,to ',
        'to_port' => 'parsePlainCurly, port ',
        'icmp-type' => 'parsePlain,icmp-type {,}',
        'icmp6-type' => 'parsePlain,icmp6-type {,}',
        'flags' => 'parsePlain, flags ',
        'state' => 'parseState',
        'set-prio' => 'parsePlain, set prio ',
        'prio' => 'parsePlain, prio ',
        'tos' => 'parsePlain, tos ',
        'tag' => 'parsePlain, tag ',
        'tagged' => 'parsePlain, tagged ',
        'allowopts' => 'parseBool,allow-opts',
        'dn' =>  'parsePlain',
        'label' => 'parsePlain,label ",",63',
        'descr' => 'parseComment'
    );

    /**
     * parse type
     * @param string $value field value
     * @return string
     */
    protected function parseType($value)
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
     * parse gateway (route-to)
     * @param string $value field value
     * @return string
     */
    protected function parseRoute($value)
    {
        if (!empty($value) && !empty($this->gatewayMapping[$value]['logic'])) {
            return " " . $this->gatewayMapping[$value]['logic'] . " ";
        } else {
            return "";
        }
    }

    /**
     * parse state settings
     * @param array $value state option
     * @return string
     */
    protected function parseState($value)
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
     * add reply-to tag when applicable
     * @param array $rule rule
     */
    protected function convertReplyTo(&$rule)
    {
        if (!empty($rule['reply-to'])) {
            // reply-to gateway set, when found map to reply attribute, otherwise skip keyword
            if (!empty($this->gatewayMapping[$rule['reply-to']])) {
                $if = $this->gatewayMapping[$rule['reply-to']]['interface'];
                if (!empty($this->gatewayMapping[$rule['reply-to']]['gateway'])) {
                    $gw = $this->gatewayMapping[$rule['reply-to']]['gateway'];
                    $rule['reply'] = "reply-to ( {$if} {$gw} ) ";
                } else {
                    $rule['reply'] = "reply-to {$if} ";
                }
            }
        } elseif (!isset($rule['disablereplyto']) && ($rule['direction'] ?? "") != 'any') {
            $proto = $rule['ipprotocol'];
            if (!empty($this->interfaceMapping[$rule['interface']]['if']) && empty($rule['gateway'])) {
                $if = $this->interfaceMapping[$rule['interface']]['if'];
                switch ($proto) {
                    case "inet6":
                        if (
                            !empty($this->interfaceMapping[$rule['interface']]['gatewayv6'])
                            && Util::isIpAddress($this->interfaceMapping[$rule['interface']]['gatewayv6'])
                        ) {
                            $gw = $this->interfaceMapping[$rule['interface']]['gatewayv6'];
                            $rule['reply'] = "reply-to ( {$if} {$gw} ) ";
                        }
                        break;
                    default:
                        if (
                            !empty($this->interfaceMapping[$rule['interface']]['gateway'])
                            && Util::isIpAddress($this->interfaceMapping[$rule['interface']]['gateway'])
                        ) {
                            $gw = $this->interfaceMapping[$rule['interface']]['gateway'];
                            $rule['reply'] = "reply-to ( {$if} {$gw} ) ";
                        }
                        break;
                }
            }
        }
    }


    /**
     * preprocess internal rule data to detail level of actual ruleset
     * handles shortcuts, like inet46 and multiple interfaces
     * @return array
     */
    private function parseFilterRules()
    {
        foreach ($this->reader() as $rule) {
            $this->convertReplyTo($rule);
            $rule['from'] = empty($rule['from']) ? "any" : $rule['from'];
            $rule['to'] = empty($rule['to']) ? "any" : $rule['to'];
            // disable rules when gateway is down and skip_rules_gw_down is set
            if (
                !empty($rule['skip_rules_gw_down']) && !empty($rule['gateway']) &&
                empty($this->gatewayMapping[$rule['gateway']])
            ) {
                $rule['disabled'] = true;
                $this->log("Gateway down");
            }
            if (
                !empty($rule['gateway']) &&
                  !empty($this->gatewayMapping[$rule['gateway']]) &&
                  !empty($rule['ipprotocol']) &&
                  $this->gatewayMapping[$rule['gateway']]['proto'] != $rule['ipprotocol']
            ) {
                $rule['disabled'] = true;
                $this->log("Gateway protocol mismatch");
            }
            if (!isset($rule['quick'])) {
                // all rules are quick by default except floating
                $rule['quick'] = !isset($rule['floating']) ? true : false;
            }
            // restructure flags
            if (isset($rule['protocol']) && $rule['protocol'] == "tcp") {
                if (isset($rule['tcpflags_any'])) {
                    $rule['flags'] = "any";
                } elseif (!empty($rule['tcpflags2'])) {
                    $rule['flags'] = "";
                    foreach (array('tcpflags1', 'tcpflags2') as $flagtag) {
                        $rule['flags'] .= $flagtag == 'tcpflags2' ? "/" : "";
                        if (!empty($rule[$flagtag])) {
                            foreach (explode(",", strtoupper($rule[$flagtag])) as $flag1) {
                                // CWR flag needs special treatment
                                $rule['flags'] .= $flag1[0] == "C" ? "W" : $flag1[0];
                            }
                        }
                    }
                }
            }
            // restructure state settings for easier output parsing
            if (!empty($rule['statetype']) && (empty($rule['type']) || $rule['type'] == 'pass')) {
                $rule['state'] = array('type' => 'keep', 'options' => array());
                switch ($rule['statetype']) {
                    case 'none':
                        $rule['state']['type'] = 'no';
                        break;
                    case 'sloppy state':
                    case 'sloppy':
                        $rule['state']['type'] = 'keep';
                        $rule['state']['options'][] = "sloppy ";
                        break;
                    default:
                        $rule['state']['type'] = explode(' ', $rule['statetype'])[0];
                }
                if ($rule['statetype'] != 'none') {
                    if (!empty($rule['nopfsync'])) {
                        $rule['state']['options'][] = "no-sync ";
                    }
                    foreach (array('max', 'max-src-nodes', 'max-src-conn', 'max-src-states') as $state_tag) {
                        if (!empty($rule[$state_tag])) {
                            $rule['state']['options'][] = $state_tag . " " . $rule[$state_tag];
                        }
                    }
                    if (!empty($rule['adaptivestart']) && is_numeric($rule['adaptivestart']) && is_numeric($rule['adaptiveend'])) {
                        $rule['state']['options'][] = "adaptive.start " . $rule['adaptivestart'] . ", adaptive.end " . $rule['adaptiveend'];
                    }
                    if (!empty($rule['statetimeout'])) {
                        $rule['state']['options'][] = "tcp.established " . $rule['statetimeout'];
                    }
                    if (!empty($rule['max-src-conn-rate']) && !empty($rule['max-src-conn-rates'])) {
                        $otbl = !empty($rule['overload']) ? $rule['overload'] : "virusprot";
                        $rule['state']['options'][] = "max-src-conn-rate " . $rule['max-src-conn-rate'] . " " .
                                             "/" . $rule['max-src-conn-rates'] . ", overload <{$otbl}> flush global ";
                    }
                    if (!empty($rule['state-policy'])) {
                        $rule['state']['options'][] = $rule['state-policy'];
                    }
                }
            }
            // icmp-type switch (ipv4/ipv6)
            if (!empty($rule['protocol']) && $rule['protocol'] == "icmp" && !empty($rule['icmptype'])) {
                if ($rule['ipprotocol'] == 'inet') {
                    $rule['icmp-type'] = $rule['icmptype'];
                } elseif ($rule['ipprotocol'] == 'inet6') {
                    $rule['icmp6-type'] = $rule['icmptype'];
                }
            }
            // icmpv6
            if ($rule['ipprotocol'] == 'inet6' && !empty($rule['protocol']) && $rule['protocol'] == "icmp") {
                $rule['protocol'] = 'ipv6-icmp';
            }
            // set prio
            if (
                isset($rule['set-prio']) && $rule['set-prio'] !== ""
                && isset($rule['set-prio-low']) && $rule['set-prio-low'] !== ""
            ) {
                $rule['set-prio'] = "({$rule['set-prio']}, {$rule['set-prio-low']})";
            }
            // convert shaper properties when requested
            if (!empty($rule['shaper1']) && empty($rule['disabled'])) {
                if (static::$dntargets === null) {
                    /* init cache pipe/queue list */
                    static::$dntargets = (new \OPNsense\TrafficShaper\TrafficShaper())->fetchAllTargets();
                }
                $shaper1 = static::$dntargets[$rule['shaper1']] ?? ['type' => ''];
                $shaper2 = static::$dntargets[$rule['shaper2'] ?? '-'] ?? ['type' => ''];
                if (!empty($rule['shaper2']) && (empty($shaper2['type']) || $shaper2['type'] != $shaper1['type'] )) {
                    $rule['disabled'] = true;
                    $this->log(sprintf('shaper type mismatch [%s],[%s]', $shaper1['type'], $shaper2['type']));
                } elseif (empty($shaper1)) {
                    $rule['disabled'] = true;
                    $this->log('shaper defined but not found');
                } elseif (!empty($shaper2['type'])) {
                    $rule['dn'] = sprintf('%s (%s, %s)', $shaper1['type'], $shaper1['id'], $shaper2['id']);
                } else {
                    $rule['dn'] = sprintf('%s %s', $shaper1['type'], $shaper1['id']);
                }
            }

            yield $rule;
        }
    }

    /**
     * init FilterRule
     * @param array $interfaceMapping internal interface mapping
     * @param array $gatewayMapping internal gateway mapping
     * @param array $conf rule configuration
     */
    public function __construct(&$interfaceMapping, &$gatewayMapping, $conf)
    {
        parent::__construct($interfaceMapping, $conf);
        $this->gatewayMapping = $gatewayMapping;
    }

    /**
     * output rule as string
     * @return string ruleset
     */
    public function __toString()
    {
        $ruleTxt = '';
        foreach ($this->parseFilterRules() as $rule) {
            $ruleTxt .= $this->ruleToText($this->procorder, $rule) . "\n";
        }
        return $ruleTxt;
    }

    /**
     * Legacy and MVC use different fields, which at some point need to be merged.
     * parseFilterRules already does this for the rule output, but gui parts are left with a mix of things
     */
    private function uiConvertNet($network)
    {
        $suffix = str_ends_with($network, 'ip') ?  gettext("address") :  gettext("net");
        $ifname = rtrim($network, 'ip');
        if (!empty($this->interfaceMapping[$ifname])) {
            $if = $this->interfaceMapping[$ifname];
            return (!empty($if['descr']) ? $if['descr'] : $ifname) . " " . $suffix;
        } elseif ($ifname == '(self)') {
            return gettext("This Firewall");
        }
        return $network;
    }
    public function getUIFromAddress()
    {
        if (!empty($this->rule['from'])) {
            return preg_replace('/,(?=[^\s])/', ', ', $this->rule['from']);
        } elseif (isset($this->rule['source']['address'])) {
            return $this->rule['source']['address'];
        } elseif (isset($this->rule['source']['any'])) {
            return '*';
        } elseif (isset($this->rule['source']['network'])) {
            return $this->uiConvertNet($this->rule['source']['network']);
        }
        return '*';
    }
    public function isUIFromNot()
    {
        return (isset($this->rule['source']) && isset($this->rule['source']['not'])) || !empty($this->rule['from_not']);
    }
    public function getUIFromPort()
    {
        if (isset($this->rule['from_port']) && $this->rule['from_port'] != '') {
            return $this->rule['from_port'];
        } elseif (isset($this->rule['source']['port'])) {
            return $this->rule['source']['port'];
        }
        return '*';
    }
    public function getUIToAddress()
    {
        if (!empty($this->rule['to'])) {
            return preg_replace('/,(?=[^\s])/', ', ', $this->rule['to']);
        } elseif (isset($this->rule['destination']['address'])) {
            return $this->rule['destination']['address'];
        } elseif (isset($this->rule['destination']['any'])) {
            return '*';
        } elseif (isset($this->rule['destination']['network'])) {
            return $this->uiConvertNet($this->rule['destination']['network']);
        }
        return '*';
    }
    public function isUIToNot()
    {
        return isset($this->rule['destination']) && isset($this->rule['destination']['not']) || !empty($this->rule['to_not']);
    }
    public function getUIToPort()
    {
        if (isset($this->rule['to_port']) && $this->rule['to_port'] != '') {
            return $this->rule['to_port'];
        } elseif (isset($this->rule['destination']['port'])) {
            return $this->rule['destination']['port'];
        }
        return '*';
    }
    public function getUIGateway()
    {
        return !empty($this->rule['gateway']) ? $this->rule['gateway'] : "*";
    }
}
