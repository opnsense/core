<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Firewall\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Firewall\Filter;

class MFP1_1_6 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof Filter) {
            $config = Config::getInstance()->object();
            $legacy_system = $config->system;
            $model->settings->filter->setNodes([
                'disable_reply_to' => !empty($legacy_system->disablereplyto) ? '1' : '0',
                'no_antilockout' => !empty($legacy_system->webgui->noantilockout) ? '1' : '0',
                'no_ipv6_rfc4890_req' => !empty($legacy_system->no_ipv6_rfc4890_req) ? '1' : '0',
                'no_port0_block' => !empty($legacy_system->no_port0_block) ? '1' : '0',
                'no_sshlockout' => !empty($legacy_system->no_sshlockout) ? '1' : '0',
                'no_virusprot' => !empty($legacy_system->no_virusprot) ? '1' : '0',
            ]);
        }

        parent::run($model);
    }

    public function post($model)
    {
        if ($model instanceof Filter) {
            $config = Config::getInstance()->object();
            $legacy_system = $config->system;
            unset(
                $legacy_system->disablereplyto,
                $legacy_system->webgui->noantilockout,
                $legacy_system->no_ipv6_rfc4890_req,
                $legacy_system->no_port0_block,
                $legacy_system->no_sshlockout,
                $legacy_system->no_virusprot,
            );
        }
    }
}
