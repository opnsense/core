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

namespace OPNsense\Unbound\FieldTypes;

use OPNsense\Base\FieldTypes\HostnameField;

class AliasReflector extends HostnameField
{
    private $postLoadValue = [];

    protected function defaultValidationMessage()
    {
        return gettext('[%s] is not a valid host/domain name.');
    }

    private function splitHostDomain(string $s): array
    {
        $s = rtrim(trim($s), '.');
        if ($s === '') {
            return ['host' => '', 'domain' => ''];
        }

        $pos = strpos($s, '.');

        return $pos === false
            ? ['host' => $s, 'domain' => '']
            : ['host' => substr($s, 0, $pos), 'domain' => substr($s, $pos + 1)];
    }

    public function actionPostLoadingEvent()
    {
        $uuid = $this->getParentNode()->getAttribute('uuid') ?? '';
        $this->postLoadValue = array_map(function ($node) {
            $hostname = $node->hostname->getValue();
            $domain = $node->domain->getValue();
            $concat = !empty($hostname) ? $hostname . '.' : '';
            $concat .= !empty($domain) ? $domain : $this->getParentNode()->domain->getValue();

            return $concat;
        }, $this->getParentModel()->getHostAliases($uuid));
        $this->setValues($this->postLoadValue);
    }

    public function setValue($value)
    {
        if ($value === implode($this->internalFieldSeparator, $this->postLoadValue)) {
            return parent::setValue($value);
        }

        $a = array_filter(array_map('trim', explode($this->internalFieldSeparator, $value)));
        $b = array_filter(array_map('trim', $this->postLoadValue));
        $toDelete = array_values(array_diff($b, $a));
        $toAdd = array_values(array_diff($a, $b));

        if (empty($toDelete) && empty($toAdd)) {
            return parent::setValue($value);
        }

        $hostNode = $this->getParentNode();
        $mdl = $this->getParentModel();
        $hostUUID = $hostNode->getAttribute('uuid') ?? '';

        foreach ($toDelete as $delAlias) {
            $split = $this->splitHostDomain($delAlias);
            if ($split['domain'] === $hostNode->domain->getValue()) {
                $split['domain'] = '';
            }
            foreach ($mdl->getHostAliases($hostUUID) as $aliasNode) {
                if ($aliasNode->hostname->getValue() === $split['host'] && $aliasNode->domain->getValue() === $split['domain']) {
                    $node = $mdl->getNodeByReference('aliases.alias');
                    if ($node != null) {
                        $node->del($aliasNode->getAttribute('uuid'));
                    }
                }
            }
        }

        foreach ($toAdd as $addAlias) {
            $split = $this->splitHostDomain($addAlias);
            if ($split['domain'] === $hostNode->domain->getValue()) {
                $split['domain'] = '';
            }
            $node = $mdl->aliases->alias->Add();
            $node->setNodes([
                'enabled' => 1,
                'host' => $hostUUID,
                'hostname' => $split['host'],
                'domain' => $split['domain'],
                'description' => ''
            ]);
        }

        return parent::setValue($value);
    }
}
