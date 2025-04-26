<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\Firewall;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;
use OPNsense\Base\Messages\Message;

/**
 * Class Alias
 * @package OPNsense\Firewall
 */
class Alias extends BaseModel
{
    /**
     * cache iteration items
     */
    private $aliasIteratorCache = [];

    /**
     * alias name cache
     */
    private $aliasReferenceCache = [];

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        foreach ($this->aliases->alias->iterateItems() as $alias) {
            if (!$validateFullModel && !$alias->isFieldChanged()) {
                continue;
            }

            $ref = $alias->__reference;

            $username = (string)$alias->username;
            $password = (string)$alias->password;

            switch ((string)$alias->authtype) {
                case 'Basic':
                    if (empty($username) || empty($password)) {
                        $messages->appendMessage(new Message(gettext('Please provide a username and password when Basic auth is selected'), $ref . '.authtype'));
                    }
                    break;
                case 'Bearer':
                    if (empty($password)) {
                        $messages->appendMessage(new Message(gettext('Please provide an API token when Bearer auth is selected'), $ref . '.authtype'));
                    } elseif (strlen($password) > 512) {
                        $messages->appendMessage(new Message(gettext('Invalid token length'), $ref . '.authtype'));
                    } elseif (!preg_match('/^[A-Za-z0-9-_.]+$/', $password)) {
                        $messages->appendMessage(new Message(gettext('Illegal characters in token'), $ref . '.authtype'));
                    }
                    break;
            }
        }

        return $messages;
    }

    /**
     * return locations where aliases can be used inside the configuration
     * @return array alias source map
     */
    private function getAliasSource()
    {
        $sources = [];
        $sources[] = [['filter', 'rule'], ['source', 'address']];
        $sources[] = [['filter', 'rule'], ['destination', 'address']];
        $sources[] = [['filter', 'rule'], ['source', 'port']];
        $sources[] = [['filter', 'rule'], ['destination', 'port']];
        $sources[] = [['filter', 'scrub', 'rule'], ['dst']];
        $sources[] = [['filter', 'scrub', 'rule'], ['src']];
        $sources[] = [['filter', 'scrub', 'rule'], ['dstport']];
        $sources[] = [['filter', 'scrub', 'rule'], ['srcport']];
        $sources[] = [['nat', 'rule'], ['source', 'address']];
        $sources[] = [['nat', 'rule'], ['source', 'port']];
        $sources[] = [['nat', 'rule'], ['destination', 'address']];
        $sources[] = [['nat', 'rule'], ['destination', 'port']];
        $sources[] = [['nat', 'rule'], ['target']];
        $sources[] = [['nat', 'rule'], ['local-port']];
        $sources[] = [['nat', 'onetoone'], ['destination', 'address']];
        $sources[] = [['nat', 'outbound', 'rule'], ['source', 'network']];
        $sources[] = [['nat', 'outbound', 'rule'], ['sourceport']];
        $sources[] = [['nat', 'outbound', 'rule'], ['destination', 'network']];
        $sources[] = [['nat', 'outbound', 'rule'], ['dstport']];
        $sources[] = [['nat', 'outbound', 'rule'], ['target']];
        $sources[] = [['staticroutes', 'route'], ['network']];
        // os-firewall plugin paths
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'rules', 'rule'], ['source_net']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'rules', 'rule'], ['source_port']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'rules', 'rule'], ['destination_net']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'rules', 'rule'], ['destination_port']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'snatrules', 'rule'], ['source_net']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'snatrules', 'rule'], ['source_port']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'snatrules', 'rule'], ['destination_net']];
        $sources[] = [['OPNsense', 'Firewall', 'Filter', 'snatrules', 'rule'], ['destination_port']];

        return $sources;
    }

    /**
     * search configuration items where the requested alias is used
     * @param $name alias name
     * @return \Generator [reference, confignode, matched item]
     */
    private function searchConfig($name)
    {
        $cfgObj = Config::getInstance()->object();
        foreach ($this->getAliasSource() as $aliasref) {
            $cfgsection = $cfgObj;
            foreach ($aliasref[0] as $cfgName) {
                if ($cfgsection != null) {
                    $cfgsection = $cfgsection->$cfgName;
                }
            }
            if ($cfgsection != null) {
                $nodeidx = 0;
                foreach ($cfgsection as $inode) {
                    $node = $inode;
                    foreach ($aliasref[1] as $cfgName) {
                        $node = $node->$cfgName;
                    }
                    if ((string)$node == $name) {
                        $ref = implode('.', $aliasref[0]) . "." . $nodeidx . "/" . implode('.', $aliasref[1]);
                        yield array($ref, &$inode, &$node);
                    }
                    $nodeidx++;
                }
            }
        }
    }

    /**
     * flush cached objects and references
     */
    public function flushCache()
    {
        $this->aliasIteratorCache = [];
        $this->aliasReferenceCache = [];
    }

    /**
     * Return all places an alias seems to be used
     * @param string $name alias name
     * @return array hashmap with references where this alias is used
     */
    public function whereUsed($name)
    {
        $result = array();
        // search legacy locations
        foreach ($this->searchConfig($name) as $item) {
            $result[$item[0]] = (string)$item[1]->descr;
        }
        // find all used in this model
        foreach ($this->aliases->alias->iterateItems() as $alias) {
            if (!in_array($alias->type, array('geoip', 'urltable'))) {
                $nodeData = $alias->content->getNodeData();
                if (isset($nodeData[$name])) {
                    if (!empty($alias->description)) {
                        $result[(string)$alias->__reference] = (string)$alias->description;
                    } else {
                        $result[(string)$alias->__reference] = (string)$alias->name;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * replace alias usage
     * @param $oldname
     * @param $newname
     */
    public function refactor($oldname, $newname)
    {
        Util::attachAliasObject($this);
        // replace in legacy config
        foreach ($this->searchConfig($oldname) as $item) {
            $item[2][0] = $newname;
        }
        // find all used in this model (alias nesting)
        foreach ($this->aliases->alias->iterateItems() as $alias) {
            if (!in_array($alias->type, array('geoip', 'urltable'))) {
                $sepchar = $alias->content->getSeparatorChar();
                $aliases = explode($sepchar, (string)$alias->content);
                if (in_array($oldname, $aliases)) {
                    $aliases = array_unique($aliases);
                    $aliases[array_search($oldname, $aliases)] = $newname;
                    $alias->content->setValue(implode($sepchar, $aliases));
                }
            }
        }
    }

    /**
     * return aliases as array
     * @param $flush flush cached objects from previous call
     * @return Generator with aliases
     */
    public function aliasIterator($flush = false)
    {
        if ($flush) {
            // flush cache
            $this->aliasIteratorCache = [];
        }
        foreach ($this->aliases->alias->iterateItems() as $uuid => $alias) {
            if (empty($this->aliasIteratorCache[$uuid])) {
                $this->aliasIteratorCache[$uuid] = [];
                foreach ($alias->iterateItems() as $key => $value) {
                    $this->aliasIteratorCache[$uuid][$key] = (string)$value;
                }
                // parse content into separate items for easier reading. items are separated by \n
                if ((string)$alias->content == "") {
                    $this->aliasIteratorCache[$uuid]['content'] = [];
                } else {
                    $this->aliasIteratorCache[$uuid]['content'] = explode("\n", (string)$alias->content);
                }
            }
            yield $this->aliasIteratorCache[$uuid];
        }
    }

    public function getByName($name, $flush = false)
    {
        if ($flush) {
            // Flush cache in case model data has been changed.
            $this->aliasReferenceCache = [];
        }
        if (empty($this->aliasReferenceCache)) {
            // cache alias uuid references, but always validate existence before return.
            foreach ($this->aliases->alias->iterateItems() as $uuid => $alias) {
                $this->aliasReferenceCache[(string)$alias->name] = $alias;
            }
        }
        if (isset($this->aliasReferenceCache[$name])) {
            return $this->aliasReferenceCache[$name];
        } else {
            return null;
        }
    }

    /**
     * Resolves an alias name (recursively) into a list of network entities (IPs/CIDRs).
     * Filters out non-network types. Enforces recursion depth and entry limits.
     *
     * @param string $name The alias name to resolve.
     * @param int $limit Maximum number of entries to return. Default 1000.
     * @param array $aliases Aliases already parsed (recursion guard).
     * @param int $recursion_depth Current depth (recursion guard). Max depth e.g., 10.
     * @return array|false List of unique IP/CIDR strings on success, false on error/limit/recursion.
     */
    public static function resolveAliasToNetworks($name, $limit = 1000, $aliases = [], $recursion_depth = 0)
    {
        // --- Guards ---
        if ($recursion_depth > 10) {
            syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Max recursion depth reached resolving \'' . $name . '\'.');
            return false;
        }
        if (in_array($name, $aliases)) {
            syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Circular reference detected for alias \'' . $name . '\'.');
            return false; // Prevent infinite loop
        }
        $aliases[] = $name;

        // --- Get Alias Node ---
        try {
            // Create an instance to use non-static methods
            $aliasModel = new self();
            // Flush the cache once at the beginning of the resolver
            if ($recursion_depth == 0) {
                $aliasModel->flushCache();
            }
            $aliasNode = $aliasModel->getByName($name);
        } catch (\Exception $e) {
            syslog(LOG_ERR, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Exception fetching alias \'' . $name . '\': ' . $e->getMessage());
            return false;
        }

        if ($aliasNode == null) {
            syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Alias \'' . $name . '\' not found.');
            return false;
        }

        // --- Type Check ---
        // Define types that are expected to resolve to IPs/Networks for shaper usage
        $allowed_types = ['host', 'network', 'urltable', 'geoip', 'external'];
        $current_type = (string)$aliasNode->type;
        if (!in_array($current_type, $allowed_types)) {
            // syslog(LOG_DEBUG, "Alias '{$name}' of type '{$current_type}' skipped (not a network type).");
            return []; // Not an error for the caller, just not relevant
        }

        // --- Resolve Content ---
        $resolved_content_map = []; // Use map keys for uniqueness
        try {
            // Get alias content based on type
            $raw_items = [];

            if (in_array($current_type, ['host', 'network'])) {
                // Simple case: get content from node
                if ((string)$aliasNode->content == "") {
                    $raw_items = [];
                } else {
                    $raw_items = explode("\n", (string)$aliasNode->content);
                }
            } elseif ($current_type == 'urltable') {
                // For URL tables, we would need to fetch and parse the content
                // TODO: Implement proper URL table resolution in a future PR
                syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: URLTable resolution for \'' . $name . '\' not fully implemented yet. Skipping.');
                $raw_items = [];
            } elseif ($current_type == 'geoip') {
                // For GeoIP, we would need to lookup the database
                // TODO: Implement proper GeoIP resolution in a future PR
                syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: GeoIP resolution for \'' . $name . '\' not fully implemented yet. Skipping.');
                $raw_items = [];
            } elseif ($current_type == 'external') {
                // External type handling
                // TODO: Implement proper external alias resolution in a future PR
                syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: External resolution for \'' . $name . '\' not fully implemented yet. Skipping.');
                $raw_items = [];
            }

            foreach ($raw_items as $item) {
                $item = trim($item);
                if (empty($item) || strpos($item, '#') === 0) continue; // Skip empty lines and comments

                // Remove potential comments after address (common in URL tables)
                $item = trim(explode('#', $item)[0]);

                if (Util::isIpAddress($item) || Util::isSubnet($item)) {
                    $resolved_content_map[$item] = 1;
                } elseif (Util::isAlias($item)) { // Nested alias
                    // Pass the current limit minus already found items
                    $current_limit = $limit - count($resolved_content_map);
                    if ($current_limit <= 0) {
                        syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Alias \'' . $name . '\' expansion limit reached before resolving nested alias \'' . $item . '\'.');
                        return false; // Exceeded limit
                    }
                    $nested_result = self::resolveAliasToNetworks($item, $current_limit, $aliases, $recursion_depth + 1);
                    if ($nested_result === false) return false; // Propagate error
                    foreach ($nested_result as $nested_item) {
                        $resolved_content_map[$nested_item] = 1;
                    }
                }
                // Else: Ignore items that are not IPs, Subnets, or resolvable Aliases

                // --- Limit Check ---
                if (count($resolved_content_map) > $limit) {
                    syslog(LOG_WARNING, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Alias \'' . $name . '\' expansion exceeded limit of ' . $limit . '.');
                    return false;
                }
            }
        } catch (\Exception $e) {
            syslog(LOG_ERR, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Exception processing content for alias \'' . $name . '\': ' . $e->getMessage());
            return false;
        }

        if (empty($resolved_content_map) && in_array($current_type, ['host', 'network'])) {
            // Log potentially unexpected empty result for static types
            syslog(LOG_NOTICE, 'OPNsense\Firewall\Alias::resolveAliasToNetworks: Alias \'' . $name . '\' of type \'' . $current_type . '\' resolved to an empty list.');
        }

        // --- Return ---
        return array_keys($resolved_content_map);
    }
}
