<?php

/*
 * Copyright (C) 2016 Deciso B.V.
 * Copyright (C) 2016-2018 Franco Fichtner <franco@opnsense.org>
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
            if (empty($rule['disabled']) && !empty($rule['sched'])) {
                $jobs[]['autocron'] = array('/usr/bin/logger "reload filter for configured schedules" ; /usr/local/etc/rc.filter_configure', '1,16,31,46');
                break;
            }
        }
    }

    /* bogons fetch always set in default config.xml */
    switch ($config['system']['bogons']['interval']) {
        case 'daily':
            $jobs[]['autocron'] = array('configctl filter schedule bogons', '1', '3', '*', '*', '*');
            break;
        case 'weekly':
            $jobs[]['autocron'] = array('configctl filter schedule bogons', '1', '3', '*', '*', '0');
            break;
        case 'monthly':
        default:
            $jobs[]['autocron'] = array('configctl filter schedule bogons', '1', '3', '1', '*', '*');
            break;
    }

    $jobs[]['autocron'] = array(
        '/usr/local/bin/flock -n -E 0 -o /tmp/filter_update_tables.lock ' .
        '/usr/local/opnsense/scripts/filter/update_tables.py',
        '*'
    );

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
 * @param $fw
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
    if (
        isset($config['filter']['bypassstaticroutes']) && isset($config['staticroutes']['route']) &&
        count($config['staticroutes']['route'])
    ) {
        $ifdetails = $fw->getIfconfigDetails();
        $GatewaysList = $fw->getGateways()->gatewaysIndexedByName(false, true);

        foreach (get_staticroutes() as $route) {
            if (!empty($GatewaysList[$route['gateway']]['interface']) && empty($route['disabled'])) {
                $gw = $GatewaysList[$route['gateway']];
                $routeent = explode("/", $route['network']);
                if (is_ipaddrv4($routeent[0]) && empty($ifdetails[$gw['if']]['ipv4'])) {
                    continue;
                } elseif (is_ipaddrv6($routeent[0]) && empty($ifdetails[$gw['if']]['ipv6'])) {
                    continue;
                } elseif (!is_ipaddrv4($routeent[0]) && !is_ipaddrv6($routeent[0])) {
                    continue;
                }

                $networks = array();
                $networks[] = array('from' => "({$gw['if']}:network)", 'to' => $route['network']);
                $networks[] = array('to' => "({$gw['if']}:network)", 'from' => $route['network']);
                $proto = strpos($route['network'], ":") !== false ? "inet6" : "inet";
                foreach ($networks as $network) {
                    $fw->registerFilterRule(
                        10,
                        array('interface' => $gw['interface'], 'statetype' => 'sloppy', 'ipprotocol' => $proto,
                            'protocol' => 'tcp','flags' => 'any', 'from' => $network['from'],
                            'to' => $network['to'], 'quick' => false,
                            'descr' => "pass traffic between statically routed subnets",
                            '#ref' => 'system_advanced_firewall.php#bypassstaticroutes'),
                        $defaults['pass']
                    );
                    $fw->registerFilterRule(
                        10,
                        array('interface' => $gw['interface'], 'statetype' => 'sloppy', 'ipprotocol' => $proto,
                            'from' => $network['from'],'to' => $network['to'], 'quick' => false,
                            'descr' => "pass traffic between statically routed subnets",
                            '#ref' => 'system_advanced_firewall.php#bypassstaticroutes'),
                        $defaults['pass']
                    );
                }
            }
        }
    }
}

function pf_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('Firewall Groups'),
        'section' => 'ifgroups.ifgroupentry',
        'id' => 'ifgroups',
        'services' => ['pf'],
    );
    $result[] = array(
        'description' => gettext('Firewall Rules'),
        'help' => gettext('Synchronize the firewall rules to the other HA host.'),
        'section' => 'filter',
        'id' => 'rules',
        'services' => ['pf'],
    );
    $result[] = array(
        'description' => gettext('Firewall Schedules'),
        'help' => gettext('Synchronize the firewall schedules to the other HA host.'),
        'section' => 'schedules',
        'id' => 'schedules',
        'services' => ['pf'],
    );
    $result[] = array(
        'description' => gettext('Firewall Categories'),
        'section' => 'OPNsense.Firewall.Category',
        'id' => 'categories',
    );
    $result[] = array(
        'description' => gettext('Firewall Log Templates'),
        'section' => 'OPNsense.Firewall.Lvtemplate',
        'id' => 'lvtemplate',
    );
    $result[] = array(
        'description' => gettext('Aliases'),
        'help' => gettext('Synchronize the aliases over to the other HA host.'),
        'section' => 'OPNsense.Firewall.Alias',
        'id' => 'aliases',
        'services' => ['pf'],
    );
    $result[] = array(
        'description' => gettext('NAT'),
        'help' => gettext('Synchronize the NAT rules over to the other HA host.'),
        'section' => 'nat',
        'id' => 'nat',
        'services' => ['pf'],
    );

    return $result;
}
