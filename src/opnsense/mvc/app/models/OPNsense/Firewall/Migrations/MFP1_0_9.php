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

class MFP1_0_9 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof Filter) {
            $config = Config::getInstance()->object();
            $legacy = $config->system;
            if (!isset($config->OPNsense->Firewall->Filter->settings->filter->scrub_enabled)) {
                $model->settings->filter->scrub_enabled = empty($legacy->scrub_interface_disable) ? '1' : '0';
            }
            if (!isset($config->OPNsense->Firewall->Filter->settings->filter->scrub_no_df)) {
                $model->settings->filter->scrub_no_df = !empty($legacy->scrubnodf) ? '1' : '0';
            }
            if (!isset($config->OPNsense->Firewall->Filter->settings->filter->scrub_random_id)) {
                $model->settings->filter->scrub_random_id = !empty($legacy->scrubrnid) ? '1' : '0';
            }
        }

        parent::run($model);
    }

    public function post($model)
    {
        if ($model instanceof Filter) {
            $legacy = Config::getInstance()->object()->system;
            unset(
                $legacy->scrub_interface_disable,
                $legacy->scrubnodf,
                $legacy->scrubrnid,
            );
        }
    }
}
