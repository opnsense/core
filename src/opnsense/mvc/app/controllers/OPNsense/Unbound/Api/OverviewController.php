<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

class OverviewController extends ApiControllerBase
{
    private $mdl = null;
    private $types = [];
    private $nodes = [];

    public function __construct()
    {
        $this->mdl = new \OPNsense\Unbound\Unbound();
        $this->types = $this->mdl->dnsbl->blocklist->getTemplateNode()->type->getNodeData();
        $this->nodes = $this->mdl->dnsbl->blocklist->getNodes();

    }

    private function getBlocklistDescription($shortcode)
    {
        if (array_key_exists($shortcode, $this->types)) {
            return $this->types[$shortcode]['value'];
        }

        return null;
    }

    private function getFlattenedCustomDomains($key) {
        return array_values(array_unique(array_filter(
            array_merge(
                ...array_values(array_map(fn($v) => array_keys($v[$key] ?? []), $this->nodes))
            ),
            'strlen'
        )));
    }

    public function isEnabledAction()
    {
        $config = Config::getInstance()->object();
        return [
            'enabled' => $this->mdl->getNodes()['general']['stats']
        ];
    }

    public function isBlockListEnabledAction()
    {
        return ['enabled' => (bool)array_filter($this->nodes['dnsbl']['blocklist'], fn($v) => $v['enabled'])];
    }

    public function RollingAction($timeperiod, $clients = '0')
    {
        $interval = filter_var($timeperiod, FILTER_SANITIZE_NUMBER_INT) == 1 ? 60 : 600;
        $type = !empty($clients) ? 'clients' : 'rolling';
        $response = (new Backend())->configdpRun('unbound qstats ' . $type, [$interval, $timeperiod]);
        return json_decode($response, true) ?? [];
    }

    public function totalsAction($maximum)
    {
        $response = (new Backend())->configdpRun('unbound qstats totals', [$maximum]);
        $parsed = json_decode($response, true);
        if (!is_array($parsed)) {
            return [];
        }

        foreach ($parsed['top_blocked'] as $domain => $props) {
            $parsed['top_blocked'][$domain]['blocklist'] ??= $this->getBlocklistDescription($props['blocklist']);
        }

        $parsed['whitelisted_domains'] = $this->getFlattenedCustomDomains('whitelists');
        $parsed['blocklisted_domains'] = $this->getFlattenedCustomDomains('blocklists');

        return $parsed;
    }

    public function searchQueriesAction()
    {
        $client = $this->request->get("client", null);
        $time_start = $this->request->get("timeStart", null);
        $time_end = $this->request->get("timeEnd", null);

        $client = Util::isIpAddress($client) ? $client : null;
        $time_start = is_int($time_start) ? $time_start : null;
        $time_end = is_int($time_end) ? $time_end : null;

        if (isset($client, $time_start, $time_end)) {
            $response = (new Backend())->configdpRun('unbound qstats query', [$client, $time_start, $time_end]);
        } else {
            $response = (new Backend())->configdpRun('unbound qstats details', [1000]);
        }

        $parsed = json_decode($response, true) ?? [];

        foreach ($parsed as $idx => $query) {
            $parsed[$idx]['blocklist'] ??= $this->getBlocklistDescription($query['blocklist']);

            /* Handle front-end color status mapping, start off with OK */
            $parsed[$idx]['status'] = 0;

            if (in_array($query['action'], ["Block", "Drop"])) {
                /* block or drop action */
                $action_map = ["Block" => 3, "Drop" => 4];
                $parsed[$idx]['status'] = $action_map[$query['action']];
            } elseif (in_array($query['source'], ["Local", "Local-data", "Cache"])) {
                /* Pass, but from local, local-data or cache */
                $parsed[$idx]['status'] = 1;
            } elseif ($query['rcode'] != 'NOERROR') {
                /* pass from recursion, any rcode other than NOERROR should be flagged */
                $parsed[$idx]['status'] = 2;
            }
        }

        $response = $this->searchRecordsetBase($parsed);
        $response['whitelisted_domains'] = $this->getFlattenedCustomDomains('whitelists');
        $response['blocklisted_domains'] = $this->getFlattenedCustomDomains('blocklists');

        return $response;
    }
}
