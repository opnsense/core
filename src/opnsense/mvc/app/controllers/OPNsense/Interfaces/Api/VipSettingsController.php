<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Interfaces\Api;

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Base\UserException;
use OPNsense\Base\ApiMutableModelControllerBase;

class VipSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'vip';
    protected static $internalModelClass = 'OPNsense\Interfaces\Vip';

    /**
     * extract network field into subnet + bits for model
     */
    private function getVipOverlay()
    {
        $overlay = ['network' => ''];
        $tmp = $this->request->getPost('vip');
        if (!empty($tmp['network'])) {
            $parts = explode('/', $tmp['network'], 2);
            $overlay['subnet'] = $parts[0];
            if (count($parts) < 2) {
                $overlay['subnet_bits'] = strpos($parts[0], ':') !== false ? 128 : 32;
            } else {
                $overlay['subnet_bits'] = $parts[1];
            }
        }
        return $overlay;
    }

    /**
     * retrieve first unused VHID number
     */
    public function getUnusedVhidAction()
    {
        $vhids = [];
        foreach ($this->getModel()->vip->iterateItems() as $vip) {
            if (!in_array((string)$vip->vhid, $vhids) && !empty((string)$vip->vhid)) {
                $vhids[] = (string)$vip->vhid;
            }
        }
        for ($i=1; $i <= 255; $i++) {
            if (!in_array((string)$i, $vhids)) {
                return ['vhid' => $i, 'status' => 'ok'];
            }
        }
        return ['status' => 'not_found'];
    }

    /**
     * remap subnet and subnet_bits to network (which represents combined field)
     */
    private function handleFormValidations($response)
    {
        if (!empty($response['validations'])) {
            foreach (array_keys($response['validations']) as $fieldname) {
                if (in_array($fieldname, ['vip.subnet', 'vip.subnet_bits'])) {
                    if (empty($response['validations']['vip.network'])) {
                        $response['validations']['vip.network'] = [];
                    }
                    if (is_array($response['validations'][$fieldname])) {
                        $response['validations']['vip.network'] = array_merge(
                            $response['validations']['vip.network'],
                            $response['validations'][$fieldname]
                        );
                    } else {
                        $response['validations']['vip.network'][] = $response['validations'][$fieldname];
                    }
                    unset($response['validations'][$fieldname]);
                }
            }
        }
        return $response;
    }

    public function searchItemAction()
    {
        return $this->searchBase('vip', ['interface', 'mode', 'type', 'descr'], 'descr');
    }

    public function setItemAction($uuid)
    {
        return $this->handleFormValidations($this->setBase('vip', 'vip', $uuid, $this->getVipOverlay()));

    }

    public function addItemAction()
    {
        return $this->handleFormValidations($this->addBase('vip', 'vip', $this->getVipOverlay()));
    }

    public function getItemAction($uuid = null)
    {
        $vip = $this->getBase('vip', 'vip', $uuid);
        // Merge subnet + netmask into network field
        if (!empty($vip['vip']) && !empty($vip['vip']['subnet'])) {
            $vip['vip']['network'] = $vip['vip']['subnet'] . "/" . $vip['vip']['subnet_bits'];
        } elseif (!empty($vip['vip'])) {
            $vip['vip']['network'] = '';
        }
        unset($vip['vip']['subnet']);
        unset($vip['vip']['subnet_bits']);
        return $vip;
    }

    public function delItemAction($uuid)
    {
        return $this->delBase("vip", $uuid);
    }

    public function reconfigureAction()
    {
        $result = array("status" => "failed");
        if ($this->request->isPost()) {
            $result['status'] = strtolower(trim((new Backend())->configdRun('interface vip configure')));
        }
        return $result;
    }
}
