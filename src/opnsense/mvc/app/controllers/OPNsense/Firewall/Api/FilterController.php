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
use OPNsense\Firewall\FilterLegacyMapper;
use OPNsense\Firewall\Util;

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
    public function getInternalRulesAction($ruleType = null)
    {
        // 1) Determine which type of internal rules to fetch
        if ($ruleType === null) {
            $ruleType = $this->request->get('type');
        }
        if (empty($ruleType)) {
            $ruleType = 'internal';
        }

        // 2) Fetch raw internal rules
        $backend = new Backend();
        $rawOutput = trim($backend->configdRun("filter get_internal_rules " . escapeshellarg($ruleType)));
        $data = json_decode($rawOutput, true);

        if ($data === null) {
            return [
                "status"  => "error",
                "message" => "Failed to decode firewall rules output."
            ];
        }

        // 3) Build a dynamic template from our model
        $template = $this->buildRuleTemplate();

        // 4) Normalize rules using the FilterLegacyMapper
        $mapper = new FilterLegacyMapper();
        $normalizedRules = $mapper->normalizeRules($data, $template, $ruleType);

        // 5) Filter out disabled rules
        $filteredRules = array_filter($normalizedRules, function ($rule) {
            return $rule['enabled'] !== '0';
        });

        return [
            "status" => "ok",
            "rules"  => $filteredRules
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
     * @param array|null  $category          The category filter.
     * @param string|null $selectedInterface The selected interface filter.
     * @param string|null $includeInternalStr Comma-separated string of internal rule types to include.
     *
     * @return array The final paginated, merged result.
     */
    private function fetchMergedRules($category, $selectedInterface, $includeInternalStr)
    {
        // 1. Define the model filter function
        $modelFilter = function ($record) use ($category, $selectedInterface) {
            if (!empty($selectedInterface)) {
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
                if ($resolvedKey === null) {
                    return false;
                }

                // Split the rule’s interface field into an array to support multiple interfaces.
                $ruleInterfaces = array_map('trim', explode(',', (string)$record->interface));

                // Check if the resolved key is in the array of interfaces.
                if (!in_array($resolvedKey, $ruleInterfaces)) {
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

        // 2. Disable pagination during fetch
        $dummyRequest = new class($this->request) {
            private $origReq;
            public function __construct($req) {
                $this->origReq = $req;
            }
            public function get($key, $default = null) {
                return $key === 'rowCount' ? -1 : ($key === 'current' ? 1 : $this->origReq->get($key, $default) ?? '');
            }
            public function __call($name, $args) {
                return call_user_func_array([$this->origReq, $name], $args);
            }
        };

        // 3. Fetch model rules
        $element = $this->getModel()->getNodeByReference("rules.rule");
        $fields = [];
        if ($element && (is_a($element, ArrayField::class) || is_subclass_of($element, ArrayField::class))) {
            foreach ($element->iterateItems() as $node) {
                foreach ($node->iterateItems() as $key => $val) {
                    $fields[] = $key;
                }
                break; // Only process the first rule for field extraction
            }
        }
        $grid = new UIModelGrid($element);
        $modelData = $grid->fetchBindRequest($dummyRequest, $fields, "sequence", $modelFilter);
        $modelRules = $modelData['rows'] ?? [];

        // 4. Fetch internal rules if requested
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

        // 5. Merge model and internal rules, then apply pagination
        $merged = array_merge($modelRules, $internalRules);
        $result = $this->searchRecordsetBase($merged, $fields, "sequence");

        // 6. Post process only internal rules (replace raw interface names with descriptions)
        // This must be done after filtering, or the filter will not match as it compares the original interface name
        $ifMap = (new InterfaceController())->getInterfaceNamesAction();

        // Only model rules will have a valid UUID
        function isValidUUID($uuid) {
            return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($uuid));
        }

        if (!empty($result['rows'])) {
            foreach ($result['rows'] as &$row) {
                if (!isValidUUID($row['uuid'])) {
                    $rawIf = $row['interface'];
                    if (isset($ifMap[$rawIf])) {
                        $row['interface'] = $ifMap[$rawIf];
                    }
                }
            }
            unset($row);
        }

        // 7. Determine if a rule has aliases for the alias formatter
        $aliasFields = ['source_net', 'source_port', 'destination_net', 'destination_port'];

        // For each rule, split the comma-separated field values and generate an alias flag array
        foreach ($result['rows'] as &$row) {
            foreach ($aliasFields as $field) {
                if (!empty($row[$field])) {
                    $values = array_map('trim', explode(',', $row[$field]));
                    $row["is_alias_{$field}"] = array_map(function ($value) {
                        return Util::isAlias($value);
                    }, $values);
                }
            }
        }
        unset($row);

        // 8. Add the evaluation, states, packets, and bytes fields to each rule
        $ruleStats = $this->getRuleStatistics();

        foreach ($result['rows'] as &$rule) {
            $ruleKey = !empty($rule['uuid']) ? $rule['uuid'] : ($rule['label'] ?? '');
            $stats = $ruleStats[$ruleKey] ?? [];

            $rule['evaluations'] = $stats['evaluations'] ?? '';
            $rule['states']      = $stats['states'] ?? '';
            $rule['packets']     = $stats['packets'] ?? '';
            $rule['bytes']       = $stats['bytes'] ?? '';
        }
        unset($rule);

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
        if (!empty($category) && !is_array($category)) {
            $category = array_map('trim', explode(',', $category));
        }

        $selectedInterface = $this->request->get('interface');
        $includeInternalStr = $this->request->get('include_internal', 'string');

        return $this->fetchMergedRules($category, $selectedInterface, $includeInternalStr);
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
