<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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
class GreSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'gre';
    protected static $internalModelClass = 'OPNsense\Interfaces\Gre';


    /**
     * write updated or removed gre to temp
     */
    private function stashUpdate($greif)
    {
        file_put_contents("/tmp/.gre.todo", "{$greif}\n", FILE_APPEND | LOCK_EX);
        chmod("/tmp/.gre.todo", 0750);
    }

    /**
     * search gres
     * @return array search results
     */
    public function searchItemAction()
    {
        return $this->searchBase("gre", null, "descr");
    }

    /**
     * Update gre with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     */
    public function setItemAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('gre.' . $uuid);
        $overlay = null;
        if (!empty($node)) {
            // not allowed to change gre interface name
            $overlay['greif'] = (string)$node->greif;
        }

        $result = $this->setBase("gre", "gre", $uuid, $overlay);
        if ($result['result'] != 'failed') {
            $this->stashUpdate($overlay !== null ? $overlay['greif'] : $this->request->get('gre')['greif']);
        }
        return $result;
    }

    /**
     * Add new gre and set with attributes from post
     * @return array save result + validation output
     */
    public function addItemAction()
    {
        Config::getInstance()->lock();
        $overlay = [];
        $ifnames = [];
        foreach ($this->getModel()->gre->iterateItems() as $node) {
            $ifnames[] = (string)$node->greif;
        }
        for ($i = 0; true; ++$i) {
            $greif = sprintf('gre%d', $i);
            if (!in_array($greif, $ifnames)) {
                $overlay['greif'] = $greif;
                break;
            }
        }
        $result = $this->addBase("gre", "gre", $overlay);
        if ($result['result'] != 'failed') {
            $this->stashUpdate($overlay['greif']);
        }
        return $result;
    }

    /**
     * Retrieve gre settings or return defaults for new one
     * @param $uuid item unique id
     * @return array gre content
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase("gre", "gre", $uuid);
    }

    /**
     * Delete gre by uuid
     * @param string $uuid internal id
     * @return array save status
     */
    public function delItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('gre.' . $uuid);
        if ($node != null) {
            $cfg = Config::getInstance()->object();
            foreach ($cfg->interfaces->children() as $key => $value) {
                if ((string)$value->if == (string)$node->greif) {
                    throw new \OPNsense\Base\UserException(
                        sprintf(gettext("Cannot delete gre. Currently in use by [%s] %s"), $key, $value),
                        gettext("gre in use")
                    );
                }
            }
        }
        $result = $this->delBase("gre", $uuid);
        if ($result['result'] != 'failed' && $node != null) {
            $this->stashUpdate((string)$node->greif);
        }
        return $result;
    }

    public function getIfOptionsAction()
    {
        if ($this->request->isGet()) {
            $tmp = [
                'single' => [
                    'label' => gettext("Manual address")
                ]
            ];
            return $tmp + $this->getModel()->gre->Add()->{'local-addr'}->getPredefinedOptions();
        } else {
            return ["status" => "failed"];
        }
    }

    /**
     * reconfigure gres
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun("interface gre configure");
            return ["status" => "ok"];
        } else {
            return ["status" => "failed"];
        }
    }
}
