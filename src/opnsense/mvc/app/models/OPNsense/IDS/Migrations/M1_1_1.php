<?php

/*
 * Copyright (C) 2016-2018 Deciso B.V.
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

namespace OPNsense\IDS\Migrations;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Core\Config;
use OPNsense\Base\BaseModelMigration;
use OPNSense\IDS\IDS;

class M1_1_1 extends BaseModelMigration
{
    /**
     * Rename settings
     * @param IDS $model
     */
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();

        // Migrate log rotation
        if (isset($cfgObj->OPNsense->IDS->general->AlertLogrotate)) {
            $cfgObj->OPNsense->IDS->general->eveLog->rotate->count = $cfgObj->OPNsense->IDS->general->AlertLogrotate;
        }
        if (isset($cfgObj->OPNsense->IDS->general->AlertSaveLogs)) {
            $cfgObj->OPNsense->IDS->general->eveLog->rotate->size = $cfgObj->OPNsense->IDS->general->AlertSaveLogs;
        }

        // Migrate enabled types
        $types = explode(',', $cfgObj->OPNsense->IDS->general->eveLog->types);
        $extended = explode(',', $cfgObj->OPNsense->IDS->general->eveLog->extended);

        // Migrate alerts (previously enabled by default)
        if (!in_array('alert', $types)) {
            $types[] = 'alert';
        }

        // Migrate extended alerts
        if (isset($cfgObj->OPNsense->IDS->general->LogPayload)  &&
            $cfgObj->OPNsense->IDS->general->LogPayload == 1    &&
            !in_array('alert', $extended)
        ) {
            $extended[] = 'alert';
        }

        // Migrate HTTP
        if (isset($cfgObj->OPNsense->IDS->eveLog->http->enable)  &&
            $cfgObj->OPNsense->IDS->eveLog->http->enable == 1    &&
            !in_array('http', $types)
        ) {
            $types[] = 'http';
        }

        // Migrate extended HTTP
        if (isset($cfgObj->OPNsense->IDS->eveLog->http->extended)  &&
            $cfgObj->OPNsense->IDS->eveLog->http->extended == 1    &&
            !in_array('http', $extended)
        ) {
            $extended[] = 'http';
        }

        // Migrate TLS
        if (isset($cfgObj->OPNsense->IDS->eveLog->tls->enable)  &&
            $cfgObj->OPNsense->IDS->eveLog->tls->enable == 1    &&
            !in_array('tls', $types)
        ) {
            $types[] = 'tls';
        }

        // Migrate extended TLS
        if (isset($cfgObj->OPNsense->IDS->eveLog->tls->extended)  &&
            $cfgObj->OPNsense->IDS->eveLog->tls->extended == 1    &&
            !in_array('tls', $extended)
        ) {
            $extended[] = 'tls';
        }


        $cfgObj->OPNsense->IDS->general->eveLog->types = join(',', $types);
        $cfgObj->OPNsense->IDS->general->eveLog->extended = join(',', $extended);
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        unset($config->OPNsense->IDS->general->AlertLogrotate);
        unset($config->OPNsense->IDS->general->AlertSaveLogs);
        unset($config->OPNsense->IDS->general->LogPayload);
        unset($config->OPNsense->IDS->eveLog->http);
        unset($config->OPNsense->IDS->eveLog->tls);
    }
}
