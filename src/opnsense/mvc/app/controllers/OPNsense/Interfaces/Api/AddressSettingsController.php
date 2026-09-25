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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class AddressSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'settings';
    protected static $internalModelClass = 'OPNsense\Interfaces\AddressSettings';

    public function searchItemAction()
    {
        return $this->searchBase('settings');
    }

    public function getItemAction($ifname = null)
    {
        return $this->getBase('settings', 'settings', $ifname);
    }

    public function setItemAction($ifname)
    {
        return $this->setBase('settings', 'settings', $ifname);
    }

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $this->throwReadOnly();
            if (trim((new Backend())->configdRun('interface apply')) == 'OK') {
                Config::getInstance()->lock();
                foreach ($this->getModel()->get_if_todo() as $key => $props) {
                    if (($props['pending_action'] ?? '') !== 'configure' || empty($props['pending'])) {
                        continue;
                    }
                    if (!isset(Config::getInstance()->object()->interfaces->$key)) {
                        continue;
                    }
                    $fields = [];
                    foreach (Config::getInstance()->object()->interfaces->$key->children() as $field => $value) {
                        $fields[] = $field;
                    }
                    foreach ($fields as $field) {
                        unset(Config::getInstance()->object()->interfaces->$key->$field);
                    }
                    foreach ($props['pending'] as $field => $value) {
                        Config::getInstance()->object()->interfaces->$key->addChild($field, $value);
                    }
                }
                Config::getInstance()->save();
                $this->getModel()->flush_todo();
                (new Backend())->configdRun('filter reload skip_alias', true);
                return ['status' => 'ok'];
            }
        }
        return ['status' => 'failed'];
    }
}
