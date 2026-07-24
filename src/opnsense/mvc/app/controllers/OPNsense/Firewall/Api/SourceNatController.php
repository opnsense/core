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

use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class SourceNatController extends FilterBaseController
{
    protected static $categorysource = "snatrules.rule";

    /**
     * set/get only affect general settings
     */
    public function setAction()
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $mdl = $this->getModel();
            $mdl->general->setNodes($this->request->getPost('filter')['general'] ?? []);
            $result = $this->validate($mdl->general, 'filter.general');
            if (empty($result['result'])) {
                return $this->save(false, true);
            }
        }
        return $result;
    }

    public function getAction()
    {
        $data = parent::getAction();
        return [
            self::$internalModelName => [
                'general' => $data[self::$internalModelName]['general']
            ]
        ];
    }

    private function getAutomaticOutboundNatRules(): array
    {
        $automatic_rules = (json_decode((new Backend())->configdRun('filter list automatic_outbound_nat'), true))['pf'] ?? [];
        $config = Config::getInstance()->object();
        $rows = [];
        $sequence = 1;

        foreach ($automatic_rules as $interface => $source_networks) {
            $descr = (string)($config->interfaces->{$interface}->descr ?? strtoupper($interface));
            $source_net = implode(',', array_keys($source_networks));
            $rows[] = [
                'uuid' => 'automatic_isakmp_' . $interface,
                'enabled' => '1',
                'sequence' => (string)$sequence,
                'interface' => $interface,
                '%interface' => $descr,
                'ipprotocol' => 'inet',
                '%ipprotocol' => 'IPv4',
                'protocol' => 'any',
                'source_net' => $source_net,
                'destination_port' => '500',
                'staticnatport' => '1',
                'description' => gettext('Auto created rule for ISAKMP'),
                'is_automatic' => true,
                'sort_order' => sprintf('%d.0%06d', 500000, $sequence),
                'prio_group' => '500000',
            ];
            $sequence++;
            $rows[] = [
                'uuid' => 'automatic_' . $interface,
                'enabled' => '1',
                'sequence' => (string)$sequence,
                'interface' => $interface,
                '%interface' => $descr,
                'ipprotocol' => 'inet',
                '%ipprotocol' => 'IPv4',
                'protocol' => 'any',
                'source_net' => $source_net,
                'staticnatport' => '0',
                'description' => gettext('Auto created rule'),
                'is_automatic' => true,
                'sort_order' => sprintf('%d.0%06d', 500000, $sequence),
                'prio_group' => '500000',
            ];
            $sequence++;
        }

        return $rows;
    }

    // Return changes on configured snat_mode
    // - disabled: ""
    // - hybrid: automatic and manual rules
    // - advanced (manual): manual rules
    // - automatic: automatic rules
    public function searchRuleAction()
    {
        $category = (array)$this->request->get('category');
        $mode = $this->getModel()->general->snat_mode->getValue();
        $allrules = [];

        if (in_array($mode, ['hybrid', 'advanced'], true)) {
            foreach ($this->getModel()->snatrules->rule->iterateItems() as $key => $node) {
                $allrules[] = array_merge(['uuid' => $key], $node->getNodeContent());
            }
        }

        if (in_array($mode, ['automatic', 'hybrid'], true)) {
            $allrules = array_merge($allrules, $this->getAutomaticOutboundNatRules());
        }

        $filter_funct = function (&$record) use ($category) {
            /* categories are indexed by name in the record, but offered as uuid in the selector */
            $catids = !empty($record['categories']) ? explode(',', $record['categories']) : [];

            /* offer list of colors to be used by the frontend */
            $record['category_colors'] = $this->getCategoryColors(
                !empty($record['categories']) ? explode(',', $record['categories']) : []
            );
            /* format "networks" and ports */
            foreach (['source_net','source_port','destination_net','destination_port', 'target', 'target_port'] as $field) {
                if (!empty($record[$field])) {
                    $record["alias_meta_{$field}"] = $this->getNetworks($record[$field]);
                }
            }
            // Always show the automatic rules, even when a category is selected
            return !empty($record['is_automatic']) || empty($category) || array_intersect($catids, $category);
        };

        return $this->searchRecordsetBase(
            $allrules,
            null,
            "sort_order",
            $filter_funct,
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "snatrules.rule", $uuid);
    }

    public function addRuleAction()
    {
        return $this->addBase("rule", "snatrules.rule");
    }

    public function getRuleAction($uuid = null)
    {
        return $this->setCopySequence(
            $this->getBase("rule", "snatrules.rule", $uuid),
            $this->getModel()->snatrules->rule
        );
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("snatrules.rule", $uuid);
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("snatrules.rule", $uuid, $enabled);
    }

    public function moveRuleBeforeAction($selected_uuid, $target_uuid)
    {
        return $this->moveRuleBeforeBase($selected_uuid, $target_uuid, 'snatrules.rule', 'sequence');
    }

    public function toggleRuleLogAction($uuid, $log)
    {
        return $this->toggleRuleLogBase($uuid, $log, 'snatrules.rule');
    }

    public function downloadRulesAction()
    {
        return $this->downloadRulesBase('snatrules.rule', ['sort_order', 'prio_group']);
    }

    public function uploadRulesAction()
    {
        return $this->uploadRulesBase('snatrules.rule', ['sort_order', 'prio_group']);
    }
}
