<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

class M1_0_2 extends BaseModelMigration
{
    /**
     * Migrate pre-shared-keys from advanced settings legacy page stored under "ipsec" section
     */
    public function run($model)
    {
        if (!$model instanceof IPsec) {
            return;
        }
        $cnf = Config::getInstance()->object();
        if (!isset($cnf->ipsec)) {
            return;
        }
        $all_idents = [];
        if (isset($cnf->ipsec->max_ikev1_exchanges) && $cnf->ipsec->max_ikev1_exchanges != '') {
            $model->charon->max_ikev1_exchanges = (string)$cnf->ipsec->max_ikev1_exchanges;
            unset($cnf->ipsec->max_ikev1_exchanges);
        }

        $keys = [];
        foreach ($cnf->ipsec->children() as $key => $value) {
            if (strpos($key, 'ipsec_') === 0 && strlen($key) == 9) {
                $log_item = substr($key, 6);
                $model->charon->syslog->daemon->$log_item = (string)$value;
                $keys[] = $key;
            }
        }
        foreach ($keys as $key) {
            unset($cnf->ipsec->$key);
        }

        if (isset($cnf->ipsec->passthrough_networks) && $cnf->ipsec->passthrough_networks != '') {
            $model->general->passthrough_networks = (string)$cnf->ipsec->passthrough_networks;
            unset($cnf->ipsec->passthrough_networks);
        }
        if (isset($cnf->ipsec->preferred_oldsa) && !empty((string)$cnf->ipsec->preferred_oldsa)) {
            $model->general->preferred_oldsa = "1";
            unset($cnf->ipsec->preferred_oldsa);
        }
    }
}
