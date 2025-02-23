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

class FilterController extends FilterBaseController
{
    protected static $categorysource = "rules.rule";

    public function searchRuleAction()
    {
        $category = $this->request->get('category');
        $filter_funct = function ($record) use ($category) {
            return empty($category) || array_intersect(explode(',', $record->categories), $category);
        };
        return $this->searchBase("rules.rule", null, "sequence", $filter_funct);
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
}
