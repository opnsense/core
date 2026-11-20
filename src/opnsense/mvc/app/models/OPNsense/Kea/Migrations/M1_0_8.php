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

namespace OPNsense\Kea\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Kea\KeaDhcpv4;

class M1_0_8 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof KeaDhcpv4) {
            foreach ($model->subnets->subnet4->iterateItems() as $subnet) {
                if (
                    // Assume when a dns server has been set, an empty ddns suffix is intentional
                    $subnet->ddns_dns_server->isEmpty() &&
                    $subnet->ddns_qualifying_suffix->isEmpty() &&
                    !$subnet->option_data->domain_name->isEmpty()
                ) {
                    $subnet->ddns_qualifying_suffix->setValue($subnet->option_data->domain_name->getValue());
                }
            }
        }

        parent::run($model);
    }
}
