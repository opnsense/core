<?php

/*
 * Copyright (C) 2014-2026 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

function radvd_configure()
{
    return [
        'dhcp' => ['radvd_configure_dhcp:3'],
        'local' => ['radvd_configure_do'],
        'radvd' => ['radvd_configure_do'],
    ];
}

function radvd_enabled($mdl)
{
    global $config;

    $explicit_off = [];

    /* handle manually configured DHCP6 server settings first */
    foreach ($mdl->entries->iterateItems() as $entry) {
        $ifnm = $entry->interface->getValue();
        if (isset($config['interfaces'][$ifnm]['enable']) && !$entry->enabled->isEmpty()) {
            return true;
        } elseif ($entry->enabled->isEmpty()) {
            $explicit_off[] = $ifnm;
        }
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (legacy_config_get_interfaces(['virtual' => false]) as $ifnm => $ifcfg) {
        if (in_array($ifnm, $explicit_off)) {
            continue;
        }
        if (isset($ifcfg['enable']) && ($ifcfg['ipaddrv6'] ?? 'none') == 'track6' && !isset($ifcfg['dhcpd6track6allowoverride'])) {
            return true;
        }
    }

    return false;
}

function radvd_services()
{
    $mdl = new OPNsense\Radvd\Radvd();
    $services = [];

    if (radvd_enabled($mdl)) {
        $pconfig = [];
        $pconfig['name'] = 'radvd';
        $pconfig['description'] = gettext('Router Advertisements');
        $pconfig['php']['restart'] = ['radvd_configure_do'];
        $pconfig['php']['start'] = ['radvd_configure_do'];
        $pconfig['pidfile'] = '/var/run/radvd.pid';
        $services[] = $pconfig;
    }

    return $services;
}

function radvd_xmlrpc_sync()
{
    return [[
        'help' => gettext('Synchronize router advertisements.'),
        'description' => gettext('Router Advertisements'),
        'section' => 'OPNsense.radvd',
        'services' => ['radvd'],
        'id' => 'radvd',
    ]];
}

function radvd_configure_dhcp($verbose = false, $family = null, $ignorelist = [])
{
    if ($family == null || $family == 'inet6') {
        radvd_configure_do($verbose, $ignorelist);
    }
}

function radvd_stale_prefixes_state_file()
{
    return '/var/db/opnsense/radvd_stale_prefixes.json';
}

function radvd_dynamic_preferred_lifetime()
{
    /* RFC 9096 section 3.4: ND_PREFERRED_LIMIT. */
    return 2700;
}

function radvd_dynamic_router_lifetime()
{
    /* RFC 9096 section 3.4: Router Lifetime matches ND_PREFERRED_LIMIT. */
    return 2700;
}

function radvd_dynamic_valid_lifetime()
{
    /* RFC 9096 section 3.4: ND_VALID_LIMIT. */
    return 5400;
}

function radvd_lifetime_limit($value, $limit)
{
    if (is_numeric($value) && (int)$value >= 0) {
        return min((int)$value, $limit);
    }

    return null;
}

function radvd_prefix_lifetimes($device, $prefix, $ifconfig_details, $fallback = false, $parent_prefixes = [])
{
    $preferred_lifetime = null;
    $valid_lifetime = null;
    $uses_parent_prefix = false;

    if (!empty($prefix) && !empty($ifconfig_details[$device]['ipv6'])) {
        foreach ($ifconfig_details[$device]['ipv6'] as $addr) {
            if (empty($addr['ipaddr']) || empty($addr['subnetbits'])) {
                continue;
            }

            $addr_prefix = gen_subnetv6($addr['ipaddr'], $addr['subnetbits']) . "/{$addr['subnetbits']}";
            if ($addr_prefix != $prefix) {
                continue;
            }

            $preferred_lifetime = radvd_lifetime_limit($addr['pltime'] ?? null, radvd_dynamic_preferred_lifetime());
            $valid_lifetime = radvd_lifetime_limit($addr['vltime'] ?? null, radvd_dynamic_valid_lifetime());
            break;
        }
    }

    foreach ($parent_prefixes as $parent_prefix) {
        if (radvd_prefix_contains_prefix($parent_prefix, $prefix)) {
            $uses_parent_prefix = true;
            break;
        }
    }

    /*
     * RFC 9096 section 3.4: use kernel lifetimes when available, otherwise
     * use bounded dynamic lifetimes for stale state and tied options.
     */
    if (($fallback || $uses_parent_prefix) && $valid_lifetime === null) {
        $valid_lifetime = radvd_dynamic_valid_lifetime();
    }
    if (($fallback || $valid_lifetime !== null) && $preferred_lifetime === null) {
        $preferred_lifetime = min(radvd_dynamic_preferred_lifetime(), $valid_lifetime);
    }
    if ($preferred_lifetime !== null && $valid_lifetime !== null && $preferred_lifetime > $valid_lifetime) {
        $preferred_lifetime = $valid_lifetime;
    }

    return [$preferred_lifetime, $valid_lifetime];
}

function radvd_prefix_contains_prefix($parent_prefix, $prefix)
{
    if (!is_subnetv6($parent_prefix) || !is_subnetv6($prefix)) {
        return false;
    }

    list (, $parent_bits) = explode('/', $parent_prefix);
    list ($address, $bits) = explode('/', $prefix);

    return (int)$parent_bits <= (int)$bits && ip_in_subnet($address, $parent_prefix);
}

function radvd_related_prefixes($prefix, $parent_prefixes)
{
    $result = [];

    if (is_subnetv6($prefix)) {
        $result[$prefix] = true;
    }

    foreach ($parent_prefixes as $parent_prefix) {
        if (radvd_prefix_contains_prefix($parent_prefix, $prefix)) {
            $result[$parent_prefix] = true;
        }
    }

    return array_keys($result);
}

function radvd_uses_dhcpv6_pd($trackif)
{
    global $config;

    return ($config['interfaces'][$trackif]['ipaddrv6'] ?? null) == 'dhcp6';
}

function radvd_delegated_prefixes($trackif, $child_prefixes = [])
{
    global $config;

    $result = [];
    $trackcfg = $config['interfaces'][$trackif] ?? [];
    if (($trackcfg['ipaddrv6'] ?? null) != 'dhcp6') {
        return [];
    }

    $device = $trackcfg['if'] ?? null;
    $filename = !empty($device) ? "/tmp/" . basename($device) . "_prefixv6" : null;
    /* dhcp6c stores delegated prefixes in /tmp/<interface>_prefixv6. */
    if (!empty($filename) && is_readable($filename)) {
        foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $prefix) {
            if (is_subnetv6($prefix)) {
                $result[$prefix] = true;
            }
        }
    }

    if (!is_array($child_prefixes)) {
        $child_prefixes = [$child_prefixes];
    }

    $sla_len = $trackcfg['dhcp6-ia-pd-len'] ?? null;
    if (is_numeric($sla_len)) {
        foreach ($child_prefixes as $child_prefix) {
            if (!is_subnetv6($child_prefix)) {
                continue;
            }

            list ($address, $bits) = explode('/', $child_prefix);
            $parent_bits = (int)$bits - (int)$sla_len;
            if ($parent_bits >= 0 && $parent_bits <= (int)$bits) {
                $result[gen_subnetv6($address, $parent_bits) . "/{$parent_bits}"] = true;
            }
        }
    }

    return array_keys($result);
}

function radvd_address_uses_prefix($address, $prefix)
{
    $address = strtok($address ?? '', '%');

    /*
     * RFC 9096 section 3.4: only shorten DNS option lifetimes when the
     * advertised resolver is actually tied to the delegated GUA prefix.
     */
    if (!is_ipaddrv6($address) || is_linklocal($address) || is_uniquelocal($address)) {
        return false;
    }

    return ip_in_subnet($address, $prefix);
}

function radvd_any_address_uses_prefix($addresses, $prefix)
{
    foreach ($addresses as $address) {
        if (radvd_address_uses_prefix($address, $prefix)) {
            return true;
        }
    }

    return false;
}

function radvd_any_address_uses_prefixes($addresses, $prefixes)
{
    foreach ($prefixes as $prefix) {
        if (radvd_any_address_uses_prefix($addresses, $prefix)) {
            return true;
        }
    }

    return false;
}

function radvd_lifetime_for_addresses($addresses, $prefix_lifetimes)
{
    $lifetime = null;

    /*
     * RFC 9096 section 3.4: if an option depends on multiple delegated
     * prefixes, its lifetime must not outlive the shortest matching prefix.
     */
    foreach ($addresses as $address) {
        foreach ($prefix_lifetimes as $prefix => $lifetimes) {
            if (!radvd_address_uses_prefix($address, $prefix) || !isset($lifetimes['valid'])) {
                continue;
            }

            if ($lifetime === null || $lifetimes['valid'] < $lifetime) {
                $lifetime = $lifetimes['valid'];
            }
        }
    }

    return $lifetime;
}

function radvd_lifetime_for_prefixes($prefixes, $prefix_lifetimes)
{
    $addresses = [];

    foreach ($prefixes as $prefix) {
        $addresses[] = explode('/', $prefix)[0];
    }

    return radvd_lifetime_for_addresses($addresses, $prefix_lifetimes);
}

function radvd_stale_prefixes_load()
{
    $filename = radvd_stale_prefixes_state_file();
    $dirname = dirname($filename);

    if (!is_dir($dirname)) {
        @mkdir($dirname, 0755, true);
    }

    $fobj = new \OPNsense\Core\FileObject($filename, 'a+', 0600, LOCK_EX);
    $state = $fobj->seek(0)->readJson() ?? [];

    return [$fobj, is_array($state) ? $state : []];
}

function radvd_stale_prefixes_ensure_loaded(&$fobj, &$state)
{
    if ($fobj == null) {
        list ($fobj, $state) = radvd_stale_prefixes_load();
    }
}

function radvd_stale_prefixes_save($fobj, $state)
{
    $fobj->seek(0)->truncate(0)->writeJson($state);
}

function radvd_stale_prefixes_prune(&$state, $now, $seen_interfaces)
{
    foreach ($state as $if => $prefixes) {
        /* Static or no-longer-stale-capable interfaces must not retain cache state. */
        if (!isset($seen_interfaces[$if]) || !is_array($prefixes)) {
            unset($state[$if]);
            continue;
        }

        foreach ($prefixes as $prefix => $prefix_data) {
            if (isset($prefix_data['stale_until']) && $prefix_data['stale_until'] <= $now) {
                unset($state[$if][$prefix]);
            }
        }

        if (empty($state[$if])) {
            unset($state[$if]);
        }
    }
}

function radvd_stale_prefixes_update(&$state, $if, $device, $trackif, $live_prefixes, $now, $advertised_prefixes = [])
{
    $stale_prefixes = [];

    if (!isset($state[$if]) || !is_array($state[$if])) {
        $state[$if] = [];
    }

    foreach ($live_prefixes as $prefix => $prefix_data) {
        $state[$if][$prefix] = array_merge(
            $prefix_data,
            [
                'first_seen' => $state[$if][$prefix]['first_seen'] ?? $now,
                'last_seen' => $now,
            ]
        );
    }

    foreach ($state[$if] as $prefix => &$prefix_data) {
        /* A currently advertised dynamic prefix is fresh again. */
        if (isset($live_prefixes[$prefix])) {
            unset($prefix_data['stale_since'], $prefix_data['stale_until']);
            continue;
        }

        /* Do not duplicate a prefix currently advertised through a static/VIP stanza. */
        if (isset($advertised_prefixes[$prefix])) {
            unset($state[$if][$prefix]);
            continue;
        }

        if (!isset($prefix_data['stale_until'])) {
            /*
             * RFC 9096 section 3.5: keep stale PIO state long enough to
             * advertise zero lifetimes for the previously advertised lifetime.
             */
            $prefix_data['interface'] = $if;
            $prefix_data['device'] = $device;
            $prefix_data['track_interface'] = $trackif;
            $prefix_data['stale_since'] = $now;
            $prefix_data['stale_until'] = $now + ($prefix_data['valid_lifetime'] ?? radvd_dynamic_valid_lifetime());
        }

        if ($prefix_data['stale_until'] <= $now) {
            unset($state[$if][$prefix]);
        } else {
            $stale_prefixes[$prefix] = $prefix_data;
        }
    }
    unset($prefix_data);

    if (empty($state[$if])) {
        unset($state[$if]);
    }

    return $stale_prefixes;
}

function radvd_stale_prefixes_render($stale_prefixes)
{
    $radvdconf = '';

    foreach ($stale_prefixes as $prefix => $prefix_data) {
        /*
         * RFC 9096 section 3.5: stale PIOs keep their original A/L flags
         * and advertise zero lifetimes so hosts deprecate the old addresses.
         */
        $radvdconf .= "    prefix {$prefix} {\n";
        $radvdconf .= "        DeprecatePrefix on;\n";
        $radvdconf .= '        AdvOnLink ' . (!empty($prefix_data['onlink']) ? 'on' : 'off') . ";\n";
        $radvdconf .= '        AdvAutonomous ' . (!empty($prefix_data['autonomous']) ? 'on' : 'off') . ";\n";
        $radvdconf .= "        AdvPreferredLifetime 0;\n";
        $radvdconf .= "        AdvValidLifetime 0;\n";
        $radvdconf .= "    };\n";
    }

    return $radvdconf;
}

function radvd_configure_do($verbose = false, $ignorelist = [])
{
    global $config;

    $radvd_conf_file = '/var/etc/radvd.conf';
    $radvd_pid_file = '/var/run/radvd.pid';
    $mdl = new OPNsense\Radvd\Radvd();

    /* XXX avoids rendering radvd.conf in edge cases meaning no debug output either */
    if (!radvd_enabled($mdl)) {
        killbypid($radvd_pid_file);
        @unlink($radvd_conf_file);
        @unlink($radvd_pid_file);
        @unlink(radvd_stale_prefixes_state_file());
        return;
    }

    service_log('Starting router advertisement service...', $verbose);

    $ifconfig_details = legacy_interfaces_details();
    $radvdconf = "# Automatically generated, do not edit\n";
    $active = false;
    $stale_prefixes_fobj = null;
    $stale_prefixes_state = [];
    $stale_prefixes_seen_interfaces = [];
    $now = time();

    /* process all links which need the router advertise daemon */
    $manuallist = [];

    /* handle manually configured DHCP6 server settings first */
    foreach ($mdl->entries->iterateItems() as $entry) {
        $dhcpv6if = $entry->interface->getValue();

        if (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            $radvdconf .= "# Skipping disabled interface {$dhcpv6if}\n";
            continue;
        } elseif (isset($ignorelist[$dhcpv6if])) {
            $radvdconf .= "# Skipping ignored interface {$dhcpv6if}\n";
            continue;
        }

        /* disable for automatic case below */
        $manuallist[$dhcpv6if] = '1';

        if ($entry->enabled->isEmpty()) {
            $radvdconf .= "# Skipping unset interface {$dhcpv6if}\n";
            continue;
        }

        $active = true;

        $target_dev = $dhcpv6if;
        $carp_mode = false;
        $src_addr = null;
        $stanzas = [];

        if (!$entry->Base6Interface->isEmpty()) {
            $target_dev = $entry->Base6Interface->getValue();
            $stanzas[] = 'base6';
        }

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($target_dev, $ifconfig_details);
        if (is_subnetv6($networkv6) && !count($stanzas)) {
            $stanzas[] = $networkv6;
        }

        if (!$entry->AdvRASrcAddress->isEmpty()) {
            $ifcfgipv6 = $entry->AdvRASrcAddress->getValue();
        }

        $device = get_real_interface($dhcpv6if, 'inet6');
        $mtu = legacy_interface_stats($device)['mtu'];

        $trackif = ($config['interfaces'][$dhcpv6if]['ipaddrv6'] ?? null) == 'track6' ?
            ($config['interfaces'][$dhcpv6if]['track6-interface'] ?? null) : null;
        $uses_dhcpv6_pd = !empty($trackif) && radvd_uses_dhcpv6_pd($trackif);
        $parent_prefixes = $uses_dhcpv6_pd ? radvd_delegated_prefixes($trackif, $networkv6) : [];

        if (!empty($trackif)) {
            $realtrackif = get_real_interface($trackif, 'inet6');

            $trackmtu = legacy_interface_stats($realtrackif)['mtu'];
            if (!empty($trackmtu) && !empty($mtu)) {
                if ($trackmtu < $mtu) {
                    $mtu = $trackmtu;
                }
            }
        }

        if (!$entry->AdvLinkMTU->isEmpty() && !empty($mtu)) {
            if ($entry->AdvLinkMTU->getValue() < $mtu) {
                $mtu = $entry->AdvLinkMTU->getValue();
            } else {
                log_msg("Skipping AdvLinkMTU configuration since it cannot be applied on {$dhcpv6if}", LOG_WARNING);
            }
        }

        $radvdconf .= "# Generated RADVD config for manual assignment on {$dhcpv6if}\n";
        $radvdconf .= "interface {$device} {\n";
        $radvdconf .= "    AdvSendAdvert on;\n";
        $radvdconf .= "    MinRtrAdvInterval {$entry->MinRtrAdvInterval->getValue()};\n";
        $radvdconf .= "    MaxRtrAdvInterval {$entry->MaxRtrAdvInterval->getValue()};\n";
        if ($entry->AdvDefaultLifetime->isSet()) {
            $radvdconf .= "    AdvDefaultLifetime {$entry->AdvDefaultLifetime->getValue()};\n";
        } elseif ($uses_dhcpv6_pd) {
            /* RFC 9096 section 3.4: keep stale-only RAs from using radvd defaults. */
            $radvdconf .= "    AdvDefaultLifetime " . radvd_dynamic_router_lifetime() . ";\n";
        }
        $radvdconf .= sprintf("    AdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);
        $radvdconf .= "    AdvCurHopLimit {$entry->AdvCurHopLimit->getValue()};\n";
        $radvdconf .= "    AdvDefaultPreference {$entry->AdvDefaultPreference->getValue()};\n";

        switch ($entry->mode->getValue()) {
            case 'assist':
            case 'managed':
                $radvdconf .= "    AdvManagedFlag on;\n";
                $radvdconf .= "    AdvOtherConfigFlag on;\n";
                break;
            case 'stateless':
                $radvdconf .= "    AdvManagedFlag off;\n";
                $radvdconf .= "    AdvOtherConfigFlag on;\n";
                break;
            default:
                break;
        }

        foreach (config_read_array('virtualip', 'vip', false) as $vip) {
            if ($vip['interface'] != $dhcpv6if || !is_ipaddrv6($vip['subnet'])) {
                continue;
            }

            if (is_linklocal($vip['subnet'])) {
                if ($ifcfgipv6 == $vip['subnet']) {
                    $carp_mode = !empty($vip['vhid']);
                    $src_addr = $ifcfgipv6;
                }
                continue;
            }

            if ($vip['subnet_bits'] == '128' || !empty($vip['nobind'])) {
                continue;
            }

            /* force subnet to 64 as per radvd complaint "prefix length should be 64 for xzy" */
            $subnetv6 = gen_subnetv6($vip['subnet'], 64);
            $stanzas[] = "{$subnetv6}/64";
        }

        if ($src_addr) {
            /* inject configured link-local address into the RA message */
            $radvdconf .= "    AdvRASrcAddress {\n";
            $radvdconf .= "        {$src_addr};\n";
            $radvdconf .= "    };\n";
        }

        if ($carp_mode) {
            /* to avoid wrong MAC being stuck during failover */
            $radvdconf .= "    AdvSourceLLAddress off;\n";
        }

        /* to avoid final advertisement with zero router lifetime */
        $radvdconf .= "    RemoveAdvOnExit " . (!$entry->RemoveAdvOnExit->isEmpty() ? $entry->RemoveAdvOnExit->getValue() : ($carp_mode ? 'off' : 'on')) . ";\n";

        /* VIPs may duplicate readings from system */
        $stanzas = array_unique($stanzas);
        $stanza_lifetimes = [];
        $live_prefixes = [];
        $advertised_prefixes = [];

        foreach ($stanzas as $stanza) {
            $preferred_lifetime = null;
            $valid_lifetime = null;
            $adv_onlink = false;
            $adv_autonomous = false;

            if ($stanza === 'base6') {
                $radvdconf .= "    prefix ::/64 {\n";
                $baseif = get_real_interface($entry->Base6Interface->getValue(), 'inet6');
                $radvdconf .= "        Base6Interface {$baseif};\n";
            } else {
                $advertised_prefixes[$stanza] = true;
                list ($preferred_lifetime, $valid_lifetime) = radvd_prefix_lifetimes(
                    $device,
                    $stanza,
                    $ifconfig_details,
                    false,
                    $parent_prefixes
                );
                if ($valid_lifetime !== null) {
                    foreach (radvd_related_prefixes($stanza, $parent_prefixes) as $lifetime_prefix) {
                        $stanza_lifetimes[$lifetime_prefix] = [
                            'preferred' => $preferred_lifetime,
                            'valid' => $valid_lifetime,
                        ];
                    }
                }
                $radvdconf .= "    prefix {$stanza} {\n";
            }
            $radvdconf .= "        DeprecatePrefix " . (!$entry->DeprecatePrefix->isEmpty() ? $entry->DeprecatePrefix->getValue() : ($carp_mode ? 'off' : 'on')) . ";\n";
            switch ($entry->mode->getValue()) {
                case 'assist':
                case 'stateless':
                case 'unmanaged':
                    $radvdconf .= "        AdvOnLink on;\n";
                    $radvdconf .= "        AdvAutonomous on;\n";
                    $adv_onlink = true;
                    $adv_autonomous = true;
                    break;
                case 'managed':
                    $radvdconf .= "        AdvOnLink on;\n";
                    $radvdconf .= "        AdvAutonomous off;\n";
                    $adv_onlink = true;
                    break;
                case 'router':
                    $radvdconf .= "        AdvOnLink off;\n";
                    $radvdconf .= "        AdvAutonomous off;\n";
                    break;
                default:
                    break;
            }
            if (!$entry->AdvValidLifetime->isEmpty()) {
                $radvdconf .= "        AdvValidLifetime {$entry->AdvValidLifetime->getValue()};\n";
            }
            if (!$entry->AdvPreferredLifetime->isEmpty()) {
                $radvdconf .= "        AdvPreferredLifetime {$entry->AdvPreferredLifetime->getValue()};\n";
            }
            $radvdconf .= "    };\n";

            if ($stanza === $networkv6 && $valid_lifetime !== null) {
                $live_prefixes[$stanza] = [
                    'prefix' => $stanza,
                    'interface' => $dhcpv6if,
                    'device' => $device,
                    'track_interface' => $trackif,
                    'onlink' => $adv_onlink,
                    'autonomous' => $adv_autonomous,
                    'preferred_lifetime' => !$entry->AdvPreferredLifetime->isEmpty() ?
                        (int)$entry->AdvPreferredLifetime->getValue() : $preferred_lifetime,
                    'valid_lifetime' => !$entry->AdvValidLifetime->isEmpty() ?
                        (int)$entry->AdvValidLifetime->getValue() : $valid_lifetime,
                ];
            }
        }

        if ((string)$entry->deprecateStalePrefixes->getValue() === '1' && $uses_dhcpv6_pd) {
            /* Manual track6 entries opt out explicitly; the model default opts in. */
            radvd_stale_prefixes_ensure_loaded($stale_prefixes_fobj, $stale_prefixes_state);
            $stale_prefixes_seen_interfaces[$dhcpv6if] = true;
            $stale_prefixes = radvd_stale_prefixes_update(
                $stale_prefixes_state,
                $dhcpv6if,
                $device,
                $trackif,
                $live_prefixes,
                $now,
                $advertised_prefixes
            );
            $radvdconf .= radvd_stale_prefixes_render($stale_prefixes);
        }

        if (!$entry->routes->isEmpty()) {
            foreach ($entry->routes->getValues() as $raroute) {
                $radvdconf .= "    route {$raroute} {\n";
                $radvdconf .= "        RemoveRoute " . (!$entry->RemoveRoute->isEmpty() ? $entry->RemoveRoute->getValue() : ($carp_mode ? 'off' : 'on')) . ";\n";
                if (!$entry->AdvRouteLifetime->isEmpty()) {
                    $radvdconf .= "        AdvRouteLifetime {$entry->AdvRouteLifetime->getValue()};\n";
                } elseif (($route_lifetime = radvd_lifetime_for_prefixes([$raroute], $stanza_lifetimes)) !== null) {
                    $radvdconf .= "        AdvRouteLifetime {$route_lifetime};\n";
                }
                $radvdconf .= "    };\n";
            }
        }

        if (!$entry->nat64prefix->isEmpty()) {
            $radvdconf .= "    nat64prefix {$entry->nat64prefix->getValue()} {\n";
            $radvdconf .= "    };\n";
        }

        $dnssl = [];
        $rdnss = [];

        /* advertise both DNS servers and domains via RA (RFC 8106) if allowed */
        if (!$entry->dns->isEmpty()) {
            $searchlist_tmp = $entry->DNSSL->getValues();
            $dnslist_tmp = $entry->RDNSS->getValues();

            if (count($dnslist_tmp)) {
                /* take these servers then */
            } elseif (!empty(service_by_filter(['dns_ports' => '53']))) {
                if (is_ipaddrv6($ifcfgipv6)) {
                    $dnslist_tmp[] = $ifcfgipv6;
                } else {
                    log_msg("radvd_configure_do(manual) found no suitable IPv6 address on {$dhcpv6if}({$device})", LOG_WARNING);
                }
            } elseif (!empty($config['system']['dnsserver'][0])) {
                $dnslist_tmp = $config['system']['dnsserver'];
            }

            foreach ($dnslist_tmp as $server) {
                if (!is_ipaddrv6($server)) {
                    continue;
                }
                if (count($rdnss) >= 3) {
                    log_msg("The radvd RDNSS entry $server cannot be added due to too many addresses.", LOG_WARNING);
                    continue;
                }
                $rdnss[] = $server;
            }

            if (count($searchlist_tmp)) {
                $dnssl = $searchlist_tmp;
            } elseif (!empty($config['system']['domain'])) {
                $dnssl = [$config['system']['domain']];
            }
        }

        $rdnss_lifetime = null;

        if (count($rdnss)) {
            $radvdconf .= "    RDNSS " . implode(" ", $rdnss) . " {\n";
            if (!$entry->AdvRDNSSLifetime->isEmpty()) {
                $radvdconf .= "        AdvRDNSSLifetime {$entry->AdvRDNSSLifetime->getValue()};\n";
            } elseif (($rdnss_lifetime = radvd_lifetime_for_addresses($rdnss, $stanza_lifetimes)) !== null) {
                $radvdconf .= "        AdvRDNSSLifetime {$rdnss_lifetime};\n";
            }
            $radvdconf .= "    };\n";
        }

        if (count($dnssl)) {
            $radvdconf .= "    DNSSL " . implode(" ", $dnssl) . " {\n";
            if (!$entry->AdvDNSSLLifetime->isEmpty()) {
                $radvdconf .= "        AdvDNSSLLifetime {$entry->AdvDNSSLLifetime->getValue()};\n";
            } elseif ($rdnss_lifetime !== null) {
                $radvdconf .= "        AdvDNSSLLifetime {$rdnss_lifetime};\n";
            }
            $radvdconf .= "    };\n";
        }

        $radvdconf .= "};\n";
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (array_keys(get_configured_interface_with_descr()) as $if) {
        if (isset($manuallist[$if])) {
            /* handled by manual case */
            continue;
        } elseif (($config['interfaces'][$if]['ipaddrv6'] ?? 'none') != 'track6' || isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])) {
            /* not relevant to automatic case */
            continue;
        } elseif (empty($config['interfaces'][$config['interfaces'][$if]['track6-interface']])) {
            $radvdconf .= "# Skipping defunct interface {$if}\n";
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            $radvdconf .= "# Skipping disabled interface {$if}\n";
            continue;
        } elseif (isset($ignorelist[$if])) {
            $radvdconf .= "# Skipping ignored interface {$if}\n";
            continue;
        }

        $trackif = $config['interfaces'][$if]['track6-interface'];
        $device = get_real_interface($if, 'inet6');

        $autotype = isset($config['interfaces'][$trackif]['ipaddrv6']) ? $config['interfaces'][$trackif]['ipaddrv6'] : 'unknown';

        if (!in_array($autotype, ['6rd', '6to4', 'dhcp6'])) {
            $radvdconf .= "# Skipping unsupported {$autotype} interface {$if}\n";
            continue;
        }

        $active = true;

        $realtrackif = get_real_interface($trackif, 'inet6');

        $mtu = legacy_interface_stats($device)['mtu'];
        $trackmtu = legacy_interface_stats($realtrackif)['mtu'];
        if (!empty($trackmtu) && !empty($mtu)) {
            if ($trackmtu < $mtu) {
                $mtu = $trackmtu;
            }
        }

        $dnslist = [];

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($if, $ifconfig_details);
        $uses_dhcpv6_pd = radvd_uses_dhcpv6_pd($trackif);
        $parent_prefixes = $uses_dhcpv6_pd ? radvd_delegated_prefixes($trackif, $networkv6) : [];
        $related_prefixes = $uses_dhcpv6_pd ? radvd_related_prefixes($networkv6, $parent_prefixes) : [];
        list ($prefix_preferred_lifetime, $prefix_valid_lifetime) = $uses_dhcpv6_pd ? radvd_prefix_lifetimes(
            $device,
            $networkv6,
            $ifconfig_details,
            true,
            $parent_prefixes
        ) : [null, null];

        if (!empty(service_by_filter(['dns_ports' => '53']))) {
            if (is_ipaddrv6($ifcfgipv6)) {
                $dnslist[] = $ifcfgipv6;
            } else {
                log_msg("radvd_configure_do(auto) found no suitable IPv6 address on {$if}({$device})", LOG_WARNING);
            }
        } else {
            foreach (config_read_array('system', 'dnsserver', false) as $server) {
                if (!is_ipaddrv6($server)) {
                    continue;
                }
                if (count($dnslist) >= 3) {
                    log_msg("The radvd RDNSS entry $server cannot be added due to too many addresses.", LOG_WARNING);
                    continue;
                }
                $dnslist[] = $server;
            }
        }

        $radvdconf .= "# Generated RADVD config for {$autotype} assignment from {$trackif} on {$if}\n";
        $radvdconf .= "interface {$device} {\n";
        $radvdconf .= "    AdvSendAdvert on;\n";
        if ($uses_dhcpv6_pd) {
            /* RFC 9096 section 3.4: DHCPv6-PD track6 is dynamic. */
            $radvdconf .= "    AdvDefaultLifetime " . radvd_dynamic_router_lifetime() . ";\n";
        }
        $radvdconf .= sprintf("    AdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);
        $radvdconf .= "    AdvManagedFlag on;\n";
        $radvdconf .= "    AdvOtherConfigFlag on;\n";

        $live_prefixes = [];
        $advertised_prefixes = [];
        if (!empty($networkv6)) {
            $advertised_prefixes[$networkv6] = true;
            if ($uses_dhcpv6_pd) {
                $live_prefixes[$networkv6] = [
                    'prefix' => $networkv6,
                    'interface' => $if,
                    'device' => $device,
                    'track_interface' => $trackif,
                    'onlink' => true,
                    'autonomous' => true,
                    'preferred_lifetime' => $prefix_preferred_lifetime,
                    'valid_lifetime' => $prefix_valid_lifetime,
                ];
            }
            $radvdconf .= "    prefix {$networkv6} {\n";
            $radvdconf .= "        DeprecatePrefix on;\n";
            $radvdconf .= "        AdvOnLink on;\n";
            $radvdconf .= "        AdvAutonomous on;\n";
            $radvdconf .= "    };\n";
        }

        $vip_radvdconf = '';
        foreach (config_read_array('virtualip', 'vip', false) as $vip) {
            if ($vip['interface'] != $if || !is_ipaddrv6($vip['subnet']) || $vip['subnet_bits'] == '128') {
                continue;
            }

            if (is_linklocal($vip['subnet']) || !empty($vip['nobind'])) {
                continue;
            }

            /* force subnet to 64 as per radvd complaint "prefix length should be 64 for xzy" */
            $subnetv6 = gen_subnetv6($vip['subnet'], 64);
            $vipnetv6 = "{$subnetv6}/64";

            if ($vipnetv6 == $networkv6) {
                continue;
            }

            $advertised_prefixes[$vipnetv6] = true;
            $vip_radvdconf .= "    prefix {$vipnetv6} {\n";
            $vip_radvdconf .= "        DeprecatePrefix on;\n";
            $vip_radvdconf .= "        AdvOnLink on;\n";
            $vip_radvdconf .= "        AdvAutonomous on;\n";
            $vip_radvdconf .= "    };\n";
        }

        if ($uses_dhcpv6_pd) {
            /* Automatic DHCPv6-PD track6 always participates in stale-prefix deprecation. */
            radvd_stale_prefixes_ensure_loaded($stale_prefixes_fobj, $stale_prefixes_state);
            $stale_prefixes_seen_interfaces[$if] = true;
            $stale_prefixes = radvd_stale_prefixes_update(
                $stale_prefixes_state,
                $if,
                $device,
                $trackif,
                $live_prefixes,
                $now,
                $advertised_prefixes
            );
            $radvdconf .= radvd_stale_prefixes_render($stale_prefixes);
        }
        $radvdconf .= $vip_radvdconf;

        if (count($dnslist) > 0) {
            $radvdconf .= "    RDNSS " . implode(" ", $dnslist) . " {\n";
            if (radvd_any_address_uses_prefixes($dnslist, $related_prefixes)) {
                /* RFC 9096 section 3.4: DNS tied to PD must share its lifetime. */
                $radvdconf .= "        AdvRDNSSLifetime {$prefix_valid_lifetime};\n";
            }
            $radvdconf .= "    };\n";
        }

        if (!empty($config['system']['domain'])) {
            $radvdconf .= "    DNSSL {$config['system']['domain']} {\n";
            if (radvd_any_address_uses_prefixes($dnslist, $related_prefixes)) {
                /* RFC 9096 section 3.4: DNSSL follows tied RDNSS lifetime. */
                $radvdconf .= "        AdvDNSSLLifetime {$prefix_valid_lifetime};\n";
            }
            $radvdconf .= "    };\n";
        }

        $radvdconf .= "};\n";
    }

    if ($stale_prefixes_fobj != null) {
        radvd_stale_prefixes_prune($stale_prefixes_state, $now, $stale_prefixes_seen_interfaces);
        radvd_stale_prefixes_save($stale_prefixes_fobj, $stale_prefixes_state);
        unset($stale_prefixes_fobj);
    } elseif (file_exists(radvd_stale_prefixes_state_file())) {
        @unlink(radvd_stale_prefixes_state_file());
    }

    file_safe($radvd_conf_file, $radvdconf);

    if ($active) {
        $last_version = @file_get_contents("{$radvd_conf_file}.last");
        $this_version = shell_safe('/bin/cat %s | sha256', $radvd_conf_file);

        if (isvalidpid($radvd_pid_file) && $last_version == $this_version) {
            killbypid($radvd_pid_file, 'HUP');
        } else {
            killbypid($radvd_pid_file);
            file_safe("{$radvd_conf_file}.last", $this_version);
            mwexecf('/usr/local/sbin/radvd -d1 -p %s -C %s -m syslog', [
                $radvd_pid_file,
                $radvd_conf_file,
            ]);
        }
    } else {
        /* stop on invalid configuration for legacy condition above */
        killbypid($radvd_pid_file);
        @unlink("{$radvd_conf_file}.last");
    }

    service_log("done.\n", $verbose);
}
