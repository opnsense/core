<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
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
 * Class ACL, this version is only for legacy support and should eventually be replaced by a decent model.
 * @package OPNsense\Core
 */
class ACL
{
    /**
     * @var array legacy users
     */
    private $legacyUsers = array();

    /**
     * @var array privileges per group
     */
    private $legacyGroupPrivs = array();

    /**
     * @var array old page mapping structure
     */
    private $legacyACL = array();

    /**
     * temporary hack to support the old pfSense priv to page mapping and metadata.
     * @return array
     */
    private function loadLegacyPageMap()
    {
        $legacyPageMap = array();

        foreach ($this->legacyACL as $aclKey => $aclItem) {
            if (property_exists($aclItem, "match")) {
                // check if acl item already exists and add match expressions
                if (!array_key_exists($aclKey, $legacyPageMap)) {
                    $legacyPageMap[$aclKey] = array();
                }
                foreach ($aclItem->match as $matchexpr) {
                    $legacyPageMap[$aclKey][] = trim($matchexpr);
                }
            }
        }
        return $legacyPageMap;
    }

    /**
     * init legacy ACL features
     */
    private function initLegacy()
    {
        // load legacy acl from json file
        $this->legacyACL = json_decode(file_get_contents(__DIR__."/ACL_Legacy_Page_Map.json"));

        // create privilege mappings
        $this->legacyUsers = array();
        $this->legacyGroupPrivs = array();

        $legacyPageMap = $this->loadLegacyPageMap();

        $groupmap = array();

        // gather user / group data from config.xml
        $config = Config::getInstance()->object() ;
        foreach ($config->system->children() as $key => $node) {
            if ($key == 'user') {
                $this->legacyUsers[$node->name->__toString()] = array() ;
                $this->legacyUsers[$node->name->__toString()]['uid'] = $node->uid->__toString();
                $this->legacyUsers[$node->name->__toString()]['groups'] = array();
                $this->legacyUsers[$node->name->__toString()]['priv'] = array();
                foreach ($node->priv as $priv) {
                    if (substr($priv, 0, 5) == 'page-') {
                        if (array_key_exists($priv->__toString(), $legacyPageMap)) {
                            $this->legacyUsers[$node->name->__toString()]['priv'][] =
                                $legacyPageMap[$priv->__toString()];
                        }
                    }
                }
            } elseif ($key == 'group') {
                $groupmap[$node->name->__toString()] = $node ;
            }
        }

        // interpret group privilege data and update user data with group information.
        foreach ($groupmap as $groupkey => $groupNode) {
            $legacyGroupPrivs[$groupkey] = array();
            foreach ($groupNode->children() as $itemKey => $node) {
                if ($node->getName() == "member" && $node->__toString() != "") {
                    foreach ($this->legacyUsers as $username => $userinfo) {
                        if ($this->legacyUsers[$username]["uid"] == $node->__toString()) {
                            $this->legacyUsers[$username]["groups"][] = $groupkey;
                        }
                    }
                } elseif ($node->getName() == "priv" && substr($node->__toString(), 0, 5) == "page-") {
                    if (array_key_exists($node->__toString(), $legacyPageMap)) {
                        $this->legacyGroupPrivs[$groupkey][] = $legacyPageMap[$node->__toString()];
                    }
                }
            }
        }
    }

    /**
     * check url against regex mask
     * @param $url url to match
     * @param $urlmask regex mask
     * @return bool url matches mask
     */
    private function urlMatch($url, $urlmask)
    {
        $match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $urlmask);
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
        $this->initLegacy();
    }

    /**
     * legacy functionality to check if a page is accessible for the specified user.
     * @param $username user name
     * @param $url full url, for example /firewall_rules.php
     * @return bool
     */
    public function isPageAccessible($username, $url)
    {
        if (array_key_exists($username, $this->legacyUsers)) {
            // search user privs
            foreach ($this->legacyUsers[$username]["priv"] as $privset) {
                foreach ($privset as $urlmask) {
                    if ($this->urlMatch($url, $urlmask)) {
                        return true;
                    }
                }
            }
            // search group privs
            foreach ($this->legacyUsers[$username]["groups"] as $itemkey => $group) {
                if (array_key_exists($group, $this->legacyGroupPrivs)) {
                    foreach ($this->legacyGroupPrivs[$group] as $privset) {
                        foreach ($privset as $urlmask) {
                            if ($this->urlMatch($url, $urlmask)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * return privilege list as array (sorted)
     * @return array
     */
    public function getLegacyPrivList()
    {
        // convert json priv map to array
        $priv_list = array();
        foreach ($this->legacyACL as $aclKey => $aclItem) {
            $priv_list[$aclKey] = array();
            foreach ($aclItem as $propName => $propValue) {
                if ($propName == 'name' || $propName == 'descr') {
                    // translate name and description tags
                    $priv_list[$aclKey][$propName] = gettext($propValue);
                } else {
                    $priv_list[$aclKey][$propName] = $propValue;
                }
            }
        }

        // sort by name ( case insensitive )
        uasort($priv_list, function($a, $b) {
            return strcasecmp($a["name"], $b["name"]) ;
        });

        return $priv_list;
    }
}
