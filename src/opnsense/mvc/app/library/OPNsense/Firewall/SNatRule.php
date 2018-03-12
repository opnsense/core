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
 * Class SNatRule, outbound / source nat rules
 * @package OPNsense\Firewall
 */
class SNatRule extends Rule
{
    private $procorder = array(
        'disabled' => 'parseIsComment',
        'nonat' => 'parseBool,no nat,nat',
        'log' => 'parseBool,log',
        'interface' => 'parseInterface',
        'ipprotocol' => 'parsePlain',
        'protocol' => 'parseReplaceSimple,tcp/udp:{tcp udp},proto ',
        'from' => 'parsePlain,from ',
        'sourceport' => 'parsePlain, port ',
        'to' => 'parsePlain,to ',
        'dstport' => 'parsePlain, port ',
        'tag' => 'parsePlain, tag ',
        'tagged' => 'parsePlain, tagged ',
        'target' => 'parsePlain, -> ',
        'natport' => 'parsePlain, port ',
        'poolopts' => 'parsePlain',
        'staticnatport' => 'parseBool,  static-port ',
        'descr' => 'parseComment'
    );

    /**
     * preprocess internal rule data to detail level of actual ruleset
     * handles shortcuts, like inet46 and multiple interfaces
     * @return array
     */
    private function parseNatRules()
    {
        foreach ($this->reader() as $rule) {
            if (!empty($rule['nonat'])) {
                // Just a precaution, when no nat is selected make sure we're not going to enter a target.
                // (keep behaviour from legacy code as long as we don't know for sure the fields are always empty)
                $rule['target'] = null;
                $rule['poolopts'] = null;
                $rule['staticnatport'] = null;
            } elseif (empty($rule['target'])) {
                $interf = $rule['interface'];
                if (!empty($this->interfaceMapping[$interf])) {
                    $interf_settings = $this->interfaceMapping[$interf];
                    if ((($this->isIpV4($rule) && !empty($interf_settings['ifconfig']['ipv4'])) ||
                        (!$this->isIpV4($rule) && !empty($interf_settings['ifconfig']['ipv6'])))
                        && (!empty($rule['poolopts']) || $rule['poolopts'] != 'round-robin')
                    ) {
                        // When pool options are set, we may not specify our interface as a list
                        // (which doesn't require the same network validations as single items do).
                        $rule['target'] = "{$interf_settings['if']}:0";
                    } elseif (!empty($interf_settings['if'])) {
                        // Define target as list, to prevent "no IP address found for *Interface*" when pf can't
                        // find an address on the interface for the same protocol family.
                        $rule['target'] = "({$interf_settings['if']}:0)";
                    }
                }
                if (empty($rule['target'])) {
                    // no target found, disable rule
                    $rule['disabled'] = true;
                }
            } elseif ($rule['target'] == "other-subnet") {
                $rule['target'] = $rule['targetip'] . '/' . $rule['targetip_subnet'];
            } elseif (!empty($rule['target']) && Util::isAlias($rule['target'])) {
                $rule['target'] = "$".$rule['target'];
            }
            foreach (array("sourceport", "dstport", "natport") as $fieldname) {
                if (!empty($rule[$fieldname]) && Util::isAlias($rule[$fieldname])) {
                    $rule[$fieldname] = "$".$rule[$fieldname];
                }
            }
            if (!empty($rule['staticnatport']) || !empty($rule['nonat'])) {
                $rule['natport'] = '';
            } elseif (empty($rule['natport'])) {
                $rule['natport'] = "1024:65535";
            }
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
        foreach ($this->parseNatRules() as $rule) {
            $ruleTxt .= $this->ruleToText($this->procorder, $rule). "\n";
        }
        return $ruleTxt;
    }
}
