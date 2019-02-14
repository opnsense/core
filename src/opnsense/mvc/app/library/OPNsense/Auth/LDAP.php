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
     * @var null|string certificate reference (in /var/run/certs/)
     */
    private $ldapCAcert = null;

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
            syslog(LOG_ERR, sprintf($message . " [%s,%s]", $error_string, ldap_error($this->ldapHandle)));
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
        $this->addSearchAttribute("dn");
        $this->addSearchAttribute("name");
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
            "ldap_read_properties" => "ldapReadProperties"
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
        } elseif (strstr($config['ldap_urltype'], "StartTLS")) {
            $this->ldapBindURL = "ldap://";
            $this->useStartTLS = true;
        } else {
            $this->ldapBindURL = "ldaps://";
        }

        $this->ldapBindURL .= strpos($config['host'], "::") !== false ? "[{$config['host']}]" : $config['host'];
        if (!empty($config['ldap_port'])) {
            $this->ldapBindURL .= ":{$config['ldap_port']}";
        }

        // setup environment
        if (!empty($config['ldap_caref']) && stristr($config['ldap_urltype'], "standard") === false) {
            $this->setupCaEnv($config['ldap_caref']);
        }
    }

    /**
     * setup certificate environment
     * @param string $caref ca reference
     */
    public function setupCaEnv($caref)
    {
        $this->ldapCAcert = null;
        if (isset(Config::getInstance()->object()->ca)) {
            foreach (Config::getInstance()->object()->ca as $cert) {
                if (isset($cert->refid) && (string)$caref == $cert->refid) {
                    $this->ldapCAcert = (string)$cert->refid;
                    @mkdir("/var/run/certs");
                    @unlink("/var/run/certs/{$this->ldapCAcert}.ca");
                    file_put_contents("/var/run/certs/{$this->ldapCAcert}.ca", base64_decode((string)$cert->crt));
                    @chmod("/var/run/certs/{$this->ldapCAcert}.ca", 0644);
                    break;
                }
            }
        }
        if (empty($this->ldapCAcert)) {
            syslog(LOG_ERR, sprintf('LDAP: Could not lookup CA by reference for host %s.', $caref));
        }
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
        if (!empty($this->ldapCAcert)) {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_HARD);
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, '/var/run/certs');
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, "/var/run/certs/{$this->ldapCAcert}.ca");
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
                            $result[] = array(
                                'name' => $searchResults[$i][$ldapAttr][0],
                                'fullname' => !empty($searchResults[$i]['name'][0]) ?
                                    $searchResults[$i]['name'][0] : "",
                                'dn' => $searchResults[$i]['dn']
                            );
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
                for ($i = 0; $i < $searchResults["count"]; $i++) {
                    $result[] = $searchResults[$i]['dn'];
                }

                return $result;
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
                }
            }
        }

        if ($ldap_is_connected) {
            $this->lastAuthProperties['dn'] = $user_dn;
            if ($this->ldapReadProperties) {
                $sr = @ldap_read($this->ldapHandle, $user_dn, '(objectclass=*)');
                $info = @ldap_get_entries($this->ldapHandle, $sr);
                if ($info['count'] != 0) {
                    // $this->lastAuthProperties['info'] = $info[0];
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
                }
            }
        }

        return $ldap_is_connected;
    }
}
