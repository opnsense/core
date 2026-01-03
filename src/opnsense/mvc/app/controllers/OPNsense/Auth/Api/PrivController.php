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
use OPNsense\Auth\User;
use OPNsense\Auth\Group;
use OPNsense\Core\ACL;
use OPNsense\Core\Config;

/**
 * Class PrivController
 * @package OPNsense\Auth\Api
 */
class PrivController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'priv';
    protected static $internalModelClass = 'OPNsense\Auth\Priv';


    public function searchAction()
    {
        $userprivs = [];
        $groupprivs = [];
        foreach ((new User())->user->iterateItems() as $user) {
            foreach ($user->priv->getValues() as $priv) {
                if (!isset($userprivs[$priv])) {
                    $userprivs[$priv] = [];
                }
                $userprivs[$priv][] = (string)$user->name;
            }
        }
        foreach ((new Group())->group->iterateItems() as $group) {
            foreach ($group->priv->getValues() as $priv) {
                if (!isset($groupprivs[$priv])) {
                    $groupprivs[$priv] = [];
                }
                $groupprivs[$priv][] = (string)$group->name;
            }
        }

        $records = [];
        foreach ((new ACL())->getPrivList() as $auth => $props) {
            $records[] = [
                'id' => $auth,
                'name' => $props['name'],
                'match' => implode("\n", $props['match'] ?? []),
                'users' =>  $userprivs[$auth] ?? [],
                'groups' =>  $groupprivs[$auth] ?? [],
            ];
        }
        return $this->searchRecordsetBase($records);
    }

    public function getItemAction($id)
    {
        $result = parent::getAction();
        if (isset($result['priv'])) {
            $result['priv']['id'] = $id;
            foreach ((new User())->user->iterateItems() as $uuid => $user) {
                if (
                    in_array($id, $user->priv->getValues()) &&
                    isset($result['priv']['users'][$uuid])
                ) {
                    $result['priv']['users'][$uuid]['selected'] = 1;
                }
            }
            foreach ((new Group())->group->iterateItems() as $uuid => $group) {
                if (
                    in_array($id, $group->priv->getValues()) &&
                    isset($result['priv']['groups'][$uuid])
                ) {
                    $result['priv']['groups'][$uuid]['selected'] = 1;
                }
            }
        }

        return $result;
    }

    public function setItemAction($id)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
        }
        $result = parent::setAction();
        if ($result['result'] != 'failed') {
            $mdl = $this->getModel();
            $usermdl = new User();
            $groupmdl = new Group();
            foreach ([$usermdl->user, $groupmdl->group] as $topic) {
                $uuids = $topic == $usermdl->user ? $mdl->users->getValues() : $mdl->groups->getValues();
                foreach ($topic->iterateItems() as $uuid => $item) {
                    $privlist = $item->priv->getValues();
                    if (!in_array($uuid, $uuids) && in_array($id, $privlist)) {
                        unset($privlist[array_search($id, $privlist)]);
                    } elseif (in_array($uuid, $uuids) && !in_array($id, $privlist)) {
                        $privlist[] = $id;
                    } else {
                        continue;
                    }
                    $item->priv = implode(',', $privlist);
                }
            }
            $usermdl->serializeToConfig(false, true);
            $groupmdl->serializeToConfig(false, true);
            if (!(new ACL())->isPageAccessible($this->getUserName(), '/api/auth/priv')) {
                throw new UserException(
                    sprintf(gettext("User %s can not lock itself out"), $this->getUserName()),
                    gettext("Usermanager")
                );
            }
            Config::getInstance()->save();
        }
        return $result;
    }
}
