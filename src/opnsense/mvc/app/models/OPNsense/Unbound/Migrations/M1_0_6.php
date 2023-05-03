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

namespace OPNsense\Unbound\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_6 extends BaseModelMigration
{
    public function run($model)
    {
        $config = Config::getInstance()->object();

        $legacy_format = [
            'allow' => 'allow',
            'deny' => 'deny',
            'refuse' => 'refuse',
            'allow snoop' => 'allow_snoop',
            'deny nonlocal' => 'deny_non_local',
            'refuse nonlocal' => 'refuse_non_local'
        ];

        if (!empty($config->unbound->acls)) {
            foreach ($config->unbound->acls as $acl) {
                if (!isset($legacy_format[(string)$acl->aclaction])) {
                    continue;
                }

                $node = [
                    'enabled' => 1,
                    'name' => !empty($acl->aclname) ? $acl->aclname : 'Unnamed ACL',
                    'action' => $legacy_format[(string)$acl->aclaction],
                    'description' => !empty($acl->description) ? (string)$acl->description : null,
                ];

                $networks = [];

                if (!empty($acl->row)) {
                    foreach ($acl->row as $row) {
                        if (empty($row->acl_network) || empty($row->mask)) {
                            continue;
                        }

                        /* for every network that has a description provided, we create a new ACL */
                        $network = sprintf(
                            "%s/%s",
                            (string)$row->acl_network,
                            (string)$row->mask
                        );

                        if (!empty($row->description)) {
                            $new = $model->acls->acl->add();
                            $tmp = $node['name'];
                            $node['name'] .= '-' . (string)$row->description;
                            $node['networks'] = $network;
                            $new->setNodes($node);
                            $node['name'] = $tmp;
                        } else {
                            $networks[] = $network;
                        }
                    }
                } else {
                    /* ACL without network(s), drop it */
                    continue;
                }

                if (!empty($networks)) {
                    $node['networks'] = implode(",", $networks);

                    $mig_acl = $model->acls->acl->add();

                    $mig_acl->setNodes($node);
                }
            }
        }

        /* Apply the default action */
        $model->acls->default_action->applyDefault();
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        unset($config->unbound);
    }
}
