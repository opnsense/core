#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

require_once('script/load_phalcon.php');
require_once('util.inc');
require_once('interfaces.inc');


function setup_interface($instance)
{
    if (in_array($instance->dev_type, ['tun', 'tap'])) {
        if (!file_exists("/dev/{$instance->__devnode}")) {
            mwexecf('/sbin/ifconfig %s create', [$instance->__devnode]);
        }
        if (!does_interface_exist($instance->__devname)) {
            mwexecf('/sbin/ifconfig %s name %s', [$instance->__devnode, $instance->__devname]);
            mwexecf('/sbin/ifconfig %s group openvpn', [$instance->__devname]);
        }
    } elseif ($instance->dev_type == 'ovpn') {
        if (!does_interface_exist($instance->__devname)) {
            /**
             * XXX: DCO uses non standard matching, normally create should use "ifconfig ovpnX create"
             * ref: https://github.com/opnsense/src/blob/b0130349e8/sys/net/if_ovpn.c#L2392-L2400
             */
            mwexecf('/sbin/ifconfig %s create', [$instance->__devname]);
            mwexecf('/sbin/ifconfig %s group openvpn', [$instance->__devname]);
        }
    }
    /* Make sure the interface is down before handing it over to OpenVPN to prevent locking issues */
    mwexecf('/sbin/ifconfig %s down', [$instance->__devname]);
}

function ovpn_start($instance, $fhandle)
{
    setup_interface($instance);
    if (!isvalidpid($instance->pidFilename)) {
        if ($instance->role == 'server') {
            if (is_file($instance->csoDirectory)) {
                unlink($instance->csoDirectory);
            }
            @mkdir($instance->csoDirectory, 0750, true);
        }
        if (!mwexecf('/usr/local/sbin/openvpn --config %s', $instance->cnfFilename)) {
            $pid = waitforpid($instance->pidFilename, 10);
            if ($pid) {
                syslog(LOG_NOTICE, "OpenVPN {$instance->role} {$instance->vpnid} instance started on PID {$pid}.");
            } else {
                syslog(LOG_WARNING, "OpenVPN {$instance->role} {$instance->vpnid} instance start timed out.");
            }
        }
        // write instance details
        $data = [
            'md5' => md5_file($instance->cnfFilename),
            'vpnid' => (string)$instance->vpnid,
            'devname' => (string)$instance->__devname,
            'dev_type' => (string)$instance->dev_type,
        ];
        fseek($fhandle, 0);
        ftruncate($fhandle, 0);
        fwrite($fhandle, json_encode($data));
    }
}

function ovpn_stop($instance, $destroy_if = false)
{
    killbypid($instance->pidFilename);
    @unlink($instance->pidFilename);
    @unlink($instance->sockFilename);
    if ($destroy_if) {
        legacy_interface_destroy($instance->__devname);
    }
}

function ovpn_instance_stats($instance, $fhandle)
{
    fseek($fhandle, 0);
    $data = json_decode(stream_get_contents($fhandle) ?? '', true) ?? [];
    $data['has_changed'] = ($data['md5'] ?? '') != @md5_file($instance->cnfFilename);
    foreach (['vpnid', 'devname', 'dev_type'] as $fieldname) {
        $data[$fieldname] = $data[$fieldname] ?? null;
    }
    return $data;
}

function get_vhid_status()
{
    $vhids = [];
    $uuids = [];
    foreach ((new OPNsense\Interfaces\Vip())->vip->iterateItems() as $id => $item) {
        if ($item->mode == 'carp') {
            $uuids[(string)$item->vhid] =  $id;
            $vhids[$id] = 'DISABLED';
        }
    }
    foreach (legacy_interfaces_details() as $ifdata) {
        if (!empty($ifdata['carp'])) {
            foreach ($ifdata['carp'] as $data) {
                if (isset($uuids[$data['vhid']])) {
                    $vhids[$uuids[$data['vhid']]] = $data['status'];
                }
            }
        }
    }
    return $vhids;
}

$opts = getopt('ah', [], $optind);
$args = array_slice($argv, $optind);

/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

if (isset($opts['h']) || empty($args) || !in_array($args[0], ['start', 'stop', 'restart', 'configure'])) {
    echo "Usage: ovpn_service_control.php [-a] [-h] [stop|start|restart|configure] [uuid]\n\n";
    echo "\t-a all instances\n";
} elseif (isset($opts['a']) || !empty($args[1])) {
    $mdl = new OPNsense\OpenVPN\OpenVPN();
    $instance_id = $args[1] ?? null;
    $action = $args[0];

    if ($action != 'stop') {
        $mdl->generateInstanceConfig($instance_id);
    }

    $vhids = $action == 'configure' ? get_vhid_status() : [];
    $instance_ids = [];

    foreach ($mdl->Instances->Instance->iterateItems() as $key => $node) {
        if (empty((string)$node->enabled)) {
            continue;
        }
        if ($instance_id != null && $key != $instance_id) {
            continue;
        }
        $instance_ids[] = $key;
        $statHandle = fopen($node->statFilename, 'a+e');
        if (flock($statHandle, LOCK_EX)) {
            $instance_stats = ovpn_instance_stats($node, $statHandle);
            $destroy_if = !empty($instance_stats['dev_type']) && $instance_stats['dev_type'] != $node->dev_type;
            switch ($action) {
                case 'stop':
                    ovpn_stop($node);
                    break;
                case 'start':
                    ovpn_start($node, $statHandle);
                    break;
                case 'restart':
                    ovpn_stop($node, $destroy_if);
                    ovpn_start($node, $statHandle);
                    break;
                case 'configure':
                    $carp_down = false;
                    if ((string)$node->role == 'client' && !empty($vhids[(string)$node->carp_depend_on])) {
                        $carp_down = $vhids[(string)$node->carp_depend_on] != 'MASTER';
                    }
                    if ($carp_down) {
                        if (isvalidpid($node->pidFilename)) {
                            ovpn_stop($node);
                        }
                    } elseif ($instance_stats['has_changed'] || !isvalidpid($node->pidFilename)) {
                        ovpn_stop($node, $destroy_if);
                        ovpn_start($node, $statHandle);
                    }
                    break;
            }
            // cleanup old interface when needed
            if (!empty($instance_stats['devname']) && $instance_stats['devname'] != $node->__devname) {
                legacy_interface_destroy($instance_stats['devname']);
            }
            flock($statHandle, LOCK_UN);
        }
        fclose($statHandle);
    }

    /**
     * When -a is specified, cleanup up old or disabled instances
     */
    if ($instance_id == null) {
        $to_clean = [];
        foreach (glob('/var/etc/openvpn/instance-*') as $filename) {
            $uuid = explode('.', explode('/var/etc/openvpn/instance-', $filename)[1])[0];
            if (!in_array($uuid, $instance_ids)) {
                if (!isset($to_clean[$uuid])) {
                    $to_clean[$uuid] = ['filenames' => [], 'stat' => []];
                }
                $to_clean[$uuid]['filenames'][] = $filename;
                if (str_ends_with($filename, '.stat')) {
                    $to_clean[$uuid]['stat'] = json_decode(file_get_contents($filename) ?? '', true) ?? [];
                }
            }
        }
        foreach ($to_clean as $uuid => $payload) {
            $pidfile = "/var/run/ovpn-instance-{$uuid}.pid";
            if (isvalidpid($pidfile)) {
                killbypid($pidfile);
            }
            @unlink($pidfile);
            if (is_array($payload['stat']) && !empty($payload['stat']['devname'])) {
                legacy_interface_destroy($payload['stat']['devname']);
            }
            foreach ($payload['filenames'] as $filename) {
                @unlink($filename);
            }
        }
    }
}
