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
use OPNsense\Diagnostics\Api\InterfaceController;

class FilterController extends FilterBaseController
{
    protected static $categorysource = "rules.rule";

    /**
     * This function retrieves and merges firewall rules from two sources:
     *   1. The persistent model rules (defined in the filter.xml model for "rules.rule").
     *   2. Legacy internal rules (obtained via the getInternalRulesAction() method for various types).
     *
     * The merged result is then paginated and returned to the client.
     *
     * Key challenges and workarounds:
     *
     * - **Pagination Conflict:**
     *   The built-in searchBase() method automatically applies pagination based on request
     *   parameters, which causes issues when merging with additional rule sets (internal rules).
     *   To work around this, we wrap the original request in a dummy object that forces the
     *   rowCount to -1 and current page to 1, thereby disabling pagination while fetching
     *   the full set of persistent model rules.
     *
     * - **Field Auto-Detection:**
     *   UIModelGrid expects an array of fields (i.e. columns) to work with. If no explicit field list
     *   is provided, we attempt to auto-detect the fields by iterating over the first node in the
     *   ArrayField. This ensures that the grid has the necessary metadata to render the rules.
     *
     * - **Merging Data Sources:**
     *   After retrieving the persistent model rules and internal rules (which can be filtered by category),
     *   the arrays are merged. Optionally, if the request parameter 'include_internal' is set, the internal
     *   rules are filtered further by the same category filter before merging.
     *
     * - **Reapplying Pagination:**
     *   Once the data is merged, we call searchRecordsetBase() to reapply the original pagination, sorting,
     *   and searching parameters to the combined rule set.
     *
     * @return array The final paginated, merged result.
     */
    public function searchRuleAction()
    {
        // 1. Read and normalize the category filter
        $category = $this->request->get('category');
        if (!empty($category) && !is_array($category)) {
            $category = array_map('trim', explode(',', $category));
        }

        // Read the selected interface filter from the request
        $selectedInterface = $this->request->get('interface');

        // 2. Define filter for model rules including interface filter
        $modelFilter = function ($record) use ($category, $selectedInterface) {
            if (!empty($selectedInterface)) {
                // Retrieve the configuration object.
                $config = Config::getInstance()->object();
                $resolvedKey = null;
                // Iterate over configured interfaces.
                foreach ($config->interfaces->children() as $key => $node) {
                    // Compare the physical interface name stored in <if>
                    if ((string)$node->if === $selectedInterface) {
                        $resolvedKey = (string)$key;
                        break;
                    }
                }
                // If no matching key is found or it doesn't match the rule's interface, filter out this record.
                if ($resolvedKey === null || ((string)$record->interface !== $resolvedKey)) {
                    return false;
                }
            }
            // Apply category filter if specified.
            if (!empty($category)) {
                $cats = array_map('trim', explode(',', (string)$record->categories));
                if (!(bool) array_intersect($cats, $category)) {
                    return false;
                }
            }
            return true;
        };

        // 3. Disable pagination: force 'rowCount = -1' and 'current = 1'
        $dummyRequest = new class($this->request) {
            private $origReq;
            public function __construct($req) {
                $this->origReq = $req;
            }
            public function get($key, $default = null) {
                if ($key === 'rowCount') { return -1; }
                if ($key === 'current')  { return 1; }
                $value = $this->origReq->get($key, $default);
                return $value === null ? '' : $value;
            }
            public function __call($name, $args) {
                return call_user_func_array([$this->origReq, $name], $args);
            }
        };

        // 4. Fetch all model rules via UIModelGrid
        $element = $this->getModel();
        foreach (explode('.', 'rules.rule') as $step) {
            $element = $element->{$step};
        }
        $fields = null;
        if ((is_a($element, ArrayField::class) || is_subclass_of($element, ArrayField::class))
            && empty($fields)) {
            $fields = [];
            foreach ($element->iterateItems() as $node) {
                foreach ($node->iterateItems() as $key => $val) {
                    $fields[] = $key;
                }
                break;
            }
        }
        $grid = new UIModelGrid($element);
        $modelData = $grid->fetchBindRequest($dummyRequest, $fields, "sequence", $modelFilter);
        $modelRules = $modelData['rows'] ?? [];

        // 5. Check if internal rules should be included in result, we get them from a selectpicker and request handler
        $includeInternalStr = $this->request->get('include_internal', 'string');
        $internalRules = [];
        if (!empty($includeInternalStr)) {
            $includeInternal = array_map('trim', explode(',', $includeInternalStr));

            foreach ($includeInternal as $ruleType) {
                $internalData = $this->getInternalRulesAction($ruleType);
                if ($internalData['status'] === "ok") {
                    $internalRules = array_merge($internalRules, $internalData['rules'] ?? []);
                }
            }

            // Apply category filter to internal rules if applicable
            if (!empty($category)) {
                $internalRules = array_filter($internalRules, function ($rule) use ($category) {
                    $cats = array_map('trim', explode(',', (string)$rule['categories']));
                    return (bool) array_intersect($cats, $category);
                });
            }
            // Apply interface filter to internal rules if provided.
            if (!empty($selectedInterface)) {
                $internalRules = array_filter($internalRules, function ($rule) use ($selectedInterface) {
                    return ((string)$rule['interface'] === $selectedInterface);
                });
            }
        }

        // 6. Merge model rules + (optionally) internal rules, then apply pagination
        $merged = array_merge($modelRules, $internalRules);
        $result = $this->searchRecordsetBase($merged, $fields, "sequence");

        return $result;
    }

    /**
     * Retrieves internal firewall rules based on the specified rule type.
     *
     * Fetches rules from legacy filter with "filter get_internal_rules %s"
     * It normalizes the rule data, ensuring that required fields are populated with defaults when missing.
     *
     * @param string|null $ruleType Optional rule type to fetch (e.g., 'internal', 'internal2', 'floating').
     *                              If not provided, it is determined from the request or defaults to 'internal'.
     *
     * @return array An associative array containing:
     *               - "status": A string indicating success ("ok") or failure ("error").
     *               - "rules": An array of normalized firewall rules (on success).
     *               - "message": An error message if decoding the rules fails.
     */
    public function getInternalRulesAction($ruleType = null)
    {
        // Use the provided type or the one from the request.
        if ($ruleType === null) {
            $ruleType = $this->request->get('type');
        }
        if (empty($ruleType)) {
            $ruleType = 'internal';
        }

        // Set default sequence based on rule type. "internal2" rules are at end of ruleset.
        $defaultSequence = ($ruleType === 'internal2') ? 1000000 : 0;

        $backend = new Backend();
        $rawOutput = trim($backend->configdRun("filter get_internal_rules " . escapeshellarg($ruleType)));
        $data = json_decode($rawOutput, true);

        if ($data === null) {
            return [
                "status"  => "error",
                "message" => "Failed to decode firewall rules output."
            ];
        }

        // Template for expected keys and their default values.
        $template = [
            'uuid'              => '',
            'enabled'           => '1', // default assumes rule is enabled unless marked disabled
            'disablereplyto'    => '0',
            'label'             => '',
            'statetype'         => '',
            'state-policy'      => '',
            'quick'             => '0',
            'interfacenot'      => '0',
            'interface'         => '',
            'direction'         => '',
            'ipprotocol'        => '',
            'action'            => 'Pass',
            'protocol'          => 'any',
            'source_net'        => 'any',
            'source_not'        => '0',
            'source_port'       => '',
            'destination_net'   => 'any',
            'destination_not'   => '0',
            'destination_port'  => '',
            'gateway'           => 'None',
            'replyto'           => 'None',
            'log'               => '0',
            'allowopts'         => '0',
            'nosync'            => '0',
            'nopfsync'          => '0',
            'statetimeout'      => '',
            'max-src-nodes'     => '',
            'max-src-states'    => '',
            'max-src-conn'      => '',
            'max'               => '',
            'max-src-conn-rate' => '',
            'max-src-conn-rates'=> '',
            'overload'          => '',
            'adaptivestart'     => '',
            'adaptiveend'       => '',
            'prio'              => 'Any priority',
            'set-prio'          => 'Keep current priority',
            'set-prio-low'      => 'Keep current priority',
            'tag'               => '',
            'tagged'            => '',
            'tcpflags1'         => '',
            'tcpflags2'         => '',
            'categories'        => '',
            'sched'             => 'None',
            'tos'               => 'Any',
            'shaper1'           => '',
            'shaper2'           => '',
            'description'       => '',
            'type'              => '',
            '#ref'              => '',
            '#priority'         => '',
            'sequence'          => $defaultSequence
        ];

        $normalizedRules = [];
        foreach ($data as $rule) {
            $newRule = [];
            foreach ($template as $key => $default) {
                // Determine the source key based on legacy mappings.
                $sourceKey = $key;
                if ($key === 'replyto' && !isset($rule[$key]) && isset($rule['reply-to'])) {
                    $sourceKey = 'reply-to';
                }
                if ($key === 'description' && !isset($rule[$key]) && isset($rule['descr'])) {
                    $sourceKey = 'descr';
                }
                if ($key === 'source_net' && !isset($rule[$key]) && isset($rule['from'])) {
                    $sourceKey = 'from';
                }
                if ($key === 'source_port' && !isset($rule[$key]) && isset($rule['from_port'])) {
                    $sourceKey = 'from_port';
                }
                if ($key === 'destination_net' && !isset($rule[$key]) && isset($rule['to'])) {
                    $sourceKey = 'to';
                }
                if ($key === 'destination_port' && !isset($rule[$key]) && isset($rule['to_port'])) {
                    $sourceKey = 'to_port';
                }

                // Initialize the value from the source or default.
                $value = array_key_exists($sourceKey, $rule) ? $rule[$sourceKey] : $default;

                // Remap "enabled" using "disabled" if available.
                if ($key === 'enabled' && isset($rule['disabled'])) {
                    $value = $rule['disabled'] ? '0' : '1';
                }

                // Rewrite ipprotocol values inline and wrap with lang() for user-exposed text.
                if ($key === 'ipprotocol') {
                    switch ($value) {
                        case 'inet':
                            $value = gettext('IPv4');
                            break;
                        case 'inet6':
                            $value = gettext('IPv6');
                            break;
                        case 'inet46':
                            $value = gettext('IPv4+IPv6');
                            break;
                        case '':
                            $value = gettext('IPv4');
                            break;
                    }
                }

                $newRule[$key] = $value;
            }

            // Set the UUID based on the rule type if not already provided.
            if (empty($newRule['uuid'])) {
                $newRule['uuid'] = $ruleType;
            }
            if (empty($newRule['sequence'])) {
                $newRule['sequence'] = $defaultSequence;
            }

            // Skip the internal rule if it is not disabled. This skips a ton of disabled bogon interface rules
            // These are generated for any interface but kept disabled until enabled.
            if ($newRule['enabled'] !== '1') {
                continue;
            }

            $normalizedRules[] = $newRule;
        }

        return [
            "status" => "ok",
            "rules"  => $normalizedRules
        ];
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
        \OPNsense\Core\Config::getInstance()->save();

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

    /**
     * Retrieve the list of available network interfaces and format them for use in a selectpicker.
     *
     * @return array An array of interfaces, where each entry contains:
     *               - 'value' => raw interface name (e.g., "em0", "igb1")
     *               - 'label' => interface description (e.g., "LAN", "WAN")
     */
    public function getInterfaceListAction()
    {
        $interfaces = (new InterfaceController())->getInterfaceNamesAction();

        $selectpicker = [
            ['value' => '', 'label' => 'Any']
        ];

        foreach ($interfaces as $if => $descr) {
            $selectpicker[] = ['value' => $if, 'label' => $descr];
        }

        return $selectpicker;
    }

}
