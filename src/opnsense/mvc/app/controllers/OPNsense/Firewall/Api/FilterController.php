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
use OPNsense\Firewall\Util;


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
     * Iterates through rules and appends alias flag arrays.
     *
     * @param array &$rows The rules array to process.
     */
    private function addAliasFlags(array &$rows)
    {
        $aliasFields = ['source_net', 'source_port', 'destination_net', 'destination_port'];
        foreach ($rows as &$row) {
            foreach ($aliasFields as $field) {
                if (!empty($row[$field])) {
                    $values = array_map('trim', explode(',', $row[$field]));
                    $row["is_alias_{$field}"] = array_map(function ($value) {
                        return Util::isAlias($value);
                    }, $values);
                }
            }
        }
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
            $rawOutput = (new Backend())->configdRun("filter list non_mvc_rules") ?? '';
            error_log("Raw output from configdRun: " . $rawOutput);
            $otherrules = json_decode((new Backend())->configdRun("filter list non_mvc_rules") ?? '', true) ?? [];
        } else {
            $otherrules = [];
        }

        $_REQUEST = $ORG_REQ; /* XXX:  fix me ?*/
        $result = $this->searchRecordsetBase(array_merge($otherrules, $filterset), null, "sort_order", $filter_funct_rs);

        /* frontend can format aliases with an alias icon */
        $this->addAliasFlags($result['rows']);

        return $result;
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
     * Uses integer gap numbering to update the sequence for only the moved rule.
     * If no gap exists between the target and its previous rule, all rules are reordered
     * by an increment of 100 starting from 100.
     *
     * Floating, Group, and Interface rules cannot be moved before another.
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
        $selectedRule = $mdl->getNodeByReference("rules.rule.$selected_uuid");
        $targetRule   = $mdl->getNodeByReference("rules.rule.$target_uuid");

        if (!$selectedRule || !$targetRule) {
            return ["status" => "error", "message" => gettext("Rule not found")];
        }

        // Check if the first digit of sort_order is the same
        $selectedSortOrder = (string)$selectedRule->sort_order;
        $targetSortOrder   = (string)$targetRule->sort_order;
        $selectedTypeDigit = substr($selectedSortOrder, 0, 1);
        $targetTypeDigit   = substr($targetSortOrder, 0, 1);

        if ($selectedTypeDigit !== $targetTypeDigit) {
            $typeNames = [
                '2' => gettext("Floating"),
                '3' => gettext("Group"),
                '4' => gettext("Interface")
            ];
            $selectedType = $typeNames[$selectedTypeDigit] ?? gettext("Unknown");
            $targetType   = $typeNames[$targetTypeDigit] ?? gettext("Unknown");
            return [
                "status"  => "error",
                "message" => sprintf(
                    gettext("Cannot move '%s Rule' before '%s Rule'."),
                    $selectedType,
                    $targetType
                )
            ];
        }

        // Build an array of rules with their sequence values.
        $rules = [];
        foreach ($mdl->rules->rule->iterateItems() as $uuid => $rule) {
            $seq = $rule->sequence;
            if (is_object($seq)) {
                if (method_exists($seq, '__toString')) {
                    $seq = (string)$seq;
                } elseif (property_exists($seq, 'value')) {
                    $seq = $seq->value;
                } else {
                    $seq = 0;
                }
            }
            $rules[$uuid] = is_numeric($seq) ? (int)$seq : 0;
        }

        asort($rules);
        $sortedUUIDs     = array_keys($rules);
        $sortedSequences = array_values($rules);

        // Remove the selected rule from the sorted arrays.
        if (($key = array_search($selected_uuid, $sortedUUIDs)) !== false) {
            unset($sortedUUIDs[$key], $sortedSequences[$key]);
            $sortedUUIDs     = array_values($sortedUUIDs);
            $sortedSequences = array_values($sortedSequences);
        }

        if (($targetIndex = array_search($target_uuid, $sortedUUIDs)) === false) {
            return ["status" => "error", "message" => gettext("Target rule not found in list")];
        }

        // Determine the new integer sequence for the moved rule.
        $newSequence = null;
        if ($targetIndex === 0) {
            $targetSequence = $sortedSequences[0];
            if ($targetSequence > 1) {
                $newSequence = $targetSequence - 1;
            }
        } else {
            $prevSequence   = $sortedSequences[$targetIndex - 1];
            $targetSequence = $sortedSequences[$targetIndex];
            if (($targetSequence - $prevSequence) > 1) {
                $newSequence = $prevSequence + (int)floor(($targetSequence - $prevSequence) / 2);
                if ($newSequence <= $prevSequence) {
                    $newSequence = $prevSequence + 1;
                }
            }
        }

        // If no space is left, reorder all rules with an increment of 100, starting from 100.
        if ($newSequence === null || $newSequence >= $targetSequence) {
            $increment = 100;
            array_splice($sortedUUIDs, $targetIndex, 0, [$selected_uuid]);
            $newSequence = 100;
            foreach ($sortedUUIDs as $uuid) {
                $mdl->rules->rule->$uuid->sequence = (string)$newSequence;
                $newSequence += $increment;
                if ($newSequence > 999999) {
                    return [
                        "status"  => "error",
                        "message" => gettext("Cannot renumber rules without exceeding the maximum sequence limit")
                    ];
                }
            }
            $mdl->serializeToConfig();
            Config::getInstance()->save();
            return [
                "status"  => "ok",
                "message" => gettext("Rules reordered due to lack of gaps.")
            ];
        }

        if ($newSequence > 999999) {
            return [
                "status"  => "error",
                "message" => gettext("Cannot move the rule as it would exceed the sequence limit of 999999.")
            ];
        }

        // Set the new sequence for the selected rule and save changes.
        $mdl->rules->rule->$selected_uuid->sequence = (string)$newSequence;
        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return ["status" => "ok"];
    }

    /**
     * Retrieve the next available filter sequence number.
     * It returns the highest number + 100.
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
        $nextSequence = $max + 100;

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
