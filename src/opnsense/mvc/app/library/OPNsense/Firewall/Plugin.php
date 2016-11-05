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
 * Class Plugin
 * @package OPNsense\Firewall
 */
class Plugin
{
    private $anchors = array();
    private $filterRules = array();
    private $interfaceMapping = array();
    private $interfaceStaticMapping;

    /**
     * init firewall plugin component
     */
    public function __construct()
    {
    }

    /**
     * set interface mapping to USE
     * @param array $mapping named array
     */
    public function setInterfaceMapping(&$mapping)
    {
        $this->interfaceMapping = array();
        $this->interfaceMapping['loopback'] = array('if' => 'lo0', 'descr' => 'loopback');
        $this->interfaceMapping = array_merge($this->interfaceMapping, $mapping);
    }

    /**
     * @return array
     */
    public function getInterfaceMapping()
    {
        return $this->interfaceMapping;
    }

    /**
     * register anchor
     * @param string $name anchor name
     * @param string $type anchor type (fw for filter, other options are nat,rdr,binat)
     * @param string $priority sort order from low to high
     * @param string $placement placement head,tail
     * @return null
     */
    public function registerAnchor($name, $type = "fw", $priority = 0, $placement = "tail")
    {
        $anchorKey = sprintf("%s.%s.%08d.%08d", $type, $placement, $priority, count($this->anchors));
        $this->anchors[$anchorKey] = $name;
        ksort($this->anchors);
    }

    /**
     * fetch anchors as text (pf ruleset part)
     * @param string $types anchor types (fw for filter, other options are nat,rdr,binat. comma seperated)
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
                    $result .= "anchor \"{$anchor}\"\n";
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
        if ($defaults != null) {
            $conf = array_merge($defaults, $conf);
        }
        $rule = new FilterRule($this->interfaceMapping, $conf);
        if (empty($this->filterRules[$prio])) {
            $this->filterRules[$prio] = array();
        }
        $this->filterRules[$prio][] = $rule;
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
            foreach ($ruleset as $rule) {
                $output .= (string)$rule;
            }
        }
        return $output;
    }
}
