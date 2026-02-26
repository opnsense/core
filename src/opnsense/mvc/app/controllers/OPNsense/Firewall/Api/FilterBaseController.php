<?php

/*
 * Copyright (C) 2020-2026 Deciso B.V.
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\FieldTypes\PortField;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Alias;
use OPNsense\Firewall\Category;
use OPNsense\Firewall\Util;

/**
 * Class FilterBaseController implements actions for various types
 * @package OPNsense\Firewall\Api
 */
abstract class FilterBaseController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'filter';
    protected static $internalModelClass = 'OPNsense\Firewall\Filter';
    protected static $categorysource = null;

    /* store data for cached getters */
    private array $networks = [];
    private ?array $catcolors = null;

    /**
     * @param array $cats list of category ids
     * @return array colors
     */
    protected function getCategoryColors(array $cats)
    {
        if ($this->catcolors === null) {
            $this->catcolors = []; /* init to prevent empty categories initiating models constantly */
            foreach ((new Category())->categories->category->iterateItems() as $key => $category) {
                $uuid = (string)$category->getAttributes()['uuid'];
                $color = trim((string)$category->color->getValue(true));
                $this->catcolors[$uuid] = !empty($color) ? "#{$color}" : '';
            }
        }
        /* extract catcolors by index */
        return array_values(array_intersect_key($this->catcolors, array_flip($cats)));
    }

    /**
     * @param string $names comma seperated list of network items
     * @return array list of meta arrays
     */
    protected function getNetworks($names)
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
     * list categories and usage
     * @return array
     */
    public function listCategoriesAction()
    {
        $response = ['rows' => []];
        $catcount = [];
        if (!empty(static::$categorysource)) {
            $node = $this->getModel();
            foreach (explode('.', static::$categorysource) as $ref) {
                $node = $node->$ref;
            }
            foreach ($node->iterateItems() as $item) {
                if (!empty((string)$item->categories)) {
                    foreach (explode(',', (string)$item->categories) as $cat) {
                        if (!isset($catcount[$cat])) {
                            $catcount[$cat] = 0;
                        }
                        $catcount[$cat] += 1;
                    }
                }
            }
        }
        foreach ((new Category())->categories->category->iterateItems() as $key => $category) {
            $response['rows'][] = [
                "uuid" => $key,
                "name" => (string)$category->name,
                "color" => (string)$category->color,
                "used" => isset($catcount[$key]) ? $catcount[$key] : 0
            ];
        }
        array_multisort(array_column($response['rows'], "name"), SORT_ASC, SORT_NATURAL, $response['rows']);

        return $response;
    }

    /**
     * list of available network options
     * @return array
     */
    public function listNetworkSelectOptionsAction()
    {
        $result = [
            'single' => [
                'label' => gettext("Single host or Network")
            ],
            'aliases' => [
                'label' => gettext("Aliases"),
                'items' => []
            ],
            'networks' => [
                'label' => gettext("Networks"),
                'items' => [
                    'any' => gettext('any'),
                    '(self)' => gettext("This Firewall")
                ]
            ]
        ];
        foreach ((Config::getInstance()->object())->interfaces->children() as $ifname => $ifdetail) {
            $descr = htmlspecialchars(!empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($ifname));
            $result['networks']['items'][$ifname] = $descr . " " . gettext("net");
            if (!isset($ifdetail->virtual)) {
                $result['networks']['items'][$ifname . "ip"] = $descr . " " . gettext("address");
            }
        }
        foreach ((new Alias())->aliases->alias->iterateItems() as $alias) {
            if ($alias->type == 'internal') {
                /* currently only used for legacy bindings, align with legacy_list_aliases() usage */
                continue;
            } elseif ($alias->type != 'port') {
                $result['aliases']['items'][(string)$alias->name] = (string)$alias->name;
            }
        }

        return $result;
    }

    /**
     * list of available port options
     * @return array
     */
    public function listPortSelectOptionsAction()
    {
        $result = [
            'single' => [
                'label' => gettext('Single port or range'),
            ],
            'aliases' => [
                'label' => gettext('Aliases'),
                'items' => [],
            ],
            'ports' => [
                'label' => gettext('Ports'),
                'items' => [
                    '' => gettext('any'),
                ],
            ],
        ];

        /*
         * XXX Eventually it might make more sense to instantate the
         * actual protocol fields of the rules in order to get the full
         * list of options in one go (modifying the model XML to
         * automatically get the correct values).
         *
         * This works using e.g.
         *   (new Filter())->rules->rule->getTemplateNode()->source_port
         * but loads all rules as a side effect if they exist which we
         * want to avoid and raises the question how we're going to deal
         * with every field's own setup as we can't simply derive one
         * field's accepted values for another.
         */
        foreach (PortField::getWellKnown() as $service => $port) {
            $result['ports']['items'][$service] = sprintf('%s (%s)', strtoupper($service), $port);
        }

        foreach ((new Alias())->aliases->alias->iterateItems() as $alias) {
            if ($alias->type == 'internal') {
                /* currently only used for legacy bindings, align with legacy_list_aliases() usage */
                continue;
            }
            if ((string)$alias->type == 'port') {
                $result['aliases']['items'][(string)$alias->name] = (string)$alias->name;
            }
        }

        return $result;
    }

    public function applyAction($rollback_revision = null)
    {
        if ($this->request->isPost()) {
            if ($rollback_revision != null) {
                // background rollback timer
                (new Backend())->configdpRun('filter rollback_timer', [$rollback_revision], true);
            }
            return array("status" => (new Backend())->configdRun('filter reload'));
        } else {
            return array("status" => "error");
        }
    }

    public function cancelRollbackAction($rollback_revision)
    {
        if ($this->request->isPost()) {
            return array(
                "status" => (new Backend())->configdpRun('filter cancel_rollback', [$rollback_revision])
            );
        } else {
            return array("status" => "error");
        }
    }

    public function savepointAction()
    {
        if ($this->request->isPost()) {
            // trigger a save, so we know revision->time matches our running config
            Config::getInstance()->save();
            return array(
                "status" => "ok",
                "retention" => (string)Config::getInstance()->backupCount(),
                "revision" => (string)Config::getInstance()->object()->revision->time
            );
        } else {
            return array("status" => "error");
        }
    }

    public function revertAction($revision)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $filename = Config::getInstance()->getBackupFilename($revision);
            if (!$filename) {
                Config::getInstance()->unlock();
                return ["status" => gettext("unknown (or removed) savepoint")];
            }
            $this->getModel()->rollback($revision);
            Config::getInstance()->unlock();
            (new Backend())->configdRun('filter reload');
            return ["status" => "ok"];
        } else {
            return array("status" => "error");
        }
    }

    /**
     * Moves the selected rule so that it appears immediately before the target rule.
     *
     * Uses integer gap numbering to update the sequence for only the moved rule.
     * Rules will be renumbered within the selected range to prevent movements causing overlaps,
     * but try to keep the changes as minimal as possible.
     *
     * @param string $selected_uuid     The UUID of the rule to be moved.
     * @param string $target_uuid       The UUID of the target rule (the rule before which the selected rule is to be placed).
     * @param string $node_reference    Node reference prefix, e.g. "onetoone.rule".
     * @param string $sort_key          Sort key field, e.g. "sequence".
     * @return array Returns ["status" => "ok"] on success, throws a UserException otherwise.
     */
    public function moveRuleBeforeBase($selected_uuid, $target_uuid, $node_reference, $sort_key)
    {
        if (!$this->request->isPost()) {
            return ["status" => "error", "message" => gettext("Invalid request method")];
        }

        $target_node   = $this->getModel()->getNodeByReference($node_reference . '.' . $target_uuid);
        $selected_node = $this->getModel()->getNodeByReference($node_reference . '.' . $selected_uuid);

        if ($target_node === null || $selected_node === null) {
            throw new UserException(
                gettext("Either source or destination is not a rule managed with this component")
            );
        }

        $step_size = 50;
        $new_key = null;
        $prev_record = null;

        foreach ($this->getModel()->getNodeByReference($node_reference)->sortedBy([$sort_key]) as $record) {
            $uuid = $record->getAttribute('uuid');

            if ($target_uuid === $uuid) {
                $prev_sequence = (($prev_record?->$sort_key->asFloat()) ?? 1);
                $distance = $record->$sort_key->asFloat() - $prev_sequence;

                if ($distance > 2) {
                    $new_key = intdiv($distance, 2) + $prev_sequence;
                    break;
                } else {
                    $new_key = $prev_record === null ? 1 : ($prev_sequence + $step_size);
                    $record->$sort_key = (string)($new_key + $step_size);
                }
            } elseif ($new_key !== null) {
                if ($record->$sort_key->asFloat() < $prev_record?->$sort_key->asFloat()) {
                    $record->$sort_key = (string)($prev_record?->$sort_key->asFloat() + $step_size);
                }
            }

            $prev_record = $record;
        }

        if ($new_key !== null) {
            $selected_node->$sort_key = (string)$new_key;
            $this->getModel()->serializeToConfig(false, true);
            Config::getInstance()->save();
        }

        return ["status" => "ok"];
    }

    /**
     * Toggle the "log" flag of a rule.
     *
     * @param string $uuid             UUID of the rule to update.
     * @param string $log              New log value ("0" or "1").
     * @param string $node_reference   Node reference prefix, e.g. "onetoone.rule".
     * @return array                   ["status" => "ok"] on success, throws UserException otherwise.
     */
    protected function toggleRuleLogBase($uuid, $log, $node_reference)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('Invalid request method')];
        }

        $mdl = $this->getModel();
        $node = $mdl->getNodeByReference($node_reference . '.' . $uuid);

        if ($node === null) {
            throw new UserException(gettext('Rule not found'));
        }

        $node->log = $log;
        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return ['status' => 'ok'];
    }
}
