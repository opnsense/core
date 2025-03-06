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
use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\UIModelGrid;
use OPNsense\Firewall\Util;
use OPNsense\Firewall\Group;

class FilterController extends FilterBaseController
{
    protected static $categorysource = "rules.rule";

    /**
     * Builds a rule template from the firewall model.
     *
     * @return array The template containing model keys and default values.
     */
    private function buildRuleTemplate()
    {
        $template = [];
        $model = $this->getModel();
        $ruleElement = $model->getNodeByReference("rules.rule");

        if (
            $ruleElement &&
            (is_a($ruleElement, ArrayField::class) || is_subclass_of($ruleElement, ArrayField::class))
        ) {
            foreach ($ruleElement->iterateItems() as $ruleItem) {
                foreach ($ruleItem->iterateItems() as $key => $field) {
                    $template[$key] = method_exists($field, 'getDefault') ? $field->getDefault() : '';
                }
                break; // Use only the first array item
            }
        }

        return $template;
    }

    /**
     * Retrieves internal firewall rules based on the specified rule type.
     *
     * Fetches rules from legacy filter with "filter get_internal_rules %s"
     * and passes them to the FilterLegacyMapper for normalization.
     *
     * @param string|null $ruleType Optional rule type to fetch (e.g., 'internal', 'internal2', 'floating').
     *                              If not provided, it is determined from the request or defaults to 'internal'.
     *
     * @return array An associative array containing:
     *               - "status": A string indicating success ("ok") or failure ("error").
     *               - "rules": An array of normalized firewall rules (on success).
     *               - "message": An error message if decoding the rules fails.
     */
    public function getInternalRulesAction()
    {
        // 1) Determine which type of internal rules to fetch
        // if ($ruleType === null) {
        //     $ruleType = $this->request->get('type');
        // }
        // if (empty($ruleType)) {
        //     $ruleType = 'internal';
        // }

        // 2) Fetch raw internal rules
        $backend = new Backend();
        $rawOutput = trim($backend->configdRun("filter get_internal_rules"));
        $data = json_decode($rawOutput, true);

        // if ($data === null) {
        //     return [
        //         "status"  => "error",
        //         "message" => "Failed to decode firewall rules output."
        //     ];
        // }

        // // 3) Build a dynamic template from our model
        // $template = $this->buildRuleTemplate();

        // // 4) Normalize rules using the FilterLegacyMapper
        // $mapper = new FilterLegacyMapper();
        // $normalizedRules = $mapper->normalizeRules($data, $template, $ruleType);

        // // 5) Filter out disabled rules
        // $filteredRules = array_filter($normalizedRules, function ($rule) {
        //     return $rule['enabled'] !== '0';
        // });

        return [
            "status" => "ok",
            "rules"  => $data
        ];
    }

    /**
     * return rule statistics
     * @return array statistics
     */
    private function getRuleStatistics()
    {
        $backend = new Backend();
        $output = $backend->configdRun("filter rule stats");
        $stats = json_decode($output, true);
        return is_array($stats) ? $stats : [];
    }

    /**
     * Retrieves interface groups and their member interfaces
     *
     * @return array An associative array where keys are group names and values are arrays of interface members.
     */
    private function getInterfaceGroups()
    {
        $result = [];

        // Load the firewall group model
        $model = new Group();

        // Ensure the ifgroupentry node exists
        if (!$model->ifgroupentry) {
            return $result;
        }

        // Iterate over each group entry
        foreach ($model->ifgroupentry->iterateItems() as $groupItem) {
            $groupName = (string)$groupItem->ifname;
            $members = [];

            if (!empty((string)$groupItem->members)) {
                $membersString = (string)$groupItem->members;
                $members = array_map('trim', explode(',', $membersString));
            }

            $result[$groupName] = $members;
        }

        return $result;
    }

    /**
     * Retrieves and merges firewall rules from model and internal sources, then paginates them.
     *
     * @return array The final paginated, merged result.
     */
    public function searchRuleAction()
    {
        $category = $this->request->get('category');
        $interface = $this->request->get('interface');

        $filter_funct = function ($record) use ($category, $interface) {
            if (is_array($record)) {
                $this_cat = $record['categories'] ?? [];
                $this_if = $record['interface'] ?? '';
            } else {
                $this_cat = $record->categories;
                $this_if = $record->interface;
            }

            $is_cat = empty($category) || array_intersect(explode(',', $this_cat), $category);
            /* XXX: needs work as an explicit interface also needs to include groups */
            $is_if =  empty($interface) || array_intersect(explode(',', $this_if), [$interface]);
            return $is_cat && $is_if;
        };
        $filterset = $this->searchBase("rules.rule", null, "sequence", $filter_funct)['rows'];
        /* XXX: make optional, internal and legacy rules */
        $otherrules = json_decode((new Backend())->configdRun("filter get_internal_rules") ?? [], true);

        return $this->searchRecordsetBase(array_merge($otherrules, $filterset), null, "sequence", $filter_funct);
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
