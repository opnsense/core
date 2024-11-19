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

namespace OPNsense\Auth;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

/**
 * Class User
 * @package OPNsense\System
 */
class User extends BaseModel
{
    /**
     * @param string $name username
     * @return User object
     */
    public function getUserByName(string $name)
    {
        foreach ($this->user->iterateItems() as $node) {
            if ($node->name == $name) {
                return $node;
            }
        }
        return null;
    }

    /**
     * @param string $name username
     * @return User object
     */
    public function getUserByApiKey(string $key)
    {
        foreach ($this->user->iterateItems() as $node) {
            if ($node->apikeys->get($key) !== null) {
                return $node;
            }
        }
        return null;
    }

    /**
     * @param string $key api key
     * @return array authentication data
     */
    public function getApiKeySecret(string $key)
    {
        foreach ($this->user->iterateItems() as $node) {
            $item = $node->apikeys->get($key);
            if (!empty($item)) {
                $item['name'] = (string)$node->name;
                $item['disabled'] = (string)$node->disabled;
                $item['expires'] = (string)$node->expires;
                return $item;
            }
        }
        return null;
    }

    /**
     * @return array list of api key records
     */
    public function getApiKeys()
    {
        $result = [];
        foreach ($this->user->iterateItems() as $node) {
            foreach ($node->apikeys->all() as $apikey) {
                $result[] = array_merge(['username' => (string)$node->name], $apikey);
            }
        }
        return $result;
    }

    /**
     * @param string username
     * @return array list of privileges for this user
     */
    public function getUserPrivs($username)
    {
        $result = [];
        foreach ($this->user->iterateItems() as $node) {
            if ($node->name == $username) {
                $result = array_merge($result, explode(',', $node->priv));
                $all_groups = explode(',', $node->group_memberships);
                if (empty($all_groups)) {
                    break;
                }
                foreach (Config::getInstance()->object()->system->group as $node) {
                    if (in_array($node->gid, $all_groups)) {
                        foreach ($node->priv as $value) {
                            foreach (explode(',', $value) as $priv) {
                                if (!in_array($priv, $result)) {
                                    $result[] = $priv;
                                }
                            }
                        }
                    }
                }
                break;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        foreach ($this->user->iterateItems() as $node) {
            if (!$validateFullModel && !$node->isFieldChanged()) {
                continue;
            }
            $key = $node->__reference;
            if (empty((string)$node->password->getCurrentValue()) && empty((string)$node->scrambled_password)) {
                $messages->appendMessage(new Message(gettext("A password is required"), $key . ".password"));
            }
            /* XXX: validate reserved users? (/etc/passwd)*/
        }
        return $messages;
    }

    /**
     * @param string password
     * @return hash, type dependend on configuration
     */
    public function generatePasswordHash($password)
    {
        $hash = false;
        $webgui = Config::getInstance()->object()->system->webgui;
        if (
            !empty($webgui) &&
            !empty((string)$webgui->enable_password_policy_constraints) &&
            !empty((string)$webgui->password_policy_compliance)
        ) {
            /* compliance SHA-512 hashing */
            $process = proc_open(
                '/usr/local/bin/openssl passwd -6 -stdin',
                [['pipe', 'r'], ['pipe', 'w']],
                $pipes
            );
            if (is_resource($process)) {
                fwrite($pipes[0], $password);
                fclose($pipes[0]);
                $hash = trim(stream_get_contents($pipes[1]));
                fclose($pipes[1]);
                proc_close($process);
            }
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, [ 'cost' => 11 ]);
        }
        return $hash;
    }
}
