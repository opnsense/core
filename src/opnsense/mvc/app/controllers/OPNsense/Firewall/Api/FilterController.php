<?php

/*
 * Copyright (C) 2020-2025 Deciso B.V.
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

use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Category;
use OPNsense\Firewall\Group;


class FilterController extends FilterBaseController
{
    protected static $categorysource = "rules.rule";

    private function getFieldMap()
    {
        $result = ['category' => [], 'interface' => []];
        foreach ((new Category())->categories->category->iterateItems() as $key => $category) {
            $result['category'][$key] = (string)$category->name;
        }
        foreach ((Config::getInstance()->object())->interfaces->children() as $key => $ifdetail) {
            $descr = !empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($key);
            $result['interface'][$key] = $descr;
        }
        $result['action'] = [
            'pass' => 'Pass',
            'block' => 'Block',
            'reject' => 'Reject'
        ];

        $result['ipprotocol'] = [
            'inet' => gettext('IPv4'),
            'inet6' => gettext('IPv6'),
            'inet46' => gettext('IPv4+IPv6')
        ];

        return $result;
    }

    /**
     * Retrieves and merges firewall rules from model and internal sources, then paginates them.
     *
     * @return array The final paginated, merged result.
     */
    public function searchRuleAction()
    {
        $categories = $this->request->get('category');
        if (!empty($this->request->get('interface'))) {
            $interface = $this->request->get('interface');
            $interfaces = [$interface];
            /* add groups which contain the selected interface */
            foreach ((new Group())->ifgroupentry->iterateItems() as $groupItem) {
                if (in_array($interface, explode(',', (string)$groupItem->members))) {
                    $interfaces[] = (string)$groupItem->ifname;
                }
            }
        } else {
            $interfaces = null;
        }
        $show_all = !empty($this->request->get('show_all'));

        /* filter logic for mvc rules */
        $filter_funct_mvc = function ($record) use ($categories, $interfaces, $show_all) {
            $is_cat = empty($categories) || array_intersect(explode(',', $record->categories), $categories);
            $is_if =  empty($interfaces) || array_intersect(explode(',', $record->interface), $interfaces);
            $is_if = $is_if || $show_all && $record->interface->isEmpty();
            return $is_cat && $is_if;
        };

        /* filter logic for legacy and internal rules */
        $fieldmap = $this->getFieldMap();
        if ($show_all) {
            /* only query stats when fill info is requested */
            $rule_stats = json_decode((new Backend())->configdRun("filter rule stats") ?? '', true) ?? [];
        } else {
            $rule_stats = [];
        }

        $filter_funct_rs  = function (&$record) use ($categories, $interfaces, $show_all, $fieldmap, $rule_stats) {
            /* always merge stats when found */
            if (!empty($record['uuid']) && !empty($rule_stats[$record['uuid']])) {
                foreach ($rule_stats[$record['uuid']] as $key => $value) {
                    $record[$key] = $value;
                }
            }

            if (empty($record['legacy'])) {
                /* mvc already filtered */
                return true;
            }
            $is_cat = empty($categories) || array_intersect(explode(',', $record['category'] ?? ''), $categories);
            $is_if =  empty($interfaces) || array_intersect(explode(',', $record['interface'] ?? ''), $interfaces);
            $is_if = $is_if || $show_all && empty($record['interface']);
            if ($is_cat && $is_if) {
                /* translate/convert legacy fields before returning, similar to mvc handling */
                foreach ($fieldmap as $topic => $data) {
                    if (!empty($record[$topic])) {
                        $tmp = [];
                        foreach (explode(',', $record[$topic]) as $item) {
                            $tmp[] = $data[$item] ?? $item;
                        }
                        $record[$topic] = implode(',', $tmp);
                    }
                }
                return true;
            } else {
                return false;
            }
        };

        /**
         * XXX: fetch mvc results first, we need to collect all to ensure proper pagination
         *      as pagination is passed using the request, we need to reset it temporary here as we don't know
         *      which page we need (yet) and don't want to duplicate large portions of code.
         **/
        $ORG_REQ = $_REQUEST;
        unset($_REQUEST['rowCount']);
        unset($_REQUEST['current']);
        $filterset = $this->searchBase("rules.rule", null, "sort_order", $filter_funct_mvc)['rows'];

        /* only fetch internal and legacy rules when 'show_all' is set */
        if ($show_all) {
            $otherrules = json_decode((new Backend())->configdRun("filter list non_mvc_rules") ?? '', true) ?? [];
        } else {
            $otherrules = [];
        }

        $_REQUEST = $ORG_REQ; /* XXX:  fix me ?*/
        return $this->searchRecordsetBase(array_merge($otherrules, $filterset), null, "sort_order", $filter_funct_rs);
    }

    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "rules.rule", $uuid);
    }

    public function addRuleAction()
    {
        return $this->addBase("rule", "rules.rule");
    }

    public function getRuleAction($uuid = null)
    {
        return $this->getBase("rule", "rules.rule", $uuid);
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("rules.rule", $uuid);
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("rules.rule", $uuid, $enabled);
    }

    /**
     * Moves the selected rule so that it appears immediately before the target rule.
     *
     * Rcalculates the sequence numbers for all rules using a fixed increment.
     * If the new maximum sequence would exceed, the operation fails.
     *
     * @param string $selected_uuid The UUID of the rule to be moved.
     * @param string $target_uuid   The UUID of the target rule (the rule before which the selected rule is to be placed).
     * @return array Returns ["status" => "ok"] on success, or an error message otherwise.
     */
    public function moveRuleBeforeAction($selected_uuid, $target_uuid)
    {
        if (!$this->request->isPost()) {
            return ["status" => "error", "message" => gettext("Invalid request method")];
        }

        $mdl = $this->getModel();
        $selectedRule = $mdl->getNodeByReference("rules.rule." . $selected_uuid);
        $targetRule = $mdl->getNodeByReference("rules.rule." . $target_uuid);

        if ($selectedRule === null || $targetRule === null) {
            return ["status" => "error", "message" => gettext("Rule not found")];
        }

        // Build an array of rule UUIDs sorted by their current sequence (ascending order)
        $rules = [];
        foreach ($mdl->rules->rule->iterateItems() as $uuid => $rule) {
            $rules[$uuid] = (int)(string)$rule->sequence;
        }
        asort($rules);
        $sortedUUIDs = array_keys($rules);

        // Remove the selected rule from the sorted list.
        $sortedUUIDs = array_values(array_filter($sortedUUIDs, function($uuid) use ($selected_uuid) {
            return $uuid !== $selected_uuid;
        }));

        // Find the index of the target rule.
        $targetIndex = array_search($target_uuid, $sortedUUIDs);
        if ($targetIndex === false) {
            return ["status" => "error", "message" => gettext("Target rule not found in list")];
        }

        // Insert the selected rule before the target rule.
        array_splice($sortedUUIDs, $targetIndex, 0, [$selected_uuid]);

        // Use a fixed increment and check maximum sequence.
        $increment = 1;
        $totalRules = count($sortedUUIDs);
        $maxSeq = $increment * $totalRules;
        if ($maxSeq > 999999) {
            return ["status" => "error", "message" => gettext("Cannot renumber rules without exceeding the maximum sequence limit")];
        }

        // Renumber all rules with the new order.
        foreach ($sortedUUIDs as $index => $uuid) {
            $mdl->rules->rule->$uuid->sequence = (string)(($index + 1) * $increment);
        }

        // Save changes.
        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return ["status" => "ok"];
    }

    /**
     * Retrieve the next available filter sequence number.
     * It returns the highest number + 1.
     * This is matches how the logic of the FilterSequenceField would increment the sequence number.
     */
    public function getNextSequenceAction()
    {
        $sequences = [];
        $mdl = $this->getModel();

        foreach ($mdl->rules->rule->iterateItems() as $rule) {
            $value = (int)((string)$rule->sequence);
            if ($value > 0) {
                $sequences[] = $value;
            }
        }

        // If no sequences are found, start with base value
        $max = empty($sequences) ? 0 : max($sequences);
        $nextSequence = $max + 1;

        return ['status' => 'ok', 'sequence' => $nextSequence];
    }

    public function getInterfaceListAction()
    {
        $result = [
            ['value' => '', 'label' => 'Any']
        ];
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            $result[] = ['value' => $key, 'label' => empty($intf->descr) ? $key : (string)$intf->descr];
        }
        return $result;
    }

}
