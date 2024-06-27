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

// <!--  external, category, descr, interface, type, source, destination, natreflection, disabled -->

class MFP1_0_4 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof Filter) {
            $sequence = 1;
            $catmdl = new Category();
            foreach ((Config::getInstance()->object())->nat->children() as $child) {
                if ($child->getName() == 'onetoone') {
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

                    if (!empty($addr['source']) && !empty($addr['destination']) && !empty((string)$child->external)) {
                        $node = $model->onetoone->rule->Add();
                        $node->enabled = empty((string)$child->disabled) ? "1" : "0";
                        $node->log = empty((string)$child->log) ? "0" : "1";
                        $node->sequence = (string)($sequence++);
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
                        $node->interface = (string)$child->interface;
                        $node->type = !empty((string)$child->type) ? (string)$child->type : 'binat';
                        $node->external = (string)$child->external;
                        $node->source_net = $addr['source'];
                        $node->destination_net = $addr['destination'];
                        $node->source_not = !empty((string)$child->source->not) ? '1' : '0';
                        $node->destination_not = !empty((string)$child->destination->not) ? '1' : '0';
                        $node->description = (string)$child->descr;
                        if (!empty((string)$child->natreflection)) {
                            $node->natreflection = (string)$child->natreflection;
                        }
                    }
                }
            }
        }
    }

    public function post($model)
    {
        if ($model instanceof Filter) {
            $cfgObj = Config::getInstance()->object();
            if (isset($cfgObj->nat->onetoone)) {
                unset($cfgObj->nat->onetoone);
            }
        }
    }
}
