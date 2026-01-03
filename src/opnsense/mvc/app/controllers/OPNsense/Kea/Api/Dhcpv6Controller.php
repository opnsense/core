<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

class Dhcpv6Controller extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'dhcpv6';
    protected static $internalModelClass = 'OPNsense\Kea\KeaDhcpv6';

    /**
     * @inheritdoc
     */
    public function getAction()
    {
        $data = parent::getAction();
        return [
            self::$internalModelName => [
                'general' => $data[self::$internalModelName]['general'],
                'lexpire' => $data[self::$internalModelName]['lexpire'],
                'ha' => $data[self::$internalModelName]['ha'],
                'this_hostname' => (string)Config::getInstance()->object()->system->hostname
            ]
        ];
    }

    public function searchSubnetAction()
    {
        return $this->searchBase("subnets.subnet6", null, "subnet");
    }

    public function setSubnetAction($uuid)
    {
        return $this->setBase("subnet6", "subnets.subnet6", $uuid);
    }

    public function addSubnetAction()
    {
        return $this->addBase("subnet6", "subnets.subnet6");
    }

    public function getSubnetAction($uuid = null)
    {
        return $this->getBase("subnet6", "subnets.subnet6", $uuid);
    }

    public function delSubnetAction($uuid)
    {
        return $this->delBase("subnets.subnet6", $uuid);
    }

    public function searchReservationAction()
    {
        return $this->searchBase("reservations.reservation", null, "duid");
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
            foreach ($this->getModel()->subnets->subnet6->iterateItems() as $key => $node) {
                $subnets[(string)$node->subnet] = $key;
            }
            return $this->importCsv(
                'reservations.reservation',
                $this->request->getPost('payload'),
                ['duid', 'subnet'],
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

    public function searchPdPoolAction()
    {
        return $this->searchBase("pd_pools.pd_pool");
    }

    public function setPdPoolAction($uuid)
    {
        return $this->setBase("pd_pool", "pd_pools.pd_pool", $uuid);
    }

    public function addPdPoolAction()
    {
        return $this->addBase("pd_pool", "pd_pools.pd_pool");
    }

    public function getPdPoolAction($uuid = null)
    {
        return $this->getBase("pd_pool", "pd_pools.pd_pool", $uuid);
    }

    public function delPdPoolAction($uuid)
    {
        return $this->delBase("pd_pools.pd_pool", $uuid);
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
