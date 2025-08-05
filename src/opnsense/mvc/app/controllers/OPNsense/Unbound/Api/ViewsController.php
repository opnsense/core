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

class ViewsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'view';
    protected static $internalModelClass = '\OPNsense\Unbound\Unbound';

    public function searchViewAction()
    {
        return $this->searchBase('split_dns.views.view', null, null);
    }

    public function getViewAction($uuid = null)
    {
        return $this->getBase('view', 'split_dns.views.view', $uuid);
    }

    public function addViewAction($uuid = null)
    {
        return $this->addBase('view', 'split_dns.views.view', $uuid);
    }

    public function delViewAction($uuid)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $mdl = $this->getModel();

            // Clean up host references (similar to WireGuard client cleanup)
            foreach ($mdl->split_dns->view_hosts->host->iterateItems() as $key => $node) {
                $view_uuids = array_filter(explode(',', (string)$node->view_uuids));
                if (in_array($uuid, $view_uuids)) {
                    $node->view_uuids = implode(',', array_diff($view_uuids, [$uuid]));
                }
            }

            // Clean up subnet references
            foreach ($mdl->split_dns->view_subnets->subnet->iterateItems() as $key => $node) {
                if ((string)$node->view_uuid === $uuid) {
                    $node->view_uuid = '';
                }
            }

            $mdl->serializeToConfig(false, true);
        }
        return $this->delBase('split_dns.views.view', $uuid);
    }

    public function setViewAction($uuid = null)
    {
        return $this->setBase('view', 'split_dns.views.view', $uuid);
    }

    public function toggleViewAction($uuid)
    {
        return $this->toggleBase('split_dns.views.view', $uuid);
    }
}
