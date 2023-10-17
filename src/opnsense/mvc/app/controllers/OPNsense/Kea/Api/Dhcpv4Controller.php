<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Kea\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class Dhcpv4Controller extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'dhcpv4';
    protected static $internalModelClass = 'OPNsense\Kea\KeaDhcpv4';

    /**
     * @inheritdoc
     */
    public function getAction()
    {
        $data = parent::getAction();
        return [
            self::$internalModelName => [
                'general' => $data[self::$internalModelName]['general']
            ]
        ];
    }

    public function searchSubnetAction()
    {
        return $this->searchBase("subnets.subnet4", ['subnet'], "subnet");
    }

    public function setSubnetAction($uuid)
    {
        return $this->setBase("subnet4", "subnets.subnet4", $uuid);
    }

    public function addSubnetAction()
    {
        return $this->addBase("subnet4", "subnets.subnet4");
    }

    public function getSubnetAction($uuid = null)
    {
        return $this->getBase("subnet4", "subnets.subnet4", $uuid);
    }

    public function delSubnetAction($uuid)
    {
        return $this->delBase("subnets.subnet4", $uuid);
    }

    public function searchReservationAction()
    {
        return $this->searchBase("reservations.reservation", ['subnet', 'hw_address', 'description'], "hw_address");
    }

    public function setReservationAction($uuid)
    {
        return $this->setBase("reservation", "reservations.reservation", $uuid);
    }

    public function addReservationAction()
    {
        return $this->addBase("reservation", "reservations.reservation");
    }

    public function getReservationAction($uuid = null)
    {
        return $this->getBase("reservation", "reservations.reservation", $uuid);
    }

    public function delReservationAction($uuid)
    {
        return $this->delBase("reservations.reservation", $uuid);
    }
}
