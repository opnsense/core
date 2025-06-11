<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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
     * list installed auth connectors
     * @return array
     */
    private function listConnectors()
    {
        $connectors = [];
        $interface = 'OPNsense\\Auth\\IAuthConnector';
        foreach (glob(__DIR__ . "/*.php") as $filename) {
            $classname = 'OPNsense\\Auth\\' . explode('.php', basename($filename))[0];
            $reflClass = new \ReflectionClass($classname);
            if (!$reflClass->isInterface() && $reflClass->implementsInterface($interface)) {
                $connectorType = $reflClass->getMethod('getType')->invoke(null);
                $connectors[$connectorType] = [
                    'class' => $classname,
                    'classHandle' => $reflClass,
                    'type' => $connectorType
                ];
            }
        }
        return $connectors;
    }

    /**
     * @param string $service filter on service when offered
     * @return array list of SSO providers
     */
    public function listSSOproviders(string $service = ''): array
    {
        $result = [];
        $interface = 'OPNsense\Auth\SSOProviders\\ISSOContainer';
        foreach (glob(__DIR__ . "/SSOProviders/*.php") as $filename) {
            $classname = 'OPNsense\\Auth\\SSOProviders\\' . explode('.php', basename($filename))[0];
            $reflClass = new \ReflectionClass($classname);
            if (!$reflClass->isInterface() && $reflClass->implementsInterface($interface)) {
                foreach (($reflClass->newInstance())->listProviders() as $provider) {
                    if (empty($service) || $provider->service == $service) {
                        $result[] = $provider;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * request list of configured servers, the factory needs to be aware of its options and settings to
     * be able to instantiate useful connectors.
     * @param string $service name of the service we request our authenticators for
     * @return array list of configured servers
     */
    public function listServers(string $service = '')
    {
        $servers = [];
        $servers['Local Database'] = array("name" => "Local Database", "type" => "local");
        $configObj = Config::getInstance()->object();
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'authserver' && !empty($value->type) && !empty($value->name)) {
                $authServerSettings = [];
                foreach ($value as $itemKey => $itemValue) {
                    $authServerSettings[$itemKey] = (string)$itemValue;
                }
                $servers[$authServerSettings['name']] = $authServerSettings;
            }
        }

        if (!empty($service)) {
            /**
             * Single sign on providers are bound to their service, which is different than our usual
             * user/pass authentication flow in which case the authenticate() method passes the requested service
             */
            foreach ($this->listSSOproviders($service) as $provider) {
                $servers[$provider->id] = $provider->asArray();
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
        $servers = $this->listServers(); /* only servers, no SSO providers */
        $servers['Local API'] = ["name" => "Local API Database", "type" => "api"];
        // create a new auth connector
        if (isset($servers[$authserver]['type'])) {
            $connectors = $this->listConnectors();
            if (!empty($connectors[$servers[$authserver]['type']])) {
                $authObject = $connectors[$servers[$authserver]['type']]['classHandle']->newInstance();
            }
            if (isset($authObject)) {
                $props = $servers[$authserver];
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
        $aliases = [];
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
                            return true;
                        } else {
                            // since checkConstraints() is defined on the service, who doesn't know about the
                            // authentication method. We can safely assume we cannot authenticate.
                            syslog(LOG_WARNING, sprintf(
                                "user %s could not authenticate for %s, failed constraints on %s authenticated via %s",
                                $username,
                                $service_name,
                                get_class($service),
                                get_class($authenticator)
                            ));
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
        return false;
    }

    /**
     * list configuration options for pluggable auth modules
     * @return array
     */
    public function listConfigOptions()
    {
        $result = [];
        foreach ($this->listConnectors() as $connector) {
            if ($connector['classHandle']->hasMethod('getDescription')) {
                $obj = $connector['classHandle']->newInstance();
                $authItem = $connector;
                $authItem['description'] = $obj->getDescription();
                if ($connector['classHandle']->hasMethod('getConfigurationOptions')) {
                    $authItem['additionalFields'] = $obj->getConfigurationOptions();
                } else {
                    $authItem['additionalFields'] = [];
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
