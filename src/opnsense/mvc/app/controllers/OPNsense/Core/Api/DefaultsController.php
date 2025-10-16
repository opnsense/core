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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\ConfigMaintenance;

/**
 * Class DefaultsController
 * @package OPNsense\Core\Api
 */
class DefaultsController extends ApiControllerBase
{
    /**
     * when the user-config-readonly privilege is set, raise an error
     */
    private function throwReadOnly()
    {
        if ((new ACL())->hasPrivilege($this->getUserName(), 'user-config-readonly')) {
            throw new UserException(
                sprintf("User %s denied for write access (user-config-readonly set)", $this->getUserName())
            );
        }
    }

    /**
     * return defaults
     */
    public function getAction()
    {
        $default_ip = '192.168.1.1';
        if (is_file('/usr/local/etc/config.xml')) {
            $cfg = Config::getInstance()->toArrayFromFile('/usr/local/etc/config.xml');
            if (
                is_array($cfg) &&
                !empty($cfg['interfaces']) &&
                !empty($cfg['interfaces']['lan']) &&
                !empty($cfg['interfaces']['lan']['ipaddr'])
            ) {
                $default_ip = $cfg['interfaces']['lan']['ipaddr'];
            }
        }
        return ['default_ip' => $default_ip];
    }

    /**
     * reset to defaults
     */
    public function factoryDefaultsAction()
    {
        $this->throwReadOnly();
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }

        /* schedule factory defaults so we can safely respond to the client */
        (new Backend())->configdRun('system reset_factory_defaults', true);
        return ['status' => 'ok'];
    }

    /**
     * return used configuration items
     */
    public function getInstalledSectionsAction()
    {
        $result = ['items' => []];
        $cm = new ConfigMaintenance();
        foreach ($cm->traverseConfig() as $item) {
            $result['items'][] = $item;
        }
        usort($result['items'], fn($a, $b) => strcasecmp($a['description'], $b['description']));
        return $result;
    }

    /**
     * reset a (list of) section(s)
     */
    public function resetAction()
    {
        $this->throwReadOnly();
        if (
            !$this->request->isPost() ||
            !is_array($this->request->getPost('items')) ||
            empty($this->request->getPost('items'))
        ) {
            return ['status' => 'failed'];
        }
        $cm = new ConfigMaintenance();
        $modelmap = $cm->getMap();
        foreach ($this->request->getPost('items') as $section) {
            if (isset($modelmap[$section])) {
                /* installed model flush */
                $mdl = new $modelmap[$section]['class'](true);
                $mdl->Default();
                $mdl->serializeToConfig(false, true);
            } else {
                $cm->delItem($section);
            }
        }

        Config::getInstance()->save();

        return ['status' => 'ok'];
    }
}
