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

namespace OPNsense\OpenVPN\Migrations;

use OPNsense\Core\Config;
use OPNsense\Base\BaseModelMigration;
use OPNsense\OpenVPN\OpenVPN;
use OPNsense\Firewall\Util;

class M1_0_0 extends BaseModelMigration
{
    /**
     * is valid network or ip address
     */
    private function valid_net($val)
    {
        return Util::isIpAddress($val) || Util::isSubnet($val);
    }

    /**
     * Migrate legacy aliases
     * @param $model
     */
    public function run($model)
    {
        if ($model instanceof OpenVPN) {
            $cfgObj = Config::getInstance()->object();
            if (!empty($cfgObj->openvpn) && !empty($cfgObj->openvpn->{'openvpn-csc'})) {
                foreach ($cfgObj->openvpn->{'openvpn-csc'} as $csc) {
                    $record = $model->Overwrites->Overwrite->Add();
                    $record->enabled = empty((string)$csc->disable) ? '1' : '0';
                    if (!empty((string)$csc->ovpn_servers)) {
                        $record->servers = (string)$csc->ovpn_servers;
                    }
                    $record->common_name = (string)$csc->common_name;
                    $record->description = (string)$csc->description;
                    if ($this->valid_net((string)$csc->tunnel_network)) {
                        $record->tunnel_network = (string)$csc->tunnel_network;
                    }
                    if ($this->valid_net((string)$csc->tunnel_networkv6)) {
                        $record->tunnel_networkv6 = (string)$csc->tunnel_networkv6;
                    }
                    foreach (['local', 'remote'] as $type) {
                        $nets = [];
                        $f1 = $type . '_network';
                        $f2 = $type . '_networkv6';
                        foreach (explode(',', (string)$csc->$f1 . ',' . (string)$csc->$f2) as $item) {
                            if (trim($item) != '' && $this->valid_net($item)) {
                                $nets[] = trim($item);
                            }
                        }
                        $record->{$type . '_networks'} = implode(',', $nets);
                    }
                    if (!empty((string)$csc->gwredir)) {
                        $record->redirect_gateway  = 'def1';
                    }
                    $record->push_reset = !empty((string)$csc->push_reset) ? '1' : '0';
                    $record->block = !empty((string)$csc->block) ? '1' : '0';
                    $record->dns_domain = (string)$csc->dns_domain;
                    $record->dns_domain_search = (string)$csc->dns_domain_search;
                    foreach (['dns_server', 'ntp_server', 'wins_server'] as $fieldname) {
                        $items = [];
                        for ($i = 1; $i <= 4; ++$i) {
                            $fname = $fieldname . $i;
                            if (!empty((string)$csc->$fname)) {
                                $items[] = (string)$csc->$fname;
                            }
                        }
                        $record->{$fieldname . 's'} = implode(',', $items);
                    }
                }
            }
        }
    }

    /**
     * cleanup old config after config save
     * @param $model
     */
    public function post($model)
    {
        if ($model instanceof OpenVPN) {
            $cfgObj = Config::getInstance()->object();
            unset($cfgObj->openvpn->{'openvpn-csc'});
        }
    }
}
