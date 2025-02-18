<?php

/**
 *    Copyright (C) 2023 Deciso B.V.
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
 *
 */

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * @package OPNsense\Interfaces
 */
class LaggSettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'lagg';
    protected static $internalModelClass = 'OPNsense\Interfaces\Lagg';


    /**
     * write updated or removed laggif to temp
     */
    private function stashUpdate($laggif)
    {
        file_put_contents("/tmp/.lagg.todo", "{$laggif}\n", FILE_APPEND | LOCK_EX);
        chmod("/tmp/.lagg.todo", 0750);
    }

    /**
     * search laggs
     * @return array search results
     */
    public function searchItemAction()
    {
        return $this->searchBase("lagg", null, "descr");
    }

    /**
     * Update lagg with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     */
    public function setItemAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('lagg.' . $uuid);
        $overlay = null;
        if (!empty($node)) {
            // not allowed to change lagg interface name
            $overlay['laggif'] = (string)$node->laggif;
        }

        $result = $this->setBase("lagg", "lagg", $uuid, $overlay);
        if ($result['result'] != 'failed') {
            $this->stashUpdate($overlay !== null ? $overlay['laggif'] : $this->request->get('lagg')['laggif']);
        }
        return $result;
    }

    /**
     * Add new lagg and set with attributes from post
     * @return array save result + validation output
     */
    public function addItemAction()
    {
        Config::getInstance()->lock();
        $overlay = [];
        $ifnames = [];
        foreach ($this->getModel()->lagg->iterateItems() as $node) {
            $ifnames[] = (string)$node->laggif;
        }
        for ($i = 0; true; ++$i) {
            $laggif = sprintf('lagg%d', $i);
            if (!in_array($laggif, $ifnames)) {
                $overlay['laggif'] = $laggif;
                break;
            }
        }
        $result = $this->addBase("lagg", "lagg", $overlay);
        if ($result['result'] != 'failed') {
            $this->stashUpdate($overlay['laggif']);
        }
        return $result;
    }

    /**
     * Retrieve lagg settings or return defaults for new one
     * @param $uuid item unique id
     * @return array lagg content
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase("lagg", "lagg", $uuid);
    }

    /**
     * Delete lagg by uuid
     * @param string $uuid internal id
     * @return array save status
     */
    public function delItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('lagg.' . $uuid);
        $laggif = $node != null ? (string)$node->laggif : null;
        $uses = [];
        $cfg = Config::getInstance()->object();
        foreach ($cfg->interfaces->children() as $key => $value) {
            if ((string)$value->if == $laggif) {
                $uses['interfaces.' . $key] = !empty($value->descr) ? (string)$value->descr : $key;
            }
        }
        if (isset($cfg->vlans) && isset($cfg->vlans->vlan)) {
            foreach ($cfg->vlans->children() as $vlan) {
                if ((string)$vlan->if == $laggif) {
                    $uses[(string)$vlan->vlanif] = !empty($vlan->descr) ? (string)$vlan->descr : $key;
                }
            }
        }
        if (!empty($uses)) {
            $message = "";
            foreach ($uses as $key => $value) {
                $message .= htmlspecialchars(sprintf("\n[%s] %s", $key, $value), ENT_NOQUOTES | ENT_HTML401);
            }
            $message = sprintf(gettext("Cannot delete lagg. Currently in use by %s"), $message);
            throw new \OPNsense\Base\UserException($message, gettext("Lagg in use"));
        }
        $result = $this->delBase("lagg", $uuid);
        if ($result['result'] != 'failed' && !empty($laggif)) {
            $this->stashUpdate($laggif);
        }
        return $result;
    }

    /**
     * reconfigure laggs
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun("interface lagg configure");
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }
}
