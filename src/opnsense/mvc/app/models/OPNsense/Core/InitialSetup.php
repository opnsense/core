<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Core;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;
use OPNsense\Auth\User;
use OPNsense\Firewall\Util;
use OPNsense\Dnsmasq\Dnsmasq;
use OPNsense\Routing\Gateways;

/**
 * wrapper model class to update various configuration parts in a controlled way for the setup wizard
 */
class InitialSetup extends BaseModel
{
    /**
     * @param string $path dot seperated path to collect
     * @param bool $isBoolean return as boolean value 0/1
     * @return string
     */
    private function getConfigItem(string $path, bool $isBoolean = false)
    {
        $node = Config::getInstance()->object();
        foreach (explode('.', $path) as $item) {
            if (isset($node->$item)) {
                $node = $node->$item;
            } else {
                return $isBoolean ? '0' : '';
            }
        }
        if (count($node) > 1) {
            $tmp = [];
            foreach ($node as $item) {
                $tmp[] = (string)$item;
            }
            return implode(',', $tmp);
        }
        if ($isBoolean && (string)$node == '') {
            return '0';
        } else {
            return (string)$node;
        }
    }

    /**
     * setup initial values, read from configuration
     */
    protected function init()
    {
        $this->hostname = $this->getConfigItem('system.hostname');
        $this->domain = $this->getConfigItem('system.domain');
        $this->language = $this->getConfigItem('system.language');
        if ($this->language->isEmpty()) {
            $this->language = 'en_US';
        }
        $this->dns_servers = $this->getConfigItem('system.dnsserver');
        $this->timezone = $this->getConfigItem('system.timezone');
        if ($this->timezone->isEmpty()) {
            $this->timezone = 'Etc/UTC';
        }
        $this->unbound->enabled = $this->getConfigItem('OPNsense.unboundplus.general.enabled', true);
        $this->unbound->dnssec = $this->getConfigItem('OPNsense.unboundplus.general.dnssec', true);
        $this->unbound->dnssecstripped = $this->getConfigItem('OPNsense.unboundplus.advanced.dnssecstripped');
        if (str_starts_with($this->getConfigItem('interfaces.wan.if'), 'pppoe')) {
            $ipv4_type = 'pppoe';
        } elseif ($this->getConfigItem('interfaces.wan.ipaddr') == 'dhcp') {
            $ipv4_type = 'dhcp';
        } else {
            $ipv4_type = 'static';
        }
        $this->interfaces->wan->ipv4_type = $ipv4_type;
        $this->interfaces->wan->spoofmac = $this->getConfigItem('interfaces.wan.spoofmac');
        $this->interfaces->wan->mtu = $this->getConfigItem('interfaces.wan.mtu');
        $this->interfaces->wan->mss = $this->getConfigItem('interfaces.wan.mss');
        $this->interfaces->wan->dhcphostname = $this->getConfigItem('interfaces.wan.dhcphostname');
        $this->interfaces->wan->blockpriv = $this->getConfigItem('interfaces.wan.blockpriv', true);
        $this->interfaces->wan->blockbogons = $this->getConfigItem('interfaces.wan.blockbogons', true);

        if ($ipv4_type == 'static') {
            $this->interfaces->wan->ipaddr = sprintf(
                "%s/%s",
                $this->getConfigItem('interfaces.wan.ipaddr'),
                $this->getConfigItem('interfaces.wan.subnet')
            );
        } elseif ($ipv4_type == 'pppoe') {
            $pppoeif = $this->getConfigItem('interfaces.wan.if');
            foreach (Config::getInstance()->object()->ppps->children() as $ppp) {
                if ($ppp->if == $pppoeif) {
                    $this->interfaces->wan->pppoe_username = (string)$ppp->username;
                    if (!empty((string)$ppp->password)) {
                        $this->interfaces->wan->pppoe_password = base64_decode((string)$ppp->password);
                    }
                    $this->interfaces->wan->pppoe_provider = (string)$ppp->provider;
                }
            }
        }
        if (!empty($this->getConfigItem('interfaces.lan.ipaddr'))) {
            $this->interfaces->lan->ipaddr = sprintf(
                "%s/%s",
                $this->getConfigItem('interfaces.lan.ipaddr'),
                $this->getConfigItem('interfaces.lan.subnet')
            );
        } else {
            $this->interfaces->lan->configure_dhcp = '0';
            $this->interfaces->lan->disable = '1';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        if ((string)$this->password != (string)$this->password_confirm) {
            $messages->appendMessage(
                new Message(
                    gettext("The passwords do not match."),
                    "password"
                )
            );
        }
        if ($this->interfaces->wan->ipv4_type == 'pppoe') {
            /* PPPoE requires credentials */
            if ($this->interfaces->wan->pppoe_username->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("A username is required."),
                        "interfaces.wan.pppoe_username"
                    )
                );
            }
            if ($this->interfaces->wan->pppoe_password->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("A password is required."),
                        "interfaces.wan.pppoe_password"
                    )
                );
            }
        } elseif ($this->interfaces->wan->ipv4_type == 'static') {
            if (
                !empty($this->interfaces->wan->gateway) &&
                !Util::isIPInCIDR($this->interfaces->wan->gateway, $this->interfaces->wan->ipaddr)
            ) {
                $messages->appendMessage(
                    new Message(
                        gettext("Please make sure the gateway address is reachable from the choosen subnet."),
                        "interfaces.wan.gateway"
                    )
                );
            }
        }

        if ($this->interfaces->lan->disable == '0') {
            $cnt = count(iterator_to_array(Util::cidrRangeIterator($this->interfaces->lan->ipaddr)));
            if (!$this->interfaces->lan->configure_dhcp->isEmpty() && $cnt < 50) {
                $messages->appendMessage(
                    new Message(
                        gettext("Automatic DHCP server configuration is only supported for networks larger than /27."),
                        "interfaces.lan.configure_dhcp"
                    )
                );
            }
            if ($this->interfaces->lan->ipaddr->isEmpty()) {
                $messages->appendMessage(
                    new Message(
                        gettext("When LAN is enabled, an address needs to be provided."),
                        "interfaces.lan.ipaddr"
                    )
                );
            }
        }


        return $messages;
    }

    /**
     * flush general settings from in-memory model back to config
     */
    private function flush_general()
    {
        $target = Config::getInstance()->object();
        /* system settings */
        $target->system->timezone = (string)$this->timezone;
        unset($target->system->dnsserver);
        foreach (explode(',', (string)$this->dns_servers) as $dnsserver) {
            $target->system->addChild('dnsserver', $dnsserver);
        }
        $target->system->dnsallowoverride = (string)$this->dnsallowoverride;
        $target->system->language = (string)$this->language;
        $target->system->hostname = (string)$this->hostname;
        $target->system->domain = (string)$this->domain;

        /* configure unbound dns */
        $target->OPNsense->unboundplus->general->enabled = (string)$this->unbound->enabled;
        $target->OPNsense->unboundplus->general->dnssec = (string)$this->unbound->dnssec;
        $target->OPNsense->unboundplus->advanced->dnssecstripped = (string)$this->unbound->dnssecstripped;
    }

    /**
     * flush wan interface configuration from in-memory model back to config
     */
    private function flush_network_wan()
    {
        $target = Config::getInstance()->object();
        /* configure wan */
        $gateways = new Gateways();
        foreach ($gateways->gateway_item->iterateItems() as $uuid => $node) {
            if ($node->interface == 'wan' && $node->ipprotocol == 'inet') {
                $gateways->gateway_item->del($uuid);
            }
        }

        $wanif = $this->getConfigItem('interfaces.wan.if');
        if ($this->interfaces->wan->ipv4_type == 'pppoe') {
            /* PPPoE configuration will nest interfaces, make sure we can still update the interface once configured */
            if (!isset($target->ppps)) {
                $target->addChild('ppps');
            }
            $pppoe_idx = -1;
            $max_ptpid = 0;
            for ($idx = 0; $idx < count($target->ppps->ppp); ++$idx) {
                if ($target->ppps->ppp[$idx]->if == $wanif) {
                    $pppoe_idx = $idx;
                }
                $max_ptpid = max($max_ptpid, $target->ppps->ppp[$idx]->ptpid);
            }

            if ($pppoe_idx == -1) {
                /* add pppoe on top of current configured wan */
                $node = $target->ppps->addChild('ppp');
                $node->addChild('ports', $wanif);
                $node->addChild('ptpid', (string)($max_ptpid + 1));
                $node->addChild('if', 'pppoe' . $node->ptpid);
                $target->interfaces->wan->if = (string)$node->if;
            } else {
                $node = $target->ppps->ppp[$pppoe_idx];
            }
            $node->type = 'pppoe';
            $node->username = (string)$this->interfaces->wan->pppoe_username;
            $node->password = base64_encode((string)$this->interfaces->wan->pppoe_password);
        }
        unset($target->interfaces->wan->dhcphostname);
        if (in_array($this->interfaces->wan->ipv4_type, ['pppoe', 'dhcp'])) {
            $target->interfaces->wan->ipaddr = (string)$this->interfaces->wan->ipv4_type;
            $target->interfaces->wan->subnet = '';
            if ($this->interfaces->wan->ipv4_type == 'dhcp') {
                $target->interfaces->wan->dhcphostname = (string)$this->interfaces->wan->dhcphostname;
            }
        } else {
            $parts = explode('/', $this->interfaces->wan->ipaddr);
            $target->interfaces->wan->ipaddr = $parts[0];
            $target->interfaces->wan->subnet = $parts[1];
            if (!$this->interfaces->wan->gateway->isEmpty()) {
                $gateways->createOrUpdateGateway([
                    'interface' => 'wan',
                    'gateway' => (string)$this->interfaces->wan->gateway,
                    'name' => 'WAN_GW',
                    'weight' => '1',
                    'monitor_disable' => '1',
                    'descr' => 'WAN Gateway',
                    'defaultgw' => '1',
                ]);
            }
        }
        $target->interfaces->wan->enable = '1';
        $target->interfaces->wan->spoofmac = (string)$this->interfaces->wan->spoofmac;
        $target->interfaces->wan->mtu = (string)$this->interfaces->wan->mtu;
        $target->interfaces->wan->mss = (string)$this->interfaces->wan->mss;
        $target->interfaces->wan->blockpriv = (string)$this->interfaces->wan->blockpriv;
        $target->interfaces->wan->blockbogons = (string)$this->interfaces->wan->blockbogons;
        $gateways->serializeToConfig(false, true);
    }

    /**
     * flush lan interface configuration from in-memory model back to config
     */
    private function flush_network_lan()
    {
        $target = Config::getInstance()->object();
        /* configure lan */
        if (!isset($target->interfaces->lan)) {
            $target->interfaces->addChild('lan');
        }
        $target->interfaces->lan->enable = $this->interfaces->lan->disable->isEmpty() ? '1' : '0';
        $dnsmasq = new Dnsmasq();
        foreach ($dnsmasq->dhcp_ranges->iterateItems() as $uuid => $node) {
            if ($node->interface == 'lan') {
                /* always remove lan dhcp ranges */
                $dnsmasq->dhcp_ranges->del($uuid);
            }
        }
        if ($target->interfaces->lan->enable == '1') {
            $parts = explode('/', $this->interfaces->lan->ipaddr);
            $target->interfaces->lan->ipaddr = $parts[0];
            $target->interfaces->lan->subnet = $parts[1];
            // configure_dhcp
            if (!$this->interfaces->lan->configure_dhcp->isEmpty()) {
                $avail_addrs = iterator_to_array(Util::cidrRangeIterator($this->interfaces->lan->ipaddr));
                if (!$dnsmasq->interface->isEmpty() && !in_array('lan', explode(',', $dnsmasq->interface))) {
                    $dnsmasq->interface = (string)$dnsmasq->interface . ',lan';
                }
                $dnsmasq->port = '0';
                $dnsmasq->enable = '1';
                $dhcprange = $dnsmasq->dhcp_ranges->Add();
                $dhcprange->interface = 'lan';
                $dhcprange->start_addr = $avail_addrs[40];
                $dhcprange->end_addr = $avail_addrs[count($avail_addrs) - 10];
            }
        } else {
            unset($target->interfaces->lan);
        }
        $dnsmasq->serializeToConfig(false, true);
        /* forcefully disable isc dhcpd when enabled */
        if (isset($target->dhcpd)) {
            foreach ($target->dhcpd->children() as $node) {
                unset($node->enable);
            }
        }
    }

    /**
     * reset root password when offered
     */
    private function flush_initial_pass()
    {
        /* update root password */
        if (!$this->password->isEmpty()) {
            $usermdl = new User();
            $rootuser = $usermdl->getUserByName('root');
            if ($rootuser) {
                $hash = $usermdl->generatePasswordHash((string)$this->password);
                if ($hash !== false && strpos($hash, '$') === 0) {
                    $rootuser->password = $hash;
                    $usermdl->serializeToConfig(false, true);
                }
            }
        }
    }

    /**
     * remove initial wizard tag
     */
    private function unset_initial_wizard()
    {
        unset(Config::getInstance()->object()->trigger_initial_wizard);
    }


    /**
     * update configuration
     */
    public function updateConfig()
    {
        $this->flush_general();
        $this->flush_network_wan();
        $this->flush_network_lan();
        $this->flush_initial_pass();
        $this->unset_initial_wizard();

        Config::getInstance()->save();
        return ['status' => 'done'];
    }
}
