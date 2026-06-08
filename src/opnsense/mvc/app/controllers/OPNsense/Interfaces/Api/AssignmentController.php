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

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class AssignmentController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'interface';
    protected static $internalModelClass = 'OPNsense\Interfaces\NetworkInterface';

    public function searchItemAction()
    {
        return $this->searchBase("interface");
    }

    public function setItemAction($ifname)
    {
        return $this->setBase("interface", "interface", $ifname);
    }

    public function addItemAction()
    {
        return $this->addBase("interface", "interface");
    }

    public function getItemAction($ifname = null)
    {
        return $this->getBase("interface", "interface", $ifname);
    }

    public function delItemAction($ifnames)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        Config::getInstance()->lock();
        $paths = [
            '/*/ifgroups/ifgroupentry' => gettext("The interface is part of a group. Please remove it from the group to continue"),
            '/*/bridges/bridged' => gettext("The interface is part of a bridge. Please remove it from the bridge to continue"),
            '/*/gres/gre' => gettext("The interface is part of a gre tunnel. Please delete the tunnel to continue"),
            '/*/gifs/gif' => gettext("The interface is part of a gif tunnel. Please delete the tunnel to continue")
        ];
        foreach ($paths as $path => $message) {
            foreach (explode(',', $ifnames) as $ifname) {
                foreach (Config::getInstance()->object()->xpath($path) as $node) {
                    $members = [];
                    foreach (['members', 'if'] as $tag) {
                        foreach (array_filter(explode(',', (string)$node->$tag)) as $member) {
                            $members[] = explode('_vip', $member)[0];
                        }
                    }
                    if (in_array($ifname, $members)) {
                        throw new UserException($message, sprintf(gettext('[%s] in use'), $ifname));
                    }
                }
                if (
                    !empty(Config::getInstance()->object()->interfaces->$ifname) &&
                    !empty(Config::getInstance()->object()->interfaces->$ifname->lock)
                ) {
                    throw new UserException(
                        gettext("Interface locked, unset lock first before removal"),
                        gettext('locked')
                    );
                }
            }
        }
        return $this->delBase("interface", $ifnames);
    }

    private function cleanRules($ifname)
    {
        $sources = [
            ['filter', 'rule'],
            ['nat', 'rule'],
            ['nat', 'onetoone'],
            ['nat', 'outbound', 'rule'],
            ['OPNsense', 'Firewall', 'Filter', 'rules', 'rule'],
            ['OPNsense', 'Firewall', 'Filter', 'snatrules', 'rule'],
            ['OPNsense', 'Firewall', 'Filter', 'npt', 'rule']
        ];

        foreach ($sources as $aliasref) {
            $cfgsection = Config::getInstance()->object();
            foreach ($aliasref as $cfgName) {
                if ($cfgsection != null) {
                    $cfgsection = $cfgsection->$cfgName;
                }
            }
            if ($cfgsection != null) {
                $to_delete = [];
                foreach ($cfgsection as $idx => $node) {
                    $ifnames = explode(',', $node->interface);
                    if (in_array($ifname, $ifnames)) {
                        $new_list = array_diff($ifnames, [$ifname]);
                        if (empty($new_list)) {
                            $to_delete[] = $node;
                        } else {
                            $node->interface = implode(',', $new_list);
                        }
                    }
                }
                foreach ($to_delete as $node) {
                    $dom = dom_import_simplexml($node);
                    $dom->parentNode->removeChild($dom);
                }
            }
        }
    }

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            /***
             * Interface apply and final configuration update are separated steps to avoid
             * reloading the filter with the previous interface configuation
             **/
            if (trim($backend->configdRun("interface apply")) == 'OK') {
                Config::getInstance()->lock();
                foreach ($this->getModel()->get_if_todo() as $key => $props) {
                    if (!isset(Config::getInstance()->object()->interfaces->$key)) {
                        continue;
                    }
                    if ($props['pending_action'] == 'delete') {
                        $this->cleanRules($key); /* remove associated rules */
                        unset(Config::getInstance()->object()->interfaces->$key);
                    } elseif ($props['pending_action'] == 'relink') {
                        Config::getInstance()->object()->interfaces->$key->if = $props['pending_if'];
                    }
                }
                Config::getInstance()->save();
                $this->getModel()->flush_todo();
                /* exec filter reload after doing accounting */
                $backend->configdRun('filter reload skip_alias', true);
                return ["status" => "ok"];
            }
        }
        return ["status" => "failed"];
    }
}
