<?php

/*
 * Copyright (C) 2015-2019 Deciso B.V.
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
 * Class AuthenticationFactory
 * @package OPNsense\Auth
 */
class AuthenticationFactory
{
    /**
     * @var IAuthConnector|null last used authentication method in authenticate()
     */
    var $lastUsedAuth = null;

    /**
     * search already known local userDN's into simple mapping if auth method is current standard method
     * @param string $authserver auth server name
     * @return array list of dn's
     */
    private function fetchUserDNs($authserver = null)
    {
        $result = array();
        $configObj = Config::getInstance()->object();
        if (
            $authserver == null || (isset($configObj->system->webgui->authmode) &&
                (string)$configObj->system->webgui->authmode == $authserver)
        ) {
            foreach ($configObj->system->children() as $key => $value) {
                if ($key == 'user' && !empty($value->user_dn)) {
                    $result[(string)$value->name] = (string)$value->user_dn;
                }
            }
        }
        return $result;
    }

    /**
     * list installed auth connectors
     * @return array
     */
    private function listConnectors()
    {
        $connectors = array();
        foreach (glob(__DIR__ . "/*.php") as $filename) {
            $pathParts = explode('/', $filename);
            $vendor = $pathParts[count($pathParts) - 3];
            $module = $pathParts[count($pathParts) - 2];
            $classname = explode('.php', $pathParts[count($pathParts) - 1])[0];
            $reflClass = new \ReflectionClass("{$vendor}\\{$module}\\{$classname}");
            if ($reflClass->implementsInterface('OPNsense\\Auth\\IAuthConnector')) {
                if ($reflClass->hasMethod('getType')) {
                    $connectorType = $reflClass->getMethod('getType')->invoke(null);
                    $connector = array();
                    $connector['class'] = "{$vendor}\\{$module}\\{$classname}";
                    $connector['classHandle'] = $reflClass;
                    $connector['type'] = $connectorType;
                    $connectors[$connectorType] = $connector;
                }
            }
        }
        return $connectors;
    }

    /**
     * request list of configured servers, the factory needs to be aware of its options and settings to
     * be able to instantiate useful connectors.
     * @return array list of configured servers
     */
    public function listServers()
    {
        $servers = array();
        $servers['Local Database'] = array("name" => "Local Database", "type" => "local");
        $configObj = Config::getInstance()->object();
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'authserver' && !empty($value->type) && !empty($value->name)) {
                $authServerSettings = array();
                foreach ($value as $itemKey => $itemValue) {
                    $authServerSettings[$itemKey] = (string)$itemValue;
                }
                $servers[$authServerSettings['name']] = $authServerSettings;
            }
        }

        return $servers;
    }

    /**
     * get new authenticator
     * @param string $authserver authentication server name
     * @return IAuthConnector|null
     */
    public function get($authserver)
    {
        $localUserMap = array();
        $servers = $this->listServers();
        $servers['Local API'] = array("name" => "Local API Database", "type" => "api");
        // create a new auth connector
        if (isset($servers[$authserver]['type'])) {
            $connectors = $this->listConnectors();
            if (!empty($connectors[$servers[$authserver]['type']])) {
                $authObject = $connectors[$servers[$authserver]['type']]['classHandle']->newInstance();
            }
            if (in_array($servers[$authserver]['type'], array('ldap', 'ldap-totp'))) {
                $localUserMap = $this->fetchUserDNs();
            }

            if (isset($authObject)) {
                $props = $servers[$authserver];
                // when a local user exist and has a different (distinguished) name on the authenticator we already
                // know of, we send the mapping to the authenticator as property "local_users".
                $props['local_users'] = $localUserMap;
                $authObject->setProperties($props);
                return $authObject;
            }
        }

        return null;
    }

    /**
     * get Service object
     * @param $service_name string service name to use, defined in Services directory
     * @return IService|null service object or null if not found
     */
    public function getService($service_name)
    {
        $aliases = array();
        // cleanse service name
        $srv_name = strtolower(str_replace(array('-', '_'), '', $service_name));
        foreach (glob(__DIR__ . "/Services/*.php") as $filename) {
            $srv_found = basename($filename, '.php');
            $reflClass = new \ReflectionClass("OPNsense\\Auth\\Services\\{$srv_found}");
            if ($reflClass->implementsInterface('OPNsense\\Auth\\IService')) {
                // stash aliases
                foreach ($reflClass->getMethod('aliases')->invoke(null) as $alias) {
                    $aliases[$alias] = $reflClass;
                }
                if (strtolower($srv_found) == $srv_name) {
                    return $reflClass->newInstance();
                }
            }
        }
        // class not found, test if one of the classes found aliases our requested service
        foreach ($aliases as $alias => $reflClass) {
            if (strtolower($alias) == $srv_name) {
                return $reflClass->newInstance();
            }
        }
        return null;
    }

    /**
     * Authenticate user for requested service
     * @param $service_name string service name to use, defined in Services directory
     * @param $username string username
     * @param $password string password
     * @return boolean
     */
    public function authenticate($service_name, $username, $password)
    {
        openlog("audit", LOG_ODELAY, LOG_AUTH);
        $service = $this->getService($service_name);
        if ($service !== null) {
            $service->setUserName($username);
            foreach ($service->supportedAuthenticators() as $authname) {
                $authenticator = $this->get($authname);
                if ($authenticator !== null) {
                    $this->lastUsedAuth = $authenticator;
                    if ($authenticator->authenticate($service->getUserName(), $password)) {
                        if ($service->checkConstraints()) {
                            syslog(LOG_NOTICE, sprintf(
                                "user %s authenticated successfully for %s [using %s + %s]",
                                $username,
                                $service_name,
                                get_class($service),
                                get_class($authenticator)
                            ));
                            closelog();
                            return true;
                        } else {
                            // since checkConstraints() is defined on the service, who doesn't know about the
                            // authentication method. We can safely asume we cannot authenticate.
                            syslog(LOG_WARNING, sprintf(
                                "user %s could not authenticate for %s, failed constraints on %s authenticated via %s",
                                $username,
                                $service_name,
                                get_class($service),
                                get_class($authenticator)
                            ));
                            closelog();
                            return false;
                        }
                    } else {
                        syslog(LOG_DEBUG, sprintf(
                            "user %s failed authentication for %s on %s via %s",
                            $username,
                            $service_name,
                            get_class($service),
                            get_class($authenticator)
                        ));
                    }
                }
            }
        }
        syslog(LOG_WARNING, sprintf(
            "user %s could not authenticate for %s. [using %s + %s]",
            $username,
            $service_name,
            !empty($service) ? get_class($service) : '-',
            !empty($authenticator) ? get_class($authenticator) : '-'
        ));
        closelog();
        return false;
    }

    /**
     * list configuration options for pluggable auth modules
     * @return array
     */
    public function listConfigOptions()
    {
        $result = array();
        foreach ($this->listConnectors() as $connector) {
            if ($connector['classHandle']->hasMethod('getDescription')) {
                $obj = $connector['classHandle']->newInstance();
                $authItem = $connector;
                $authItem['description'] = $obj->getDescription();
                if ($connector['classHandle']->hasMethod('getConfigurationOptions')) {
                    $authItem['additionalFields'] = $obj->getConfigurationOptions();
                } else {
                    $authItem['additionalFields'] = array();
                }
                $result[$obj->getType()] = $authItem;
            }
        }
        return $result;
    }

    /**
     * return authenticator properties from last authentication
     * @return array mixed named list of authentication properties
     */
    public function getLastAuthProperties()
    {
        if ($this->lastUsedAuth != null) {
            return $this->lastUsedAuth->getLastAuthProperties();
        } else {
            return [];
        }
    }
}
