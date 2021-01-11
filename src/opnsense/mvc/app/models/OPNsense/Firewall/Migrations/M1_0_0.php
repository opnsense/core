<?php

/**
 *    Copyright (C) 2018 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Firewall\Migrations;

use OPNsense\Core\Config;
use OPNsense\Base\BaseModelMigration;
use OPNsense\Firewall\Alias;

class M1_0_0 extends BaseModelMigration
{
    /**
     * Migrate legacy aliases
     * @param $model
     */
    public function run($model)
    {
        if ($model instanceof Alias) {
            $cfgObj = Config::getInstance()->object();
            if (!empty($cfgObj->aliases) && !empty($cfgObj->aliases->alias)) {
                foreach ($cfgObj->aliases->alias as $alias) {
                    // find node by name or create a new one, aliases should be unique by name
                    $node = null;
                    foreach ($model->aliases->alias->iterateItems() as $new_alias) {
                        if ((string)$new_alias->name == (string)$alias->name) {
                            $node = $new_alias;
                            break;
                        }
                    }
                    if ($node === null) {
                        $node = $model->aliases->alias->Add();
                    }
                    // set alias properties
                    $node->description = substr(preg_replace(
                        "/[^\t\n\v\f\r 0-9a-zA-Z.\-,_\x{00A0}-\x{FFFF}]/u",
                        " ",
                        (string)$alias->descr
                    ), 0, 255);
                    $node->name = (string)$alias->name;
                    $node->type = (string)$alias->type;
                    if (in_array((string)$alias->type, array('urltable_ports', 'url_ports'))) {
                        // unsupported, replace with empty port alias
                        $node->type = "port";
                    } elseif ($alias->url) {
                        // url content only contains a single item
                        $node->content = (string)$alias->url;
                    } elseif ($alias->aliasurl) {
                        // aliasurl in legacy config could consist of multiple <aliasurl> entries
                        $content = array();
                        foreach ($alias->aliasurl as $url) {
                            $content[] = (string)$url;
                        }
                        $node->content = implode("\n", $content);
                    } elseif ($alias->address) {
                        // address entries
                        $node->content = str_replace(" ", "\n", trim((string)$alias->address));
                    }
                    if ($alias->proto) {
                        $node->proto = (string)$alias->proto;
                    }
                    if ($alias->updatefreq) {
                        $node->updatefreq = (string)$alias->updatefreq;
                    }
                }
            }
        }
    }

    /**
     * cleanup old config after config save, we need the old data to avoid race conditions in validations
     * @param $model
     */
    public function post($model)
    {
        if ($model instanceof Alias) {
            $cfgObj = Config::getInstance()->object();
            unset($cfgObj->aliases);
        }
    }
}
