<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

use OPNsense\Core\Config;
use OPNsense\Core\Backend;

/**
 * Class LDAP connector
 * @package OPNsense\Auth
 */
class LDAP extends Base implements IAuthConnector
{
    /**
     * @var int ldap version to use
     */
    private $ldapVersion = 3;

    /**
     * @var null base ldap search DN
     */
    private $baseSearchDN = null;

    /**
     * @var null bind internal reference
     */
    private $ldapHandle = null;

    /**
     * @var array list of attributes to return in searches
     */
    private $ldapSearchAttr = array();

    /**
     * @var null|string ldap configuration property set.
     */
    private $ldapBindURL = null;

    /**
     * @var null|string ldap administrative bind dn
     */
    private $ldapBindDN = null;

    /**
     * @var null|string ldap administrative bind passwd
     */
    private $ldapBindPassword = null;

    /**
     * @var null|string user attribute
     */
    private $ldapAttributeUser = null;

    /**
     * @var null|string ldap extended query
     */
    private $ldapExtendedQuery = null;

    /**
     * @var auth containers
     */
    private $ldapAuthcontainers = null;

    /**
     * @var ldap scope
     */
    private $ldapScope = 'subtree';

    /**
     * @var null|string url type (standard, startTLS, SSL)
     */
    private $ldapURLType = null;

    /**
     * @var array list of already known usernames vs distinguished names
     */
    private $userDNmap = array();

    /**
     * @var bool if true, startTLS will be initialized
     */
    private $useStartTLS = false;

    /**
     * when set, $lastAuthProperties will contain the authenticated user properties
     */
    private $ldapReadProperties = false;

    /**
     * when set, synchronize groups defined in memberOf attribute to local database
     */
    private $ldapSyncMemberOf = false;

    /**
     * when set, allow local user creation
     */
    private $ldapSyncCreateLocalUsers = false;

    /**
     * limit the groups which will be considered for sync, empty means all
     */
    private $ldapSyncMemberOfLimit = null;

    /**
     * @var array internal list of authentication properties (returned by radius auth)
     */
    private $lastAuthProperties = array();

    /**
     * close ldap handle if open
     */
    private function closeLDAPHandle()
    {
        if ($this->ldapHandle !== null) {
            @ldap_close($this->ldapHandle);
        }
    }

    /**
     * add additional query result attributes
     * @param $attrName string attribute to append to result list
     */
    private function addSearchAttribute($attrName)
    {
        if (!array_key_exists($attrName, $this->ldapSearchAttr)) {
            $this->ldapSearchAttr[] = $attrName;
        }
    }

    /**
     * search ldap tree
     * @param string $filter ldap filter string to use
     * @return array|bool result list or false on errors
     */
    private function search($filter)
    {
        $result = false;
        if ($this->ldapHandle != null) {
            $searchpaths = array($this->baseSearchDN);
            if (!empty($this->ldapAuthcontainers)) {
                $searchpaths = explode(';', $this->ldapAuthcontainers);
            }
            foreach ($searchpaths as $baseDN) {
                if ($this->ldapScope == 'one') {
                    $sr = @ldap_list($this->ldapHandle, $baseDN, $filter, $this->ldapSearchAttr);
                } else {
                    $sr = @ldap_search($this->ldapHandle, $baseDN, $filter, $this->ldapSearchAttr);
                }
                if ($sr !== false) {
                    $info = @ldap_get_entries($this->ldapHandle, $sr);
                    if ($info !== false) {
                        if ($result === false) {
                            $result = array();
                            $result['count'] = 0;
                        }
                        for ($i = 0; $i < $info["count"]; $i++) {
                            $result['count']++;
                            $result[] = $info[$i];
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * log ldap errors, append ldap error output when available
     * @param string message
     */
    private function logLdapError($message)
    {
        $error_string = "";
        if ($this->ldapHandle !== false) {
            ldap_get_option($this->ldapHandle, LDAP_OPT_ERROR_STRING, $error_string);
            $error_string = str_replace(array("\n","\r","\t"), ' ', $error_string);
            syslog(LOG_ERR, sprintf($message . " [%s; %s]", $error_string, ldap_error($this->ldapHandle)));
            if (!empty($error_string)) {
                $this->lastAuthErrors['error'] = $error_string;
            }
            $this->lastAuthErrors['ldap_error'] = ldap_error($this->ldapHandle);
        } else {
            syslog(LOG_ERR, $message);
        }
    }

    /**
     * type name in configuration
     * @return string
     */
    public static function getType()
    {
        return 'ldap';
    }

    /**
     * user friendly description of this authenticator
     * @return string
     */
    public function getDescription()
    {
        return gettext("LDAP");
    }

    /**
     * construct a new LDAP connector
     * @param null|string $baseSearchDN setup base searchDN or list of DN's separated by ;
     * @param int $ldapVersion setup ldap version
     */
    public function __construct($baseSearchDN = null, $ldapVersion = 3)
    {
        $this->ldapVersion = $ldapVersion;
        $this->baseSearchDN = $baseSearchDN;
        // setup ldap general search list, list gets updated by requested data
        // Note that the "dn" is always returned irrespective of which attributes types are requested
        $this->addSearchAttribute("displayName");
        $this->addSearchAttribute("cn");
        $this->addSearchAttribute("name");
        $this->addSearchAttribute("mail");
    }

    /**
     * set connector properties
     * @param array $config connection properties
     */
    public function setProperties($config)
    {
        $confMap = array("ldap_protver" => "ldapVersion",
            "ldap_basedn" => "baseSearchDN",
            "ldap_binddn" => "ldapBindDN",
            "ldap_bindpw" => "ldapBindPassword",
            "ldap_attr_user" => "ldapAttributeUser",
            "ldap_extended_query" => "ldapExtendedQuery",
            "ldap_authcn" => "ldapAuthcontainers",
            "ldap_scope" => "ldapScope",
            "local_users" => "userDNmap",
            "ldap_read_properties" => "ldapReadProperties",
            "ldap_sync_memberof" => "ldapSyncMemberOf",
            "ldap_sync_memberof_groups" => "ldapSyncMemberOfLimit"
        );

        // map properties 1-on-1
        foreach ($confMap as $confSetting => $objectProperty) {
            if (!empty($config[$confSetting]) && property_exists($this, $objectProperty)) {
                $this->$objectProperty = $config[$confSetting];
            }
        }

        // translate config settings
        // Encryption types: Standard ( none ), StartTLS and SSL
        if (strstr($config['ldap_urltype'], "Standard")) {
            $this->ldapBindURL = "ldap://";
            $this->ldapURLType = "standard";
        } elseif (strstr($config['ldap_urltype'], "StartTLS")) {
            $this->ldapBindURL = "ldap://";
            $this->useStartTLS = true;
            $this->ldapURLType = "StartTLS";
        } else {
            $this->ldapBindURL = "ldaps://";
            $this->ldapURLType = "SSL";
        }

        $this->ldapBindURL .= strpos($config['host'], "::") !== false ? "[{$config['host']}]" : $config['host'];
        if (!empty($config['ldap_port'])) {
            $this->ldapBindURL .= ":{$config['ldap_port']}";
        }
        if (!empty($config['caseInSensitiveUsernames'])) {
            $this->caseInSensitiveUsernames = true;
        }
        if (!empty($config['ldap_sync_create_local_users'])) {
            $this->ldapSyncCreateLocalUsers = true;
        }
    }

    /**
     * retrieve configuration options
     * @return array
     */
    public function getConfigurationOptions()
    {
        $options = array();
        $options["caseInSensitiveUsernames"] = array();
        $options["caseInSensitiveUsernames"]["name"] = gettext("Match case insensitive");
        $options["caseInSensitiveUsernames"]["help"] = gettext("Allow mixed case input when gathering local user settings.");
        $options["caseInSensitiveUsernames"]["type"] = "checkbox";
        $options["caseInSensitiveUsernames"]["validate"] = function ($value) {
            return array();
        };
        return $options;
    }

    /**
     * close ldap handle on destruction
     */
    public function __destruct()
    {
        $this->closeLDAPHandle();
    }

    /**
     * initiate a connection.
     * @param $bind_url string url to use
     * @param null|string $userdn connect dn to use, leave empty for anonymous
     * @param null|string $password password
     * @param int $timeout network timeout
     * @return bool connect status (success/fail)
     */
    public function connect($bind_url, $userdn = null, $password = null, $timeout = 30)
    {
        $retval = false;
        set_error_handler(
            function () {
                null;
            }
        );

        $this->closeLDAPHandle();

        // Note: All TLS options must be set before ldap_connect is called
        if ($this->ldapURLType != "standard") {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_HARD);
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, "/etc/ssl/cert.pem");
        } else {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }
        $this->ldapHandle = @ldap_connect($bind_url);

        if ($this->useStartTLS) {
            ldap_set_option($this->ldapHandle, LDAP_OPT_PROTOCOL_VERSION, 3);
            if (ldap_start_tls($this->ldapHandle) === false) {
                $this->logLdapError("Could not startTLS on ldap connection");
                $this->ldapHandle = false;
            }
        }

        if ($this->ldapHandle !== false) {
            ldap_set_option($this->ldapHandle, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
            ldap_set_option($this->ldapHandle, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($this->ldapHandle, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
            ldap_set_option($this->ldapHandle, LDAP_OPT_PROTOCOL_VERSION, (int)$this->ldapVersion);
            $bindResult = @ldap_bind($this->ldapHandle, $userdn, $password);
            if ($bindResult) {
                $retval = true;
            } else {
                $this->logLdapError("LDAP bind error");
            }
        }

        restore_error_handler();
        if (!$retval) {
            $this->ldapHandle = null;
        }
        return $retval;
    }

    /**
     * search user by name or expression
     * @param string $username username(s) to search
     * @param string $userNameAttribute ldap attribute to use for the search
     * @param string|null $extendedQuery additional search criteria (narrow down search)
     * @return array|bool
     */
    public function searchUsers($username, $userNameAttribute, $extendedQuery = null)
    {
        if ($this->ldapHandle !== false) {
            // on Active Directory sAMAccountName is returned as samaccountname
            $userNameAttribute = strtolower($userNameAttribute);
            // add $userNameAttribute to search results
            $this->addSearchAttribute($userNameAttribute);
            $result = array();
            if (empty($extendedQuery)) {
                $searchResults = $this->search("({$userNameAttribute}={$username})");
            } else {
                // add additional search phrases
                $searchResults = $this->search("(&({$userNameAttribute}={$username})({$extendedQuery}))");
            }
            if ($searchResults !== false) {
                for ($i = 0; $i < $searchResults["count"]; $i++) {
                    // fetch distinguished name and most likely username (try the search field first)
                    foreach (array($userNameAttribute, "name") as $ldapAttr) {
                        if (isset($searchResults[$i][$ldapAttr]) && $searchResults[$i][$ldapAttr]['count'] > 0) {
                            $user = array(
                                'name' => $searchResults[$i][$ldapAttr][0],
                                'dn' => $searchResults[$i]['dn']
                            );
                            if (!empty($searchResults[$i]['displayname'][0])) {
                                $user['fullname'] = $searchResults[$i]['displayname'][0];
                            } elseif (!empty($searchResults[$i]['cn'][0])) {
                                $user['fullname'] = $searchResults[$i]['cn'][0];
                            } elseif (!empty($searchResults[$i]['name'][0])) {
                                $user['fullname'] = $searchResults[$i]['name'][0];
                            } else {
                                $user['fullname'] = '';
                            }
                            if (!empty($searchResults[$i]['mail'][0])) {
                                $user['email'] = $searchResults[$i]['mail'][0];
                            } else {
                                $user['email'] = '';
                            }
                            $result[] = $user;
                            break;
                        }
                    }
                }
                return $result;
            }
        }
        return false;
    }

    /**
     * List organizational units
     * @return array|bool list of OUs or false on failure
     */
    public function listOUs()
    {
        $result = array();
        if ($this->ldapHandle !== false) {
            $searchResults = $this->search("(|(ou=*)(cn=Users))");
            if ($searchResults !== false) {
                $this->logLdapError("LDAP containers search result count: " . $searchResults["count"]);
                for ($i = 0; $i < $searchResults["count"]; $i++) {
                    $result[] = $searchResults[$i]['dn'];
                }

                return $result;
            } else {
                   $this->logLdapError("LDAP containers search returned no results");
            }
        }

        return false;
    }

    /**
     * unused
     * @return array mixed named list of authentication properties
     */
    public function getLastAuthProperties()
    {
        return $this->lastAuthProperties;
    }

    /**
     * update user group policies when configured
     * @param string $username authenticated username
     */
    private function updatePolicies($username)
    {
        if ($this->ldapSyncMemberOf && !empty($this->lastAuthProperties['memberof'])) {
            $user = $this->getUser($username);
            // gather known and user configured groups to be able to compare the results from ldap
            $user_groups = array();
            $known_groups = array();
            $cnf = Config::getInstance()->object();
            if (isset($cnf->system->group)) {
                foreach ($cnf->system->group as $group) {
                    $known_groups[] = strtolower((string)$group->name);
                    // when user is known, collect current groups
                    if ($user != null && in_array((string)$user->uid, (array)$group->member)) {
                        $user_groups[] = strtolower((string)$group->name);
                    }
                }
            }
            // collect all groups from the memberof attribute, store full object path for logging
            // first cn= defines our local groupname
            $ldap_groups = array();
            foreach (explode("\n", $this->lastAuthProperties['memberof']) as $member) {
                if (stripos($member, "cn=") === 0) {
                    $ldap_groups[strtolower(explode(",", substr($member, 3))[0])] = $member;
                }
            }
            // list of enabled groups (all when empty), so we can ignore some local groups if needed
            if (!empty($this->ldapSyncMemberOfLimit)) {
                $sync_groups = explode(",", strtolower($this->ldapSyncMemberOfLimit));
            } else {
                $sync_groups = $known_groups;
            }
            //
            // sort groups and intersect with $sync_groups to determine difference.
            natcasesort($sync_groups);
            natcasesort($user_groups);
            natcasesort($ldap_groups);
            $diff_ugrp = array_intersect($sync_groups, $user_groups);
            $diff_lgrp = array_intersect($sync_groups, array_keys($ldap_groups));
            if ($diff_lgrp != $diff_ugrp) {
                // update when changed
                if ($user == null && $this->ldapSyncCreateLocalUsers) {
                    // user creation when enabled
                    $add_user = json_decode((new Backend())->configdpRun("auth add user", array($username)), true);
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
                        if (
                            in_array((string)$user->uid, (array)$group->member)
                              && empty($ldap_groups[$lc_groupname])
                        ) {
                            unset($group->member[array_search((string)$user->uid, (array)$group->member)]);
                            syslog(LOG_NOTICE, sprintf(
                                'User: policy change for %s unlink group %s',
                                $username,
                                (string)$group->name
                            ));
                        } elseif (
                            !in_array((string)$user->uid, (array)$group->member)
                              && !empty($ldap_groups[$lc_groupname])
                        ) {
                            syslog(LOG_NOTICE, sprintf(
                                'User: policy change for %s link group %s [%s]',
                                $username,
                                (string)$group->name,
                                $ldap_groups[$lc_groupname]
                            ));
                            $group->addChild('member', (string)$user->uid);
                        }
                    }
                }
                Config::getInstance()->save();
                (new Backend())->configdpRun("auth user changed", array($username));
            }
        }
    }

    /**
     * authenticate user against ldap server
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool authentication status
     */
    public function authenticate($username, $password)
    {
        $ldap_is_connected = false;
        $user_dn = null;
        // authenticate user
        if (empty($password)) {
            // prevent anonymous bind
            return false;
        } elseif (array_key_exists($username, $this->userDNmap)) {
            // we can map $username to distinguished name, just feed to connect
            $user_dn = $this->userDNmap[$username];
            $ldap_is_connected = $this->connect($this->ldapBindURL, $this->userDNmap[$username], $password);
        } else {
            // we don't know this users distinguished name, try to find it
            if ($this->connect($this->ldapBindURL, $this->ldapBindDN, $this->ldapBindPassword)) {
                $result = $this->searchUsers($username, $this->ldapAttributeUser, $this->ldapExtendedQuery);
                if ($result !== false && count($result) > 0) {
                    $user_dn = $result[0]['dn'];
                    $ldap_is_connected = $this->connect($this->ldapBindURL, $result[0]['dn'], $password);
                } else {
                    $this->lastAuthErrors['error'] = "User DN not found";
                }
            }
        }

        if ($ldap_is_connected) {
            $this->lastAuthProperties['dn'] = $user_dn;
            if ($this->ldapReadProperties) {
                $sr = @ldap_read($this->ldapHandle, $user_dn, '(objectclass=*)', ['*', 'memberOf']);
                $info = @ldap_get_entries($this->ldapHandle, $sr);
                if ($info['count'] != 0) {
                    foreach ($info[0] as $ldap_key => $ldap_value) {
                        if (!is_numeric($ldap_key) && $ldap_key !== 'count') {
                            if (isset($ldap_value['count'])) {
                                unset($ldap_value['count']);
                                $this->lastAuthProperties[$ldap_key] = implode("\n", $ldap_value);
                            } elseif ($ldap_value !== "") {
                                $this->lastAuthProperties[$ldap_key] = $ldap_value;
                            }
                        }
                    }
                    // update group policies when applicable
                    $this->updatePolicies($username);
                }
            }
        }

        return $ldap_is_connected;
    }
}
