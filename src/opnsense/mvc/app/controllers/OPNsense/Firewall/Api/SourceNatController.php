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
use OPNsense\Firewall\Util;

class SourceNatController extends FilterBaseController
{
    protected static $categorysource = "snatrules.rule";

    public function setGeneralAction()
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

    // XXX: These are synthetic, display only for user convenience.
    //      The backend should generate them in the same way, but there is no relation to this.
    private function getAutomaticOutboundNatRules(): array
    {
        $config = Config::getInstance()->object();
        $source_networks = [];
        $nat_interfaces = [];

        if (!isset($config->interfaces)) {
            return [];
        }

        foreach ($config->interfaces->children() as $if => $ifcfg) {
            $if = (string)$if;
            $ipaddr = (string)($ifcfg->ipaddr ?? '');
            $gateway = (string)($ifcfg->gateway ?? '');
            $device = (string)($ifcfg->if ?? '');
            $descr = (string)($ifcfg->descr ?? '');

            if ($descr === '') {
                $descr = strtoupper($if);
            }

            /*
             * Only IPv4 interfaces participate in the automatic outbound NAT preview.
             * DHCP counts as IPv4 for WAN style interfaces.
             */
            $has_ipv4 = $ipaddr === 'dhcp' || Util::isIpv4Address($ipaddr);
            if (!$has_ipv4) {
                continue;
            }

            /*
             * Raw config does not resolve gateway status like filter_core_getInterfaceMapping().
             * DHCP WANs usually have no static gateway in the interface node, but should still
             * be treated as automatic outbound NAT interfaces.
             */
            $is_wan_candidate = $ipaddr === 'dhcp' || ($gateway !== '' && $gateway !== 'none');

            if ($is_wan_candidate && substr($device, 0, 4) !== 'ovpn') {
                $nat_interfaces[] = [
                    'interface' => $if,
                    'descr' => $descr,
                ];
            } else {
                $source_networks[] = $if;
            }
        }

        $rows = [];
        $sequence = 1;

        foreach ($nat_interfaces as $natintf) {
            foreach ($source_networks as $source_net) {
                $target = $natintf['interface'] . 'ip';
                $rows[] = [
                    'uuid' => 'automatic_isakmp_' . $natintf['interface'] . '_' . $source_net,
                    'enabled' => '1',
                    'nonat' => '0',
                    'nosync' => '0',
                    'sequence' => (string)$sequence,
                    'interface' => $natintf['interface'],
                    '%interface' => $natintf['descr'],
                    'ipprotocol' => 'inet',
                    '%ipprotocol' => 'IPv4',
                    'protocol' => 'any',
                    '%protocol' => '*',
                    'source_net' => $source_net,
                    'source_not' => '0',
                    'source_port' => '',
                    'destination_net' => 'any',
                    'destination_not' => '0',
                    'destination_port' => '500',
                    'target' => $target,
                    'target_port' => '',
                    'staticnatport' => '1',
                    'log' => '0',
                    'categories' => '',
                    'tag' => '',
                    'tagged' => '',
                    'description' => gettext('Auto created rule for ISAKMP'),
                    'is_automatic' => true,
                    'sort_order' => sprintf('%d.0%06d', 500000, $sequence),
                    'prio_group' => '500000',
                ];
                $sequence++;
                $rows[] = [
                    'uuid' => 'automatic_' . $natintf['interface'] . '_' . $source_net,
                    'enabled' => '1',
                    'nonat' => '0',
                    'nosync' => '0',
                    'sequence' => (string)$sequence,
                    'interface' => $natintf['interface'],
                    '%interface' => $natintf['descr'],
                    'ipprotocol' => 'inet',
                    '%ipprotocol' => 'IPv4',
                    'protocol' => 'any',
                    '%protocol' => '*',
                    'source_net' => $source_net,
                    'source_not' => '0',
                    'source_port' => '',
                    'destination_net' => 'any',
                    'destination_not' => '0',
                    'destination_port' => '',
                    'target' => $target,
                    'target_port' => '',
                    'staticnatport' => '0',
                    'log' => '0',
                    'categories' => '',
                    'tag' => '',
                    'tagged' => '',
                    'description' => gettext('Auto created rule'),
                    'is_automatic' => true,
                    'sort_order' => sprintf('%d.0%06d', 500000, $sequence),
                    'prio_group' => '500000',
                ];
                $sequence++;
            }
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
        return $this->getBase("rule", "snatrules.rule", $uuid);
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
