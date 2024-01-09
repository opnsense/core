<?php

/**
 *    Copyright (C) 2023 Deciso B.V.
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
use OPNsense\Firewall\Filter;
use OPNsense\Firewall\Category;
use OPNsense\Firewall\Util;

class MFP1_0_3 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof Filter) {
            $sequence = 1;
            $catmdl = new Category();
            foreach ((Config::getInstance()->object())->nat->children() as $child) {
                if ($child->getName() == 'npt') {
                    if (
                        (
                            Util::isSubnet((string)$child->source->address) ||
                            Util::isIpAddress((string)$child->source->address)
                        ) && (
                            empty((string)$child->destination->address) ||
                            Util::isSubnet((string)$child->destination->address) ||
                            Util::isIpAddress((string)$child->destination->address)
                        )
                    ) {
                        $node = $model->npt->rule->Add();
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
                        $node->source_net = (string)$child->source->address;
                        if (
                            !empty((string)$child->destination->address) &&
                            strpos((string)$child->destination->address, "/") === false
                        ) {
                            // matching subnets when omited
                            $tmp = explode('/', (string)$child->source->address . "/128")[1];
                            $node->destination_net = (string)$child->destination->address . "/" . $tmp;
                        } else {
                            $node->destination_net = (string)$child->destination->address;
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
            if (isset($cfgObj->nat->npt)) {
                unset($cfgObj->nat->npt);
            }
        }
    }
}
