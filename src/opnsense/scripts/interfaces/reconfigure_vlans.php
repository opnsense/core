#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2022-2023 Deciso B.V.
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

require_once 'config.inc';
require_once 'filter.inc';
require_once 'interfaces.inc';
require_once 'util.inc';

/* gather all relevant vlan's (new/updated and removed) into a single list */
$all_vlans = [];
$all_parents = [];
$vfilename = '/tmp/.vlans.removed';
if (file_exists($vfilename) && filesize($vfilename) > 0) {
    $handle = fopen($vfilename, 'r+');
    if (flock($handle, LOCK_EX)) {
        fseek($handle, 0);
        foreach (explode("\n", fread($handle, filesize($vfilename))) as $line) {
            if (!isset($all_vlans[$line]) && trim($line) != '') {
                $all_vlans[$line] = [];
            }
        }
        fseek($handle, 0);
        ftruncate($handle, 0);
        flock($handle, LOCK_UN);
    }
}
/* merge configured vlans */
if (!empty($config['vlans']['vlan'])) {
    foreach ($config['vlans']['vlan'] as $vlan) {
        $all_vlans[$vlan['vlanif']] = $vlan;
        if (!isset($all_parents[$vlan['if']])) {
            $all_parents[$vlan['if']] = 0;
        }
        $all_parents[$vlan['if']]++;
    }
}

/* handle existing vlans */
foreach (legacy_interfaces_details() as $ifname => $ifdetails) {
    if (empty($ifdetails['vlan'])) {
        continue;
    }
    if (!isset($all_vlans[$ifname])) {
        continue;
    }
    if (empty($all_vlans[$ifname])) {
        /* option 1: removed vlan */
        legacy_interface_destroy($ifname);
    } else {
        $vlan = $all_vlans[$ifname];
        if (empty($vlan['proto'])) {
            $vlan['proto'] = empty($all_parents[$vlan['vlanif']]) ? '802.1q' : '802.1ad';
        }
        $cvlan = $ifdetails['vlan'];
        if ($vlan['tag'] != $cvlan['tag'] || $vlan['if'] != $cvlan['parent']) {
            /* option 2: changed vlan, unlink and relink */
            /*
             * XXX: legacy code used interface_configure() in these cases,
             * but since you cannot change a tag or a parent for an assigned
             * interface.  At the moment that does not seem to make much sense.
             */
            legacy_vlan_remove_tag($vlan['vlanif']);
            legacy_vlan_tag($vlan['vlanif'], $vlan['if'], $vlan['tag'], $vlan['pcp'], $vlan['proto']);
        } else {
            /* option 3: only pcp or proto changed, which can be altered instantly */
            if ($vlan['pcp'] != $cvlan['pcp']) {
                legacy_vlan_pcp($vlan['vlanif'], $vlan['pcp']);
            }
            if ($vlan['proto'] != $cvlan['proto']) {
                legacy_vlan_proto($vlan['vlanif'], $vlan['proto']);
            }
        }
    }
    unset($all_vlans[$ifname]);
}

/* configure new */
foreach ($all_vlans as $ifname => $vlan) {
    if (!empty($vlan)) {
        $vlan['proto'] = !in_array($vlan['if'], $all_parents) ? '802.1q' : '802.1ad';
        _interfaces_vlan_configure($vlan);
    }
}

ifgroup_setup();
