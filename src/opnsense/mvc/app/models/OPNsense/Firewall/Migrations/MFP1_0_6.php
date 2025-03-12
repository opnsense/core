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

namespace OPNsense\Firewall\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Firewall\Category;
use OPNsense\Firewall\Filter;
use OPNsense\Firewall\Util;

class MFP1_0_6 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof Filter) {
            $catmdl = new Category();
            foreach ((Config::getInstance()->object())->nat->children() as $outbound) {
                if ($outbound->getName() == 'outbound') {
                    if (!empty((string)$outbound->mode)) {
                        $model->outbound->mode = (string)$outbound->mode;
                    }
                    $sequence = 1;
                    foreach ($outbound->rule as $child) {
                        if (empty($child)) {
                            continue;
                        }
                        $addr = [];
                        foreach (['destination', 'source'] as $fieldname) {
                            if (!empty(((string)$child->$fieldname->any))) {
                                $addr[$fieldname] = 'any';
                            } elseif (Util::isSubnet((string)$child->$fieldname->address)) {
                                $addr[$fieldname] = (string)$child->$fieldname->address;
                            } elseif (Util::isIpAddress((string)$child->$fieldname->address)) {
                                $subn = strpos($child->$fieldname->address, ':') === false ? '32' : '128';
                                $addr[$fieldname] = (string)$child->$fieldname->address . '/' . $subn;
                            } elseif (!empty((string)$child->$fieldname->address)) {
                                $addr[$fieldname] = (string)$child->$fieldname->address;
                            } elseif (!empty((string)$child->$fieldname->network)) {
                                $addr[$fieldname] = (string)$child->$fieldname->network;
                            } else {
                                $addr[$fieldname] = null;
                            }
                        }
                        $node = $model->outbound->rule->Add();
                        $node->enabled = empty((string)$child->disabled) ? '1' : '0';
                        $node->sequence = (string)($sequence++);
                        $node->nonat = empty((string)$child->nonat) ? '0' : '1';
                        $node->interface = (string)$child->interface;
                        $node->ipprotocol = (string)$child->ipprotocol;
                        $node->protocol = empty((string)$child->protocol) ? 'any' : strtoupper((string)$child->protocol);
                        $node->source_not = !empty((string)$child->source->not) ? '1' : '0';
                        $node->source_net = $addr['source'];
                        $node->source_port = (string)$child->sourceport;
                        $node->destination_not = !empty((string)$child->destination->not) ? '1' : '0';
                        $node->destination_net = $addr['destination'];
                        $node->destination_port = (string)$child->dstport;
                        if (empty((string)$child->nonat)) {
                            $node->target = ((string)$child->target=='other-subnet') ? (string)$child->targetip."/".(string)$child->targetip_subnet : (string)$child->target;
                            $node->target_port = (string)$child->natport;
                            $node->static_port = !empty((string)$child->staticnatport) ? '1' : '0';
                        } else {
                            $node->target = '';
                            $node->target_port = '';
                            $node->static_port = '0';                       
                        }
                        $node->log = empty((string)$child->log) ? '0' : '1';
                        if ((string)$child->poolopts=='round-robin sticky-address') {
                            $pool_options = 'rr_sticky_addr';
                        } elseif ((string)$child->poolopts=='random sticky-address') {
                            $pool_options = 'ra_sticky_addr';
                        } else {
                            $pool_options = str_replace("-", "_", (string)$child->poolopts);
                        }
                        $node->pool_options = $pool_options;
                        $node->source_hash_key = (string)$child->poolopts_sourcehashkey;
                        $node->set_tag = (string)$child->tag;
                        $node->match_tag = (string)$child->tagged;
                        $node->no_xmlrpc_sync = empty((string)$child->nosync) ? '0' : '1';                      
                        if (!empty((string)$child->category)) {
                            $cats = [];
                            foreach (explode(',', (string)$child->category) as $cat) {
                                $tmp = $catmdl->getByName($cat);
                                if ($tmp != null) {
                                    $cats[] = $tmp->getAttributes()['uuid'];
                                }
                            }
                            $node->categories = implode(",", $cats);
                        }
                        $node->description = (string)$child->descr;
                    }
                }
            }
        }
    }

    public function post($model)
    {
        if ($model instanceof Filter) {
            $cfgObj = Config::getInstance()->object();
            if (isset($cfgObj->nat->outbound)) {
                unset($cfgObj->nat->outbound);
            }
            if (empty(trim((string)$cfgObj->nat))) {
                unset($cfgObj->nat);
            }
        }
    }
}
