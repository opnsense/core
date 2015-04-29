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
    private $legacyUsers = array();
    private $legacyGroupPrivs = array();

    /**
     * temporary hack to support the old pfSense priv to page mapping.
     * @return array
     */
    private function loadLegacyPageMap()
    {
        $legacyPageMap = array();
        $handle = fopen(__DIR__."/ACL_Legacy_Page_Map.txt", "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $parts = explode("=", $line);
                if (count($parts) == 2) {
                    if (array_key_exists($parts[0], $legacyPageMap) == 0) {
                        $legacyPageMap[$parts[0]] = array();
                    }
                    $legacyPageMap[$parts[0]][] = trim($parts[1]);
                }
            }
            fclose($handle);
        }

        return $legacyPageMap;
    }

    /**
     * init legacy ACL features
     */
    private function initLegacy()
    {
        $this->legacyUsers = array();
        $this->legacyGroupPrivs = array();

        $legacyPageMap = $this->loadLegacyPageMap();

        $groupmap = array();

        // gather user / group data from config.xml
        $config = Config::getInstance()->object() ;
        foreach ($config->system->children() as $key => $node) {
            if ($key == "user") {
                $this->legacyUsers[$node->name->__toString()] = array() ;
                $this->legacyUsers[$node->name->__toString()]["uid"] = $node->uid->__toString();
                $this->legacyUsers[$node->name->__toString()]["groups"] = array();
                $this->legacyUsers[$node->name->__toString()]["priv"] = array();
                foreach ($node->priv as $priv) {
                    if (substr($priv, 0, 5) == "page-") {
                        if (array_key_exists($priv->__toString(), $legacyPageMap)) {
                            $this->legacyUsers[$node->name->__toString()]["priv"][] = $legacyPageMap[$priv->__toString()] ;
                        }
                    }
                }                
            } elseif ($key == "group") {
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
                    $match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $urlmask);
                    $result = preg_match("@^/{$match}$@", "{$url}");
                    if ($result) {
                        return true;
                    }
                }                        
            }
            // search groups
            foreach ($this->legacyUsers[$username]["groups"] as $itemkey => $group) {
                if (array_key_exists($group, $this->legacyGroupPrivs)) {
                    foreach ($this->legacyGroupPrivs[$group] as $privset) {
                        foreach ($privset as $urlmask) {
                            $match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $urlmask);
                            $result = preg_match("@^/{$match}$@", "{$url}");
                            if ($result) {
                                return true;
                            }
                        }
                    }
                }

            }
        }

        return false;
    }

    public function __construct()
    {
        $this->initLegacy();
    }
}
