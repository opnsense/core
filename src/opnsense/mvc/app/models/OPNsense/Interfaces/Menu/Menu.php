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

namespace OPNsense\Interfaces\Menu;

use OPNsense\Base\Menu\MenuContainer;
use OPNsense\Core\Config;

class Menu extends MenuContainer
{
    public function collect()
    {
        $config = Config::getInstance()->object();
        $iftargets = ['if' => [], 'gr' => [], 'wl' => []];
        $ifgroups = [];
        $ifgroups_seq = [];

        if ($config->interfaces->count() > 0) {
            if ($config->ifgroups->count() > 0) {
                foreach ($config->ifgroups->children() as $key => $node) {
                    if (empty($node->members) || !empty($node->nogroup)) {
                        continue;
                    }
                    if (!empty((string)$node->sequence)) {
                        $ifgroups_seq[(string)$node->ifname] = (int)((string)$node->sequence);
                    }
                    /* we need both if and gr reference */
                    $iftargets['if'][(string)$node->ifname] = (string)$node->ifname;
                    $iftargets['gr'][(string)$node->ifname] = (string)$node->ifname;
                    foreach (preg_split('/[ |,]+/', (string)$node->members) as $member) {
                        if (!array_key_exists($member, $ifgroups)) {
                            $ifgroups[$member] = [];
                        }
                        array_push($ifgroups[$member], (string)$node->ifname);
                    }
                }
            }
            foreach ($config->interfaces->children() as $key => $node) {
                // Interfaces tab
                if (empty($node->virtual)) {
                    $iftargets['if'][$key] = !empty($node->descr) ? (string)$node->descr : strtoupper($key);
                }
                // Wireless status tab
                if (isset($node->wireless)) {
                    $iftargets['wl'][$key] = !empty($node->descr) ? (string)$node->descr : strtoupper($key);
                }
            }
        }
        foreach (array_keys($iftargets) as $tab) {
            natcasesort($iftargets[$tab]);
        }

        // add groups and interfaces to "Interfaces" menu tab...
        $ordid = count($ifgroups_seq) > 0 ? max($ifgroups_seq) : 0;
        foreach ($iftargets['if'] as $key => $descr) {
            if (array_key_exists($key, $iftargets['gr'])) {
                $this->appendItem('Interfaces', $key, [
                    'fixedname' => '[' . $descr . ']',
                    'cssclass' => 'fa fa-sitemap',
                    'order' => isset($ifgroups_seq[$key]) ? $ifgroups_seq[$key] : $ordid++,
                ]);
            } elseif (!array_key_exists($key, $ifgroups)) {
                $this->appendItem('Interfaces', $key, [
                    'url' => '/interfaces.php?if=' . $key,
                    'fixedname' => '[' . $descr . ']',
                    'cssclass' => 'fa fa-sitemap',
                    'order' => $ordid++,
                ]);
            }
        }

        foreach ($ifgroups as $key => $groupings) {
            $first = true;
            foreach ($groupings as $grouping) {
                if (empty($iftargets['if'][$key])) {
                    // referential integrity between ifgroups and interfaces isn't assured, skip when interface doesn't exist
                    continue;
                }
                $this->appendItem('Interfaces.' . $grouping, $key, [
                    'url' => '/interfaces.php?if=' . $key . '&group=' . $grouping,
                    'fixedname' => '[' . $iftargets['if'][$key] . ']',
                    'order' => array_search($key, array_keys($iftargets['if']))
                ]);
                if ($first) {
                    $this->appendItem('Interfaces.' . $grouping . '.' . $key, 'Origin', [
                        'url' => '/interfaces.php?if=' . $key,
                        'visibility' => 'hidden',
                    ]);
                    $first = false;
                }
            }
        }

        $ordid = 100;
        foreach ($iftargets['wl'] as $key => $descr) {
            $this->appendItem('Interfaces.Wireless', $key, [
                'fixedname' => sprintf(gettext('%s Status'), $descr),
                'url' => '/status_wireless.php?if=' . $key,
                'order' => $ordid++,
            ]);
        }

    }
}