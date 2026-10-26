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
use OPNsense\Firewall\Filter;
use OPNsense\Firewall\Group;
use OPNsense\Firewall\Util;

class FilterController extends FilterBaseController
{
    protected static $categorysource = "rules.rule";

    /* cache properties */
    private array $legacy_fieldmap = [];

    /**
     * @return array cached fieldmapping for legacy data
     */
    private function getLegacyFieldMap()
    {
        if (empty($this->legacy_fieldmap)) {
            $this->legacy_fieldmap = ['category' => [], 'interface' => []];
            foreach ((new Category())->categories->category->iterateItems() as $key => $category) {
                $this->legacy_fieldmap['category'][$key] = (string)$category->name;
            }
            foreach ((Config::getInstance()->object())->interfaces->children() as $key => $ifdetail) {
                $descr = !empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($key);
                $this->legacy_fieldmap['interface'][$key] = $descr;
            }
            $this->legacy_fieldmap['%action'] = [
                'block' => gettext('Block'),
                'match' => gettext('Match'),
                'pass' => gettext('Pass'),
                'reject' => gettext('Reject'),
            ];
            $this->legacy_fieldmap['%direction'] = [
                'in' => gettext('In'),
                'out' => gettext('Out'),
                'any' => gettext('Any'),
            ];
            $this->legacy_fieldmap['%ipprotocol'] = [
                'inet' => gettext('IPv4'),
                'inet6' => gettext('IPv6'),
                'inet46' => '', /* XXX remove when filter.lib.inc use is removed */
            ];
        }

        return $this->legacy_fieldmap;
    }

    /**
     * Retrieves and merges firewall rules from model and internal sources, then paginates them.
     *
     * @return array The final paginated, merged result.
     */
    public function searchRuleAction()
    {
        $categories = $this->request->get('category');
        $show_all = !empty($this->request->get('show_all'));
        $search_phrase = trim((string)$this->request->getPost('searchPhrase', null, ''));
        if (!$this->request->has('interface')) {
            // ALL rules
            $interfaces = null;
        } else {
            // interface param may be empty
            $interfaces = array_filter(explode(',', (string)$this->request->get('interface')), 'strlen');

            if (!empty($interfaces)) {
                /* add groups which contain the selected interface when looking at full impact */
                foreach ((new Group())->ifgroupentry->iterateItems() as $groupItem) {
                    if (array_intersect($interfaces, $groupItem->members->getValues())) {
                        $interfaces[] = $groupItem->ifname->getValue();
                    }
                }
            }
        }
        /* extract all mvc records so we can filter and sort all at once */
        $allrules = [];
        foreach ($this->getModel()->rules->rule->iterateItems() as $key => $node) {
            $allrules[] = array_merge(['uuid' => $key], $node->getNodeContent());
        }

        if ($show_all) {
            /* only query stats when fill info is requested */
            $rule_stats = json_decode((new Backend())->configdRun('filter rule stats'), true) ?? [];
        } else {
            $rule_stats = [];
        }

        $decorate_funct_rs = function (&$record, $r_categories = null) {
            if ($r_categories === null) {
                $r_categories = !empty($record['categories']) ?
                    array_map('trim', explode(',', $record['categories'])) : [];
            }

            /* frontend can format categories with colors */
            $record['category_colors'] = $this->getCategoryColors($r_categories);

            /* frontend can format aliases with an alias icon */
            foreach (['source_net','source_port','destination_net','destination_port'] as $field) {
                if (!empty($record[$field])) {
                    $record["alias_meta_{$field}"] = $this->getNetworks($record[$field]);
                }
            }
        };

        $filter_funct_rs = function (&$record) use (
            $categories,
            $interfaces,
            $rule_stats
        ) {
            /* Filter criteria */
            $r_categories = !empty($record['categories']) ? array_map('trim', explode(',', $record['categories'])) : [];
            $is_cat = empty($categories) || array_intersect($r_categories, $categories);
            $rule_interfaces = array_filter(explode(',', $record['interface'] ?? ''));
            if ($interfaces === null || (empty($record['interface']))) {
                /*
                 * All rules view uses a "null" interface filter.
                 * Rules without an assigned interface are floating/global and match everywhere.
                 */
                $is_if = true;
            } elseif (!empty($record['interfacenot'])) {
                /*
                 * Inverted interface rule: selected interfaces are exclusions,
                 * so show it only when none overlap with the active interface filter.
                 * These also count as floating rules.
                 */
                $is_if = empty(array_intersect($rule_interfaces, $interfaces ?? []));
            } elseif (empty($interfaces) && count($rule_interfaces) != 1) {
                /*
                 * Floating rules view: uses an "empty" interface filter.
                 * Any rule not bound to exactly one interface counts as floating.
                 */
                $is_if = true;
            } else {
                /*
                 * Interface/Group rules view: show rules matching at least one selected interface or group.
                 * This includes normal single interface/group and floating multi interface/group rules.
                 */
                $is_if = !empty(array_intersect($rule_interfaces, $interfaces ?? []));
            }

            if (!$is_cat || !$is_if) {
                return false; /* not reached */
            }

            /* translate/convert legacy fields before returning, similar to mvc handling */
            if (!empty($record['legacy'])) {
                foreach ($this->getLegacyFieldMap() as $topic => $data) {
                    if (!empty($record[$topic])) {
                        $tmp = [];
                        foreach (explode(',', $record[$topic]) as $item) {
                            $tmp[] = $data[$item] ?? $item;
                        }
                        $record[$topic] = implode(',', $tmp);
                    }
                }
            }

            /* Formatting */

            /* always merge stats when found */
            if (!empty($record['uuid']) && !empty($rule_stats[$record['uuid']])) {
                $record = array_merge($record, $rule_stats[$record['uuid']]);
            }

            return true;
        };

        /* always fetch internal and legacy rules, automatic rules have their own category that is always visible */
        $allrules = array_merge(
            $allrules,
            json_decode((new Backend())->configdRun('filter list non_mvc_rules'), true) ?? []
        );

        $search_clauses = [];
        $backend = new Backend();
        foreach (preg_split('/\s+/', $search_phrase, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            // XXX: ideally this should get its own parameter and not reuse show_all
            if ($show_all && Util::isIpAddress($token)) {
                $tmp = json_decode($backend->configdpRun('filter find_table_references', [$token]), true) ?? [];
                $aliases = [$token];
                if (is_array($tmp) && !empty($tmp['matches'])) {
                    $aliases = array_merge($aliases, $tmp['matches']);
                }
                $search_clauses[] = $aliases;
            } else {
                $search_clauses[] = $token;
            }
        }
        $result = $this->searchRecordsetBase(
            $allrules,
            null,
            "sort_order",
            $filter_funct_rs,
            SORT_NATURAL | SORT_FLAG_CASE,
            $search_clauses
        );
        /* frontend expects "meta_" fields, which should only be relevant for rendering purposes */
        foreach ($result['rows'] as &$record) {
            $decorate_funct_rs($record);
        }

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
        return $this->setCopySequence($this->getBase('rule', 'rules.rule', $uuid));
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("rules.rule", $uuid);
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("rules.rule", $uuid, $enabled);
    }

    public function toggleRuleLogAction($uuid, $log)
    {
        return $this->toggleRuleLogBase($uuid, $log, 'rules.rule');
    }

    public function moveRuleBeforeAction($selected_uuids, $target_uuid)
    {
        return $this->moveRuleBeforeBase($selected_uuids, $target_uuid, 'rules.rule', 'sequence', 'prio_group');
    }

    /**
     * return interface options
     */
    public function getInterfaceListAction()
    {
        $result = [
            'floating' => [
                'label' => gettext('Floating'),
                'icon' => 'fa fa-layer-group fa-fw text-primary',
                'items' => [],
            ],
            'groups' => [
                'label' => gettext('Groups'),
                'icon' => 'fa fa-sitemap fa-fw text-warning',
                'items' => [],
            ],
            'interfaces' => [
                'label' => gettext('Interfaces'),
                'icon' => 'fa fa-ethernet fa-fw text-info',
                'items' => [],
            ],
            'any' => [
                'label' => gettext('Any'),
                'icon' => 'fa fa-globe-europe fa-fw',
                'items' => [],
            ],
        ];

        // Helper to build item
        $makeItem = fn($value, $label, $type) => [
            'value' => $value,
            'label' => $label,
            'type' => $type
        ];

        // Floating
        $result['floating']['items'][] = $makeItem('__floating', gettext('Floating'), 'floating');

        // Groups + Interfaces
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            // XXX: Loopback excluded since no rules should be on there
            if ($key == 'lo0') {
                continue;
            }
            $descr = !empty($intf->descr) ? (string)$intf->descr : strtoupper($key);
            $type = (string)$intf->type == 'group' ? 'group' : 'interface';
            $result["{$type}s"]['items'][] = $makeItem($key, $descr, $type);
        }

        // All rules
        $result['any']['items'][] = $makeItem('__any', gettext('All rules'), 'any');

        foreach ($result as &$section) {
            usort($section['items'], fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
        }

        return $result;
    }

    // XXX: as shaper1/2 don't have functional keys, we can only export uuid's here
    public function downloadRulesAction()
    {
        return $this->downloadRulesBase('rules.rule', [], ['overload']);
    }

    public function uploadRulesAction()
    {
        return $this->uploadRulesBase('rules.rule', [], ['overload']);
    }

    public function flushInspectCacheAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('Invalid request method')];
        }

        (new Backend())->configdRun('!filter rule stats');

        return ['status' => 'ok'];
    }
}
