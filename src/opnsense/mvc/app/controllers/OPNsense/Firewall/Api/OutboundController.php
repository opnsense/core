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

namespace OPNsense\Firewall\Api;

use OPNsense\Core\Config;
use OPNsense\Firewall\Util;
use OPNsense\Firewall\Alias;
use OPNsense\Interfaces\Vip;
use OPNsense\Core\Backend;

class OutboundController extends FilterBaseController
{
    protected static $categorysource = "outbound.rule";

    public function searchRuleAction()
    {
        $category = $this->request->get('category');
        $filter_funct = function ($record) use ($category) {
            return empty($category) || array_intersect(explode(',', $record->categories), $category);
        };
        $result = $this->searchBase("outbound.rule", null, "sequence", $filter_funct);
        foreach ($result['rows'] as &$rows) {
            $rows['source_port'] = ($rows['protocol']!='any' ? strtolower($rows['protocol'])."/ " : "").(!empty((string)$rows['source_port']) ? $rows['source_port'] : "*");
            $rows['destination_port'] = ($rows['protocol']!='any' ? strtolower($rows['protocol'])."/ " : "").(!empty((string)$rows['destination_port']) ? $rows['destination_port'] : "*");
            $rows['target'] = !empty((string)$rows['nonat']) ? "NO NAT" : (empty((string)$rows['target']) ? "Interface address" : $rows['target']);
            $rows['static_port'] = !empty((string)$rows['static_port']) ? "YES" : "NO";
        }
        return $result;
    }

    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "outbound.rule", $uuid, $this->getOverlay());
    }

    public function addRuleAction()
    {
        return $this->addBase("rule", "outbound.rule", $this->getOverlay());
    }

    public function getRuleAction($uuid = null)
    {
        return $this->getBase("rule", "outbound.rule", $uuid);
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("outbound.rule", $uuid);
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("outbound.rule", $uuid, $enabled);
    }

    public function getOverlay()
    {
        $overlay = null;
        $pool_options = $this->request->get('rule')['pool_options'];
        $source_hash_key = $this->request->get('rule')['source_hash_key'];    
        if ($pool_options!='source_hash' && !empty($source_hash_key)) {
            $overlay['source_hash_key'] = '';
        }
        return $overlay;
    }

    public function listTranslationNetworkAction()
    {
        $result = [
            'default' => [
                'label' => gettext("Interface address")
            ],
            'single' => [
                'label' => gettext("Single host or Network")
            ],
            'networks' => [
                'label' => gettext("Networks"),
                'items' => []
            ],
            'virtualips' => [
                'label' => gettext("Virtual IP's"),
                'items' => []
            ],
            'aliases' => [
                'label' => gettext("Aliases"),
                'items' => []
            ]
        ];
        foreach ((Config::getInstance()->object())->interfaces->children() as $ifname => $ifdetail) {
            $descr = htmlspecialchars(!empty($ifdetail->descr) ? $ifdetail->descr : strtoupper($ifname));
            if (!isset($ifdetail->virtual)) {
                $result['networks']['items'][$ifname . "ip"] = $descr . " " . gettext("address");
            }
        }
        asort($result['networks']['items']);
        foreach ((new Vip())->vip->iterateItems() as $vip) {
            if (empty((string)$vip->noexpand)) {
                if ((string)$vip->mode == "proxyarp") {
                    $start = Util::ip2long32(Util::genSubnet((string)$vip->subnet, (string)$vip->subnet_bits));
                    $end = Util::ip2long32(Util::genSubnetMax((string)$vip->subnet, (string)$vip->subnet_bits));
                    $len = $end - $start;
                    $result['virtualips']['items'][(string)$vip->subnet.'/'.(string)$vip->subnet_bits] = "Subnet: ".(string)$vip->subnet."/".(string)$vip->subnet_bits." (".(string)$vip->descr.")";
                    for ($i = 0; $i <= $len; $i++) {
                        $snip = Util::long2ip32($start+$i);
                        $result['virtualips']['items'][$snip] = $snip." (".(string)$vip->descr.")";
                    }
                } else {
                    $result['virtualips']['items'][(string)$vip->subnet] = (string)$vip->subnet." (".(string)$vip->descr.")";
                }
            }
        }
        foreach ((new Alias())->aliases->alias->iterateItems() as $alias) {
            if ((string)$alias->type == 'host') {
                $result['aliases']['items'][(string)$alias->name] = (string)$alias->name;
            }
        }
        asort($result['aliases']['items']);

        return $result;
    }

    public function searchModeAction()
    {
        return array("mode" => (string)$this->getModel()->outbound->mode);
    }

    public function saveModeAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('mode')) {
            $mode = $this->request->getPost('mode');
            $node = $this->getModel()->outbound;
            $node->mode = $mode;
            $result = $this->save(false, true);
            if($result['result'] == 'saved') {
                (new Backend())->configdRun('filter reload');
            }
            return $result;
        }
    }

    public function searchAutoRuleAction()
    {
        $records = json_decode((new Backend())->configdRun('filter outbound_auto_rules'), true);
        return $this->searchRecordsetBase($records);
    }
}
