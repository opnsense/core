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
namespace OPNsense\Core\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Base\UserException;

class TunablesController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'sysctl';
    protected static $internalModelClass = 'OPNsense\Core\Tunables';

    public function searchItemAction()
    {
        return $this->searchBase("item", null, "sysctl");
    }

    public function setItemAction($uuid)
    {
        if ($this->request->isPost() && count(explode('-', $uuid)) != 5) {
            /* generate new uuid when key is a tunable name (from system_sysctl_defaults) */
            Config::getInstance()->lock();
            $uuid = $this->getModel()->item->generateUUID();
        }
        return $this->setBase("sysctl", "item", $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase("sysctl", "item");
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("sysctl", "item", $uuid);
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("item", $uuid);
    }

    public function resetAction()
    {
        if ($this->request->isPost()) {
            if (file_exists('/usr/local/etc/config.xml')) {
                Config::getInstance()->lock();
                $factory_config = Config::getInstance()->toArrayFromFile('/usr/local/etc/config.xml', []);
                $mdl = $this->getModel()->Default();
                if (!empty($factory_config['sysctl']['item'])) {
                    foreach ($factory_config['sysctl']['item'] as $item) {
                        $node = $mdl->item->Add();
                        foreach ($item as $key => $val) {
                            $node->$key = (string)$val;
                        }
                    }
                }
                $this->save();
                return ['status' => 'ok'];
            } else {
                return ['status' => 'no_default'];
            }
        }
        return ['status' => 'failed'];
    }

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            /* both sysctl and login use tunables, restart them both */
            $tmp1 = strtolower(trim((new Backend())->configdpRun('service restart', ['login'])));
            $tmp2 = strtolower(trim((new Backend())->configdpRun('service restart', ['sysctl'])));

            return ['status' => $tmp1 == 'ok' && $tmp2 == 'ok' ? 'ok' : 'failed'];
        }
        return ['status' => 'failed'];
    }
}
