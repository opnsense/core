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

require_once 'base32/Base32.php';
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Auth\Group;
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class UserController
 * @package OPNsense\Auth\Api
 */
class UserController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'user';
    protected static $internalModelClass = 'OPNsense\Auth\User';

    private $export_ignore = [
        'uid', 'comments', 'password', 'authorizedkeys', 'otp_seed', 'scope', 'scrambled_password', 'dashboard'
    ];

    private function getHostname()
    {
        $config = Config::getInstance()->object();
        return $config->system->hostname . '.' . $config->system->domain;
    }

    protected function setBaseHook($node)
    {
        $this_uid = (string)$node->uid;
        $group_memberships = (string)$node->group_memberships;
        $this_gids = !empty($group_memberships) ? explode(',', $group_memberships) : [];
        $groupmdl = new Group();
        foreach ($groupmdl->group->iterateItems() as $uuid => $group) {
            $members = $group->member->getValues();
            if (in_array($this_uid, $members) && !in_array($group->gid, $this_gids)) {
                unset($members[array_search($this_uid, $members)]);
            } elseif (!in_array($this_uid, $members) && in_array($group->gid, $this_gids)) {
                $members[] = $this_uid;
            } else {
                continue;
            }
            $group->member = implode(',', $members);
        }
        /* will be persisted by regular save */
        $groupmdl->serializeToConfig(false, true);

        if (!(new ACL())->isPageAccessible($this->getUserName(), '/api/auth/user')) {
            throw new UserException(
                sprintf(gettext("User %s can not lock itself out"), $this->getUserName()),
                gettext("Usermanager")
            );
        }

        /* Password handling */
        if (
            !empty((string)$node->scrambled_password) || (
            $node->password->isFieldChanged() && !$node->password->isEmpty()
            )
        ) {
            if (!empty((string)$node->scrambled_password)) {
                /* generate a random password */
                $password = random_bytes(50);
                /* XXX since PHP 8.2.18 we need to avoid NUL char */
                while (($i = strpos($password, "\0")) !== false) {
                    $password[$i] = random_bytes(1);
                }
            } else {
                $password = $node->password->getValue();
            }
            $hash = $this->getModel()->generatePasswordHash($password);
            if ($hash !== false && strpos($hash, '$') === 0) {
                $node->password = $hash;
            } else {
                /* log and throw exception, not being able to hash the password should be fatal. */
                $this->getLogger('audit')->error(sprintf("Failed to hash password for user %s", $node->name));
                throw new UserException(sprintf(gettext("Failed to hash password for user %s"), $node->name));
            }
        }
    }

    public function searchAction()
    {
        $result = $this->searchBase('user');
        if (!empty($result['rows'])) {
            /* XXX: this is a bit of a gimmick, for performance reasons we might decide to drop this at some point  */
            foreach ($result['rows'] as &$row) {
                $row['is_admin'] = in_array('page-all', $this->getModel()->getUserPrivs($row['name'])) ? '1' : '0';
                /* shells usually start with a /, prevent default text and translations triggering the warning */
                $row['shell_warning'] = strpos($row['shell'], '/') === 0 && empty($row['is_admin']) ? '1' : '0';
            }
        }
        return $result;
    }

    public function getAction($uuid = null)
    {
        $result = $this->getBase('user', 'user', $uuid);
        $result['user']['otp_uri'] = '';
        if (!empty($result['user']['otp_seed'])) {
            $result['user']['otp_uri'] = sprintf(
                "otpauth://totp/%s@%s?secret=%s&issuer=OPNsense&image=https://docs.opnsense.org/_static/favicon.png",
                $result['user']['name'],
                $this->getHostname(),
                $result['user']['otp_seed']
            );
        }
        if ((new \OPNsense\Core\ACL())->isPageAccessible($_SESSION['Username'], '/api/trust/cert')) {
            $result['user']['certs'] = [];
        }
        return $result;
    }

    public function downloadAction()
    {
        if ($this->request->isGet()) {
            $data = $this->getModel()->user->asRecordSet(
                false,
                $this->export_ignore
            );
            $this->exportCsv($data);
        }
    }

    public function uploadAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('payload')) {
            /* list of fields not part of our import */
            $that = $this;
            return $this->importCsv(
                'user',
                $this->request->getPost('payload'),
                ['name'],
                function (&$record) use ($that) {
                    foreach ($that->export_ignore as $fieldname) {
                        if (isset($record[$fieldname])) {
                            unset($record[$fieldname]);
                        }
                    }
                },
                function ($node) use ($that) {
                    /* new user without password, scramble one */
                    if ($node->password->isFieldChanged() && $node->password->isEmpty()) {
                        $node->scrambled_password = '1';
                    }
                    $that->setBaseHook($node);
                }
            );
        } else {
            return ['status' => 'failed'];
        }
    }

    public function newOtpSeedAction()
    {
        $seed = \Base32\Base32::encode(random_bytes(20));
        return [
            'seed' => $seed,
            'otp_uri_template' => sprintf(
                "otpauth://totp/%s@%s?secret=%s&issuer=OPNsense&image=https://docs.opnsense.org/_static/favicon.png",
                '|USER|',
                $this->getHostname(),
                $seed
            )
        ];
    }

    public function addAction()
    {
        $data = $this->request->getPost(static::$internalModelName);
        $this->setSaveAuditMessage(sprintf('user "%s" created', $data['name']));
        $result = $this->addBase('user', 'user');
        if ($result['result'] != 'failed') {
            if (!empty($data['name'])) {
                (new Backend())->configdpRun('auth sync user', [$data['name']]);
            }
        }
        return $result;
    }

    public function setAction($uuid = null)
    {
        $data = $this->request->getPost(static::$internalModelName);
        $this->setSaveAuditMessage(sprintf('user "%s" changed', $data['name']));
        $result = $this->setBase('user', 'user', $uuid);
        if ($result['result'] != 'failed') {
            if (!empty($data['name'])) {
                (new Backend())->configdpRun('auth sync user', [$data['name']]);
            }
        }
        return $result;
    }

    public function delAction($uuid)
    {
        $username = null;
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $node = $this->getModel()->getNodeByReference('user.' . $uuid);
            if ($node->scope == 'system') {
                throw new UserException(
                    sprintf(gettext("Not allowed to delete system user %s"), $node->name),
                    gettext("Usermanager")
                );
            } elseif ($node->name == $this->getUserName()) {
                throw new UserException(
                    sprintf(gettext("Not allowed to remove logged in user %s"), $node->name),
                    gettext("Usermanager")
                );
            }
            if (!empty($node)) {
                $username = (string)$node->name;
            }
        }
        $this->setSaveAuditMessage(sprintf('The user "%s" was successfully removed.', $username));
        $result = $this->delBase('user', $uuid);
        if ($username != null) {
            (new Backend())->configdpRun('auth sync user', [$username]);
        }
        return $result;
    }

    public function searchApiKeyAction()
    {
        return $this->searchRecordsetBase($this->getModel()->getApiKeys());
    }

    public function delApiKeyAction($id)
    {
        /* id is a base64 encoded string, we need to encode 'key' to prevent mangling data in the request */
        $key = base64_decode($id);
        if ($key !== null && $this->request->isPost()) {
            Config::getInstance()->lock();
            $user = $this->getModel()->getUserByApiKey($key);
            if ($user !== null) {
                $user->apikeys->del($key);
                $this->save(false, true);
                return ['result' => 'deleted'];
            } else {
                return ['result' => 'not found'];
            }
        }
        return ["result" => "failed"];
    }

    public function addApiKeyAction($username)
    {
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $user = $this->getModel()->getUserByName($username);
            if ($user != null) {
                $tmp = $user->apikeys->add();
                if (!empty($tmp)) {
                    $this->save(false, true);
                    return array_merge(['result' => 'ok', 'hostname' => $this->getHostname()], $tmp);
                }
            }
            Config::getInstance()->unlock();
        }
        return ["result" => "failed"];
    }
}
