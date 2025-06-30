<?php

/*
 * Copyright (C) 2024 Franco Fichtner <franco@opnsense.org>
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
        if ((string)$relay->enabled == '1' && ($dst = $mdl->getNodeByReference("destinations.{$relay->destination}")) != null) {
            $pconfig = [];
            $pconfig['name'] = 'dhcrelay';
            $pconfig['description'] = (strpos((string)$dst->server, '.') !== false ?
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
    $relays = [];

    foreach ($mdl->relays->iterateItems() as $relay) {
        $target_id = $relay->getAttribute('uuid');
        if (!empty($id_map) && !in_array($target_id, $id_map)) {
            continue;
        }

        if ((string)$relay->enabled == '1') {
            $relays[] = $relay;
        }

        killbypid("/var/run/dhcrelay-{$target_id}.pid");
    }

    if (!count($relays)) {
        return;
    }

    service_log(sprintf('Starting DHCP relay%s...', empty($id_map) ? 's' : ' for ' . join(', ', $id_map)), $verbose);

    $iflist = get_configured_interface_with_descr();
    $ifconfig_details = legacy_interfaces_details();

    foreach ($relays as $relay) {
        $destination = $mdl->getNodeByReference("destinations.{$relay->destination}");
        if ($destination == null) {
            log_msg("dhcrelay_configure_id() found no destination server for $interface($device)", LOG_WARNING);
            continue;
        }

        $family = strpos((string)$destination->server, '.') !== false ? 'inet' : 'inet6';

        $interface = (string)$relay->interface;
        $device = get_real_interface($interface, $family);

        if (empty($device) || !isset($ifconfig_details[$device]) || $ifconfig_details[$device]['macaddr'] == '00:00:00:00:00:00') {
            log_msg("dhcrelay_configure_id() found no device or ethernet address for $interface($device)", LOG_WARNING);
            continue;
        }

        if (
            !isset($iflist[$interface]) || ($family == 'inet' && !get_interface_ip($interface, $ifconfig_details)) ||
            ($family == 'inet6' && !get_interface_ipv6($interface, $ifconfig_details))
        ) {
            log_msg("dhcrelay_configure_id() found no suitable IP address for $interface($device)", LOG_WARNING);
            continue;
        }

        $cmd = [exec_safe('daemon -S -T dhcrelay -f -p %s', "/var/run/dhcrelay-{$relay->getAttribute('uuid')}.pid")];
        $cmd[] = sprintf('/usr/local/sbin/dhcrelay%s -d', $family == 'inet6' ? '6' : '');
        if (!empty((string)$relay->agent_info)) {
            $cmd[] = sprintf('-o%s', $family == 'inet' ? 'r' : '');
        }
        $cmd[] = exec_safe('-i %s', $device);

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
            $cmd[] = exec_safe('%s', $server);
            $has_servers = true;
        }

        if (!$has_servers) {
            log_msg("dhcrelay_configure_id() has no reachable servers for $interface($device)", LOG_WARNING);
            continue;
        }

        mwexec(join(' ', $cmd));
    }

    service_log("done.\n", $verbose);
}

function dhcrelay_bound_instances($family = null)
{
    $mdl = new \OPNsense\DHCRelay\DHCRelay();
    $instances = [];

    foreach ($mdl->relays->iterateItems() as $relay) {
        if ((string)$relay->enabled != '1') {
            continue;
        }

        $destination = $mdl->getNodeByReference("destinations.{$relay->destination}");
        if ($destination == null) {
            continue;
        }

        $dstfamily = strpos((string)$destination->server, '.') !== false ? 'inet' : 'inet6';
        if ($family !== null && $family != $dstfamily) {
            continue;
        }

        if (!isset($instances[(string)$relay->interface])) {
            $instances[(string)$relay->interface] = [];
        }

        $instances[(string)$relay->interface][] = $relay->getAttribute('uuid');
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
