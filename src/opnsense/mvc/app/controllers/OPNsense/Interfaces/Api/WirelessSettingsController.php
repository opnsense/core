<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\UserException;
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;


class WirelessSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'wireless';
    protected static $internalModelClass = 'OPNsense\Interfaces\Wireless';

    protected function setBaseHook($node)
    {
        if ($node->cloneif->isEmpty()) {
            $names = [];
            foreach($this->getModel()->clone->iterateItems() as $clone) {
                if ($clone !== $node && $clone->if->isEqual($node->if->getValue())) {
                    $names[] = $clone->cloneif->getValue();
                }
            }
            for ($i=1; true; ++$i) {
                $node->cloneif = sprintf("%s_wlan%d", $node->if->getValue(), $i);
                if (!in_array($node->cloneif, $names)) {
                    break;
                }
            }
        }
    }

    public function searchItemAction()
    {
        return $this->searchBase('clone');
    }

    public function setItemAction($uuid)
    {
        if (isset($_POST['cloneif'])) {
            unset($_POST['cloneif']);
        }
        return $this->setBase('wireless', 'clone', $uuid);
    }

    public function addItemAction()
    {
        if (isset($_POST['cloneif'])) {
            unset($_POST['cloneif']);
        }
        return $this->addBase('wireless', 'clone');
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('wireless', 'clone', $uuid);
    }

    public function delItemAction($uuids)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            foreach (explode(',', $uuids) as $uuid) {
                $node = $this->getModel()->getNodeByReference('clone.' . $uuid);
                if ($node === null) {
                    continue;
                }
                foreach (Config::getInstance()->object()->interfaces->children() as $k => $child) {
                    if ($node->cloneif->isEqual((string)$child->if)) {
                        $msg = sprintf(gettext(
                            'This wireless clone cannot be deleted because it is assigned to interface \'%s\'.'),
                            empty((string)$child->descr) ? strtoupper((string)$k) : (string)$child->descr
                        );
                        throw new UserException($msg, gettext("Wireless"));
                    }
                }
            }
        }
        return $this->delBase('clone', $uuids);
    }

    public function reconfigureAction()
    {
        $result = ["status" => "failed"];
        if ($this->request->isPost()) {
            $result['status'] = strtolower(trim((new Backend())->configdRun('interface wireless configure')));
            $this->runInterfaceRegistration();
        }
        return $result;
    }
}
