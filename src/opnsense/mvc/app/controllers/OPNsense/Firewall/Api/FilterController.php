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

        // 2. Define filter for model rules
        $modelFilter = function ($record) use ($category) {
            if (empty($category)) {
                return true;
            }
            $cats = array_map('trim', explode(',', (string)$record->categories));
            return (bool) array_intersect($cats, $category);
        };

        // 3. Disable pagination: force 'rowCount = -1' and 'current = 1'
        $dummyRequest = new class($this->request) {
            private $origReq;
            public function __construct($req) { $this->origReq = $req; }
            public function get($key, $default = null) {
                if ($key === 'rowCount') { return -1; }
                if ($key === 'current')  { return 1; }
                return $this->origReq->get($key, $default);
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
        if ((is_a($element, "OPNsense\\Base\\FieldTypes\\ArrayField")
            || is_subclass_of($element, "OPNsense\\Base\\FieldTypes\\ArrayField"))
            && empty($fields)
        ) {
            $fields = [];
            foreach ($element->iterateItems() as $node) {
                foreach ($node->iterateItems() as $key => $val) {
                    $fields[] = $key;
                }
                break;
            }
        }
        $grid = new \OPNsense\Base\UIModelGrid($element);
        $modelData = $grid->fetchBindRequest($dummyRequest, $fields, "sequence", $modelFilter);
        $modelRules = $modelData['rows'] ?? [];

        // 5. Check if internal rules should be included in result, we get them from a selectpicker and request handler
        $includeInternalStr = $this->request->get('include_internal', 'string');
        $internalRules = [];
        if (!empty($includeInternalStr)) {
            // Convert the comma-separated string into an array
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
     * @param string|null $ruleType Optional rule type to fetch (e.g., 'internal', 'internal2').
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

        // Set default sequence based on rule type. "internal2" rules are at end of ruleset
        if ($ruleType === 'internal2') {
            $defaultSequence = 1000000;
        } else {
            $defaultSequence = 0;
        }

        $backend = new \OPNsense\Core\Backend();
        $rawOutput = trim($backend->configdRun("filter get_internal_rules " . escapeshellarg($ruleType)));
        $data = json_decode($rawOutput, true);

        if ($data === null) {
            return [
                "status" => "error",
                "message" => "Failed to decode firewall rules output."
            ];
        }

        $template = [
            'uuid'              => '',
            'enabled'           => '1',
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
                $sourceKey = $key;
                if ($key === 'replyto' && !isset($rule[$key]) && isset($rule['reply-to'])) {
                    $sourceKey = 'reply-to';
                }
                if ($key === 'description' && !isset($rule[$key]) && isset($rule['descr'])) {
                    $sourceKey = 'descr';
                }
                $newRule[$key] = array_key_exists($sourceKey, $rule) ? $rule[$sourceKey] : $default;
            }
            if (empty($newRule['uuid'])) {
                $newRule['uuid'] = 'internal';
            }
            if (empty($newRule['sequence'])) {
                $newRule['sequence'] = $defaultSequence;
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

    public function moveUpAction($uuid)
    {
        return $this->moveRule($uuid, -1);
    }

    public function moveDownAction($uuid)
    {
        return $this->moveRule($uuid, +1);
    }

    /**
     * Move a firewall rule either up (-1) or down (+1) in the sequence list.
     * This is done by swapping sequence numbers between two rules.
     *
     * @param string $uuid   The UUID of the rule to move
     * @param int    $offset -1 to move up, +1 to move down
     * @return array         ["status" => "ok"] on success, "error" otherwise
     */
    private function moveRule($uuid, int $offset)
    {
        if (!$this->request->isPost()) {
            return ["status" => "error"];
        }

        $mdl = $this->getModel();
        $ruleToMove = $mdl->getNodeByReference("rules.rule." . $uuid);
        if ($ruleToMove === null) {
            return ["status" => "error"];
        }

        // Gather all rules sorted by sequence
        $allRules = [];
        foreach ($mdl->rules->rule->iterateItems() as $key => $rule) {
            $allRules[] = [
                'uuid'     => $key,
                'sequence' => (int)(string)$rule->sequence
            ];
        }

        usort($allRules, fn($a, $b) => $a['sequence'] <=> $b['sequence']);

        // Find current rule in sorted list
        $currentIndex = array_search($uuid, array_column($allRules, 'uuid'));
        if ($currentIndex === false) {
            return ["status" => "error"];
        }

        // Calculate new index
        $newIndex = $currentIndex + $offset;
        if ($newIndex < 0 || $newIndex >= count($allRules)) {
            return ["status" => "error"];
        }

        // Get UUID of the target rule
        $targetUuid = $allRules[$newIndex]['uuid'];

        // Swap sequences
        $ruleA = $mdl->getNodeByReference("rules.rule." . $uuid);
        $ruleB = $mdl->getNodeByReference("rules.rule." . $targetUuid);
        [$ruleA->sequence, $ruleB->sequence] = [(string)$ruleB->sequence, (string)$ruleA->sequence];

        // Save changes
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

}
