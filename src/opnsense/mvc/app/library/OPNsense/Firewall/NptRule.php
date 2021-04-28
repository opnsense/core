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
 * Class NptRule (IPv6)
 * @package OPNsense\Firewall
 */
class NptRule extends Rule
{
    private $procorder = array(
        'binat_1' => array(
            'disabled' => 'parseIsComment',
            'binat' => 'parseStaticText,binat ',
            'interface' => 'parseInterface',
            'from' => 'parsePlain,from , to any',
            'to' => 'parsePlain, -> ',
            'descr' => 'parseComment'
        ),
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
     */
    private function parseNptRules()
    {
        foreach ($this->reader('npt') as $rule) {
            $rule['rule_type'] = "binat_1";
            yield $rule;
        }
    }

    /**
     * output rule as string
     * @return string ruleset
     */
    public function __toString()
    {
        $ruleTxt = '';
        foreach ($this->parseNptRules() as $rule) {
            $ruleTxt .= $this->ruleToText($this->procorder[$rule['rule_type']], $rule) . "\n";
        }
        return $ruleTxt;
    }
}
