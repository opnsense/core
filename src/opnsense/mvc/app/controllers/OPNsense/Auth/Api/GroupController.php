<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Auth\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class GroupController
 * @package OPNsense\Auth\Api
 */
class GroupController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'group';
    protected static $internalModelClass = 'OPNsense\Auth\Group';

    protected function setBaseHook($node)
    {
        $this->getModel()->serializeToConfig(false, true);
        if (!(new ACL())->isPageAccessible($this->getUserName(), '/api/auth/group')) {
            throw new UserException(
                sprintf(gettext("User %s can not lock itself out"), $this->getUserName()),
                gettext("Usermanager")
            );
        }
    }

    public function searchAction()
    {
        return $this->searchBase('group');
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('group', 'group', $uuid);
    }

    public function addAction()
    {
        $result = $this->addBase('group', 'group');
        if ($result['result'] != 'failed') {
            $data = $this->request->getPost(static::$internalModelName);
            (new Backend())->configdRun('auth sync group ' . $data['name']);
        }
        return $result;
    }

    public function setAction($uuid = null)
    {
        $result = $this->setBase('group', 'group', $uuid);
        if ($result['result'] != 'failed') {
            $data = $this->request->getPost(static::$internalModelName);
            (new Backend())->configdRun('auth sync group ' . $data['name']);
        }
        return $result;
    }

    public function delAction($uuid)
    {
        $groupname = null;
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $node = $this->getModel()->getNodeByReference('group.' . $uuid);
            if ($node->scope == 'system') {
                throw new UserException(sprintf(gettext("Not allowed to delete system group %s"), $node->name));
            }
            if (!empty($node)) {
                $groupname = (string)$node->name;
            }
        }
        $result = $this->delBase('group', $uuid);
        if ($groupname != null) {
            (new Backend())->configdRun('auth sync group ' . $groupname);
        }
        return $result;
    }
}
