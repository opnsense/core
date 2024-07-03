<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

/**
 * Class Group
 * @package OPNsense\Firewall
 */
class Group extends BaseModel
{
    private function ruleIterator()
    {
        $sources = [
            ['filter', 'rule'],
            ['nat', 'rule'],
            ['nat', 'onetoone'],
            ['nat', 'outbound', 'rule'],
        ];
        // os-firewall plugin paths
        $sources[] = ['OPNsense', 'Firewall', 'Filter', 'rules', 'rule'];
        $sources[] = ['OPNsense', 'Firewall', 'Filter', 'snatrules', 'rule'];
        $sources[] = ['OPNsense', 'Firewall', 'Filter', 'npt', 'rule'];

        foreach ($sources as $aliasref) {
            $cfgsection = Config::getInstance()->object();
            foreach ($aliasref as $cfgName) {
                if ($cfgsection != null) {
                    $cfgsection = $cfgsection->$cfgName;
                }
            }
            if ($cfgsection != null) {
                $idx = 0;
                foreach ($cfgsection as $node) {
                    $tmp = $node->attributes();
                    if (!empty($tmp) && !empty($tmp['uuid'])) {
                        yield sprintf("%s.%s", implode('.', $aliasref), $tmp['uuid']) => $node;
                    } else {
                        yield sprintf("%s.%d", implode('.', $aliasref), $idx) => $node;
                    }
                    $idx++;
                }
            }
        }
    }

    /**
     * refactor group usage (replace group in rules)
     */
    public function refactor($oldname, $newname)
    {
        $has_changed = false;
        foreach ($this->ruleIterator() as $node) {
            $interfaces = explode(",", (string)$node->interface);
            if (in_array($oldname, $interfaces)) {
                  unset($interfaces[array_search((string)$oldname, $interfaces)]);
                  $interfaces[] = $newname;
                  $node->interface = implode(",", $interfaces);
                  $has_changed = true;
            }
            foreach (['source', 'destination'] as $net) {
                if (!empty($node->$net) && !empty($node->$net->network) && (string)$node->$net->network == $oldname) {
                    $node->$net->network = $newname;
                    $has_changed = true;
                }
            }
        }
        return $has_changed;
    }

    /**
     * is group used
     */
    public function whereUsed($name)
    {
        $result = [];
        foreach ($this->ruleIterator() as $path => $node) {
            $isUsed = false;
            if (in_array($name, explode(",", (string)$node->interface))) {
                $isUsed = true;
            }
            foreach (['source', 'destination'] as $net) {
                if (!empty($node->$net) && !empty($node->$net->network) && (string)$node->$net->network == $name) {
                    $isUsed = true;
                }
            }
            if ($isUsed) {
                $result[$path] = !empty($node->descr) ? (string)$node->descr : "";
            }
        }
        return $result;
    }
}
