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

class MFP1_1_7 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof Filter) {
            $config = Config::getInstance()->object();
            $legacy_system = $config->system;
            $legacy_filter = $config->filter;
            $model->settings->filter->setNodes([
                'disable_filter' => !empty($legacy_system->disablefilter) ? '1' : '0',
                'optimization' => !empty($legacy_system->optimization) ? (string)$legacy_system->optimization : 'normal',
                'state_policy' => !empty($legacy_system->{'state-policy'}) ? '1' : '0',
                'adaptive_start' => !empty($legacy_system->adaptivestart) ? (string)$legacy_system->adaptivestart : '',
                'adaptive_end' => !empty($legacy_system->adaptiveend) ? (string)$legacy_system->adaptiveend : '',
                'maximum_states' => !empty($legacy_system->maximumstates) ? (string)$legacy_system->maximumstates : '',
                'maximum_fragments' => !empty($legacy_system->maximumfrags) ? (string)$legacy_system->maximumfrags : '',
                'maximum_table_entries' => !empty($legacy_system->maximumtableentries) ? (string)$legacy_system->maximumtableentries : '',
                'bypass_static_routes' => !empty($legacy_filter->bypassstaticroutes) ? '1' : '0',
                'syncookies' => (string)$legacy_system->syncookies,
                'syncookies_adaptive_start' => (string)$legacy_system->syncookies_adaptstart,
                'syncookies_adaptive_end' => (string)$legacy_system->syncookies_adaptend,
                'keep_counters' => !empty($legacy_system->keepcounters) ? '1' : '0',
            ]);
            $model->settings->alias->setNodes([
                'aliases_resolve_interval' => !empty($legacy_system->aliasesresolveinterval) ? (string)$legacy_system->aliasesresolveinterval : '300',
                'check_aliases_url_cert' => !empty($legacy_system->checkaliasesurlcert) ? '1' : '0',
            ]);
            $model->settings->logging->setNodes([
                'debug' => !empty($legacy_system->pfdebug) ? (string)$legacy_system->pfdebug : 'urgent',
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
                $legacy_system->disablefilter,
                $legacy_system->optimization,
                $legacy_system->{'state-policy'},
                $legacy_system->adaptivestart,
                $legacy_system->adaptiveend,
                $legacy_system->maximumstates,
                $legacy_system->maximumfrags,
                $legacy_system->maximumtableentries,
                $legacy_system->syncookies,
                $legacy_system->syncookies_adaptstart,
                $legacy_system->syncookies_adaptend,
                $legacy_system->keepcounters,
                $legacy_system->aliasesresolveinterval,
                $legacy_system->checkaliasesurlcert,
                $legacy_system->pfdebug,
            );
            $legacy_filter = $config->filter;
            unset(
                $legacy_filter->bypassstaticroutes,
            );
        }
    }
}
