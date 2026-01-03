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
use OPNsense\Core\Backend;

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
    protected $lastAuthErrors = [];

    /**
     * return group memberships
     * @param string $username username to find
     * @return array
     */
    private function groups($username)
    {
        $groups = [];
        $user = $this->getUser($username);
        if ($user != null) {
            $uid = (string)$user->uid;
            $cnf = Config::getInstance()->object();
            if (isset($cnf->system->group)) {
                foreach ($cnf->system->group as $group) {
                    if (isset($group->member)) {
                        foreach ($group->member as $member) {
                            if (in_array((string)$uid, explode(',', $member))) {
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
        return [];
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
     * update user group membership
     * @param string $username username
     * @param string $memberof list (\n separated) of groups
     * @param array $scope list of groups that should be considered
     * @param boolean $createuser create user when it does not exist
     * @param array $default_groups list of groups to always add
     */
    protected function setGroupMembership($username, $memberof, $scope = [], $createuser = false, $default_groups = [])
    {
        $user = $this->getUser($username);
        // gather known and user configured groups to be able to compare the results from ldap
        $user_groups = [];
        $known_groups = [];
        $cnf = Config::getInstance()->object();
        if (isset($cnf->system->group)) {
            foreach ($cnf->system->group as $group) {
                $known_groups[] = strtolower((string)$group->name);
                // when user is known, collect current groups
                $group_members = explode(',', implode(',', (array)$group->member));
                if ($user != null && in_array((string)$user->uid, $group_members)) {
                    $user_groups[] = strtolower((string)$group->name);
                }
            }
        }
        // append default groups
        $ldap_groups = [];
        foreach ($default_groups as $key) {
            $ldap_groups[$key] = $key;
        }
        // collect all groups from the memberof attribute, store full object path for logging
        // first cn= defines our local groupname
        foreach (explode("\n", $memberof) as $member) {
            if (stripos($member, "cn=") === 0) {
                $ldap_groups[strtolower(explode(",", substr($member, 3))[0])] = $member;
            }
        }
        // list of enabled groups (all when empty), so we can ignore some local groups if needed
        $sync_groups = !empty($scope) ? array_merge($scope, $default_groups) : $known_groups;

        //
        // sort groups and intersect with $sync_groups to determine difference.
        natcasesort($sync_groups);
        natcasesort($user_groups);
        natcasesort($ldap_groups);
        $diff_ugrp = array_intersect($sync_groups, $user_groups);
        $diff_lgrp = array_intersect($sync_groups, array_keys($ldap_groups));
        if ($diff_lgrp != $diff_ugrp) {
            // update when changed
            if ($user == null && $createuser) {
                // user creation when enabled
                $add_user = json_decode((new Backend())->configdpRun("auth add user", [$username]), true);
                if (!empty($add_user) && $add_user['status'] == 'ok') {
                    Config::getInstance()->forceReload();
                    $user = $this->getUser($username);
                }
            }
            if ($user == null) {
                return;
            }
            // Lock our configuration while updating, remove now unassigned groups and add new ones
            // if returned by ldap.
            $cnf = Config::getInstance()->lock(true)->object();
            foreach ($cnf->system->group as $group) {
                $lc_groupname = strtolower((string)$group->name);
                if (in_array($lc_groupname, $sync_groups)) {
                    $members = [];
                    foreach ($group->member as $member) {
                        $members = array_merge($members, explode(',', $member));
                    }
                    if (in_array((string)$user->uid, $members) && empty($ldap_groups[$lc_groupname])) {
                        while (in_array((string)$user->uid, $members) && empty($ldap_groups[$lc_groupname])) {
                            unset($members[array_search((string)$user->uid, $members)]);
                        }
                        $group->member = implode(',', $members);
                        syslog(LOG_NOTICE, sprintf(
                            'User: policy change for %s unlink group %s',
                            $username,
                            (string)$group->name
                        ));
                    } elseif (!in_array((string)$user->uid, $members) && !empty($ldap_groups[$lc_groupname])) {
                        syslog(LOG_NOTICE, sprintf(
                            'User: policy change for %s link group %s [%s]',
                            $username,
                            (string)$group->name,
                            $ldap_groups[$lc_groupname]
                        ));
                        $group->member = implode(',', array_merge($members, [(string)$user->uid]));
                    }
                }
            }
            Config::getInstance()->save();
            (new Backend())->configdpRun("auth user changed", [$username]);
        }
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

    /**
     * authenticate user, implementation when using this base classes authenticate()
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool
     */
    protected function _authenticate($username, $password)
    {
        return false;
    }

    public function preauth($config)
    {
        return $this;
    }

    /**
     * authenticate user, when failed, make sure we always spend the same time for the sequence.
     * This also adds a penalty for failed attempts.
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool
     */
    public function authenticate($username, $password)
    {
        $tstart = microtime(true);
        $expected_time = 2000000; /* failed login, aim at 2 seconds total time */
        $result = $this->_authenticate($username, $password);

        $timeleft = $expected_time - ((microtime(true) - $tstart) * 1000000);
        if (!$result && $timeleft > 0) {
            usleep((int)$timeleft);
        }

        return $result;
    }
}
