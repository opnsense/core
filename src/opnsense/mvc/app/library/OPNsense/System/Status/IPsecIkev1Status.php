<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis
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

namespace OPNsense\System\Status;

use OPNsense\System\AbstractStatus;
use OPNsense\System\SystemStatusCode;
use OPNsense\Core\Config;
use OPNsense\IPsec\Swanctl;

class IPsecIkev1Status extends AbstractStatus
{
    public function __construct()
    {
        $this->internalPriority = 3;
        $this->internalPersistent = false;
        $this->internalTitle = gettext('IPsec IKEv1 deprecation');
        $this->internalIsBanner = true;
        $this->internalScope[] = '/ui/ipsec/connections*';
        $this->internalScope[] = '/ui/ipsec/tunnels*';
    }

    public function collectStatus()
    {
        $has_ikev1 = false;

        foreach ((new Swanctl())->Connections->Connection->iterateItems() as $conn) {
            if (
                !empty((string)$conn->enabled) &&
                in_array((string)$conn->version, ['0', '1'], true)
            ) {
                $has_ikev1 = true;
                break;
            }
        }

        if (!$has_ikev1) {
            $config = Config::getInstance()->object();
            if (
                file_exists('/usr/local/www/vpn_ipsec_phase1.php') &&
                isset($config->ipsec->enable) &&
                !empty($config->ipsec->phase1)
            ) {
                foreach ($config->ipsec->phase1 as $p1) {
                    $iketype = !empty((string)$p1->iketype) ? (string)$p1->iketype : 'ikev1';
                    if (
                        empty((string)$p1->disabled) &&
                        in_array($iketype, ['ike', 'ikev1'], true)
                    ) {
                        $has_ikev1 = true;
                        break;
                    }
                }
            }
        }

        if ($has_ikev1) {
            $this->internalMessage = gettext(
                'One or more enabled IPsec connections allow IKEv1, which is deprecated and will be removed in a future release. Please consider switching to IKEv2.'
            );
            $this->internalStatus = SystemStatusCode::NOTICE;
        }
    }
}
