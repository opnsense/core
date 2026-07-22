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

namespace OPNsense\Firewall\Menu;

use OPNsense\Base\Menu\MenuContainer;
use OPNsense\Core\Config;

class Menu extends MenuContainer
{
    public function collect()
    {
        $config = Config::getInstance()->object();
        $iftargets = [];
        if ($config->interfaces->count() > 0) {
            foreach ($config->interfaces->children() as $key => $node) {
                // "Firewall: Rules" menu tab...
                if (isset($node->enable) && $node->if != 'lo0') {
                    $iftargets[$key] = !empty($node->descr) ? (string)$node->descr : strtoupper($key);
                }
            }
        }
        natcasesort($iftargets);

        // add interfaces to "Firewall: Rules" menu tab...
        $has_legacy_fw = !empty($config->filter->rule) && !empty($config->filter->rule->count());
        $has_legacy_outbound_nat = !empty($config->nat->outbound->rule) &&
            !empty($config->nat->outbound->rule->count());
        if ($has_legacy_fw || $has_legacy_outbound_nat) {
            $this->appendItem('Firewall', 'Migration', [
                'url' => '/ui/firewall/migration',
                'fixedname' => gettext('Migration assistant'),
                'cssClass' => 'fa fa-gears fa-fw',
                'order' => 0,
            ]);
        }

        /* do not hide the legacy firewall rules GUI when the plugin is installed */
        $has_legacy_fw = file_exists('/usr/local/www/firewall_rules.php');
        if ($has_legacy_fw) {
            $iftargets = array_merge(['FloatingRules' => gettext('Floating')], $iftargets);
            $this->appendItem('Firewall', 'Rules', [
                'fixedname' => gettext('Rules [legacy]'),
                'cssClass' => 'fa fa-check fa-fw',
            ]);
        }

        $ordid = 1;
        foreach ($iftargets as $key => $descr) {
            if (!$has_legacy_fw) {
                /* only search */
                $this->appendItem('Firewall.Rule', $key, [
                    'url' => '/ui/firewall/filter/#interface=' . $key,
                    'visibility' => 'menu', /* XXX this isn't a feature but it works */
                    'fixedname' => $descr,
                    'order' => $ordid++,
                ]);
                continue;
            }
            /* legacy rules */
            $this->appendItem('Firewall.Rules', $key, [
                'url' => '/firewall_rules.php?if=' . $key,
                'fixedname' => $descr,
                'order' => $ordid++,
            ]);
            $this->appendItem('Firewall.Rules.' . $key, 'Select' . $key, [
                'url' => '/firewall_rules.php?if=' . $key . '&*',
                'visibility' => 'hidden',
            ]);
            if ($key == 'FloatingRules') {
                $this->appendItem('Firewall.Rules.' . $key, 'Top' . $key, [
                    'url' => '/firewall_rules.php',
                    'visibility' => 'hidden',
                ]);
            }
            $this->appendItem('Firewall.Rules.' . $key, 'Add' . $key, [
                'url' => '/firewall_rules_edit.php?if=' . $key,
                'visibility' => 'hidden',
            ]);
            $this->appendItem('Firewall.Rules.' . $key, 'Edit' . $key, [
                'url' => '/firewall_rules_edit.php?if=' . $key . '&*',
                'visibility' => 'hidden',
            ]);
        }

        /* XXX do not hide the legacy outbound NAT rules GUI when the plugin is installed */
        if ($has_legacy_outbound_nat && file_exists('/usr/local/www/firewall_nat_out.php')) {
            $this->appendItem('Firewall.NAT', 'Outbound', [
                'url' => '/firewall_nat_out.php',
                'fixedname' => gettext('Outbound'),
                'order' => 300,
            ]);
        }
    }
}
