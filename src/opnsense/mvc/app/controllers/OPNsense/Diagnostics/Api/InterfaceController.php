<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Interfaces\Vip;

/**
 * Class InterfaceController
 * @package OPNsense\Diagnostics\Api
 */
class InterfaceController extends ApiControllerBase
{
    /**
     * collect interface names
     * @return array interface mapping (raw interface to description)
     */
    private function getInterfaceNames()
    {
        $intfmap = array();
        $config = Config::getInstance()->object();
        if ($config->interfaces->count() > 0) {
            foreach ($config->interfaces->children() as $key => $node) {
                $intfmap[(string)$node->if] = !empty((string)$node->descr) ? (string)$node->descr : $key;
            }
        }
        return $intfmap;
    }

    /**
     * retrieve interface name mapping
     * @return array interface mapping (raw interface to description)
     */
    public function getInterfaceNamesAction()
    {
        return $this->getInterfaceNames();
    }

    /**
     * retrieve system arp table contents
     * @return array
     */
    public function getArpAction()
    {
        $backend = new Backend();
        if ($this->request->get('resolve') == 'yes') {
            $response = $backend->configdRun('interface list arp -r json');
        } else {
            $response = $backend->configdRun('interface list arp json');
        }

        $arptable = json_decode($response, true);

        $intfmap = $this->getInterfaceNames();
        // merge arp output with interface names
        if (is_array($arptable)) {
            foreach ($arptable as &$arpentry) {
                if (array_key_exists($arpentry['intf'], $intfmap)) {
                    $arpentry['intf_description'] = $intfmap[$arpentry['intf']];
                } else {
                    $arpentry['intf_description'] = "";
                }
            }
        }

        return $arptable;
    }

    /**
     * search wrapper around getArpAction
     * @return array
     */
    public function searchArpAction()
    {
        return $this->searchRecordsetBase($this->getArpAction());
    }

    /**
     * retrieve system arp table contents
     * @return array
     */
    public function flushArpAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('interface flush arp');
            return $response;
        } else {
            return array("message" => "error");
        }
    }

    /**
     * retrieve system ndp table contents
     * @return array
     */
    public function getNdpAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('interface list ndp json');
        $ndptable = json_decode($response, true);

        $intfmap = $this->getInterfaceNames();
        // merge ndp output with interface names
        if (is_array($ndptable)) {
            foreach ($ndptable as &$ndpentry) {
                if (array_key_exists($ndpentry['intf'], $intfmap)) {
                    $ndpentry['intf_description'] = $intfmap[$ndpentry['intf']];
                } else {
                    $ndpentry['intf_description'] = "";
                }
            }
        }

        return $ndptable;
    }

    /**
     * search wrapper around getNdpAction
     * @return array
     */
    public function searchNdpAction()
    {
        return $this->searchRecordsetBase($this->getNdpAction());
    }

    /**
     * retrieve system routing table
     * @return mixed
     */
    public function getRoutesAction()
    {
        $backend = new Backend();
        if (empty($this->request->get('resolve'))) {
            $response = $backend->configdRun('interface routes list -n json');
        } else {
            $response = $backend->configdRun('interface routes list json');
        }

        $routingtable = json_decode($response, true);
        if (is_array($routingtable)) {
            $intfmap = $this->getInterfaceNames();
            foreach ($routingtable as &$routingentry) {
                if (array_key_exists($routingentry['netif'], $intfmap)) {
                    $routingentry['intf_description'] = $intfmap[$routingentry['netif']];
                } else {
                    $routingentry['intf_description'] = "";
                }
            }
        }
        return $routingtable;
    }

    /**
     * drop route
     * @return mixed
     */
    public function delRouteAction()
    {
        if (
            $this->request->isPost() && $this->request->hasPost("destination")
              && $this->request->hasPost("gateway")
        ) {
            $backend = new Backend();
            $dest = $this->request->getPost("destination", "striptags", null);
            $gw = $this->request->getPost("gateway", "striptags", null);
            $response = trim($backend->configdpRun("interface route del", array($dest, $gw)));
            return array("message" => $response);
        } else {
            return array("message" => "error");
        }
    }

    /**
     * retrieve system-wide statistics for each network protocol
     * @return mixed
     */
    public function getProtocolStatisticsAction()
    {
        return json_decode((new Backend())->configdRun('interface show protocol'), true);
    }

    /**
     * retrieve system-wide statistics for each network adapter
     * @return mixed
     */
    public function getInterfaceStatisticsAction()
    {
        $stats = [];
        $tmp = json_decode((new Backend())->configdRun('interface show interfaces'), true);
        if (is_array($tmp) && !empty($tmp['statistics']) && !empty($tmp['statistics']['interface'])) {
            $intfmap = $this->getInterfaceNames();
            foreach ($tmp['statistics']['interface'] as $node) {
                if (!empty($intfmap[$node['name']])) {
                    $key = sprintf("[%s] (%s) / %s", $intfmap[$node['name']], $node['name'], $node['address']);
                } else {
                    $key = sprintf("[%s] / %s", $node['name'], $node['address']);
                }
                $stats[$key] = $node;
            }
        }

        return ['statistics' => $stats];
    }

    /**
     * retrieve status/config for each network adapter
     * @return mixed
     */
    public function getInterfaceConfigAction()
    {
        return json_decode((new Backend())->configdRun('interface list ifconfig'), true);
    }

    /**
     * retrieve virtual ip status (carp)
     */
    public function getVipStatusAction()
    {
        $records = [];
        $addresses = [];
        foreach ((new Vip())->vip->iterateItems() as $vip) {
            if (!empty((string)$vip->vhid)) {
                /* index by hex presentation to avoid mismatches on IPv6 compressed format */
                $addrkey = bin2hex(inet_pton((string)$vip->subnet));
                $addresses[$addrkey] = (string)$vip->mode;
            }
        }
        $vhids = [];
        $ifconfig = json_decode((new Backend())->configdRun('interface list ifconfig'), true);
        $ifnames = $this->getInterfaceNames();
        if (!empty($ifconfig)) {
            foreach ($ifconfig as $if => $data) {
                if (!empty($data['carp'])) {
                    $tmpcarp = [];
                    foreach ($data['carp'] as $carp) {
                        $vhids[] = $carp['vhid'];
                        $tmpcarp[$carp['vhid']] = $carp;
                    }
                    foreach (['ipv4', 'ipv6'] as $proto) {
                        foreach ($data[$proto] as $subnet) {
                            if (!empty($subnet['vhid'])) {
                                $record = [
                                    'interface' => !empty($ifnames[$if]) ? $ifnames[$if] : null,
                                    'vhid' => $subnet['vhid'],
                                    'advbase' => $tmpcarp[$subnet['vhid']]['advbase'],
                                    'advskew' => $tmpcarp[$subnet['vhid']]['advskew'],
                                    'subnet' => $subnet['ipaddr'],
                                    'status' => $tmpcarp[$subnet['vhid']]['status'],
                                    'mode' => 'ipalias'
                                ];
                                $addrkey = bin2hex(inet_pton($subnet['ipaddr']));
                                if (!empty($addresses[$addrkey])) {
                                    $record['mode'] = $addresses[$addrkey];
                                }
                                $records[] = $record;
                            }
                        }
                    }
                }
            }
        }

        // add disabled or not yet enabled
        foreach ((new Vip())->vip->iterateItems() as $vip) {
            if (!in_array((string)$vip->vhid, $vhids) && !empty((string)$vip->vhid)) {
                $record = [
                    'vhid' => (string)$vip->vhid,
                    'advbase' => (string)$vip->advbase,
                    'advskew' => (string)$vip->advskew,
                    'subnet' => (string)$vip->subnet,
                    'mode' =>   (string)$vip->mode,
                    'interface' => (string)$vip->interface,
                    'status' => 'DISABLED'
                ];
                $interface = $record['interface'];
                if (
                    !empty(Config::getInstance()->object()->interfaces->$interface) &&
                    !empty(Config::getInstance()->object()->interfaces->$interface->descr)
                ) {
                    $record['interface'] = (string)Config::getInstance()->object()->interfaces->$interface->descr;
                } else {
                    $record['interface'] = strtoupper($record['interface']);
                }
                $records[] = $record;
            }
        }
        // add translations and text fields
        foreach ($records as &$record) {
            // static case to feed translations
            switch ($record['status']) {
                case 'MASTER':
                    $record['status_txt'] = gettext('MASTER');
                    break;
                case 'BACKUP':
                    $record['status_txt'] = gettext('BACKUP');
                    break;
                case 'INIT':
                    $record['status_txt'] = gettext('INIT');
                    break;
                default:
                    $record['status_txt'] = gettext('DISABLED');
            }
            if ($record['mode'] == 'carp') {
                $record['vhid_txt'] = sprintf(
                    gettext('%s (freq. %s/%s)'),
                    $record['vhid'],
                    $record['advbase'],
                    $record['advskew']
                );
            } else {
                $record['vhid_txt'] = $record['vhid'];
            }
        }
        $mode = $this->request->getPost('mode');
        $filter_funct = null;
        if (!empty($mode)) {
            $filter_funct = function ($record) use ($mode) {
                return in_array($record['mode'], $mode);
            };
        }
        $response = $this->searchRecordsetBase($records, null, null, $filter_funct);
        $response['carp'] = json_decode((new Backend())->configdRun("interface show carp"), true) ?? [];
        return $response;
    }

    /**
     * set new carp node status (enable, disable, maintenance)
     * @return array
     */
    public function CarpStatusAction($status)
    {
        if ($this->request->isPost()) {
            $response = json_decode((new Backend())->configdpRun('interface carp_set_status', [$status]), true);
            if (!empty($response)) {
                return $response;
            }
        }
        return array("message" => "error");
    }

    public function getPfSyncNodesAction()
    {
        $records = json_decode((new Backend())->configdRun("filter list pfsync json"), true) ?? [];
        $records = !empty($records['nodes']) ? $records['nodes'] : [];
        return $this->searchRecordsetBase($records);
    }

    /**
     * retrieve system-wide socket statistics (merge netstat with sockstat)
     * @return mixed
     */
    public function getSocketStatisticsAction()
    {
        $stats = ['Active Internet connections' => [], 'Active UNIX domain sockets' => []];
        $tmp = json_decode((new Backend())->configdRun('interface show sockets'), true);
        if (is_array($tmp) && !empty($tmp['statistics']) && !empty($tmp['statistics']['socket'])) {
            // combine netstat with sockstat for the full picture
            $sockstat = json_decode((new Backend())->configdRun('interface dump sockstat'), true);
            $sock_app = [];
            if (is_array($sockstat)) {
                foreach ($sockstat as $record) {
                    $sock_app[sprintf("%s/%s%s", $record['proto'], $record['local'], $record['remote'])] = $record;
                }
            }
            foreach ($tmp['statistics']['socket'] as $node) {
                if (!empty($node['protocol'])) {
                    $sstatkey = sprintf(
                        "%s/%s:%s%s:%s",
                        $node['protocol'],
                        $node['local']['address'],
                        $node['local']['port'],
                        $node['remote']['address'],
                        $node['remote']['port']
                    );
                    if (!empty($sock_app[$sstatkey])) {
                        $node = array_merge_recursive($node, $sock_app[$sstatkey]);
                    }
                    $key = sprintf(
                        "%s/[%s:%s-%s:%s]",
                        $node['protocol'],
                        $node['local']['address'],
                        $node['local']['port'],
                        $node['remote']['address'],
                        $node['remote']['port']
                    );
                    $stats['Active Internet connections'][$key] = $node;
                } else {
                    if (!empty($node['type']) && !empty($node['path'])) {
                        $sstatkey = sprintf("%s/%s", $node['type'], $node['path']);
                        if (!empty($sock_app[$sstatkey])) {
                            $node = array_merge_recursive($node, $sock_app[$sstatkey]);
                        }
                    }
                    $key = sprintf(
                        '%s%s%s',
                        $node['address'],
                        !empty($node['path']) ? ' - ' : '',
                        !empty($node['path']) ? $node['path'] : ''
                    );
                    $stats['Active UNIX domain sockets'][$key] = $node;
                }
            }
        }

        return ['statistics' => $stats];
    }

    /**
     * retrieve statistics recorded by the memory management routines
     * @return mixed
     */
    public function getMemoryStatisticsAction()
    {
        return json_decode((new Backend())->configdRun('interface show memory'), true);
    }

    /**
     * retrieve bpf(4) peers statistics
     * @return mixed
     */
    public function getBpfStatisticsAction()
    {
        return json_decode((new Backend())->configdRun('interface show bpf'), true);
    }

    /**
     * retrieve netisr(9) statistics
     * @return mixed
     */
    public function getNetisrStatisticsAction()
    {
        return json_decode((new Backend())->configdRun('interface show netisr'), true);
    }
}
