<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\OpenVPN\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;

class InstanceField extends ArrayField
{
    /**
     * push internal reusable properties as virtuals
     */
    protected function actionPostLoadingEvent()
    {
        foreach ($this->internalChildnodes as $node) {
            $uuid = $node->getAttributes()['uuid'] ?? null;
            if (!$node->getInternalIsVirtual() && $uuid) {
                /* hidden, only visible when called direct (e.g. $node->current_XXX) */
                $files = [
                    'cnfFilename' => "/var/etc/openvpn/instance-{$uuid}.conf",
                    'pidFilename' => "/var/run/ovpn-instance-{$uuid}.pid",
                    'sockFilename' => "/var/etc/openvpn/instance-{$uuid}.sock",
                    'statFilename' => "/var/etc/openvpn/instance-{$uuid}.stat",
                    'csoDirectory' => "/var/etc/openvpn-csc/$node->vpnid",
                    '__devnode' => "{$node->dev_type}{$node->vpnid}",
                    '__devname' => "ovpn" . ((string)$node->role)[0] . "{$node->vpnid}",
                ];
                foreach ($files as $name => $payload) {
                    $new_item = new TextField();
                    $new_item->setInternalIsVirtual();
                    $new_item->setValue($payload);
                    $node->addChildNode($name, $new_item);
                }
            }
        }
        return parent::actionPostLoadingEvent();
    }
}
