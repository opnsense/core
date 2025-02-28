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

namespace OPNsense\IPsec\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\IPsec\IPsec;

class M1_0_4 extends BaseModelMigration
{
    public function run($model)
    {
        if (!$model instanceof IPsec) {
            return;
        }
        $cnf = Config::getInstance()->object();
        if (!isset($cnf->ipsec) || !isset($cnf->ipsec->client)) {
            return;
        }
        if (isset($cnf->ipsec->client) && isset($cnf->ipsec->client->net_list)) {
            $model->charon->cisco_unity = '1';
            unset($cnf->ipsec->client->net_list);
        }
        $dns_servers = [];
        foreach (['dns_server1', 'dns_server2', 'dns_server3', 'dns_server4'] as $tmp) {
            if (!empty((string)$cnf->ipsec->client->$tmp)) {
                $dns_servers[] = (string)$cnf->ipsec->client->$tmp;
                unset($cnf->ipsec->client->$tmp);
            }
        }
        if (!empty($dns_servers)) {
            $model->charon->plugins->attr->dns = implode(',', $dns_servers);
        }

        $nbns_servers = [];
        foreach (['wins_server1', 'wins_server2'] as $tmp) {
            if (!empty((string)$cnf->ipsec->client->$tmp)) {
                $nbns_servers[] = (string)$cnf->ipsec->client->$tmp;
                unset($cnf->ipsec->client->$tmp);
            }
        }
        if (!empty($nbns_servers)) {
            $model->charon->plugins->attr->nbns = implode(',', $nbns_servers);
        }

        if (!empty((string)$cnf->ipsec->client->dns_domain)) {
            $model->charon->plugins->attr->x_28674 = (string)$cnf->ipsec->client->dns_domain;
            $model->charon->plugins->attr->x_28675 = (string)$cnf->ipsec->client->dns_domain;
            unset($cnf->ipsec->client->dns_domain);
        }

        if (!empty((string)$cnf->ipsec->client->dns_split)) {
            /* overwrites previous when both are set */
            $model->charon->plugins->attr->x_28675 = (string)$cnf->ipsec->client->dns_split;
            unset($cnf->ipsec->client->dns_split);
        }

        if (!empty((string)$cnf->ipsec->client->login_banner)) {
            $model->charon->plugins->attr->x_28672 = (string)$cnf->ipsec->client->login_banner;
            unset($cnf->ipsec->client->login_banner);
        }

        if (isset($cnf->ipsec->client->save_passwd)) {
            $model->charon->plugins->attr->x_28673 = '1';
            unset($cnf->ipsec->client->save_passwd);
        }

        if (!empty((string)$cnf->ipsec->client->pfs_group)) {
            $model->charon->plugins->attr->x_28679 = (string)$cnf->ipsec->client->pfs_group;
            unset($cnf->ipsec->client->pfs_group);
        }

        if (!empty((string)$cnf->ipsec->client->radius_source)) {
            $model->charon->plugins->{'eap-radius'}->servers = (string)$cnf->ipsec->client->radius_source;
            unset($cnf->ipsec->client->radius_source);
        } else {
            if (isset($cnf->ipsec->phase1)) {
                foreach ($cnf->ipsec->phase1 as $phase1) {
                    if (
                        !isset($phase1->disabled) && isset($phase1->mobile) &&
                        $phase1->authentication_method == 'eap-radius'
                    ) {
                        $model->charon->plugins->{'eap-radius'}->servers = (string)$phase1->authservers;
                    }
                }
            }
        }

        if (!empty((string)$cnf->ipsec->client->user_source)) {
            $tmp = explode(',', (string)$cnf->ipsec->client->user_source);
            $user_source = [];
            foreach ($model->general->user_source->getNodeData() as $key => $data) {
                if (in_array($key, $tmp)) {
                    $user_source[] = $key;
                }
            }
            if (!empty($user_source)) {
                $model->general->user_source = implode(',', $user_source);
            }
            unset($cnf->ipsec->client->user_source);
        }

        if (!empty((string)$cnf->ipsec->client->local_group)) {
            foreach ($model->general->local_group->getNodeData() as $key => $data) {
                if ((string)$cnf->ipsec->client->local_group == $data['value']) {
                    $model->general->local_group = $key;
                }
            }
            unset($cnf->ipsec->client->local_group);
        }
    }
}
