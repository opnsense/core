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
        'bootup' => ['dhcrelay_configure_do'],
        'dhcrelay' => ['dhcrelay_configure_do:3'],
        'local' => ['dhcrelay_configure_do'],
    ];
}

function dhcrelay_services()
{
    $services = [];

    foreach ((new \OPNsense\DHCRelay\DHCRelay())->relays->iterateItems() as $relay) {
        if ((string)$relay->enabled == '1') {
            $pconfig = [];
            $pconfig['name'] = 'dhcrelay';
            $pconfig['description'] = gettext('DHCPv4 Relay') . " ({$relay->interface})";
            $pconfig['php']['restart'] = ['dhcrelay_configure_do'];
            $pconfig['php']['start'] = ['dhcrelay_configure_do'];
            $pconfig['pidfile'] = "/var/run/dhcrelay-{$relay->getAttribute('uuid')}.pid";
            $pconfig['id'] = $relay->getAttribute('uuid');
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

function dhcrelay_configure_do($verbose = false, $id = null)
{
    $mdl = new \OPNsense\DHCRelay\DHCRelay();
    $relays = [];

    foreach ($mdl->relays->iterateItems() as $relay) {
        $target_id = $relay->getAttribute('uuid');
        if ($id != null && $id != $target_id) {
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

    service_log('Starting DHCP relays...', $verbose);

    $iflist = get_configured_interface_with_descr();
    $ifconfig_details = legacy_interfaces_details();

    foreach ($relays as $relay) {
        $interface = (string)$relay->interface;
        $device = get_real_interface($interface);

        if (!isset($iflist[$interface]) || !get_interface_ip($interface, $ifconfig_details)) {
            log_msg("dhcrelay_configure_do() found no IP address for $interface($device)", LOG_WARNING);
            continue;
        }

        if (empty($device) || isset($ifconfig_details[$device]) && $ifconfig_details[$device]['macaddr'] == '00:00:00:00:00:00') {
            log_msg("dhcrelay_configure_do() found no ethernet address for $interface($device)", LOG_WARNING);
            continue;
        }

        $destination = $mdl->getNodeByReference("destinations.{$relay->destination}");
        if ($destination == null) {
            log_msg("dhcrelay_configure_do() found no destination server for $interface($device)", LOG_WARNING);
            continue;
        }

        $cmd = [exec_safe('daemon -f -p %s', "/var/run/dhcrelay-{$relay->getAttribute('uuid')}.pid")];
        $cmd[] = '/usr/local/sbin/dhcrelay -d';
        if (!empty((string)$relay->agent_info)) {
            $cmd[] = '-or';
        }
        $cmd[] = exec_safe('-i %s', $device);

        foreach (explode(',', (string)$destination->server) as $server) {
            $cmd[] = exec_safe('%s', $server);
        }

        mwexec(join(' ', $cmd));
    }

    service_log("done.\n", $verbose);
}
