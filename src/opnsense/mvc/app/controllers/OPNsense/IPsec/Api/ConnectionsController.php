<?php

/*
 * Copyright (C) 2022-2024 Deciso B.V.
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

namespace OPNsense\IPsec\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

/**
 * Class ConnectionsController
 * @package OPNsense\IPsec\Api
 */
class ConnectionsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'swanctl';
    protected static $internalModelClass = 'OPNsense\IPsec\Swanctl';

    /**
     * @return null|function lambda to filter on provided connection uuid in GET['connection']
     */
    private function connectionFilter()
    {
        $connection = $this->request->get('connection');
        $filter_func = null;
        if (!empty($connection)) {
            $filter_func = function ($record) use ($connection) {
                return $record->connection == $connection;
            };
        }
        return $filter_func;
    }

    /**
     * @param array $payload result array
     * @param string $topic topic used as root container
     * @return array $payload with optional preselected connection defaults (to be used by children of connection)
     */
    private function wrapDefaults($payload, $topic)
    {
        $conn_uuid = $this->request->get('connection');
        if (!empty($conn_uuid)) {
            foreach ($payload[$topic]['connection'] as $key => &$value) {
                if ($key == $conn_uuid) {
                    $value['selected'] = 1;
                } else {
                    $value['selected'] = 0;
                }
            }
        }
        return $payload;
    }

    public function searchConnectionAction()
    {
        return $this->searchBase(
            'Connections.Connection',
            ['description', 'enabled', 'local_addrs', 'remote_addrs', 'local_ts', 'remote_ts']
        );
    }

    public function setConnectionAction($uuid = null)
    {
        $copy_uuid = null;
        $post = $this->request->getPost('connection');
        if (empty($uuid) && !empty($post) && !empty($post['uuid'])) {
            // use form provided uuid when not provided as uri parameter
            $uuid = $post['uuid'];
            $copy_uuid = $post['org_uuid'] ?? null;
        }
        $result = $this->setBase('connection', 'Connections.Connection', $uuid);
        // copy children (when none exist)
        if (!empty($copy_uuid) && $result['result'] != 'failed') {
            $changed = false;
            foreach (['locals.local', 'remotes.remote', 'children.child'] as $ref) {
                $container = $this->getModel()->getNodeByReference($ref);
                if ($container != null) {
                    $orignal_items = [];
                    $has_children = false;
                    foreach ($container->iterateItems() as $node_uuid => $node) {
                        if ($node->connection == $copy_uuid) {
                            $record = [];
                            foreach ($node->iterateItems() as $key => $field) {
                                $record[$key] = (string)$field;
                            }
                            $orignal_items[] = $record;
                        } elseif ($node->connection == $uuid) {
                            $has_children = true;
                        }
                    }
                    if (!$has_children) {
                        foreach ($orignal_items as $record) {
                            $node = $container->Add();
                            $record['connection'] = $uuid;
                            $node->setNodes($record);
                            $changed = true;
                        }
                    }
                }
            }
            if ($changed) {
                $this->save();
            }
        }
        return $result;
    }

    public function addConnectionAction()
    {
        return $this->addBase('connection', 'Connections.Connection');
    }

    public function getConnectionAction($uuid = null)
    {
        $result = $this->getBase('connection', 'Connections.Connection', $uuid);
        if (!empty($result['connection'])) {
            $fetchmode = $this->request->has("fetchmode") ? $this->request->get("fetchmode") : null;
            $result['connection']['org_uuid'] = $uuid;
            if (empty($uuid) || $fetchmode == 'copy') {
                $result['connection']['uuid'] = $this->getModel()->Connections->generateUUID();
            } else {
                $result['connection']['uuid'] = $uuid;
            }
        }
        return $result;
    }

    public function toggleConnectionAction($uuid, $enabled = null)
    {
        return $this->toggleBase('Connections.Connection', $uuid, $enabled);
    }

    public function connectionExistsAction($uuid)
    {
        return [
            "exists" => isset($this->getModel()->Connections->Connection->$uuid)
        ];
    }

    public function delConnectionAction($uuid)
    {
        // remove children
        foreach (['locals.local', 'remotes.remote', 'children.child'] as $ref) {
            $tmp = $this->getModel()->getNodeByReference($ref);
            if ($tmp != null) {
                foreach ($tmp->iterateItems() as $node_uuid => $node) {
                    if ($node->connection == $uuid) {
                        $this->delBase($ref, $node_uuid);
                    }
                }
            }
        }
        return $this->delBase('Connections.Connection', $uuid);
    }

    public function searchLocalAction()
    {
        return $this->searchBase(
            'locals.local',
            ['description', 'round', 'auth', 'enabled'],
            'description',
            $this->connectionFilter()
        );
    }
    public function getLocalAction($uuid = null)
    {
        return $this->wrapDefaults(
            $this->getBase('local', 'locals.local', $uuid),
            'local'
        );
    }
    public function setLocalAction($uuid = null)
    {
        return $this->setBase('local', 'locals.local', $uuid);
    }
    public function addLocalAction()
    {
        return $this->addBase('local', 'locals.local');
    }
    public function toggleLocalAction($uuid, $enabled = null)
    {
        return $this->toggleBase('locals.local', $uuid, $enabled);
    }
    public function delLocalAction($uuid)
    {
        return $this->delBase('locals.local', $uuid);
    }

    public function searchRemoteAction()
    {
        return $this->searchBase(
            'remotes.remote',
            ['description', 'round', 'auth', 'enabled'],
            'description',
            $this->connectionFilter()
        );
    }
    public function getRemoteAction($uuid = null)
    {
        return $this->wrapDefaults(
            $this->getBase('remote', 'remotes.remote', $uuid),
            'remote'
        );
    }
    public function setRemoteAction($uuid = null)
    {
        return $this->setBase('remote', 'remotes.remote', $uuid);
    }
    public function addRemoteAction()
    {
        return $this->addBase('remote', 'remotes.remote');
    }
    public function toggleRemoteAction($uuid, $enabled = null)
    {
        return $this->toggleBase('remotes.remote', $uuid, $enabled);
    }
    public function delRemoteAction($uuid)
    {
        return $this->delBase('remotes.remote', $uuid);
    }

    public function searchChildAction()
    {
        return $this->searchBase(
            'children.child',
            ['description', 'enabled', 'local_ts', 'remote_ts'],
            'description',
            $this->connectionFilter()
        );
    }
    public function getChildAction($uuid = null)
    {
        return $this->wrapDefaults(
            $this->getBase('child', 'children.child', $uuid),
            'child'
        );
    }
    public function setChildAction($uuid = null)
    {
        return $this->setBase('child', 'children.child', $uuid);
    }
    public function addChildAction()
    {
        return $this->addBase('child', 'children.child');
    }
    public function toggleChildAction($uuid, $enabled = null)
    {
        return $this->toggleBase('children.child', $uuid, $enabled);
    }
    public function delChildAction($uuid)
    {
        return $this->delBase('children.child', $uuid);
    }

    /**
     * is IPsec enabled
     */
    public function isEnabledAction()
    {
        return [
            'enabled' => isset(Config::getInstance()->object()->ipsec->enable)
        ];
    }

    /**
     * toggle if IPsec is enabled
     */
    public function toggleAction($enabled = null)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            if ($enabled == "0" || $enabled == "1") {
                $new_status = $enabled == "1";
            } else {
                $new_status = !isset($config->ipsec->enable);
            }
            if ($new_status) {
                $config->ipsec->enable = true;
            } elseif (isset($config->ipsec->enable)) {
                unset($config->ipsec->enable);
            }
            Config::getInstance()->save();
            return ['status' => 'ok'];
        }
        return ['status' => 'failed'];
    }

    /**
     * Fetch the contents of swanctl.conf
     */
    public function swanctlAction()
    {
        $backend = new Backend();

        $responseArray = json_decode($backend->configdRun('ipsec get swanctl'), true);

        if (isset($responseArray['error'])) {
            return ["status" => "failed", "message" => $responseArray['message']];
        }

        return ["status" => "success", "content" => $responseArray['content']];
    }
}
