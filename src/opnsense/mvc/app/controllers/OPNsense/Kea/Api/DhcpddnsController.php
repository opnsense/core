<?php

/*
 * Copyright (C) 2025 Yip Rui Fung <rf@yrf.me>
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

namespace OPNsense\Kea\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class DhcpddnsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'dhcp_ddns';
    protected static $internalModelClass = 'OPNsense\Kea\KeaDhcpDdns';

    /* Forward DDNS domains */
    public function searchForwardDomainAction()
    {
        return $this->searchBase('forward_ddns.ddns_domains', null, 'name');
    }

    public function getForwardDomainAction($uuid = null)
    {
        return $this->getBase('ddns_domains', 'forward_ddns.ddns_domains', $uuid);
    }

    public function addForwardDomainAction()
    {
        return $this->addBase('ddns_domains', 'forward_ddns.ddns_domains');
    }

    public function setForwardDomainAction($uuid)
    {
        return $this->setBase('ddns_domains', 'forward_ddns.ddns_domains', $uuid);
    }

    public function delForwardDomainAction($uuid)
    {
        return $this->delBase('forward_ddns.ddns_domains', $uuid);
    }

    /* Reverse DDNS domains */
    public function searchReverseDomainAction()
    {
        return $this->searchBase('reverse_ddns.ddns_domains', null, 'name');
    }

    public function getReverseDomainAction($uuid = null)
    {
        return $this->getBase('ddns_domains', 'reverse_ddns.ddns_domains', $uuid);
    }

    public function addReverseDomainAction()
    {
        return $this->addBase('ddns_domains', 'reverse_ddns.ddns_domains');
    }

    public function setReverseDomainAction($uuid)
    {
        return $this->setBase('ddns_domains', 'reverse_ddns.ddns_domains', $uuid);
    }

    public function delReverseDomainAction($uuid)
    {
        return $this->delBase('reverse_ddns.ddns_domains', $uuid);
    }

    /* TSIG keys */
    public function searchTsigKeyAction()
    {
        return $this->searchBase('tsig_keys', null, 'name');
    }

    public function getTsigKeyAction($uuid = null)
    {
        return $this->getBase('tsig_keys', 'tsig_keys', $uuid);
    }

    public function addTsigKeyAction()
    {
        return $this->addBase('tsig_keys', 'tsig_keys');
    }

    public function setTsigKeyAction($uuid)
    {
        return $this->setBase('tsig_keys', 'tsig_keys', $uuid);
    }

    public function delTsigKeyAction($uuid)
    {
        return $this->delBase('tsig_keys', $uuid);
    }

    /* Shared DNS servers */
    public function searchDnsServerAction()
    {
        return $this->searchBase('dns_servers', null, 'ip_address');
    }

    public function getDnsServerAction($uuid = null)
    {
        return $this->getBase('dns_servers', 'dns_servers', $uuid);
    }

    public function addDnsServerAction()
    {
        return $this->addBase('dns_servers', 'dns_servers');
    }

    public function setDnsServerAction($uuid)
    {
        return $this->setBase('dns_servers', 'dns_servers', $uuid);
    }

    public function delDnsServerAction($uuid)
    {
        return $this->delBase('dns_servers', $uuid);
    }
}
