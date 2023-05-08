<?php

/*
 * Copyright (C) 2022-2023 Deciso B.V.
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

class M1_0_3 extends BaseModelMigration
{
    private $legacy_format = [
        'outgoing_num_tcp',
        'incoming_num_tcp',
        'num_queries_per_thread',
        'jostle_timeout',
        'cache_max_ttl',
        'cache_min_ttl',
        'infra_host_ttl',
        'infra_cache_numhosts',
        'unwanted_reply_threshold',
        'log_verbosity',
        'extended_statistics',
        'log_queries',
        'hideidentity',
        'hideversion',
        'prefetch',
        'prefetchkey',
        'dnssecstripped',
        'serveexpired',
        'qnameminstrict',
        'msgcachesize'
    ];

    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();
        $legacy_config = [];

        foreach ($config->unbound->children() as $key => $value) {
            if (in_array($key, $this->legacy_format) && !empty((string)$value)) {
                if ($key == 'msgcachesize') {
                    $legacy_config[$key] = (string)$value . 'm';
                    /* Mimic legacy behaviour for the msg cache size value (if applied) */
                    $legacy_config['rrsetcachesize'] = ($value * 2) . 'm';
                    continue;
                } elseif ($key == 'dnssecstripped') {
                    $legacy_config['dnssecstripped'] = empty((string)$value) ? 0 : 1;
                    continue;
                } elseif ($key == 'num_queries_per_thread') {
                    $legacy_config['outgoingrange'] = $value * 2;
                    /* FALLTHROUGH */
                }

                /* handle differing keys, underscore got removed in model transition */
                $legacy_config[str_replace('_', '', $key)] = (string)$value;
            }
        }

        foreach (['privatedomain', 'insecuredomain'] as $misc_node) {
            $node_value = (string)$config->OPNsense->unboundplus->miscellaneous->$misc_node;
            if (!empty($node_value)) {
                $legacy_config[$misc_node] = $node_value;
            }
        }

        $model->advanced->setNodes($legacy_config);
    }

    /**
     * cleanup old config after config save
     * @param $model
     */
    public function post($model)
    {
        $config = Config::getInstance()->object();
        foreach ($this->legacy_format as $node) {
            unset($config->unbound->$node);
        }
    }
}
