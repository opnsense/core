<?php

/**
 *    Copyright (C) 2022 Deciso B.V.
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
 */

namespace OPNsense\Interfaces\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Routing\Gateways;

class GatewayField extends BaseListField
{
    private static $interface_gateways;

    /**
     * @var string Network family (ipv4, ipv6)
     */
    protected $internalAddressFamily;

    /**
     * @var string label for empty value
     */
    protected $internalEmptyValueLabel;

    /**
     * setter for address family.
     *
     * @param $value address family [ipv4, ipv6, empty for all]
     */
    public function setAddressFamily($value)
    {
        $this->internalAddressFamily = trim(strtolower($value));
    }

    /**
     * setter for empty value label.
     *
     * @param $value string
     */
    public function setEmptyValueLabel($value)
    {
        $this->internalEmptyValueLabel = trim($value);
    }

    protected function actionPostLoadingEvent()
    {
        $this->internalOptionList[''] = $this->internalEmptyValueLabel;

        $ipprotocol = null;
        switch ($this->internalAddressFamily) {
            case 'ipv4':
                $ipprotocol = 'inet';
                break;
            case 'ipv6':
                $ipprotocol = 'inet6';
                break;
        }

        $gatewayClass = new Gateways([]);

        foreach ($gatewayClass->getGateways() as $id => $gateway) {
            if ($this->internalParentNode->if != $gateway['if']) {
                continue;
            }

            if (null !== $ipprotocol && $ipprotocol != $gateway['ipprotocol']) {
                continue;
            }

            if ($gateway['defunct']) {
                continue;
            }

            $this->internalOptionList[$gateway['name']] = sprintf(
                '%s - %s',
                $gateway['name'],
                $gatewayClass->getAddress($gateway['name'])
            );
        }

        return parent::actionPostLoadingEvent();
    }
}
