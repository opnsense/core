<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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
use OPNsense\Core\Shell;

class M1_0_0 extends BaseModelMigration
{
    /**
     * setup initial reqid's in phase2 entries
     */
    public function post($model)
    {
        $cnf = Config::getInstance()->object();
        if (isset($cnf->ipsec->phase1) && isset($cnf->ipsec->phase2)) {
            $reqids = [];
            $last_seq = 1;
            foreach ($cnf->ipsec->phase1 as $phase1) {
                $p2sequence = 0;
                foreach ($cnf->ipsec->phase2 as $phase2) {
                    if ((string)$phase1->ikeid != (string)$phase2->ikeid) {
                        continue;
                    }
                    if (empty($phase2->reqid)) {
                        if ((string)$phase2->mode == "route-based") {
                            // persist previous logic for route-based entries
                            $phase2->reqid = (int)$phase1->ikeid * 1000 + $p2sequence;
                        } else {
                            // allocate next sequence in the list
                            $phase2->reqid = (int)$last_seq;
                        }
                        $reqids[] = $last_seq;
                        while (in_array($last_seq, $reqids)) {
                            $last_seq++;
                        }
                    }
                    $p2sequence++;
                }
            }
        }
    }
}
