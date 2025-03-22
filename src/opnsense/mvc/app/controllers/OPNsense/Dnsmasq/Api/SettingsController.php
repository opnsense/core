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

    /**
     * Tags and interface filter function.
     * Interfaces are tags too, in the sense of dnsmasq.
     *
     * @param array $filterValues List of values to filter against (e.g. UUIDs, interface names).
     * @param array $fieldNames   List of field names to extract values from in each record.
     *
     * @return callable|null
     */
    private function buildFilterFunction(array $filterValues, array $fieldNames): ?callable
    {
        if (empty($filterValues)) {
            return null;
        }

        return function ($record) use ($filterValues, $fieldNames) {
            foreach ($fieldNames as $fieldName) {
                // Skip this field if not present in current record
                if (!isset($record->{$fieldName})) {
                    continue;
                }

                // Some fields allow multi selection
                $fieldValues = array_map('trim', explode(',', (string)$record->{$fieldName}));

                // Match field values against filter list
                foreach ($fieldValues as $value) {
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
        $filters = $this->request->get('tags') ?? [];
        $filter_funct = $this->buildFilterFunction($filters, ['set_tag']);
        return $this->searchBase('hosts', null, null, $filter_funct);
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
            $this->exportCsv($this->getModel()->hosts->asRecordSet(false, ['comments']));
        }
    }

    public function uploadHostsAction()
    {
        if ($this->request->isPost() && $this->request->hasPost('payload')) {
            /* fields used by kea */
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
        $filters = $this->request->get('tags') ?? [];
        $filter_funct = $this->buildFilterFunction($filters, ['interface', 'set_tag']);
        return $this->searchBase('dhcp_ranges', null, null, $filter_funct);
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
        $filters = $this->request->get('tags') ?? [];
        $filter_funct = $this->buildFilterFunction($filters, ['interface', 'tag']);
        return $this->searchBase('dhcp_options', null, null, $filter_funct);
    }

    public function getOptionAction($uuid = null)
    {
        return $this->getBase('option', 'dhcp_options', $uuid);
    }

    public function setOptionAction($uuid)
    {
        return $this->setBase('option', 'dhcp_options', $uuid);
    }

    public function addOptionAction()
    {
        return $this->addBase('option', 'dhcp_options');
    }

    public function delOptionAction($uuid)
    {
        return $this->delBase('dhcp_options', $uuid);
    }

    /* dhcp match options */
    public function searchMatchAction()
    {
        $filters = $this->request->get('tags') ?? [];
        $filter_funct = $this->buildFilterFunction($filters, ['set_tag']);
        return $this->searchBase('dhcp_options_match', null, null, $filter_funct);
    }

    public function getMatchAction($uuid = null)
    {
        return $this->getBase('match', 'dhcp_options_match', $uuid);
    }

    public function setMatchAction($uuid)
    {
        return $this->setBase('match', 'dhcp_options_match', $uuid);
    }

    public function addMatchAction()
    {
        return $this->addBase('match', 'dhcp_options_match');
    }

    public function delMatchAction($uuid)
    {
        return $this->delBase('dhcp_options_match', $uuid);
    }

    /* dhcp boot options */
    public function searchBootAction()
    {
        $filters = $this->request->get('tags') ?? [];
        $filter_funct = $this->buildFilterFunction($filters, ['tag']);
        return $this->searchBase('dhcp_boot', null, null, $filter_funct);
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
        $result = [];

        // Interfaces
        $interfaces = [];
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $intf) {
            if ((string)$intf->type === 'group') {
                continue;
            }

            $interfaces[] = [
                'value' => $key,
                'label' => empty($intf->descr) ? strtoupper($key) : (string)$intf->descr
            ];
        }
        usort($interfaces, fn($a, $b) => strcasecmp($a['label'], $b['label']));

        // Tags
        $tags = [];
        foreach ($this->getModel()->dhcp_tags->iterateItems() as $tag) {
            $uuid = $tag->getAttributes()['uuid'] ?? null;
            $name = trim((string)$tag->tag);
            if (!empty($uuid) && !empty($name)) {
                $tags[] = [
                    'value' => $uuid,
                    'label' => $name
                ];
            }
        }
        usort($tags, fn($a, $b) => strcasecmp($a['label'], $b['label']));

        // Assemble result
        return [
            'tags' => [
                'label' => gettext('Tags'),
                'icon'  => 'fa fa-tag text-primary',
                'items' => $tags
            ],
            'interfaces' => [
                'label' => gettext('Interfaces'),
                'icon'  => 'fa fa-ethernet text-info',
                'items' => $interfaces
            ]
        ];
    }

}
