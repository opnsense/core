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

use OPNsense\Base\UserException;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Category;
use OPNsense\Firewall\Group;
use OPNsense\Firewall\Util;
use OPNsense\Firewall\Alias;

class FilterController extends FilterBaseController
{
    protected static $categorysource = "rules.rule";

    /* cache properties */
    private array $networks = [];
    private array $legacy_fieldmap = [];
    private array $catcolors = [];

    /**
     * @param string $names comma seperated list of network items
     * @return array list of meta arrays
     */
    private function getNetworks($names)
    {
        /* As we are rendering MVC and legacy content, we can't use the descriptions from the fieldtypes */
        if (empty($this->networks)) {
            $nets = [];
            $nets['any'] = gettext('any');
            $nets['(self)'] = gettext('This Firewall');
            foreach (Config::getInstance()->object()->interfaces->children() as $ifname => $ifdetail) {
                $descr = htmlspecialchars(!empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($ifname));
                $nets[$ifname] = $descr . ' ' . gettext('net');
                if (!empty($ifdetail->if)) {
                    /* some automatic rules use device names */
                    $nets[(string)$ifdetail->if] = $descr . ' ' . gettext('net');
                }
                $nets[$ifname . 'ip'] = $descr . ' ' . gettext('address');
            }
            foreach ($nets as $key => $value) {
                $this->networks[$key] = [
                    'value' => $key,
                    '%value' => $value,
                    'isAlias' => false,
                    'description' => ''
                ];
            }
            $aliasmdl = new Alias(true);
            Util::attachAliasObject($aliasmdl);
            foreach ($aliasmdl->aliasIterator() as $alias) {
                $this->networks[$alias['name']] = [
                    'value' => $alias['name'],
                    '%value' => $alias['name'],
                    'isAlias' => true,
                    'description' => Util::aliasDescription($alias['name'])
                ];
            }
        }
        $result = [];
        foreach (array_map('trim', explode(',', $names)) as $name) {
            if (isset($this->networks[$name])) {
                $result[] = $this->networks[$name];
            } else {
                /* unknown type (e.g. address or port) */
                $result[] = [
                    'value' => $name,
                    '%value' => $name,
                    'isAlias' => false,
                    'description' => ''
                ];
            }
        }
        return $result;
    }

    /**
     * @param array $cats list of category ids
     * @return array colors
     */
    private function getCategoryColors($cats)
    {
        if (empty($this->catcolors)) {
            foreach ((new Category())->categories->category->iterateItems() as $key => $category) {
                $uuid = (string)$category->getAttributes()['uuid'];
                $color = trim((string)$category->color);
                $this->catcolors[$uuid] = !empty($color) ? "#{$color}" : '#000';
            }
        }
        $result = [];
        foreach ($cats as $cat) {
            if (isset($this->catcolors[$cat])) {
                $result[] = $this->catcolors[$cat];
            }
        }
        return $result;
    }

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
            $this->legacy_fieldmap['action'] = [
                'pass' => 'Pass',
                'block' => 'Block',
                'reject' => 'Reject'
            ];

            $this->legacy_fieldmap['ipprotocol'] = [
                'inet' => gettext('IPv4'),
                'inet6' => gettext('IPv6'),
                'inet46' => gettext('IPv4+IPv6')
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
        if (!empty($this->request->get('interface'))) {
            $interfaces = explode(",", $this->request->get('interface'));
            /* add groups which contain the selected interface */
            foreach ((new Group())->ifgroupentry->iterateItems() as $groupItem) {
                if (array_intersect($interfaces, explode(',', (string)$groupItem->members))) {
                    $interfaces[] = (string)$groupItem->ifname;
                }
            }
        } else {
            $interfaces = null;
        }

        /* filter logic for mvc rules */
        $filter_funct_mvc = function ($record) use ($categories, $interfaces, $show_all) {
            $is_cat = empty($categories) || array_intersect(explode(',', $record->categories), $categories);
            $rule_interfaces = array_filter(explode(',', (string)$record->interface));

            if (empty($interfaces)) {
                $is_if = count($rule_interfaces) != 1;
            } elseif ($show_all) {
                $is_if = array_intersect($interfaces, $rule_interfaces) || empty($rule_interfaces);
            } else {
                $is_if = count($rule_interfaces) === 1 && array_intersect($interfaces, $rule_interfaces);
            }

            return $is_cat && $is_if;
        };

        if ($show_all) {
            /* only query stats when fill info is requested */
            $rule_stats = json_decode((new Backend())->configdRun('filter rule stats'), true) ?? [];
        } else {
            $rule_stats = [];
        }


        $filter_funct_rs  = function (&$record) use ($categories, $interfaces, $rule_stats) {
            /* always merge stats when found */
            if (!empty($record['uuid']) && !empty($rule_stats[$record['uuid']])) {
                $record = array_merge($record, $rule_stats[$record['uuid']]);
            }
            /* frontend can format aliases with an alias icon */
            foreach (['source_net','source_port','destination_net','destination_port'] as $field) {
                if (!empty($record[$field])) {
                    $record["alias_meta_{$field}"] = $this->getNetworks($record[$field]);
                }
            }

            /* frontend can format categories with colors */
            $r_categories = !empty($record['categories']) ? array_map('trim', explode(',', $record['categories'])) : [];
            $record['category_colors'] = $this->getCategoryColors($r_categories);

            if (empty($record['legacy'])) {
                /* mvc already filtered */
                return true;
            }
            $is_cat = empty($categories) || array_intersect($r_categories, $categories);

            if (empty($interfaces)) {
                $is_if = empty($record['interface']) || count(explode(',', $record['interface'])) > 1;
            } else {
                $is_if = array_intersect(explode(',', $record['interface'] ?? ''), $interfaces);
                $is_if = $is_if || empty($record['interface']);
            }
            if ($is_cat && $is_if) {
                /* translate/convert legacy fields before returning, similar to mvc handling */
                foreach ($this->getLegacyFieldMap() as $topic => $data) {
                    if (!empty($record[$topic])) {
                        $tmp = [];
                        foreach (explode(',', $record[$topic]) as $item) {
                            $tmp[] = $data[$item] ?? $item;
                        }
                        $record[$topic] = implode(',', $tmp);
                    }
                }
                // Tag legacy rules as "Automatic generated rules" if they have an empty category
                if (!empty($record['is_automatic'])) {
                    $record['categories'] = gettext('Automatically generated rules');
                    $record['category_colors'] = ['#000'];
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
        if ($show_all) {
            /* searchBase should not filter here since we later search for IP addresses in aliases */
            unset($_REQUEST['searchPhrase']);
        }
        $filterset = $this->searchBase("rules.rule", null, "sort_order", $filter_funct_mvc)['rows'];

        /* only fetch internal and legacy rules when 'show_all' is set */
        if ($show_all) {
            $otherrules = json_decode((new Backend())->configdRun('filter list non_mvc_rules'), true) ?? [];
        } else {
            $otherrules = [];
        }

        $_REQUEST = $ORG_REQ; /* XXX:  fix me ?*/

        $search_clauses = [];
        $backend = new Backend();
        foreach (preg_split('/\s+/', (string)$this->request->getPost('searchPhrase', null, '')) as $token) {
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
            array_merge($otherrules, $filterset),
            null,
            "sort_order",
            $filter_funct_rs,
            SORT_NATURAL | SORT_FLAG_CASE,
            $search_clauses
        );

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
        $result = $this->getBase("rule", "rules.rule", $uuid);
        if ($this->request->get('fetchmode') === 'copy' && !empty($result['rule'])) {
            /* copy mode, generate new sequence at the end */
            $max = 0;
            foreach ($this->getModel()->rules->rule->iterateItems() as $rule) {
                $max = (int)((string)$rule->sequence) > $max ? (int)((string)$rule->sequence) : $max;
            }
            $result['rule']['sequence'] = $max + 100;
        }
        return $result;
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
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('Invalid request method')];
        }

        $mdl = $this->getModel();
        $node = null;
        foreach ($mdl->rules->rule->iterateItems() as $item) {
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

    /**
     * iterate rules for a prio_group, yield this record and the next in line (if any)
     */
    protected function ittrRules($prio_group)
    {
        $prev_record = null;
        foreach ($this->getModel()->rules->rule->sortedBy(['prio_group', 'sequence']) as $record) {
            if ($record->prio_group->isEqual($prio_group)) {
                yield ['this' => $record, 'prev' => $prev_record];
                $prev_record = $record;
            } elseif ($prev_record !== null) {
                /* last of selected group */
                break;
            }
        }
    }

    /**
     * Moves the selected rule so that it appears immediately before the target rule.
     *
     * Uses integer gap numbering to update the sequence for only the moved rule.
     * Rules will be renumbered within the selected range to prevent movements causing overlaps,
     * but try to keep the changes as minimal as possible.
     *
     * Floating, Group, and Interface rules cannot be moved before another.
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
        /* validate */
        $target_node = $this->getModel()->getNodeByReference('rules.rule.' . $target_uuid);
        $selected_node = $this->getModel()->getNodeByReference('rules.rule.' . $selected_uuid);
        if ($target_node === null || $selected_node === null) {
            throw new UserException(
                gettext("Either source or destination is not a rule managed with this component"),
                gettext("Filter")
            );
        } elseif (!$selected_node->prio_group->isEqual($target_node->prio_group)) {
            /* types don't match */
            $typeNames = [
                '2' => gettext("Floating"),
                '3' => gettext("Group"),
                '4' => gettext("Interface")
            ];
            $selectedType = $typeNames[substr($selected_node->prio_group, 0, 1)] ?? gettext("Unknown");
            $targetType   = $typeNames[substr($target_node->prio_group, 0, 1)] ?? gettext("Unknown");
            throw new UserException(
                sprintf(
                    gettext("Cannot move '%s Rule' before '%s Rule'."),
                    $selectedType,
                    $targetType
                ),
                gettext("Filter")
            );
        } elseif ($selected_uuid === $target_uuid) {
            throw new UserException(gettext("Cannot move to the same spot."), gettext("Filter"));
        }
        /* move the rule and optionally reorganize*/
        $step_size = 50;
        $new_key = null;
        foreach ($this->ittrRules($target_node->prio_group) as $item) {
            $uuid = $item['this']->getAttribute('uuid');
            if ($target_uuid === $uuid) {
                $prev_sequence = (($item['prev']?->sequence->asFloat()) ?? 1);
                $distance = $item['this']->sequence->asFloat() - $prev_sequence;
                if ($distance > 2) {
                    $new_key = intdiv($distance, 2) + $prev_sequence;
                    break;
                } else {
                    $new_key = $item['prev'] === null ? 1 : ($prev_sequence + $step_size);
                    $item['this']->sequence = (string)($new_key + $step_size);
                }
            } elseif ($new_key !== null) {
                if ($item['this']->sequence->asFloat() < $item['prev']?->sequence->asFloat()) {
                    $item['this']->sequence = (string)($item['prev']?->sequence->asFloat() + $step_size);
                }
            }
        }
        if ($new_key !== null) {
            $selected_node->sequence = (string)$new_key;
            $this->getModel()->serializeToConfig(false, true); /* we're only changing sequences, forcefully save */
            Config::getInstance()->save();
        }

        return ["status" => "ok"];
    }

    /**
     * return interface options
     */
    public function getInterfaceListAction()
    {
        $result = [
            'floating' => [
                'label' => gettext('Floating'),
                'icon' => 'fa fa-layer-group text-primary',
                'items' => []
            ],
            'groups' => [
                'label' => gettext('Groups'),
                'icon' => 'fa fa-sitemap text-warning',
                'items' => []
            ],
            'interfaces' => [
                'label' => gettext('Interfaces'),
                'icon' => 'fa fa-ethernet text-info',
                'items' => []
            ]
        ];

        // Count rules per interface
        $ruleCounts = [];
        foreach ((new \OPNsense\Firewall\Filter())->rules->rule->iterateItems() as $rule) {
            $interfaces = array_filter(explode(',', (string)$rule->interface));

            if (count($interfaces) !== 1) {
                // floating: empty or multiple interfaces
                $ruleCounts['floating'] = ($ruleCounts['floating'] ?? 0) + 1;
            } else {
                // single interface
                $ruleCounts[$interfaces[0]] = ($ruleCounts[$interfaces[0]] ?? 0) + 1;
            }
        }

        // Helper to build item with label and count
        $makeItem = fn($value, $label, $count, $type) => [
            'value' => $value,
            'label' => $label,
            'count' => $count,
            'type' => $type
        ];

        // Floating
        $result['floating']['items'][] = $makeItem('', gettext('Any'), $ruleCounts['floating'] ?? 0, 'floating');

        // Groups
        foreach ((new \OPNsense\Firewall\Group())->ifgroupentry->iterateItems() as $groupItem) {
            $name = (string)$groupItem->ifname;
            $result['groups']['items'][] = $makeItem($name, $name, $ruleCounts[$name] ?? 0, 'group');
        }

        // Interfaces
        $groupKeys = array_column($result['groups']['items'], 'value');
        foreach (\OPNsense\Core\Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            if (!in_array($key, $groupKeys)) {
                $descr = !empty($intf->descr) ? (string)$intf->descr : strtoupper($key);
                $result['interfaces']['items'][] = $makeItem($key, $descr, $ruleCounts[$key] ?? 0, 'interface');
            }
        }

        // Sort items by count and alphabetically
        foreach ($result as &$section) {
            usort($section['items'], fn($a, $b) =>
                ($b['count'] ?? 0) <=> ($a['count'] ?? 0)
                    ?: strcasecmp($a['label'], $b['label']));
        }

        return $result;
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
