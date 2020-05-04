<?php

/*
 * Copyright (C) 2017 Deciso B.V.
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
 * Class DNatRule, (pf nat type rule, optionally combined with rdr+nat rules for reflection)
 * @package OPNsense\Firewall
 */
class DNatRule extends Rule
{
    private $procorder = array(
        'nat' => array(
            'disabled' => 'parseIsComment',
            'type' => 'parsePlain',
            'interface' => 'parseInterface',
            'from' => 'parsePlain,from ',
            'to' => 'parsePlain,to ',
            'external' => 'parsePlain, -> ',
            'descr' => 'parseComment'
        ),
        'nat_rdr' => array(
            'disabled' => 'parseIsComment',
            'nat' => 'parseStaticText,rdr ',
            'interface' => 'parseInterface',
            'to' => 'parsePlainCurly,from ',
            'external' => 'parsePlainCurly,to ',
            'from' => 'parsePlainCurly, -> , bitmask',
            'descr' => 'parseComment'
        ),
        'nat_refl' => array(
            'disabled' => 'parseIsComment',
            'nat' => 'parseStaticText,nat ',
            'interface' => 'parseInterface',
            'ipprotocol' => 'parsePlain',
            'protocol' => 'parseReplaceSimple,tcp/udp:{tcp udp},proto ',
            'interface.from' => 'parseInterface, from (,:network)',
            'from' => 'parsePlainCurly,to ',
            'interface.to' => 'parseInterface, -> (,)',
            'staticnatport' => 'parseBool,  static-port , port 1024:65535 ',
            'descr' => 'parseComment'
        )
    );

    /**
     * search interfaces without a gateway other then the one provided
     * @param $interface
     * @return array list of interfaces
     */
    private function reflectionInterfaces($interface)
    {
        $result = array();
        foreach ($this->interfaceMapping as $intfk => $intf) {
            if (
                empty($intf['gateway']) && empty($intf['gatewayv6']) && $interface != $intfk
                && !in_array($intf['if'], $result) && $intfk != 'loopback'
            ) {
                $result[] = $intfk;
            }
        }
        return $result;
    }

    /**
     * preprocess internal rule data to detail level of actual ruleset
     * handles shortcuts, like inet46 and multiple interfaces
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    private function parseNatRules()
    {
        foreach ($this->reader() as $rule) {
            $rule['rule_type'] = "nat";
            $rule['type'] = empty($rule['type']) ? "binat" : $rule['type'];

            // target address, when invalid, disable rule
            if (!empty($rule['external'])) {
                if (Util::isAlias($rule['external'])) {
                    $rule['external'] = "\${$rule['external']}";
                } elseif (!Util::isIpAddress($rule['external']) && !Util::isSubnet($rule['external'])) {
                    $rule['disabled'] = true;
                    $this->log("Invalid address {$rule['external']}");
                } elseif (strpos($rule['external'], '/') === false && strpos($rule['from'], '/') !== false) {
                    $rule['external'] .= "/" . explode('/', $rule['from'])[1];
                }
            }
            yield $rule;

            // yield reflection rdr rules when enabled
            $interface = $rule['interface'];
            $reflinterf = $this->reflectionInterfaces($interface);
            if (!$rule['disabled'] && $rule['natreflection'] == "enable") {
                foreach ($reflinterf as $interf) {
                    $is_ipv4 = $this->isIpV4($rule);
                    if (
                        ($is_ipv4 && !empty($this->interfaceMapping[$interf]['ifconfig']['ipv4'])) ||
                        (!$is_ipv4 && !empty($this->interfaceMapping[$interf]['ifconfig']['ipv6']))
                    ) {
                        $rule['rule_type'] = "nat_rdr";
                        $rule['interface'] = $interf;
                        yield $rule;
                    }
                }
            }

            // yield reflection nat rules when enabled, but only for interfaces with networks configured
            if (!$rule['disabled'] && !empty($rule['enablenatreflectionhelper'])) {
                $reflinterf[] = $interface;
                foreach ($reflinterf as $interf) {
                    if (!empty($this->interfaceMapping[$interf])) {
                        $is_ipv4 = $this->isIpV4($rule);
                        if (
                            ($is_ipv4 && !empty($this->interfaceMapping[$interf]['ifconfig']['ipv4'])) ||
                            (!$is_ipv4 && !empty($this->interfaceMapping[$interf]['ifconfig']['ipv6']))
                        ) {
                            // we don't seem to know the ip protocol here, make sure our ruleset contains one
                            $rule['ipprotocol'] = $is_ipv4 ? "inet" : "inet6";
                            $rule['rule_type'] = "nat_refl";
                            $rule['interface'] = $interf;
                            $rule['staticnatport'] = !empty($rule['staticnatport']);
                            yield $rule;
                        }
                    }
                }
            }
        }
    }

    /**
     * output rule as string
     * @return string ruleset
     * @throws \OPNsense\Base\ModelException
     */
    public function __toString()
    {
        $ruleTxt = '';
        foreach ($this->parseNatRules() as $rule) {
            $ruleTxt .= $this->ruleToText($this->procorder[$rule['rule_type']], $rule) . "\n";
        }
        return $ruleTxt;
    }
}
