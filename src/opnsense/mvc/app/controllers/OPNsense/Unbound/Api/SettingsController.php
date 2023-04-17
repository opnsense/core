<?php

/*
 * Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Unbound\Unbound';
    protected static $internalModelName = 'unbound';

    private $type = 'forward';

    public function updateBlocklistAction()
    {
        $result = ["status" => "failed"];
        if ($this->request->isPost() && $this->request->hasPost('domain') && $this->request->hasPost('type')) {
            Config::getInstance()->lock();
            $domain = $this->request->getPost('domain');
            $type = $this->request->getPost('type');
            $mdl = $this->getModel();
            $item = $mdl->getNodeByReference('dnsbl.' . $type);

            if ($item != null) {
                $remove = function ($csv, $part) {
                    while (($i = array_search($part, $csv)) !== false) {
                        unset($csv[$i]);
                    }
                    return implode(',', $csv);
                };

                // strip off any trailing dot
                $value = rtrim($domain, '.');
                $wl = explode(',', (string)$mdl->dnsbl->whitelists);
                $bl = explode(',', (string)$mdl->dnsbl->blocklists);

                $existing_domains = explode(',', (string)$item);
                if (in_array($value, $existing_domains)) {
                    // value already in model, no need to re-run a potentially
                    // expensive dnsbl action
                    return ["status" => "OK"];
                }

                // Check if domains should be switched around in the model
                if ($type == 'whitelists' && in_array($value, $bl)) {
                    $mdl->dnsbl->blocklists = $remove($bl, $value);
                } elseif ($type == 'blocklists' && in_array($value, $wl)) {
                    $mdl->dnsbl->whitelists = $remove($wl, $value);
                }

                // update the model
                $list = array_filter($existing_domains); // removes all empty entries
                $list[] = $value;
                $mdl->dnsbl->$type = implode(',', $list);

                $mdl->serializeToConfig();
                Config::getInstance()->save();

                $service = new \OPNsense\Unbound\Api\ServiceController();
                $result = $service->dnsblAction();
            }
        }
        return $result;
    }

    public function getNameserversAction()
    {
        if ($this->request->isGet()) {
            $backend = new Backend();
            $nameservers = json_decode(trim($backend->configdRun("system list nameservers")));

            if ($nameservers !== null) {
                return $nameservers;
            }
        }
        return array("message" => "Unable to run configd action");
    }

    /*
     * Catch all Dot API endpoints and redirect them to Forward for
     * backwards compatibility and infer the type from the request.
     * If no type is provided, default to forward (__call only triggers on non-existing methods).
     */
    public function __call($method, $args)
    {
        if (substr($method, -6) == 'Action') {
            $fn = preg_replace('/Dot/', 'Forward', $method);
            if (method_exists(get_class($this), $fn) && preg_match("/.*dot/i", $method)) {
                $this->type = "dot";
                return $this->$fn(...$args);
            }
        }
    }

    public function searchForwardAction()
    {
        $filter_fn = function ($record) {
            return $record->type == $this->type;
        };

        return $this->searchBase(
            'dots.dot',
            array('enabled', 'server', 'port', 'verify', 'type', 'domain'),
            null,
            $filter_fn
        );
    }

    public function getForwardAction($uuid = null)
    {
        return $this->getBase('dot', 'dots.dot', $uuid);
    }

    public function addForwardAction()
    {
        return $this->addBase(
            'dot',
            'dots.dot',
            [ "type" => $this->type ]
        );
    }

    public function delForwardAction($uuid)
    {
        return $this->delBase('dots.dot', $uuid);
    }

    public function setForwardAction($uuid)
    {
        return $this->setBase(
            'dot',
            'dots.dot',
            $uuid,
            [ "type" => $this->type ]
        );
    }

    public function toggleForwardAction($uuid, $enabled = null)
    {
        return $this->toggleBase('dots.dot', $uuid, $enabled);
    }

    /* Host overrides */

    public function searchHostOverrideAction()
    {
        return $this->searchBase(
            'hosts.host',
            ['enabled', 'hostname', 'domain', 'rr', 'mxprio', 'mx', 'server', 'description'],
            'hostname',
            null,
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    public function getHostOverrideAction($uuid = null)
    {
        return $this->getBase('host', 'hosts.host', $uuid);
    }

    public function addHostOverrideAction()
    {
        return $this->addBase('host', 'hosts.host');
    }

    public function delHostOverrideAction($uuid)
    {
        /* Make sure the linked aliases are deleted as well. */
        $node = $this->getModel();
        foreach ($node->aliases->alias->iterateItems() as $alias_uuid => $alias) {
            if ($alias->host == $uuid) {
                $this->delBase('aliases.alias', $alias_uuid);
            }
        }

        return $this->delBase('hosts.host', $uuid);
    }

    public function setHostOverrideAction($uuid)
    {
        return $this->setBase('host', 'hosts.host', $uuid);
    }

    public function toggleHostOverrideAction($uuid, $enabled = null)
    {
        return $this->toggleBase('hosts.host', $uuid, $enabled);
    }

    /* Aliases for hosts */

    public function searchHostAliasAction()
    {
        $host = $this->request->get('host');
        $filter_func = null;
        if (!empty($host)) {
            $filter_func = function ($record) use ($host) {
                return $record->host == $host;
            };
        }
        return $this->searchBase(
            'aliases.alias',
            ['enabled', 'host', 'hostname', 'domain', 'description'],
            "hostname",
            $filter_func,
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    public function getHostAliasAction($uuid = null)
    {
        $host_uuid = $this->request->get('host');
        $result = $this->getBase('alias', 'aliases.alias', $uuid);
        if (empty($uuid) && !empty($host_uuid)) {
            foreach ($result['alias']['host'] as $key => &$value) {
                if ($key == $host_uuid) {
                    $value['selected'] = 1;
                } else {
                    $value['selected'] = 0;
                }
            }
        }
        return $result;
    }

    public function addHostAliasAction()
    {
        return $this->addBase('alias', 'aliases.alias');
    }

    public function delHostAliasAction($uuid)
    {
        return $this->delBase('aliases.alias', $uuid);
    }

    public function setHostAliasAction($uuid)
    {
        return $this->setBase('alias', 'aliases.alias', $uuid);
    }

    public function toggleHostAliasAction($uuid, $enabled = null)
    {
        return $this->toggleBase('aliases.alias', $uuid, $enabled);
    }

    /* Domain overrides */

    public function searchDomainOverrideAction()
    {
        return $this->searchBase(
            'domains.domain',
            ['enabled', 'domain', 'server', 'description'],
            "domain",
            null,
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    public function getDomainOverrideAction($uuid = null)
    {
        return $this->getBase('domain', 'domains.domain', $uuid);
    }

    public function addDomainOverrideAction()
    {
        return $this->addBase('domain', 'domains.domain');
    }

    public function delDomainOverrideAction($uuid)
    {
        return $this->delBase('domains.domain', $uuid);
    }

    public function setDomainOverrideAction($uuid)
    {
        return $this->setBase('domain', 'domains.domain', $uuid);
    }

    public function toggleDomainOverrideAction($uuid, $enabled = null)
    {
        return $this->toggleBase('domains.domain', $uuid, $enabled);
    }

    /* ACLs */

    public function searchAclAction()
    {
        return $this->searchBase(
            'acls.acl',
            ['enabled', 'name', 'action', 'description'],
            'acl.action',
            null,
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    public function getAclAction($uuid = null)
    {
        return $this->getBase('acl', 'acls.acl', $uuid);
    }

    public function addAclAction()
    {
        return $this->addBase('acl', 'acls.acl');
    }

    public function delAclAction($uuid)
    {
        return $this->delBase('acls.acl', $uuid);
    }

    public function setAclAction($uuid)
    {
        return $this->setBase('acl', 'acls.acl', $uuid);
    }

    public function toggleAclAction($uuid, $enabled = null)
    {
        return $this->toggleBase('acls.acl', $uuid, $enabled);
    }
}
