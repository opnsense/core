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
class GifSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'gif';
    protected static $internalModelClass = 'OPNsense\Interfaces\Gif';


    /**
     * write updated or removed gif to temp
     */
    private function stashUpdate($gifif)
    {
        file_put_contents("/tmp/.gif.todo", "{$gifif}\n", FILE_APPEND | LOCK_EX);
        chmod("/tmp/.gif.todo", 0750);
    }

    /**
     * search gifs
     * @return array search results
     */
    public function searchItemAction()
    {
        return $this->searchBase("gif", null, "descr");
    }

    /**
     * Update gif with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     */
    public function setItemAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('gif.' . $uuid);
        $overlay = null;
        if (!empty($node)) {
            // not allowed to change gif interface name
            $overlay['gifif'] = (string)$node->gifif;
        }

        $result = $this->setBase("gif", "gif", $uuid, $overlay);
        if ($result['result'] != 'failed') {
            $this->stashUpdate($overlay !== null ? $overlay['gifif'] : $this->request->get('gif')['gifif']);
        }
        return $result;
    }

    /**
     * Add new gif and set with attributes from post
     * @return array save result + validation output
     */
    public function addItemAction()
    {
        Config::getInstance()->lock();
        $overlay = [];
        $ifnames = [];
        foreach ($this->getModel()->gif->iterateItems() as $node) {
            $ifnames[] = (string)$node->gifif;
        }
        for ($i = 0; true; ++$i) {
            $gifif = sprintf('gif%d', $i);
            if (!in_array($gifif, $ifnames)) {
                $overlay['gifif'] = $gifif;
                break;
            }
        }
        $result = $this->addBase("gif", "gif", $overlay);
        if ($result['result'] != 'failed') {
            $this->stashUpdate($overlay['gifif']);
        }
        return $result;
    }

    /**
     * Retrieve gif settings or return defaults for new one
     * @param $uuid item unique id
     * @return array gif content
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase("gif", "gif", $uuid);
    }

    /**
     * Delete gif by uuid
     * @param string $uuid internal id
     * @return array save status
     */
    public function delItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('gif.' . $uuid);
        if ($node != null) {
            $cfg = Config::getInstance()->object();
            foreach ($cfg->interfaces->children() as $key => $value) {
                if ((string)$value->if == (string)$node->gifif) {
                    throw new \OPNsense\Base\UserException(
                        sprintf(gettext("Cannot delete gif. Currently in use by [%s] %s"), $key, $value),
                        gettext("gif in use")
                    );
                }
            }
        }
        $result = $this->delBase("gif", $uuid);
        if ($result['result'] != 'failed' && $node != null) {
            $this->stashUpdate((string)$node->gifif);
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
            return $tmp + $this->getModel()->gif->Add()->{'local-addr'}->getPredefinedOptions();
        } else {
            return ["status" => "failed"];
        }
    }

    /**
     * reconfigure gifs
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun("interface gif configure");
            return ["status" => "ok"];
        } else {
            return ["status" => "failed"];
        }
    }
}
