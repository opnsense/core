<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Auth;

use OPNsense\Core\Config;

/**
 * Authenticator stub, implements local methods
 * @package OPNsense\Auth
 */
abstract class Base
{
    /**
     * @var bool match usernames case insensitive
     */
    protected $caseInSensitiveUsernames = false;

    /**
     * @var array internal list of LDAP errors
     */
    protected $lastAuthErrors = array();

    /**
     * return group memberships
     * @param string $username username to find
     * @return array
     */
    private function groups($username)
    {
        $groups = array();
        $user = $this->getUser($username);
        if ($user != null) {
            $uid = (string)$user->uid;
            $cnf = Config::getInstance()->object();
            if (isset($cnf->system->group)) {
                foreach ($cnf->system->group as $group) {
                    if (isset($group->member)) {
                        foreach ($group->member as $member) {
                            if ((string)$uid == (string)$member) {
                                $groups[] = (string)$group->gid;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $groups;
    }

    /**
     * check if password meets policy constraints, needs implementation if it applies.
     * @param string $username username to check
     * @param string $old_password current password
     * @param string $new_password password to check
     * @return array of unmet policy constraints
     */
    public function checkPolicy($username, $old_password, $new_password)
    {
        return array();
    }

    /**
     * check if the user should change his or her password, needs implementation if it applies.
     * @param string $username username to check
     * @param string $password password to check
     * @return boolean
     */
    public function shouldChangePassword($username, $password = null)
    {
        return false;
    }

    /**
     * user allowed in local group
     * @param string $username username to check
     * @param string $gid group id
     * @return boolean
     */
    public function groupAllowed($username, $gid)
    {
        return in_array($gid, $this->groups($username));
    }

    /**
     * find user settings in local database
     * @param string $username username to find
     * @return SimpleXMLElement|null user settings (xml section)
     */
    protected function getUser($username)
    {
        // search local user in database
        $configObj = Config::getInstance()->object();
        $userObject = null;
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'user' && !empty($value->name)) {
                // depending on caseInSensitiveUsernames setting match exact or case-insensitive
                if (
                    (string)$value->name == $username ||
                    ($this->caseInSensitiveUsernames && strtolower((string)$value->name) == strtolower($username))
                ) {
                    // user found, stop search
                    $userObject = $value;
                    break;
                }
            }
        }
        return $userObject;
    }

    /**
     * return actual username.
     * This is more or less a temporary function to support case insensitive names in sessions
     * @param string $username username
     * @return string
     */
    public function getUserName($username)
    {
        if ($this->caseInSensitiveUsernames) {
            $user = $this->getUser($username);
            if ($user) {
                return (string)$user->name;
            }
        } else {
            return $username;
        }
    }

    /**
     * @return array of auth errors
     */
    public function getLastAuthErrors()
    {
        return $this->lastAuthErrors;
    }
}
