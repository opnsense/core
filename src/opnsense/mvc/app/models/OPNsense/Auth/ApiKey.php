<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
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

namespace OPNsense\Auth;

use OPNsense\Auth\User;
use OPNsense\Base\BaseModel;

/**
 * Class ApiKey
 * @package OPNsense\Auth
 */
class ApiKey extends BaseModel
{
    /**
     * Merge configuration data in "in memory" model on construction
     */
    public function __construct($lazyload = false)
    {
        parent::__construct($lazyload);
        foreach ((new User())->user->iterateItems() as $user) {
            foreach ($user->apikeys->all() as $item) {
                $node = $this->apikey->add($item['id']);
                $node->username = $user->name->getValue();
                $node->key = $item['key'];
                $node->created = $item['created'];
                $node->descr = $item['descr'];
                $node->descr->markUnchanged();
            }
        }
    }

    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        $usermdl = new User();
        $allids = [];
        foreach ($this->apikey->iterateItems() as $uuid => $apikey) {
            $allids[] = $uuid;
            if ($apikey->descr->isFieldChanged()) {
                $key = base64_decode($uuid); /* id = base64 decoded key */
                $usermdl->getUserByApiKey($key)?->apikeys?->setDescription($key, $apikey->descr->getValue());
            }
        }

        foreach ($usermdl->user->iterateItems() as $user) {
            foreach ($user->apikeys->all() as $item) {
                if (!in_array($item['id'], $allids)) {
                    $user->apikeys->del($item['key']);
                }
            }
        }

        /* actions are reflected into User model */
        return $usermdl->serializeToConfig(false, true);
    }
}
