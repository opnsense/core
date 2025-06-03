<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Firewall\Api;

class PortForwardController extends FilterBaseController
{
    protected static $categorysource = "portforward.rule";

    public function searchRuleAction()
    {
        $category = $this->request->get('category');
        $filter_funct = function ($record) use ($category) {
            return empty($category) || array_intersect(explode(',', $record->categories), $category);
        };
        return $this->searchBase("portforward.rule", null, "sequence", $filter_funct);
    }

    public function setRuleAction($uuid)
    {
        $old_rule_id = (string)$this->getModel()->getNodeByReference('portforward.rule.' . $uuid)->filter_rule;
        $result = $this->setBase("rule", "portforward.rule", $uuid);
        if($result['result'] == 'saved') {
            $filter_rule = $this->request->get('rule')['filter_rule'];
            $node = $this->getModel()->getNodeByReference('portforward.rule.' . $uuid);
            $destination_net = isset($this->request->get('rule')['target']) ? $this->request->get('rule')['target'] : $node->target;
            $destination_port = isset($this->request->get('rule')['target_port']) ? $this->request->get('rule')['target_port'] : $node->target_port;
            $floating = $quick = 0;
            if(count(explode(",", $this->request->get('rule')['interface'])) > 1) {
                $floating = $quick = 1;
            }
            $overlay = [
                'filter_rule' => (string)$node->filter_rule,
                'destination_net' => $destination_net,
                'destination_port' => $destination_port,
                'floating' => $floating,
                'quick' => $quick
            ];
            if (strpos($filter_rule, 'nat_') !== FALSE) {
                $exist = 0;
                foreach ($this->getModel()->firewallrules->rule->iterateItems() as $items) {
                    if ((string)$items->filter_rule==$filter_rule) {
                        $exist++;
                        $result['rules_update'] = $this->setBase("rule", "firewallrules.rule", (string)$items->getAttributes()['uuid'], $overlay);
                        break;
                    }
                }
                if ($exist==0) {
                    $result['rules_add'] = $this->addBase("rule", "firewallrules.rule", $overlay);
                }
            } elseif ($filter_rule=='add_associated' || $filter_rule=='add_unassociated') {
                $result['rules_add'] = $this->addBase("rule", "firewallrules.rule", $overlay);
            } else {
                foreach ($this->getModel()->firewallrules->rule->iterateItems() as $items) {
                    if ((string)$items->filter_rule==$old_rule_id) {
                        $result['rules_delete'] = $this->delBase("firewallrules.rule", (string)$items->getAttributes()['uuid']);
                        break;
                    }
                }
            }
        }
        return $result;
    }

    public function addRuleAction()
    {
        $result = $this->addBase("rule", "portforward.rule");
        if ($result) {
            $filter_rule = $this->request->get('rule')['filter_rule'];
            if ($filter_rule=='add_associated' || $filter_rule=='add_unassociated') {
                $node = $this->getModel()->getNodeByReference('portforward.rule.' . $result['uuid']);
                $destination_net = $this->request->get('rule')['target'];
                $destination_port = $this->request->get('rule')['target_port'];
                $floating = $quick = 0;
                if(count(explode(",", $this->request->get('rule')['interface'])) > 1) {
                    $floating = $quick = 1;
                }
                $overlay = [
                    'filter_rule' => (string)$node->filter_rule,
                    'destination_net' => $destination_net,
                    'destination_port' => $destination_port,
                    'floating' => $floating,
                    'quick' => $quick
                ];
                $this->addBase("rule", "firewallrules.rule", $overlay);
            }
            return $result;
        }
    }

    public function getRuleAction($uuid = null)
    {
        $fetchmode = $this->request->has("fetchmode") ? $this->request->get("fetchmode") : null;
        $result = $this->getBase("rule", "portforward.rule", $uuid);
        if (!empty($uuid)) {
            $node = $this->getModel()->getNodeByReference('portforward.rule.' . $uuid);
            $filter_rule = (string)$node->filter_rule;
            $filter_array = $result['rule']['filter_rule'];
            if (!empty($filter_rule) && $filter_rule!=='pass' && $fetchmode!='copy') {
                unset($filter_array['add_associated'], $filter_array['add_unassociated']);
                $filter_array[$filter_rule] = ["value" => "Rule ".$result['rule']['description'], 'selected' => 1];
            } elseif ($fetchmode=='copy') {
                $filter_array['add_associated']['selected'] = 1;
            }
            $result['rule']['filter_rule'] = $filter_array;
        }
        return $result;
    }

    public function delRuleAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('portforward.rule.' . $uuid);
        $filter_rule = (string)$node->filter_rule;
        $result = $this->delBase("portforward.rule", $uuid);
        if ($result && $filter_rule!='' && $filter_rule!='pass') {
            foreach ($this->getModel()->firewallrules->rule->iterateItems() as $items) {
                if ((string)$items->filter_rule==$filter_rule) {
                    $this->delBase("firewallrules.rule", (string)$items->getAttributes()['uuid']);
                    break;
                }
            }
        }
        return $result;
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        $result = $this->toggleBase("portforward.rule", $uuid, $enabled);
        if($result['changed'] == true) {
            if($result['result'] == 'Enabled') {
                $enabled = 1;
            } else {
                $enabled = 0;
            }
            $filter_rule = (string)$this->getModel()->getNodeByReference('portforward.rule.' . $uuid)->filter_rule;
            foreach ($this->getModel()->firewallrules->rule->iterateItems() as $node) {
                if ((string)$node->filter_rule==$filter_rule) {
                    $result['rules'] = $this->toggleBase("firewallrules.rule", (string)$node->getAttributes()['uuid'], $enabled);
                    break;
                }
            }
        }
        return $result;
    }
}
