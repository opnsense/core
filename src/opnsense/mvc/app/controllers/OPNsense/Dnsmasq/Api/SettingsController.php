<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Dnsmasq\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'dnsmasq';
    protected static $internalModelClass = '\OPNsense\Dnsmasq\Dnsmasq';
    protected static $internalModelUseSafeDelete = true;

    /**
     * Tags and interface filter function.
     * Interfaces are tags too, in the sense of dnsmasq.
     *
     * @return callable|null
     */
    private function buildFilterFunction(): ?callable
    {
        $filterValues = $this->request->get('tags') ?? [];
        $fieldNames = ['interface', 'set_tag', 'tag'];
        if (empty($filterValues)) {
            return null;
        }

        return function ($record) use ($filterValues, $fieldNames) {
            foreach ($fieldNames as $fieldName) {
                // Skip this field if not present in current record
                if (!isset($record->{$fieldName})) {
                    continue;
                }

                // Match field values against filter list
                foreach (array_map('trim', explode(',', (string)$record->{$fieldName})) as $value) {
                    if (in_array($value, $filterValues, true)) {
                        return true;
                    }
                }
            }

            return false;
        };
    }

    /**
     * @inheritdoc
     */
    public function getAction()
    {
        $data = parent::getAction();
        $data[self::$internalModelName]['dhcp']['this_domain'] = (string)Config::getInstance()->object()->system->domain;

        return $data;
    }

    /* hosts */
    public function searchHostAction()
    {
        return $this->searchBase('hosts', null, null, $this->buildFilterFunction());
    }

    public function getHostAction($uuid = null)
    {
        return $this->getBase('host', 'hosts', $uuid);
    }

    public function setHostAction($uuid)
    {
        return $this->setBase('host', 'hosts', $uuid);
    }

    public function addHostAction()
    {
        return $this->addBase('host', 'hosts');
    }

    public function delHostAction($uuid)
    {
        return $this->delBase('hosts', $uuid);
    }

    public function downloadHostsAction()
    {
        if ($this->request->isGet()) {
            $map = [
                'ip' => 'ip_address',
                'hwaddr' => 'hw_address',
                'host' => 'hostname',
                'descr' => 'description'
            ];

            $result = array_map(function ($item) use ($map) {
                return array_combine(
                    array_map(fn($k) => $map[$k] ?? $k, array_keys($item)),
                    array_values($item)
                );
            }, $this->getModel()->hosts->asRecordSet(false, ['comments']));

            $this->exportCsv($result);
        }
    }

    public function uploadHostsAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('payload')) {
            /* fields used by kea (and isc dhcp export) */
            $map = [
                'ip_address' => 'ip',
                'hw_address' => 'hwaddr',
                'hostname' => 'host',
                'description' => 'descr',
            ];
            return $this->importCsv(
                'hosts',
                $this->request->getPost('payload'),
                ['host', 'domain', 'ip'],
                function (&$record) use ($map) {
                    foreach ($map as $from => $to) {
                        if (isset($record[$from])) {
                            $record[$to] = $record[$from];
                        }
                    }
                }
            );
        } else {
            return ['status' => 'failed'];
        }
    }

    /* domains */
    public function searchDomainAction()
    {
        return $this->searchBase('domainoverrides');
    }

    public function getDomainAction($uuid = null)
    {
        return $this->getBase('domainoverride', 'domainoverrides', $uuid);
    }

    public function setDomainAction($uuid)
    {
        return $this->setBase('domainoverride', 'domainoverrides', $uuid);
    }

    public function addDomainAction()
    {
        return $this->addBase('domainoverride', 'domainoverrides');
    }

    public function delDomainAction($uuid)
    {
        return $this->delBase('domainoverrides', $uuid);
    }

    /* dhcp tags */
    public function searchTagAction()
    {
        $filters = $this->request->get('tags') ?? [];

        $filter_funct = null;
        if (!empty($filters)) {
            $filter_funct = function ($record) use ($filters) {
                $attributes = $record->getAttributes();
                $uuid = $attributes['uuid'] ?? null;
                return in_array($uuid, $filters, true);
            };
        }

        return $this->searchBase('dhcp_tags', null, null, $filter_funct);
    }

    public function getTagAction($uuid = null)
    {
        return $this->getBase('tag', 'dhcp_tags', $uuid);
    }

    public function setTagAction($uuid)
    {
        return $this->setBase('tag', 'dhcp_tags', $uuid);
    }

    public function addTagAction()
    {
        return $this->addBase('tag', 'dhcp_tags');
    }

    public function delTagAction($uuid)
    {
        return $this->delBase('dhcp_tags', $uuid);
    }

    /* dhcp ranges */
    public function searchRangeAction()
    {
        return $this->searchBase('dhcp_ranges', null, null, $this->buildFilterFunction());
    }

    public function getRangeAction($uuid = null)
    {
        return $this->getBase('range', 'dhcp_ranges', $uuid);
    }

    public function setRangeAction($uuid)
    {
        return $this->setBase('range', 'dhcp_ranges', $uuid);
    }

    public function addRangeAction()
    {
        return $this->addBase('range', 'dhcp_ranges');
    }

    public function delRangeAction($uuid)
    {
        return $this->delBase('dhcp_ranges', $uuid);
    }

    /* dhcp options */
    public function searchOptionAction()
    {
        return $this->searchBase('dhcp_options', null, null, $this->buildFilterFunction());
    }

    public function getOptionAction($uuid = null)
    {
        return $this->getBase('option', 'dhcp_options', $uuid);
    }

    private function getOptionOverlay(): array
    {
        $option = $this->request->getPost()['option'];
        $overlay = [];

        if ($option['type'] === 'set') {
            $overlay['set_tag'] = '';
        } elseif ($option['type'] === 'match') {
            $overlay['tag'] = '';
            $overlay['interface'] = '';
            $overlay['force'] = '';
        }

        return $overlay;
    }

    public function setOptionAction($uuid)
    {
        return $this->setBase('option', 'dhcp_options', $uuid, $this->getOptionOverlay());
    }

    public function addOptionAction()
    {
        return $this->addBase('option', 'dhcp_options', $this->getOptionOverlay());
    }

    public function delOptionAction($uuid)
    {
        return $this->delBase('dhcp_options', $uuid);
    }

    /* dhcp boot options */
    public function searchBootAction()
    {
        return $this->searchBase('dhcp_boot', null, null, $this->buildFilterFunction());
    }

    public function getBootAction($uuid = null)
    {
        return $this->getBase('boot', 'dhcp_boot', $uuid);
    }

    public function setBootAction($uuid)
    {
        return $this->setBase('boot', 'dhcp_boot', $uuid);
    }

    public function addBootAction()
    {
        return $this->addBase('boot', 'dhcp_boot');
    }

    public function delBootAction($uuid)
    {
        return $this->delBase('dhcp_boot', $uuid);
    }

    /**
     * Return selectpicker options for interfaces and tags
     */
    public function getTagListAction()
    {
        $result = [
            'tags' => [
                'label' => gettext('Tags'),
                'icon'  => 'fa fa-tag text-primary',
                'items' => []
            ],
            'interfaces' => [
                'label' => gettext('Interfaces'),
                'icon'  => 'fa fa-ethernet text-info',
                'items' => []
            ]
        ];

        // Interfaces
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            if ((string)$intf->type === 'group') {
                continue;
            }

            $result['interfaces']['items'][] = [
                'value' => $key,
                'label' => empty($intf->descr) ? strtoupper($key) : (string)$intf->descr
            ];
        }

        // Tags
        foreach ($this->getModel()->dhcp_tags->iterateItems() as $uuid => $tag) {
            $result['tags']['items'][] = [
                'value' => $uuid,
                'label' => (string)$tag->tag
            ];
        }

        foreach (array_keys($result) as $key) {
            usort($result[$key]['items'], fn($a, $b) => strcasecmp($a['label'], $b['label']));
        }

        // Assemble result
        return $result;
    }
}
