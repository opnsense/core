<?php

/*
    Copyright (C) 2016 Deciso B.V.
    Copyright (C) 2016-2018 Franco Fichtner <franco@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

function pf_services()
{
    global $config;

    $services = array();

    if (!isset($config['system']['disablefilter'])) {
        $services[] = array(
            'description' => gettext('Packet Filter'),
            'configd' => array(
                'restart' => array('filter reload'),
            ),
            'nocheck' => true,
            'name' => 'pf',
        );
    }

    return $services;
}

function pf_cron()
{
    global $config;

    $jobs = array();

    if (isset($config['filter']['rule'])) {
        foreach ($config['filter']['rule'] as $rule) {
            if (!empty($rule['sched'])) {
                $jobs[]['autocron'] = array('/usr/local/etc/rc.filter_configure', '13,28,43,58');
                break;
            }
        }
    }

    return $jobs;
}

function pf_interfaces()
{
    global $config;

    $interfaces = array();

    /* add interface groups */
    if (isset($config['ifgroups']['ifgroupentry'])) {
        foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
            $oc = array("enable" => true);
            $oc['networks'] = array();
            $oc['if'] = $ifgen['ifname'];
            $oc['descr'] = $ifgen['ifname'];
            $oc['virtual'] = true;
            $oc['type'] = 'group';
            $interfaces[$ifgen['ifname']] = $oc;
        }
    }

    return $interfaces;
}

/**
 * options we should probably remove from the system at some point, lets make them plugabble before removal
 */
function pf_firewall($fw)
{
    global $config;

    $defaults = array();
    $defaults['pass'] = array("type" => "pass", "log" => !isset($config['syslog']['nologdefaultpass']));

    /*
     *  pass traffic between statically routed subnets and the subnet on the
     *  interface in question to avoid problems with complicated routing
     *  topologies
     */
    if (isset($config['filter']['bypassstaticroutes']) && isset($config['staticroutes']['route']) &&
        count($config['staticroutes']['route'])) {
        $ifdetails = legacy_interfaces_details();
        $intf = $fw->getInterfaceMapping();
        $GatewaysList = return_gateways_array(false, true) + return_gateway_groups_array();

        foreach (get_staticroutes() as $route) {
            $friendly = $GatewaysList[$route['gateway']]['friendlyiface'];
            if (!empty($intf[$friendly]) && empty($route['disabled'])) {
                $routeent = explode("/", $route['network']);
                if (is_ipaddrv4($routeent[0]) && empty($ifdetails[$intf[$friendly]['if']]['ipv4'])) {
                    continue;
                } elseif (is_ipaddrv6($routeent[0]) && empty($ifdetails[$intf[$friendly]['if']]['ipv6'])) {
                    continue;
                } elseif (!is_ipaddrv4($routeent[0]) && !is_ipaddrv6($routeent[0])) {
                    continue;
                }

                $networks = array();
                $networks[] = array('from' => $intf[$friendly]['if'].":network", 'to' => $route['network']);
                $networks[] = array('to' => $intf[$friendly]['if'].":network", 'from' => $route['network']);
                $proto = strpos($route['network'], ":") !== false ? "inet6" : "inet";
                foreach ($networks as $network) {
                    $fw->registerFilterRule(
                        10,
                        array('interface' => $friendly, 'statetype' => 'sloppy', 'ipprotocol' => $proto,
                            'protocol' => 'tcp','flags' => 'any', 'from' => $network['from'],
                            'to' => $network['to'], 'quick' => false,
                            'label' => "pass traffic between statically routed subnets"),
                        $defaults['pass']
                    );
                    $fw->registerFilterRule(
                        10,
                        array('interface' => $friendly, 'statetype' => 'sloppy', 'ipprotocol' => $proto,
                            'from' => $network['from'],'to' => $network['to'], 'quick' => false,
                            'label' => "pass traffic between statically routed subnets"),
                        $defaults['pass']
                    );
                }
            }
        }
    }
}
