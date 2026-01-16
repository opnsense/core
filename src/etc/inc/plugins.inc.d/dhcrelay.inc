<?php

/*
 * Copyright (C) 2024-2026 Franco Fichtner <franco@opnsense.org>
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

function dhcrelay_configure()
{
    return [
        'bootup' => ['dhcrelay_configure_id'],
        'dhcrelay' => ['dhcrelay_configure_id:2'],
        'local' => ['dhcrelay_configure_id'],
        'newwanip' => ['dhcrelay_configure_if:3'],
    ];
}

function dhcrelay_run()
{
    return [
        'dhcrelay_interfaces' => 'dhcrelay_bound_interfaces',
    ];
}

function dhcrelay_syslog()
{
    return [
        'dhcrelay' => ['facility' => ['dhcrelay']]
    ];
}

function dhcrelay_services()
{
    $mdl = new \OPNsense\DHCRelay\DHCRelay();
    $services = [];

    foreach ($mdl->relays->iterateItems() as $relay) {
        if ($relay->enabled->isEqual('1') && ($dst = $mdl->getNodeByReference("destinations.{$relay->destination}")) != null) {
            $pconfig = [];
            $pconfig['name'] = 'dhcrelay';
            $pconfig['description'] = (strpos($dst->server->getValue(), '.') !== false ?
                gettext('DHCPv4 Relay') : gettext('DHCPv6 Relay')) . " ({$relay->interface})";
            $pconfig['php']['restart'] = ['dhcrelay_configure_id'];
            $pconfig['php']['start'] = ['dhcrelay_configure_id'];
            $pconfig['php']['args'] = ['verbose', 'id'];
            $pconfig['pidfile'] = "/var/run/dhcrelay-{$relay->getAttribute('uuid')}.pid";
            $pconfig['id'] = $relay->getAttribute('uuid');
            $pconfig['verbose'] = false;
            $services[] = $pconfig;
        }
    }

    return $services;
}

function dhcrelay_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('DHCRelay'),
        'section' => 'dhcrelay,dhcrelay6,OPNsense.DHCRelay',
        'services' => ['dhcrelay'],
        'id' => 'dhcrelay',
    ];

    return $result;
}

function dhcrelay_configure_id($verbose = false, $id_map = null)
{
    if (!plugins_argument_map($id_map)) {
        return;
    }

    $mdl = new \OPNsense\DHCRelay\DHCRelay();
    $carp_tracking = [];
    $relays = [];

    foreach ($mdl->relays->iterateItems() as $relay) {
        $target_id = $relay->getAttribute('uuid');
        if (!empty($id_map) && !in_array($target_id, $id_map)) {
            continue;
        }

        if ($relay->enabled->isEqual('1')) {
            $relays[] = $relay;

            if (!$relay->carp_depend_on->isEmpty()) {
                $carp_tracking[$relay->carp_depend_on->getValue()] = ['status' => 'DISABLED'];
            }
        }

        killbypid("/var/run/dhcrelay-{$target_id}.pid");
    }

    if (!count($relays)) {
        return;
    }

    service_log(sprintf('Starting DHCP relay%s...', empty($id_map) ? 's' : ' for ' . join(', ', $id_map)), $verbose);

    $iflist = get_configured_interface_with_descr();
    $ifconfig_details = legacy_interfaces_details();

    if (!empty($carp_tracking)) {
        foreach ((new OPNsense\Interfaces\Vip())->vip->iterateItems() as $id => $item) {
            if ($item->mode->isEqual('carp') && isset($carp_tracking[$id])) {
                $carp_tracking[$id]['vhid'] = $item->vhid->getValue();
            }
        }
        foreach ($ifconfig_details as $ifdata) {
            if (empty($ifdata['carp'])) {
                continue;
            }
            foreach ($ifdata['carp'] as $data) {
                foreach ($carp_tracking as &$item) {
                    if (isset($item['vhid']) && $item['vhid'] == $data['vhid']) {
                        $item['status'] = $data['status'];
                    }
                }
            }
        }
    }

    foreach ($relays as $relay) {
        $destination = $mdl->getNodeByReference("destinations.{$relay->destination}");
        if ($destination == null) {
            log_msg("dhcrelay_configure_id() found no destination server for $interface($device)", LOG_WARNING);
            continue;
        } elseif (
            !$relay->carp_depend_on->isEmpty() &&
            $carp_tracking[$relay->carp_depend_on->getValue()]['status'] != 'MASTER'
        ) {
            continue;   /* disabled due to carp tracking */
        }

        $family = strpos($destination->server->getValue(), '.') !== false ? 'inet' : 'inet6';

        $interface = $relay->interface->getValue();
        $device = get_real_interface($interface, $family);

        if (empty($device) || !isset($ifconfig_details[$device]) || $ifconfig_details[$device]['macaddr'] == '00:00:00:00:00:00') {
            log_msg("dhcrelay_configure_id() found no device or ethernet address for $interface($device)", LOG_WARNING);
            continue;
        }

        if (
            !isset($iflist[$interface]) || ($family == 'inet' && isset($ifconfig_details[$device]['ipv4'][0]) ||
            $family == 'inet6' && isset($ifconfig_details[$device]['ipv6'][0]))
        ) {
            log_msg("dhcrelay_configure_id() found no suitable IP address for $interface($device)", LOG_WARNING);
            continue;
        }

        $cmd_frmt = ['/usr/sbin/daemon -S -T dhcrelay -f -p %s'];
        $cmd_args = ["/var/run/dhcrelay-{$relay->getAttribute('uuid')}.pid"];

        $cmd_frmt[] = sprintf('/usr/local/sbin/dhcrelay%s -d', $family == 'inet6' ? '6' : '');

        if (!$relay->agent_info->isEmpty()) {
            $cmd_frmt[] = sprintf('-o%s', $family == 'inet' ? 'r' : '');
        }

        $cmd_frmt[] = '-i %s';
        $cmd_args[] = $device;

        $has_servers = false;

        foreach ($destination->server->getValues() as $server) {
            if ($family == 'inet6') {
                $routeif = shell_safe('/sbin/route -6 get %s | grep interface: | awk \'{ print $2 }\'', $server);
                if (empty($routeif)) {
                    log_msg("dhcrelay_configure_id() found no suitable route to $server for $interface($device)", LOG_WARNING);
                    continue;
                }
                $server .= '%' . $routeif;
            }
            $cmd_frmt[] = '%s';
            $cmd_args[] = $server;
            $has_servers = true;
        }

        if (!$has_servers) {
            log_msg("dhcrelay_configure_id() has no reachable servers for $interface($device)", LOG_WARNING);
            continue;
        }

        mwexecf($cmd_frmt, $cmd_args);
    }

    service_log("done.\n", $verbose);
}

function dhcrelay_bound_instances($family = null)
{
    $mdl = new \OPNsense\DHCRelay\DHCRelay();
    $instances = [];

    foreach ($mdl->relays->iterateItems() as $relay) {
        if (!$relay->enabled->isEqual('1')) {
            continue;
        }

        $destination = $mdl->getNodeByReference("destinations.{$relay->destination}");
        if ($destination == null) {
            continue;
        }

        $dstfamily = strpos($destination->server->getValue(), '.') !== false ? 'inet' : 'inet6';
        if ($family !== null && $family != $dstfamily) {
            continue;
        }

        $interface = $relay->interface->getValue();

        if (!isset($instances[$interface])) {
            $instances[$interface] = [];
        }

        $instances[$interface][] = $relay->getAttribute('uuid');
    }

    return $instances;
}

function dhcrelay_bound_interfaces($family = null)
{
    return array_keys(dhcrelay_bound_instances($family));
}

function dhcrelay_configure_if($verbose = false, $interface_map = null, $family = null)
{
    if (!plugins_argument_map($interface_map)) {
        return;
    }

    $instances = dhcrelay_bound_instances($family);
    $relays = [];

    foreach ($interface_map ?? [] as $interface) {
        foreach ($instances[$interface] ?? [] as $id) {
            /* grab relevant instances for batch invoke below */
            $relays[] = $id;
        }
    }

    dhcrelay_configure_id($verbose, $relays);
}
