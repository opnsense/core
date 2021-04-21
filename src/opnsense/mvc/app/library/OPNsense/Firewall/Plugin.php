<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

/**
 * Class Plugin
 * @package OPNsense\Firewall
 */
class Plugin
{
    private $gateways = null;
    private $anchors = array();
    private $filterRules = array();
    private $natRules = array();
    private $interfaceMapping = array();
    private $gatewayMapping = array();
    private $systemDefaults = array();
    private $tables = array();
    private $ifconfigDetails = array();

    /**
     * init firewall plugin component
     */
    public function __construct()
    {
        $this->systemDefaults = array("filter" => array(), "forward" => array(), "nat" => array());
        if (!empty(Config::getInstance()->object()->system->disablereplyto)) {
            $this->systemDefaults['filter']['disablereplyto'] = true;
        }
        if (!empty(Config::getInstance()->object()->system->skip_rules_gw_down)) {
            $this->systemDefaults['filter']['skip_rules_gw_down'] = true;
        }
        if (empty(Config::getInstance()->object()->system->disablenatreflection)) {
            $this->systemDefaults['forward']['natreflection'] = "enable";
        }
        if (!empty(Config::getInstance()->object()->system->enablebinatreflection)) {
            $this->systemDefaults['nat']['natreflection'] = "enable";
        }
        if (!empty(Config::getInstance()->object()->system->enablenatreflectionhelper)) {
            $this->systemDefaults['forward']['enablenatreflectionhelper'] = true;
            $this->systemDefaults['nat']['enablenatreflectionhelper'] = true;
        }
    }

    /**
     * set interface mapping to use
     * @param array $mapping named array
     */
    public function setInterfaceMapping(&$mapping)
    {
        $this->interfaceMapping = $mapping;
        // generate virtual IPv6 interfaces
        foreach ($this->interfaceMapping as $key => &$intf) {
            if (!empty($intf['ipaddrv6']) && ($intf['ipaddrv6'] == '6rd' || $intf['ipaddrv6'] == '6to4')) {
                $realif = "{$key}_stf";
                // create new interface
                $this->interfaceMapping[$realif] = array();
                $this->interfaceMapping[$realif]['ifconfig']['ipv6'] = $intf['ifconfig']['ipv6'];
                $this->interfaceMapping[$realif]['gatewayv6'] = $intf['gatewayv6'];
                $this->interfaceMapping[$realif]['is_IPv6_override'] = true;
                $this->interfaceMapping[$realif]['descr'] = $intf['descr'];
                $this->interfaceMapping[$realif]['if'] = $realif;
                // link original interface
                $intf['IPv6_override'] = $realif;
            }
        }
    }

    /**
     * set defined gateways (route-to)
     * @param  \OPNsense\Routing\Gateways $gateways object
     */
    public function setGateways(\OPNsense\Routing\Gateways $gateways)
    {
        $this->gateways = $gateways;
        foreach ($gateways->gatewaysIndexedByName(false, true) as $key => $gw) {
            if (!empty($gw['gateway_interface']) || Util::isIpAddress($gw['gateway'])) {
                if (Util::isIpAddress($gw['gateway'])) {
                    $logic = "route-to ( {$gw['if']} {$gw['gateway']} )";
                } else {
                    $logic = "route-to {$gw['if']}";
                }
                $this->gatewayMapping[$key] = array("logic" => $logic,
                                                    "interface" => $gw['if'],
                                                    "gateway" => $gw['gateway'],
                                                    "proto" => $gw['ipprotocol'],
                                                    "type" => "gateway");
            }
        }
    }

    /**
     * @return \OPNsense\Routing\Gateways gateway object
     */
    public function getGateways(): ?\OPNsense\Routing\Gateways
    {
        return $this->gateways;
    }

    /**
     * set defined gateway groups (route-to)
     * @param array $groups named array
     */
    public function setGatewayGroups($groups)
    {
        if (is_array($groups)) {
            foreach ($groups as $key => $gwgr) {
                $routeto = array();
                $proto = 'inet';
                foreach ($gwgr as $gw) {
                    if (Util::isIpAddress($gw['gwip']) && !empty($gw['int'])) {
                        $gwweight = empty($gw['weight']) ? 1 : $gw['weight'];
                        $routeto[] = str_repeat("( {$gw['int']} {$gw['gwip']} )", $gwweight);
                        if (strstr($gw['gwip'], ':')) {
                            $proto = 'inet6';
                        }
                    }
                }
                if (count($routeto) > 0) {
                    $routetologic = "route-to {" . implode(' ', $routeto) . "}";
                    if (count($routeto) > 1) {
                        $routetologic .= " round-robin ";
                    }
                    if (!empty(Config::getInstance()->object()->system->lb_use_sticky)) {
                        $routetologic .= " sticky-address ";
                    }
                    $this->gatewayMapping[$key] = array("logic" => $routetologic,
                                                        "proto" => $proto,
                                                        "type" => "group");
                }
            }
        }
    }

    /**
     * fetch gateway (names) for provided interface, would return both ipv4/ipv6
     * @param string $intf interface (e.g. em0, igb0,...)
     */
    public function getInterfaceGateways($intf)
    {
        $result = array();
        $protos_found = array();
        foreach ($this->gatewayMapping as $key => $gw) {
            if ($gw['type'] == 'gateway' && $gw['interface'] == $intf) {
                if (!in_array($gw['proto'], $protos_found)) {
                    $result[] = $key;
                    $protos_found[] = $gw['proto'];
                }
            }
        }
        return $result;
    }

    /**
     *  Fetch gateway
     *  @param string $gw gateway name
     */
    public function getGateway($gw)
    {
        return $this->gatewayMapping[$gw];
    }

    /**
     * @return array
     */
    public function getInterfaceMapping()
    {
        foreach ($this->interfaceMapping as $intfkey => $intf) {
            // suppress virtual ipv6 interfaces
            if (empty($intf['is_IPv6_override'])) {
                yield $intfkey => $intf;
            }
        }
    }

    /**
     * link parsed ifconfig output
     * @param array $ifconfig from legacy_interfaces_details()
     */
    public function setIfconfigDetails($ifconfig)
    {
        $this->ifconfigDetails = $ifconfig;
    }

    /**
     * @return array
     */
    public function getIfconfigDetails()
    {
        return $this->ifconfigDetails;
    }


    /**
     * register anchor
     * @param string $name anchor name
     * @param string $type anchor type (fw for filter, other options are nat,rdr,binat)
     * @param string $priority sort order from low to high
     * @param string $placement placement head,tail
     * @return null
     */
    public function registerAnchor($name, $type = "fw", $priority = 0, $placement = "tail", $quick = false)
    {
        $anchorKey = sprintf("%s.%s.%08d.%08d", $type, $placement, $priority, count($this->anchors));
        $this->anchors[$anchorKey] = array('name' => $name, 'quick' => $quick);
        ksort($this->anchors);
    }

    /**
     * fetch anchors as text (pf ruleset part)
     * @param string $types anchor types (fw for filter, other options are nat,rdr,binat. comma-separated)
     * @param string $placement placement head,tail
     * @return string
     */
    public function anchorToText($types = "fw", $placement = "tail")
    {
        $result = "";
        foreach (explode(',', $types) as $type) {
            foreach ($this->anchors as $anchorKey => $anchor) {
                if (strpos($anchorKey, "{$type}.{$placement}") === 0) {
                    $result .= $type == "fw" ? "" : "{$type}-";
                    $result .= "anchor \"{$anchor['name']}\"";
                    if ($anchor['quick']) {
                        $result .= " quick";
                    }
                    $result .= "\n";
                }
            }
        }
        return $result;
    }

    /**
     * register a filter rule
     * @param int $prio priority
     * @param array $conf configuration
     * @param array $defaults merge these defaults when provided
     */
    public function registerFilterRule($prio, $conf, $defaults = null)
    {
        if (!empty($this->systemDefaults['filter'])) {
            $conf = array_merge($this->systemDefaults['filter'], $conf);
        }
        if ($defaults != null) {
            $conf = array_merge($defaults, $conf);
        }
        if (empty($conf['label'])) {
            // generated rule, has no label
            $rule_hash = Util::calcRuleHash($conf);
            $conf['label'] = $rule_hash;
        }
        $rule = new FilterRule($this->interfaceMapping, $this->gatewayMapping, $conf);
        if (empty($this->filterRules[$prio])) {
            $this->filterRules[$prio] = array();
        }
        $this->filterRules[$prio][] = $rule;
    }

    /**
     * register a Forward (rdr) rule
     * @param int $prio priority
     * @param array $conf configuration
     */
    public function registerForwardRule($prio, $conf)
    {
        if (!empty($this->systemDefaults['forward'])) {
            $conf = array_merge($this->systemDefaults['forward'], $conf);
        }
        $rule = new ForwardRule($this->interfaceMapping, $conf);
        if (empty($this->natRules[$prio])) {
            $this->natRules[$prio] = array();
        }
        $this->natRules[$prio][] = $rule;
    }

    /**
     * register a destination Nat rule
     * @param int $prio priority
     * @param array $conf configuration
     */
    public function registerDNatRule($prio, $conf)
    {
        if (!empty($this->systemDefaults['nat'])) {
            $conf = array_merge($this->systemDefaults['nat'], $conf);
        }
        $rule = new DNatRule($this->interfaceMapping, $conf);
        if (empty($this->natRules[$prio])) {
            $this->natRules[$prio] = array();
        }
        $this->natRules[$prio][] = $rule;
    }

    /**
     * register a destination Nat rule
     * @param int $prio priority
     * @param array $conf configuration
     */
    public function registerSNatRule($prio, $conf)
    {
        $rule = new SNatRule($this->interfaceMapping, $conf);
        if (empty($this->natRules[$prio])) {
            $this->natRules[$prio] = array();
        }
        $this->natRules[$prio][] = $rule;
    }

    /**
     * register an Npt rule
     * @param int $prio priority
     * @param array $conf configuration
     */
    public function registerNptRule($prio, $conf)
    {
        $rule = new NptRule($this->interfaceMapping, $conf);
        if (empty($this->natRules[$prio])) {
            $this->natRules[$prio] = array();
        }
        $this->natRules[$prio][] = $rule;
    }

    /**
     * filter rules to text
     * @return string
     */
    public function outputFilterRules()
    {
        $output = "";
        ksort($this->filterRules);
        foreach ($this->filterRules as $prio => $ruleset) {
            $output .= "# [prio: {$prio}]\n";
            foreach ($ruleset as $rule) {
                $output .= (string)$rule;
            }
        }
        return $output;
    }

    /**
     * iterate through registered rules
     * @return Iterator
     */
    public function iterateFilterRules()
    {
        foreach ($this->filterRules as $prio => $ruleset) {
            foreach ($ruleset as $rule) {
                 yield $rule;
            }
        }
    }

    /**
     * filter rules to text
     * @return string
     */
    public function outputNatRules()
    {
        $output = "";
        ksort($this->natRules);
        foreach ($this->natRules as $prio => $ruleset) {
            $output .= "# [prio: {$prio}]\n";
            foreach ($ruleset as $rule) {
                $output .= (string)$rule;
            }
        }
        return $output;
    }
    /**
     * register a pf table
     * @param string $name table name
     * @param boolean $persist persistent
     * @param string $file get table from file
     */
    public function registerTable($name, $persist = false, $file = null)
    {
        $this->tables[] = array('name' => $name, 'persist' => $persist, 'file' => $file);
    }

    /**
     * fetch tables as text (pf tables part)
     * @return string
     */
    public function tablesToText()
    {
        $result = "";
        foreach ($this->tables as $table) {
            $result .= "table <{$table['name']}>";
            if ($table['persist']) {
                $result .= " persist";
            }
            if (!empty($table['file'])) {
                $result .= " file \"{$table['file']}\"";
            }
            $result .= "\n";
        }
        return $result;
    }
}
