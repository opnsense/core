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

namespace OPNsense\OpenVPN;

use OPNsense\Base\BaseModel;

/**
 * Class OpenVPN
 * @package OPNsense\OpenVPN
 */
class OpenVPN extends BaseModel
{
    public function getOverwrite($server_id, $common_name)
    {
        $result = [];
        foreach ($this->Overwrites->Overwrite->iterateItems() as $cso) {
            if (empty((string)$cso->enabled)) {
                continue;
            }
            $servers = !empty((string)$cso->servers) ? explode(',', (string)$cso->servers) : [];
            if (!empty($servers) && !in_array($server_id, $servers)) {
                continue;
            }
            if ((string)$cso->common_name != $common_name) {
                continue;
            }
            // translate content to legacy format so this may easily inject into the existing codebase
            $result['ovpn_servers'] = (string)$cso->servers;
            $result['common_name'] = (string)$cso->common_name;
            $result['description'] = (string)$cso->description;
            $result['redirect_gateway'] = (string)$cso->redirect_gateway;

            $result['tunnel_network'] = (string)$cso->tunnel_network;
            $result['tunnel_networkv6'] = (string)$cso->tunnel_networkv6;
            foreach (['local', 'remote'] as $type) {
                $f1 = $type . '_network';
                $f2 = $type . '_networkv6';
                foreach (explode(',', (string)$cso->{$type . '_networks'}) as $item) {
                    if (strpos($item, ":") === false) {
                        $target_fieldname = $f1;
                    } else {
                        $target_fieldname = $f2;
                    }
                    if (!isset($result[$target_fieldname])) {
                        $result[$target_fieldname] = $item;
                    } else {
                        $result[$target_fieldname] .=  "," . $item;
                    }
                }
            }
            if (!empty((string)$cso->push_reset)) {
                $result['push_reset'] = '1';
            }
            if (!empty((string)$cso->block)) {
                $result['block'] = '1';
            }
            $result['dns_domain'] = (string)$cso->dns_domain;
            $result['dns_domain_search'] = (string)$cso->dns_domain_search;
            foreach (['dns_server', 'ntp_server', 'wins_server'] as $fieldname) {
                if (!empty((string)$cso->$fieldname . 's')) {
                    foreach (explode(',', (string)$cso->{$fieldname . 's'}) as $idx => $item) {
                        $result[$fieldname . (string)($idx + 1)] = $item;
                    }
                }
            }
        }
        return $result;
    }
}
