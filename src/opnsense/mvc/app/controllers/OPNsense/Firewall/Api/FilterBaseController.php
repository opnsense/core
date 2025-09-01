<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Alias;
use OPNsense\Firewall\Category;

/**
 * Class FilterBaseController implements actions for various types
 * @package OPNsense\Firewall\Api
 */
abstract class FilterBaseController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'filter';
    protected static $internalModelClass = 'OPNsense\Firewall\Filter';
    protected static $categorysource = null;

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
            } elseif (strpos((string)$alias->type, "port") === false) {
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
                'label' => gettext("Single port or range"),
            ],
            'aliases' => [
                'label' => gettext("Aliases"),
                'items' => [],
            ],
            // XXX: Well known ports could be gathered from /etc/services but there is a lot of noise
            'ports' => [
                'label' => gettext("Ports"),
                'items' => [
                    "" => gettext("any"),
                ],
            ],
        ];

        foreach ((new Alias())->aliases->alias->iterateItems() as $alias) {
            if ($alias->type == 'internal') {
                /* currently only used for legacy bindings, align with legacy_list_aliases() usage */
                continue;
            }
            if (strpos((string)$alias->type, 'port') !== false) {
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
}
