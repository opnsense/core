<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

class DNatController extends FilterBaseController
{
    protected static $internalModelName = 'DNat';
    protected static $internalModelClass = 'OPNsense\\Firewall\\DNat';
    protected static $categorysource = 'rule';

    private array $export_ignore = [
        'sort_order',
        'prio_group',
        'source.address',
        'destination.address',
        'categories',
        'associated-rule-id',
        'created.username',
        'created.time',
        'created.description',
        'updated.username',
        'updated.time',
        'updated.description',
    ];

    /**
     * @inheritdoc
     */
    protected function setBaseHook($node)
    {
        $node->updated->time = sprintf('%0.2f', microtime(true));
        $node->updated->username = $this->getUserName();
        $node->updated->description = sprintf('%s made changes', $_SERVER['SCRIPT_NAME']);
        if ($node->created->time->isEmpty()) {
            $node->created->time = $node->updated->time;
            $node->created->username = $node->updated->username;
            $node->created->description = $node->updated->description;
        }
    }

    private function getAutomaticDestinationNatRules(): array
    {
        $automatic_rules = json_decode((new Backend())->configdRun('filter list automatic_destination_nat'), true) ?? [];
        $config = Config::getInstance()->object();
        $rows = [];
        $sequence = 1;

        foreach ($automatic_rules as $provider => $rules) {
            foreach ($rules as $rule) {
                $interface = $rule['interface'] ?? '';
                $descr = (string)($config->interfaces->{$interface}->descr ?? strtoupper($interface));

                $rows[] = [
                    'uuid' => sprintf('automatic_%s_%d', $provider, $sequence),
                    'ipprotocol' => $rule['ipprotocol'] ?? '',
                    '%ipprotocol' => ['inet' => 'IPv4', 'inet6' => 'IPv6'][$rule['ipprotocol'] ?? ''] ?? '*',
                    'protocol' => $rule['protocol'] ?? '',
                    '%protocol' => strtoupper($rule['protocol'] ?? ''),
                    'disabled' => '0',
                    'nordr' => !empty($rule['nordr']) ? '1' : '0',
                    'interface' => $interface,
                    '%interface' => $descr,
                    'source.network' => $rule['from'] ?? ($rule['source']['network'] ?? ''),
                    'source.port' => $rule['from_port'] ?? ($rule['source']['port'] ?? ''),
                    'source.not' => !empty($rule['from_not']) ? '1' : '0',
                    'destination.network' => $rule['to'] ?? ($rule['destination']['network'] ?? ''),
                    'destination.port' => $rule['to_port'] ?? ($rule['destination']['port'] ?? ''),
                    'destination.not' => !empty($rule['to_not']) ? '1' : '0',
                    'target' => $rule['target'] ?? '',
                    'local-port' => $rule['localport'] ?? '',
                    'natreflection' => $rule['natreflection'] ?? '',
                    'descr' => $rule['descr'] ?? '',
                    'is_automatic' => true,
                    // Automatic DNAT rule priority should be same as firewall automatic rules
                    'sort_order' => sprintf('%d.0%06d', 100000, $sequence),
                    'prio_group' => '100000',
                ];
                $sequence++;
            }
        }

        return $rows;
    }

    private function getConfiguredDestinationNatRules(): array
    {
        $rows = [];
        $configObj = Config::getInstance()->object();

        foreach ($this->getModel()->rule->iterateItems() as $uuid => $node) {
            $record = ['uuid' => $uuid];
            $reflen = strlen($node->__reference) + 1;

            /* flatten nested source/destination containers */
            foreach ($node->getFlatNodes() as $key => $field) {
                /* XXX: duplicate model-to-grid conversion from UIModelGrid, not ideal but works for now */
                $fieldname = substr($key, $reflen);
                $descr = $field->getDescription();
                $record[$fieldname] = $field->getValue();
                if ($record[$fieldname] != $descr) {
                    $record['%' . $fieldname] = $descr;
                }
            }

            // Normal DNAT rule priority should be same as firewall interface rules
            // This is only used for visualization to ensure the tabulator tree renders
            // rules in the correct order, similar to firewall rules.
            // It does not influence the processing order of the ruleset by sequence.
            $interfaces = !empty($record['interface']) ? explode(',', $record['interface']) : [];
            $has_interface = false;

            foreach ($interfaces as $interface) {
                if (isset($configObj?->interfaces?->$interface)) {
                    $has_interface = true;
                    break;
                }
            }

            // Default
            $priority = 400000;

            // Invalid rules (not applied by PF)
            if (!empty($interfaces) && !$has_interface) {
                $priority = 600000;
            }

            $record['sort_order'] = sprintf('%d.0%06d', $priority, (int)($record['sequence'] ?? 0));
            $record['prio_group'] = (string)$priority;

            $rows[] = $record;
        }

        return $rows;
    }

    public function searchRuleAction()
    {
        $category = (array)$this->request->get('category');

        /* combine before sorting and pagination */
        $allrules = array_merge(
            $this->getConfiguredDestinationNatRules(),
            $this->getAutomaticDestinationNatRules()
        );

        $filter_funct = function (&$record) use ($category) {
            /* categories are indexed by name in the record, but offered as uuid in the selector */
            $catids = !empty($record['categories']) ? explode(',', $record['categories']) : [];

            /* offer list of colors to be used by the frontend */
            $record['category_colors'] = $this->getCategoryColors(
                !empty($record['categories']) ? explode(',', $record['categories']) : []
            );

            /* format networks and ports */
            foreach (['source.network','source.port','destination.network','destination.port', 'target', 'local-port'] as $field) {
                if (!empty($record[$field])) {
                    $record["alias_meta_{$field}"] = $this->getNetworks($record[$field]);
                }
            }

            return empty($category) || array_intersect($catids, $category);
        };

        return $this->searchRecordsetBase(
            $allrules,
            null,
            'sort_order',
            $filter_funct,
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    public function setRuleAction($uuid)
    {
        /* prevent created metadata being overwritten or offered */
        if (is_array($_POST['rule']) && isset($_POST['rule']['created'])) {
            unset($_POST['rule']['created']);
        }
        return $this->setBase("rule", "rule", $uuid);
    }

    public function addRuleAction()
    {
        /* prevent created metadata being overwritten or offered */
        if (is_array($_POST['rule']) && isset($_POST['rule']['created'])) {
            unset($_POST['rule']['created']);
        }
        return $this->addBase("rule", "rule");
    }

    public function getRuleAction($uuid = null)
    {
        return $this->setCopySequence(
            $this->getBase("rule", "rule", $uuid),
            $this->getModel()->rule
        );
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("rule", $uuid);
    }

    /**
     * opposite toggle (disable instead of enable)
     */
    public function toggleRuleAction($uuid, $disabled = null)
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost() && $uuid != null) {
            Config::getInstance()->lock();
            $node = $this->getModel()->getNodeByReference('rule.' . $uuid);
            if ($node != null) {
                if (in_array($disabled, ['0', '1'])) {
                    $node->disabled = (string)$disabled;
                } else {
                    $node->disabled = (string)$node->disabled == '1' ? '0' : '1';
                }
                $result['result'] = $node->disabled->isEmpty() ? 'Enabled' : 'Disabled';
                $this->save(false, true);
            }
        }
        return $result;
    }

    public function moveRuleBeforeAction($selected_uuid, $target_uuid)
    {
        return $this->moveRuleBeforeBase($selected_uuid, $target_uuid, 'rule', 'sequence');
    }

    public function toggleRuleLogAction($uuid, $log)
    {
        return $this->toggleRuleLogBase($uuid, $log, 'rule');
    }

    public function downloadRulesAction()
    {
        return $this->downloadRulesBase('rule', $this->export_ignore);
    }

    public function uploadRulesAction()
    {
        return $this->uploadRulesBase('rule', $this->export_ignore);
    }
}
