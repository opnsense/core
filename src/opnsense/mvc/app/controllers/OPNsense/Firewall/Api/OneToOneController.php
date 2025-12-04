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

use OPNsense\Base\UserException;
use OPNsense\Core\Config;

class OneToOneController extends FilterBaseController
{
    protected static $categorysource = "onetoone.rule";

    public function searchRuleAction()
    {
        $category = (array)$this->request->get('category');

        $filter_funct = function ($record) use ($category) {
            /* categories are indexed by name in the record, but offered as uuid in the selector */
            $catids = !empty((string)$record->categories) ? explode(',', (string)$record->categories) : [];
            return empty($category) || array_intersect($catids, $category);
        };

        $results = $this->searchBase("onetoone.rule", null, "sequence", $filter_funct);

        /* carry results */
        foreach ($results['rows'] as &$record) {
            /* offer list of colors to be used by the frontend */
            $record['category_colors'] = $this->getCategoryColors(
                !empty($record['categories']) ? explode(',', $record['categories']) : []
            );
            /* format "networks" and target */
            foreach (['source_net', 'destination_net'] as $field) {
                if (!empty($record[$field])) {
                    $record["alias_meta_{$field}"] = $this->getNetworks($record[$field]);
                }
            }
        }

        return $results;
    }

    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "onetoone.rule", $uuid);
    }

    public function addRuleAction()
    {
        return $this->addBase("rule", "onetoone.rule");
    }

    public function getRuleAction($uuid = null)
    {
        return $this->getBase("rule", "onetoone.rule", $uuid);
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("onetoone.rule", $uuid);
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("onetoone.rule", $uuid, $enabled);
    }

    /**
     * Moves the selected rule so that it appears immediately before the target rule.
     *
     * Uses integer gap numbering to update the sequence for only the moved rule.
     * Rules will be renumbered within the selected range to prevent movements causing overlaps,
     * but try to keep the changes as minimal as possible.
     *
     * @param string $selected_uuid The UUID of the rule to be moved.
     * @param string $target_uuid   The UUID of the target rule (the rule before which the selected rule is to be placed).
     * @return array Returns ["status" => "ok"] on success, throws a userexception otherwise.
     */
    public function moveRuleBeforeAction($selected_uuid, $target_uuid)
    {
        if (!$this->request->isPost()) {
            return ["status" => "error", "message" => gettext("Invalid request method")];
        }
        $target_node = $this->getModel()->getNodeByReference('onetoone.rule.' . $target_uuid);
        $selected_node = $this->getModel()->getNodeByReference('onetoone.rule.' . $selected_uuid);
        if ($target_node === null || $selected_node === null) {
            throw new UserException(
                gettext("Either source or destination is not a rule managed with this component"),
                gettext("DNat")
            );
        }
        $step_size = 50;
        $new_key = null;
        $prev_record = null;
        foreach ($this->getModel()->onetoone->rule->sortedBy(['sequence']) as $record) {
            $uuid = $record->getAttribute('uuid');
            if ($target_uuid === $uuid) {
                $prev_sequence = (($prev_record?->sequence->asFloat()) ?? 1);
                $distance = $record->sequence->asFloat() - $prev_sequence;
                if ($distance > 2) {
                    $new_key = intdiv($distance, 2) + $prev_sequence;
                    break;
                } else {
                    $new_key = $prev_record === null ? 1 : ($prev_sequence + $step_size);
                    $record->sequence = (string)($new_key + $step_size);
                }
            } elseif ($new_key !== null) {
                if ($record->sequence->asFloat() < $prev_record?->sequence->asFloat()) {
                    $record->sequence = (string)($prev_record?->sequence->asFloat() + $step_size);
                }
            }
            $prev_record = $record;
        }
        if ($new_key !== null) {
            $selected_node->sequence = (string)$new_key;
            /* we're only changing sequences, forcefully save */
            $this->getModel()->serializeToConfig(false, true);
            Config::getInstance()->save();
        }

        return ["status" => "ok"];
    }

    public function toggleRuleLogAction($uuid, $log)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('Invalid request method')];
        }

        $mdl = $this->getModel();
        $node = null;
        foreach ($mdl->onetoone->rule->iterateItems() as $item) {
            if ((string)$item->getAttribute('uuid') === $uuid) {
                $node = $item;
                break;
            }
        }

        if ($node === null) {
            throw new UserException(
                gettext("Rule not found"),
                gettext("Filter")
            );
        }

        $node->log = $log;
        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return ['status' => 'ok'];
    }
}
