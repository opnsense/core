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

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * @package OPNsense\Interfaces
 */
class BridgeSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'bridge';
    protected static $internalModelClass = 'OPNsense\Interfaces\Bridge';

    /**
     * search bridges
     * @return array search results
     */
    public function searchItemAction()
    {
        return $this->searchBase("bridged", null, "descr");
    }

    /**
     * Update bridge with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     */
    public function setItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('bridged.' . $uuid);
        $overlay = null;
        if (!empty($node)) {
            // not allowed to change bridge interface name
            $overlay['bridgeif'] = (string)$node->bridgeif;
        }
        return $this->setBase("bridge", "bridged", $uuid, $overlay);
    }

    /**
     * Add new bridge and set with attributes from post
     * @return array save result + validation output
     */
    public function addItemAction()
    {
        Config::getInstance()->lock();
        $overlay = [];
        $ifnames = [];
        foreach ($this->getModel()->bridged->iterateItems() as $node) {
            $ifnames[] = (string)$node->bridgeif;
        }
        for ($i = 0; true; ++$i) {
            $gifif = sprintf('bridge%d', $i);
            if (!in_array($gifif, $ifnames)) {
                $overlay['bridgeif'] = $gifif;
                break;
            }
        }

        return $this->addBase("bridge", "bridged", $overlay);
    }

    /**
     * Retrieve bridge settings or return defaults for new one
     * @param $uuid item unique id
     * @return array bridge content
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase("bridge", "bridged", $uuid);
    }

    /**
     * Delete bridge by uuid
     * @param string $uuid internal id
     * @return array save status
     */
    public function delItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('bridged.' . $uuid);
        if ($node != null) {
            $cfg = Config::getInstance()->object();
            foreach ($cfg->interfaces->children() as $key => $value) {
                if ((string)$value->if == (string)$node->bridgeif) {
                    throw new \OPNsense\Base\UserException(
                        sprintf(gettext("Cannot delete bridge. Currently in use by [%s] %s"), $key, $value),
                        gettext("bridge in use")
                    );
                }
            }
        }
        return $this->delBase("bridged", $uuid);
    }

    /**
     * reconfigure bridges
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun("interface bridge configure");
            return ["status" => "ok"];
        } else {
            return ["status" => "failed"];
        }
    }
}
