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
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

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
                'general' => $data[self::$internalModelName]['general'],
                'ha' => $data[self::$internalModelName]['ha'],
                'this_hostname' => (string)Config::getInstance()->object()->system->hostname
            ]
        ];
    }

    public function searchSubnetAction()
    {
        return $this->searchBase("subnets.subnet4", null, "subnet");
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
        return $this->searchBase("reservations.reservation", null, "hw_address");
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

    public function downloadReservationsAction()
    {
        if ($this->request->isGet()) {
            $this->exportCsv($this->getModel()->reservations->reservation->asRecordSet(false, ['subnet']));
        }
    }

    public function uploadReservationsAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('payload')) {
            $subnets = [];
            foreach ($this->getModel()->subnets->subnet4->iterateItems() as $key => $node) {
                $subnets[(string)$node->subnet] = $key;
            }
            return $this->importCsv(
                'reservations.reservation',
                $this->request->getPost('payload'),
                ['hw_address', 'subnet'],
                function (&$record) use ($subnets) {
                    /* seek matching subnet */
                    if (!empty($record['ip_address'])) {
                        foreach ($subnets as $subnet => $uuid) {
                            if (Util::isIPInCIDR($record['ip_address'], $subnet)) {
                                $record['subnet'] = $uuid;
                            }
                        }
                    }
                }
            );
        } else {
            return ['status' => 'failed'];
        }
    }

    public function searchPeerAction()
    {
        return $this->searchBase("ha_peers.peer", null, "name");
    }

    public function setPeerAction($uuid)
    {
        return $this->setBase("peer", "ha_peers.peer", $uuid);
    }

    public function addPeerAction()
    {
        return $this->addBase("peer", "ha_peers.peer");
    }

    public function getPeerAction($uuid = null)
    {
        return $this->getBase("peer", "ha_peers.peer", $uuid);
    }

    public function delPeerAction($uuid)
    {
        return $this->delBase("ha_peers.peer", $uuid);
    }
}
