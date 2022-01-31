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

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Unbound\Unbound';
    protected static $internalModelName = 'unbound';

    public function searchDotAction()
    {
        return $this->searchBase('dots.dot', array('enabled', 'server', 'port', 'verify'));
    }

    public function getDotAction($uuid = null)
    {
        return $this->getBase('dot', 'dots.dot', $uuid);
    }

    public function addDotAction()
    {
        return $this->addBase('dot', 'dots.dot');
    }

    public function delDotAction($uuid)
    {
        return $this->delBase('dots.dot', $uuid);
    }

    public function setDotAction($uuid)
    {
        return $this->setBase('dot', 'dots.dot', $uuid);
    }

    public function toggleDotAction($uuid, $enabled = null)
    {
        return $this->toggleBase('dots.dot', $uuid, $enabled);
    }

    /* Host overrides */

    public function searchHostOverrideAction()
    {
        return $this->searchBase(
            'hosts.host',
            ['enabled', 'hostname', 'domain', 'rr', 'mxprio', 'mx', 'server', 'description'],
            "sequence"
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
            ['enabled', 'host', 'hostname', 'domain'],
            "sequence",
            $filter_func
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
        return $this->setBase('alias', 'aliases.alias');
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
            "sequence"
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
}
