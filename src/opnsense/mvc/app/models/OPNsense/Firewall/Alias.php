<?php

/**
 *    Copyright (C) 2018 Deciso B.V.
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

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

/**
 * Class Alias
 * @package OPNsense\Firewall
 */
class Alias extends BaseModel
{
    /**
     * return locations where aliases can be used inside the configuration
     * @return array alias source map
     */
    private function getAliasSource()
    {
        $sources = array();
        $sources[] = array(array('filter', 'rule'), array('source', 'address'));
        $sources[] = array(array('filter', 'rule'), array('destination', 'address'));
        $sources[] = array(array('filter', 'rule'), array('source', 'port'));
        $sources[] = array(array('filter', 'rule'), array('destination', 'port'));
        $sources[] = array(array('nat', 'rule'), array('source', 'address'));
        $sources[] = array(array('nat', 'rule'), array('source', 'port'));
        $sources[] = array(array('nat', 'rule'), array('destination', 'address'));
        $sources[] = array(array('nat', 'rule'), array('destination', 'port'));
        $sources[] = array(array('nat', 'rule'), array('target'));
        $sources[] = array(array('nat', 'rule'), array('local-port'));
        $sources[] = array(array('nat', 'onetoone'), array('destination', 'address'));
        $sources[] = array(array('nat', 'outbound', 'rule'), array('source', 'network'));
        $sources[] = array(array('nat', 'outbound', 'rule'), array('sourceport'));
        $sources[] = array(array('nat', 'outbound', 'rule'), array('destination', 'network'));
        $sources[] = array(array('nat', 'outbound', 'rule'), array('dstport'));
        $sources[] = array(array('nat', 'outbound', 'rule'), array('target'));
        $sources[] = array(array('load_balancer', 'lbpool'), array('port'));
        $sources[] = array(array('load_balancer', 'virtual_server'), array('port'));
        $sources[] = array(array('staticroutes', 'route'), array('network'));

        return $sources;
    }

    /**
     * search configuration items where the requested alias is used
     * @param $name alias name
     * @return \Generator [reference, confignode, matched item]
     */
    private function searchConfig($name)
    {
        $cfgObj = Config::getInstance()->object();
        foreach ($this->getAliasSource() as $aliasref) {
            $cfgsection = $cfgObj;
            foreach ($aliasref[0] as $cfgName) {
                if ($cfgsection != null) {
                    $cfgsection = $cfgsection->$cfgName;
                }
            }
            if ($cfgsection != null) {
                $nodeidx = 0;
                foreach ($cfgsection as $inode) {
                    $node = $inode;
                    foreach ($aliasref[1] as $cfgName) {
                        $node = $node->$cfgName;
                    }
                    if ((string)$node == $name) {
                        $ref = implode('.', $aliasref[0]) . "." . $nodeidx . "/" . implode('.', $aliasref[1]);
                        yield array($ref, &$inode, &$node);
                    }
                    $nodeidx++;
                }
            }
        }
    }

    /**
     * Return all places an alias seems to be used
     * @param string $name alias name
     * @return array hashmap with references where this alias is used
     */
    public function whereUsed($name)
    {
        $result = array();
        // search legacy locations
        foreach ($this->searchConfig($name) as $item) {
            $result[$item[0]] = (string)$item[1]->descr;
        }
        // find all used in this model
        foreach ($this->aliases->alias->iterateItems() as $alias) {
            if (!in_array($alias->type, array('geoip', 'urltable'))) {
                $nodeData = $alias->content->getNodeData();
                if (isset($nodeData[$name])) {
                    if (!empty($alias->description)) {
                        $result[(string)$alias->__reference] = (string)$alias->description;
                    } else {
                        $result[(string)$alias->__reference] = (string)$alias->name;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * replace alias usage
     * @param $oldname
     * @param $newname
     */
    public function refactor($oldname, $newname)
    {
        Util::attachAliasObject($this);
        // replace in legacy config
        foreach ($this->searchConfig($oldname) as $item) {
            $item[2][0] = $newname;
        }
        // find all used in this model (alias nesting)
        foreach ($this->aliases->alias->iterateItems() as $alias) {
            if (!in_array($alias->type, array('geoip', 'urltable'))) {
                $sepchar = $alias->content->getSeparatorChar();
                $aliases = explode($sepchar, (string)$alias->content);
                if (in_array($oldname, $aliases)) {
                    $aliases = array_unique($aliases);
                    $aliases[array_search($oldname, $aliases)] = $newname;
                    $alias->content->setValue(implode($sepchar, $aliases));
                }
            }
        }
    }

    /**
     * return aliases as array
     * @return Generator with aliases
     */
    public function aliasIterator()
    {
        $use_legacy = true;
        foreach ($this->aliases->alias->iterateItems() as $alias) {
            $record = array();
            foreach ($alias->iterateItems() as $key => $value) {
                $record[$key] = (string)$value;
            }
            yield $record;
            $use_legacy = false;
        }
        // MVC not used (yet) return legacy type aliases
        if ($use_legacy) {
            $cfgObj = Config::getInstance()->object();
            if (!empty($cfgObj->aliases->alias)) {
                foreach ($cfgObj->aliases->children() as $alias) {
                    $alias = (array)$alias;
                    $alias['content'] = !empty($alias['address']) ? str_replace(" ", "\n", $alias['address']) : null;
                    yield $alias;
                }
            }
        }
    }

    public function getByName($name)
    {
        foreach ($this->aliases->alias->iterateItems() as $alias) {
            if ((string)$alias->name == $name) {
                return $alias;
            }
        }
        return null;
    }
}
