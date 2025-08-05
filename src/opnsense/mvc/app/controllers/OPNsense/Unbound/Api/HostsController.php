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

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class HostsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'host';
    protected static $internalModelClass = '\OPNsense\Unbound\Unbound';

    public function listViewsAction()
    {
        if ($this->request->isGet()) {
            $results = ['rows' => [], 'status' => 'ok'];
            foreach ($this->getModel()->split_dns->views->view->iterateItems() as $key => $node) {
                $results['rows'][] = [
                    'uuid' => $key,
                    'name' => (string)$node->name
                ];
            }
            return $results;
        }
        return ['status' => 'failed'];
    }

    public function searchHostAction()
    {
        $views = $this->request->get('views');
        $filter_funct = function ($record) use ($views) {
            return empty($views) || array_intersect(explode(',', $record->view_uuids), $views);
        };

        return $this->searchBase('split_dns.view_hosts.host', null, null, $filter_funct);
    }

    public function getHostAction($uuid = null)
    {
        return $this->getBase('host', 'split_dns.view_hosts.host', $uuid);
    }

    public function addHostAction()
    {
        return $this->setHostAction(null);
    }

    public function delHostAction($uuid)
    {
        // Note: For Unbound, we don't need to clean up view references
        // because hosts reference views, not the other way around
        return $this->delBase('split_dns.view_hosts.host', $uuid);
    }

    public function setHostAction($uuid)
    {
        $add_uuid = null;
        if (!empty($this->request->getPost(static::$internalModelName)) && $this->request->isPost()) {
            $views = [];
            if (!empty($this->request->getPost(static::$internalModelName)['view_uuids'])) {
                $views = explode(',', $this->request->getPost(static::$internalModelName)['view_uuids']);
            }
            Config::getInstance()->lock();
            $mdl = $this->getModel();
            if (empty($uuid)) {
                // add new host, generate uuid
                $uuid = $mdl->split_dns->view_hosts->host->generateUUID();
                $add_uuid = $uuid;
            }

            // Note: Unlike WireGuard, we don't need to update view references
            // because Unbound uses a unidirectional relationship (hosts reference views)

            /**
             * Ignore validations as $uuid might be new or trigger an existing validation issue.
             * Persisting the data is handled by setBase()
             */
            $mdl->serializeToConfig(false, true);
        }
        $result = $this->setBase('host', 'split_dns.view_hosts.host', $uuid);
        if (!empty($add_uuid) && $result['result'] == 'saved') {
            $result['uuid'] = $add_uuid;
        }
        return $result;
    }

    public function toggleHostAction($uuid)
    {
        return $this->toggleBase('split_dns.view_hosts.host', $uuid);
    }
}
