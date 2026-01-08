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

namespace OPNsense\Radvd\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Firewall\Util;
use OPNsense\Interfaces\Vip;

class VipLinkLocalField extends BaseField
{
    protected $internalIsContainer = false;

    protected function defaultValidationMessage()
    {
        return gettext('Invalid IPv6 link-local virtual IP.');
    }

    private function isValidLinkLocalAddress(string $addr): bool
    {
        return strpos($addr, '%') === false &&
            strpos($addr, '/') === false &&
            Util::isIpv6Address($addr) &&
            Util::isLinkLocal($addr);
    }

    private function vipExistsOnInterface(string $addr): bool
    {
        $ifname = $this->getParentNode()->interface->getValue();

        foreach ((new Vip())->vip->iterateItems() as $vip) {
            if (!$vip->interface->isEqual($ifname)) {
                continue;
            } elseif (!in_array($vip->mode->getValue(), ['ipalias', 'carp'])) {
                continue;
            } elseif ($vip->subnet->isEqual($addr)) {
                /* XXX requires a perfect match but ignores case and compression like radvd.inc */
                return true;
            }
        }

        return false;
    }

    public function getValidators()
    {
        $validators = parent::getValidators();

        $validators[] = new CallbackValidator([
            'callback' => function ($value) {
                $messages = [];
                $addr = (string)$value;

                if ($addr === '') {
                    return [];
                }

                if (!$this->isValidLinkLocalAddress($addr)) {
                    $messages[] = gettext(
                        'Please specify an IPv6 link-local address without a zone index or netmask.'
                    );
                } elseif (!$this->vipExistsOnInterface($addr)) {
                    $messages[] = gettext(
                        'The specified address does not exist as a virtual IP on the selected interface.'
                    );
                }

                return $messages;
            }
        ]);

        return $validators;
    }
}
