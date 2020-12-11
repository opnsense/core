<?php

/**
 *    Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\Core;

/**
 * Class ACL, access control list management
 * @package OPNsense\Core
 */
class ACL
{
    /**
     * @var array user database
     */
    private $userDatabase = array();

    /**
     * @var array privileges per group
     */
    private $allGroupPrivs = array();

    /**
     * @var array page/endpoint mapping structure
     */
    private $ACLtags = array();

    /**
     * @var string location to store serialized acl
     */
    private $aclCacheFilename = null;

    /**
     * @var int time to live for serialized acl
     */
    private $aclCacheTTL = 3600;

    /**
     * ACL to page/endpoint mapping method.
     * Processes all acl tags containing patterns and generates a key/value store acl/pattern.
     * @return array
     */
    private function loadPageMap()
    {
        $pageMap = array();

        foreach ($this->ACLtags as $aclKey => $aclItem) {
            // check if acl item already exists if there's acl content for it
            if (!array_key_exists($aclKey, $pageMap) && (isset($aclItem["match"]) || isset($aclItem["pattern"]))) {
                $pageMap[$aclKey] = array();
            }
            if (isset($aclItem["match"])) {
                foreach ($aclItem['match'] as $matchexpr) {
                    $pageMap[$aclKey][] = trim($matchexpr);
                }
            }
        }
        return $pageMap;
    }

    /**
     * load user and group privileges into $this->userDatabase and $this->allGroupPrivs
     */
    private function loadUserGroupRights()
    {
        $pageMap = $this->loadPageMap();

        // create privilege mappings
        $this->userDatabase = array();
        $this->allGroupPrivs = array();

        $groupmap = array();

        // gather user / group data from config.xml
        $config = Config::getInstance()->object();
        $userUidMap = array();
        if ($config->system->count() > 0) {
            foreach ($config->system->children() as $key => $node) {
                if ($key == 'user') {
                    $username = (string)$node->name;
                    $uid = (string)$node->uid;
                    $userUidMap[$uid] = $username;
                    $this->userDatabase[$username] = array();
                    $this->userDatabase[$username]['uid'] = $uid;
                    $this->userDatabase[$username]['groups'] = array();
                    $this->userDatabase[$username]['gids'] = array();
                    $this->userDatabase[$username]['priv'] = array();
                    if (!empty($node->landing_page)) {
                        $this->userDatabase[$username]['landing_page'] = (string)$node->landing_page;
                    }
                    foreach ($node->priv as $priv) {
                        $privname = (string)$priv;
                        if (array_key_exists($privname, $pageMap)) {
                            $this->userDatabase[$username]['priv'][] = $pageMap[$privname];
                        }
                    }
                } elseif ($key == 'group') {
                    $groupmap[(string)$node->name] = $node;
                }
            }
        }

        // interpret group privilege data and update user data with group information.
        foreach ($groupmap as $groupkey => $groupNode) {
            $allGroupPrivs[$groupkey] = array();
            foreach ($groupNode->children() as $itemKey => $node) {
                $node_data = (string)$node;
                if ($itemKey == "member" && $node_data != "") {
                    $username = $userUidMap[$node_data];
                    if ($this->userDatabase[$username]["uid"] == $node_data) {
                        $this->userDatabase[$username]["groups"][] = $groupkey;
                        $this->userDatabase[$username]["gids"][] = (string)$groupNode->gid;
                    }
                } elseif ($itemKey == "priv") {
                    if (array_key_exists($node_data, $pageMap)) {
                        $this->allGroupPrivs[$groupkey][] = $pageMap[$node_data];
                    }
                }
            }
        }
    }

    /**
     * merge pluggable ACL xml's into $this->ACLtags
     * @throws \Exception
     */
    private function mergePluggableACLs()
    {
        // crawl all vendors and modules and add acl definitions
        foreach (glob(__DIR__ . '/../../*') as $vendor) {
            foreach (glob($vendor . '/*') as $module) {
                // probe for ACL implementation, which should derive from OPNsense\Core\ACL\ACL
                $tmp = explode("/", $module);
                $module_name = array_pop($tmp);
                $vendor_name = array_pop($tmp);
                $classname = "\\{$vendor_name}\\{$module_name}\\ACL\\ACL";
                if (class_exists($classname)) {
                    $acl_rfcls = new \ReflectionClass($classname);
                    $check_derived = $acl_rfcls;
                    while ($check_derived !== false) {
                        if ($check_derived->name == 'OPNsense\Core\ACL\ACL') {
                            break;
                        }
                        $check_derived = $check_derived->getParentClass();
                    }
                    if ($check_derived === false) {
                        throw new \Exception('ACL class ' . $classname . ' seems to be of wrong type');
                    }
                } else {
                    $acl_rfcls = new \ReflectionClass('OPNsense\Core\ACL\ACL');
                }
                // construct new ACL
                $acl = $acl_rfcls->newInstance($module);
                $acl->update($this->ACLtags);
            }
        }
    }

    /**
     * check url against regex mask
     * @param string $url url to match
     * @param string $urlmask regex mask
     * @return bool url matches mask
     */
    public function urlMatch($url, $urlmask)
    {
        /* "." and "?" have no effect on match, but "*" is a wildcard */
        $match = str_replace(array('.', '*','?'), array('\.', '.*','\?'), $urlmask);
        /* if pattern ends with special markers also match flat URL mask */
        $match = preg_replace('@([/&?])\.\*$@', '($1.*)?', $match);
        /* remove client side pattern from given URL */
        $url = preg_replace('@#.*$@', '', $url);

        $result = preg_match("@^/{$match}$@", "{$url}");
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Construct new ACL object
     */
    public function __construct()
    {
        // set cache location
        $this->aclCacheFilename = sys_get_temp_dir() . "/opnsense_acl_cache.json";

        // load module ACL's
        if (!$this->isExpired()) {
            $this->ACLtags = json_decode(file_get_contents($this->aclCacheFilename), true);
        }
        if (empty($this->ACLtags)) {
            // (re)generate acl mapping and save to cache
            $this->persist();
        }
        // load user and group rights
        $this->loadUserGroupRights();
    }

    /**
     * iterator to collect all assigned access patterns for this user
     * @param string $username user name
     */
    private function urlMasks($username)
    {
        if (array_key_exists($username, $this->userDatabase)) {
            // fetch masks from user privs
            foreach ($this->userDatabase[$username]["priv"] as $privset) {
                foreach ($privset as $urlmask) {
                    yield $urlmask;
                }
            }
            // fetch masks from assigned groups
            foreach ($this->userDatabase[$username]["groups"] as $itemkey => $group) {
                if (array_key_exists($group, $this->allGroupPrivs)) {
                    foreach ($this->allGroupPrivs[$group] as $privset) {
                        foreach ($privset as $urlmask) {
                            yield $urlmask;
                        }
                    }
                }
            }
        }

        /*
         * Always allow logout and menu, should be yielded as final items
         * to prevent redirect to the logout page in case unauthorised
         * pages are tried.
         */
        yield 'index.php?logout';
        yield 'api/core/menu/*';
    }

    /**
     * check if an endpoint url is accessible by the specified user.
     * @param string $username user name
     * @param string $url full url, for example /firewall_rules.php
     * @return bool
     */
    public function isPageAccessible($username, $url)
    {
        if (!empty($_SESSION['user_shouldChangePassword'])) {
            // when a password change is enforced, lock all other endpoints
            return $this->urlMatch($url, 'system_usermanager_passwordmg.php*');
        }
        foreach ($this->urlMasks($username) as $urlmask) {
            if ($this->urlMatch($url, $urlmask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * test if a user has a certain privilege set.
     * (transition method, should be replaced by group membership)
     * @param string $username user name
     * @param string $reqpriv privilege name
     * @return bool
     */
    public function hasPrivilege($username, $reqpriv)
    {
        $uid = null;
        $privs = array();
        $groups = array();
        $config = Config::getInstance()->object();
        if ($config->system->count() > 0) {
            foreach ($config->system->children() as $key => $node) {
                if ($key == 'user' && (string)$node->name == $username) {
                    foreach ($node->priv as $priv) {
                        $privs[] = (string)$priv;
                    }
                    $uid = (string)$node->uid;
                }
            }
            foreach ($config->system->children() as $key => $groupNode) {
                if ($key == 'group') {
                    $group_privs = array();
                    $userInGrp = false;
                    foreach ($groupNode->children() as $itemKey => $node) {
                        if ($node->getName() == "member" && (string)$node == $uid) {
                            $userInGrp = true;
                        } elseif ($node->getName() == "priv") {
                            $group_privs[] = (string)$node;
                        }
                    }
                    if ($userInGrp) {
                        $privs = array_merge($privs, $group_privs);
                    }
                }
            }
        }
        return in_array($reqpriv, $privs);
    }

    /**
     * check if user has group membership
     * @param string $username user name
     * @param string $groupname group name
     * @param boolean $byname query by name (or gid)
     * @return bool|null|string|string[]
     */
    public function inGroup($username, $groupname, $byname = true)
    {
        if (!empty($this->userDatabase[$username])) {
            if ($byname) {
                return in_array($groupname, $this->userDatabase[$username]['groups']);
            } else {
                return in_array($groupname, $this->userDatabase[$username]['gids']);
            }
        }
        return false;
    }

    /**
     * get user preferred landing page
     * @param string $username user name
     * @return bool|null|string|string[]
     */
    public function getLandingPage($username)
    {
        if (!empty($_SESSION['user_shouldChangePassword'])) {
            // ACL lock, may only access password page
            return "system_usermanager_passwordmg.php";
        } elseif (!empty($this->userDatabase[$username]['landing_page'])) {
            $page = $this->userDatabase[$username]['landing_page'];
            if (strpos($page, '/') === 0) {
                // remove leading slash, which would result in redirection to //page (without host) after login or auth failure.
                return substr($page, 1);
            } else {
                return $page;
            }
        } elseif (!empty($this->userDatabase[$username])) {
            // default behaviour, find first accessible location from configured privileges
            foreach ($this->urlMasks($username) as $pattern) {
                if ($pattern == "*") {
                    return "index.php";
                } elseif (!empty($pattern)) {
                    /* remove wildcard and optional trailing slashes or query symbols */
                    return preg_replace('@[/&?]?\*$@', '', $pattern);
                }
                break;
            }
            return null;
        }
    }

    /**
     * return privilege list as array (sorted), only for backward compatibility
     * @return array
     */
    public function getPrivList()
    {
        // convert json priv map to array
        $priv_list = array();
        foreach ($this->ACLtags as $aclKey => $aclItem) {
            $priv_list[$aclKey] = array();
            foreach ($aclItem as $propName => $propValue) {
                if ($propName == 'name') {
                    // translate name tag
                    $priv_list[$aclKey][$propName] = gettext($propValue);
                } else {
                    $priv_list[$aclKey][$propName] = $propValue;
                }
            }
        }

        // sort by name ( case insensitive )
        uasort($priv_list, function ($a, $b) {
            return strcasecmp($a["name"], $b["name"]);
        });

        return $priv_list;
    }

    /**
     * Load and persist ACL configuration to disk.
     * When locked we just load all the module ACL's and continue by default (return false),
     * this has a slight performance impact but is usually better then waiting for likely the same content being
     * written by another session.
     * @param bool $nowait when the cache is locked, skip waiting for it to become available.
     * @return bool has persisted
     */
    public function persist($nowait = true)
    {
        $this->mergePluggableACLs();
        $fp = fopen($this->aclCacheFilename, file_exists($this->aclCacheFilename) ? "r+" : "w+");
        $lockMode = $nowait ? LOCK_EX | LOCK_NB : LOCK_EX;
        if (flock($fp, $lockMode)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($this->ACLtags));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            chmod($this->aclCacheFilename, 0660);
            return true;
        }
        return false;
    }

    /**
     * invalidate cache, removes cache file from disk if available, which forces the next request to persist() again
     */
    public function invalidateCache()
    {
        @unlink($this->aclCacheFilename);
    }

    /**
     * check if pluggable ACL's are expired
     * @return bool is expired
     */
    public function isExpired()
    {
        if (file_exists($this->aclCacheFilename)) {
            $fstat = stat($this->aclCacheFilename);
            return $this->aclCacheTTL < (time() - $fstat['mtime']);
        }
        return true;
    }
}
