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

namespace OPNsense\Unbound\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_13 extends BaseModelMigration
{
    /**
     * - Migrate dnsbl container to ArrayField type, now differentiated by source_nets.
     * - Merge extended blocklists into OPNsense namespace
     * - move safesearch toggle to General container
     */

    private function splitNets($source_net)
    {
        if ($source_net === null || trim($source_net) === '') {
            return [''];
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $source_net)), 'strlen'));
        $groups = [];

        foreach ($items as $cidr) {
            if (!preg_match('~^(.*)/(\d{1,3})$~', $cidr, $m)) {
                continue;
            }
            $mask = (int)$m[2];
            if (!isset($groups[$mask])) {
                $groups[$mask] = [];
            }
            $groups[$mask][] = $cidr;
        }

        $out = [];
        foreach ($groups as $mask => $list) {
            $out[] = implode(',', $list);
        }

        return $out;
    }

    public function run($model)
    {
        $config = Config::getInstance()->object();
        $old_dnsbl = $config->OPNsense->unboundplus->dnsbl;

        if (!isset($old_dnsbl)) {
            return;
        }

        /* move safesearch boolean to general */
        $model->general->safesearch = $old_dnsbl->safesearch;

        /* skip default blocklist if no properties set (except for enabled) */
        $add_default = false;
        foreach ($old_dnsbl->children() as $key => $value) {
            if ($key != 'enabled' && !empty((string)$value)) {
                /* blocklist may or may not have been enabled - but properties were set, migrate it */
                $add_default = true;
            }
        }

        /* start with OPNsense namespace */
        if ($add_default) {
            $bl = $model->dnsbl->blocklist->add();
            $new_structure = array_values($model->dnsbl->blocklist->getNodeContent())[0];
            $nodes = [];

            foreach (array_keys($new_structure) as $key) {
                if (isset($old_dnsbl->$key)) {
                    $nodes[$key] = (string)$old_dnsbl->$key;
                } elseif ($key == 'allowlists') {
                    $nodes['allowlists'] = (string)$old_dnsbl->whitelists;
                } elseif (isset($bl->$key)) {
                    /* make sure "enabled" has a value */
                    $bl->$key->applyDefault();
                }
                $nodes['description'] = 'default';
            }

            $bl->setNodes($nodes);

            /* Normalize deprecated blocklists */
            $bl->type->normalizeValue();
        }

        /* Extended blocklists */
        if (isset($config->Deciso->Unbound->ExtendedDnsbl)) {
            $extdnsbl = $config->Deciso->Unbound->ExtendedDnsbl;

            /* blocklist */
            foreach ($extdnsbl->blocklists->children() as $blocklist) {
                foreach ($this->splitNets((string)$blocklist->source_net) as $net) {
                    $nodes = [];
                    $bl = $model->dnsbl->blocklist->add();
                    $new_structure = array_values($model->dnsbl->blocklist->getNodeContent())[0];
                    foreach ($blocklist->children() as $key => $value) {
                        if ($key == 'list') {
                            $nodes['type'] = str_replace('ext_', '', (string)$value);
                        } elseif ($key == 'source_net') {
                            $nodes['source_nets'] = $net;
                        } elseif ($key == 'description' && empty($value)) {
                            /* description now required */
                            $nodes['description'] = '<migrated from Extended Blocklists plugin>';
                        } elseif (isset($new_structure[$key])) {
                            $nodes[$key] = (string)$value;
                        }
                    }

                    $bl->setNodes($nodes);
                    $bl->type->normalizeValue();
                }
            }

            /* custom domains */
            foreach ($extdnsbl->custom_domains->children() as $domain) {
                foreach ($this->splitNets((string)$domain->source_net) as $net) {
                    $nodes = [];
                    $bl = $model->dnsbl->blocklist->add();
                    $new_structure = array_values($model->dnsbl->blocklist->getNodeContent())[0];
                    foreach ($domain->children() as $key => $value) {
                        if ($key == 'domains') {
                            $nodes['blocklists'] = (string)$value;
                        } elseif ($key == 'source_net') {
                            $nodes['source_nets'] = $net;
                        } elseif (isset($new_structure[$key])) {
                            /* description was already required */
                            $nodes[$key] = (string)$value;
                        }
                    }

                    $bl->setNodes($nodes);
                    $bl->type->normalizeValue();
                }
            }
        }
    }

    public function post($model)
    {
        $cfg = Config::getInstance()->object();
        if (isset($cfg->Deciso->Unbound->ExtendedDnsbl)) {
            unset($cfg->Deciso->Unbound);
        }
    }
}
