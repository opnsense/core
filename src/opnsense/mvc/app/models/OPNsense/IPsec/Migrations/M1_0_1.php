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

class M1_0_1 extends BaseModelMigration
{
    /**
     * Migrate pre-shared-keys from both IPsec legacy and user administration
     */
    public function run($model)
    {
        $cnf = Config::getInstance()->object();
        $all_idents = [];
        if (isset($cnf->system->user)) {
            foreach ($cnf->system->user as $user) {
                if (!empty((string)$user->ipsecpsk)) {
                    $all_idents[(string)$user->name] = [
                        'ident' => (string)$user->name,
                        'Key' => (string)$user->ipsecpsk,
                        'keyType' => 'PSK'
                    ];
                    unset($user->ipsecpsk);
                }
            }
        }
        if (isset($cnf->ipsec->mobilekey)) {
            foreach ($cnf->ipsec->mobilekey as $mobilekey) {
                if (!empty((string)$mobilekey->ident) && !empty((string)$mobilekey->{'pre-shared-key'})) {
                    $all_idents[(string)$mobilekey->ident] = [
                        'ident' => (string)$mobilekey->ident,
                        'Key' => (string)$mobilekey->{'pre-shared-key'},
                        'keyType' => !empty((string)$mobilekey->type) ? (string)$mobilekey->type : 'PSK'
                    ];
                }
            }
            unset($cnf->ipsec->mobilekey);
        }
        if (!empty($all_idents)) {
            foreach ($all_idents as $ident) {
                $node = null;
                foreach ($model->preSharedKeys->preSharedKey->iterateItems() as $psk) {
                    if ($ident['ident'] == (string)$psk->ident && isset($all_idents[(string)$psk->ident])) {
                        $node = $psk;
                        break;
                    }
                }
                if ($node === null) {
                    $node = $model->preSharedKeys->preSharedKey->Add();
                }
                $node->setNodes($ident);
            }
        }
    }
}
