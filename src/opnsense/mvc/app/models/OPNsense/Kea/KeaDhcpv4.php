<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Kea;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Util;

class KeaDhcpv4 extends BaseModel
{
    /**
     * Before persisting data into the model, update option_data fields for selected subnets.
     * setNodes() is used in most cases (at least from our base controller), which should make this a relatvily
     * save entrypoint to enforce some data.
     */
    public function setNodes($data)
    {
        $ifconfig = json_decode((new Backend())->configdRun('interface list ifconfig'), true) ?? [];
        foreach ($this->subnets->subnet4->iterateItems() as $subnet) {
            if (!empty((string)$subnet->option_data_autocollect)) {
                // find first possible candidate to use as a gateway.
                $host_ip = null;
                foreach ($ifconfig as $if => $details) {
                    foreach ($details['ipv4'] as $net) {
                        if (Util::isIPInCIDR($net['ipaddr'], (string)$subnet->subnet)) {
                            $host_ip = $net['ipaddr'];
                            break 2;
                        }
                    }
                }

                if (!empty($host_ip)) {
                    $subnet->option_data->routers = $host_ip;
                    $subnet->option_data->domain_name_servers = $host_ip;
                    $subnet->option_data->ntp_servers = $host_ip;
                }
            }
        }
        return parent::setNodes($data);
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // validate changed reservations
        foreach ($this->reservations->reservation->iterateItems() as $reservation) {
            if (!$validateFullModel && !$reservation->isFieldChanged()) {
                continue;
            }
            $key = $reservation->__reference;
            $subnet = "";
            $subnet_node = $this->getNodeByReference("subnets.subnet4.{$reservation->subnet}");
            if ($subnet_node) {
                $subnet = (string)$subnet_node->subnet;
            }
            if (!Util::isIPInCIDR((string)$reservation->ip_address, $subnet)) {
                $messages->appendMessage(new Message(gettext("Address not in specified subnet"), $key . ".ip_address"));
            }
        }
        return $messages;
    }


    /**
     * should filter rules be enabled
     * @return bool
     */
    public function fwrulesEnabled()
    {
        return  (string)$this->general->enabled == '1' &&
                (string)$this->general->fwrules == '1' &&
                !empty((string)(string)$this->general->interfaces);
    }
}
