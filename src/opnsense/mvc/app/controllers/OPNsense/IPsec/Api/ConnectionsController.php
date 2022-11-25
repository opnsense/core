<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

/**
 * Class ConnectionsController
 * @package OPNsense\IPsec\Api
 */
class ConnectionsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'swanctl';
    protected static $internalModelClass = 'OPNsense\IPsec\Swanctl';

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
        return $this->searchBase('Connections.Connection', ['description']);
    }

    public function setConnectionAction($uuid = null)
    {
        $post = $this->request->getPost('connection');
        if (empty($uuid) && !empty($post) && !empty($post['uuid'])) {
            // use form provided uuid when not provided as uri parameter
            $uuid = $post['uuid'];
        }
        return $this->setBase('connection', 'Connections.Connection', $uuid);
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
            if (empty($uuid) || $fetchmode == 'copy') {
                $result['connection']['uuid'] = $this->getModel()->Connections->generateUUID();
            } else {
                $result['connection']['uuid'] = $uuid;
            }
        }
        return $result;
    }

    public function connectionExistsAction($uuid)
    {
        return [
            "exists" => isset($this->getModel()->Connections->Connection->$uuid)
        ];
    }

    public function delConnectionAction($uuid)
    {
        return $this->delBase('Connections.Connection', $uuid);
    }

    public function searchLocalAction()
    {
        return $this->searchBase('locals.local', ['description'], 'description', $this->connectionFilter());
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
    public function delLocalAction($uuid)
    {
        return $this->delBase('locals.local', $uuid);
    }

    public function searchRemoteAction()
    {
        return $this->searchBase('remotes.remote', ['description'], 'description', $this->connectionFilter());
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
    public function delRemoteAction($uuid)
    {
        return $this->delBase('remotes.remote', $uuid);
    }

    public function searchChildAction()
    {
        return $this->searchBase('children.child', ['description'], 'description', $this->connectionFilter());
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
    public function delChildAction($uuid)
    {
        return $this->delBase('children.child', $uuid);
    }

}
