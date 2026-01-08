<?php

/*
 * Copyright (C) 2025-2026 Deciso B.V.
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

namespace OPNsense\Radvd\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Interfaces\Vip;

class M1_0_0 extends BaseModelMigration
{
    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();
        $legacy = $config->dhcpdv6->children();
        $vips = new Vip();

        if (is_null($legacy)) {
            /* nothing to migrate */
            return;
        }

        foreach ($legacy as $key => $node) {
            $entry = $model->entries->add();
            $content = ['interface' => $key];
            if (!empty($node->ramode)) {
                $mode = (string)$node->ramode;
                // Migrate ramode disabled option to its own enabled key
                if ($mode == 'disabled') {
                    $content['enabled'] = '0';
                    // There is no mode here, will instead become current model default
                } else {
                    $content['enabled'] = '1';
                    $content['mode'] = $mode;
                }
            }
            if (!empty($node->rapriority)) {
                $content['priority'] = (string)$node->rapriority;
            }
            if (!empty($node->rainterface)) {
                /*
                 * The migration idea here is to find it and embed the address
                 * verbatim in order to simplify the code and get rid of the
                 * legacy <interface>_vip<vhid> notation.  If the lookup fails
                 * an empty string is returned and thus no address will be
                 * referenced anymore here which avoids migration errors.
                 */
                $content['AdvRASrcAddress'] = $vips->findSubnet((string)$node->rainterface, $key, '6');
            }
            if (!empty($node->raroutes)) {
                $content['routes'] = (string)$node->raroutes;
            }
            if (!empty($node->ramininterval)) {
                $content['MinRtrAdvInterval'] = (string)$node->ramininterval;
            }
            if (!empty($node->ramaxinterval)) {
                $content['MaxRtrAdvInterval'] = (string)$node->ramaxinterval;
            }
            if (!empty($node->radisablerdnss)) {
                /* inverted with '1' as the default */
                $content['dns'] = '0';
            }
            if (!empty($node->rasamednsasdhcp6)) {
                if (!empty($node->dnsserver[0])) {
                    /* handle listget() magic */
                    $content['RDNSS'] = implode(',', (array)$node->dnsserver);
                }
                if (!empty($node->domainsearchlist)) {
                    $content['DNSSL'] = implode(',', preg_split('/[ ;]+/', (string)$node->domainsearchlist));
                }
            } else {
                if (!empty($node->radnsserver[0])) {
                    /* handle listget() magic */
                    $content['RDNSS'] = implode(',', (array)$node->radnsserver);
                }
                if (!empty($node->radomainsearchlist)) {
                    $content['DNSSL'] = implode(',', preg_split('/[ ;]+/', (string)$node->radomainsearchlist));
                }
            }
            if (!empty($node->AdvDeprecatePrefix)) {
                $content['DeprecatePrefix'] = (string)$node->AdvDeprecatePrefix;
            }
            if (!empty($node->AdvRemoveRoute) && (string)$node->AdvRemoveRoute == 'off') {
                $content['RemoveRoute'] = (string)$node->AdvRemoveRoute;
            }
            foreach (
                [
                'AdvDNSSLLifetime',
                'AdvDefaultLifetime',
                'AdvLinkMTU',
                'AdvPreferredLifetime',
                'AdvRDNSSLifetime',
                'AdvRouteLifetime',
                'AdvValidLifetime',
                ] as $adv
            ) {
                if (!empty($node->$adv)) {
                    $content[$adv] = (string)$node->$adv;
                }
            }
            if (!empty($node->ranodefault)) {
                /* must be after AdvDefaultLifetime as a legacy override */
                $content['AdvDefaultLifetime'] = '0';
            }
            $entry->setNodes($content);

            /* normalize option fields now so defaults can apply */
            $entry->DeprecatePrefix->normalizeValue();
            $entry->RemoveRoute->normalizeValue();
            $entry->mode->normalizeValue();
            $entry->AdvDefaultPreference->normalizeValue();

            /* yet if interface is empty we must remove the entry */
            $entry->interface->normalizeValue();
            if ($entry->interface->isEmpty()) {
                $model->entries->del($entry->getAttribute('uuid'));
            }
        }

        parent::run($model);
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        $legacy = $config->dhcpdv6->children();

        if (is_null($legacy)) {
            /* nothing to delete */
            return;
        }

        $remove = [
            'AdvDNSSLLifetime',
            'AdvDefaultLifetime',
            'AdvDeprecatePrefix',
            'AdvLinkMTU',
            'AdvPreferredLifetime',
            'AdvRDNSSLifetime',
            'AdvRemoveRoute',
            'AdvRouteLifetime',
            'AdvValidLifetime',
            'radisablerdnss',
            'radnsserver',
            'radomainsearchlist',
            'rainterface',
            'ramaxinterval',
            'ramininterval',
            'ramode',
            'ranodefault',
            'rapriority',
            'raroutes',
            'rasamednsasdhcp6',
        ];

        foreach ($legacy as $key => $node) {
            foreach ($remove as $_remove) {
                unset($node->$_remove);
            }
        }
    }
}
