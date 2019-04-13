<?php

/*
 * Copyright (C) 2018 Martin Wasley <martin@team-rebellion.net>
 * Copyright (C) 2016-2019 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2008 Seth Mos <seth.mos@dds.nl>
 * Copyright (C) 2010 Ermal Luçi
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
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

function dpinger_defaults()
{
    return array(
        'alert_interval' => '1',
        'interval' => '1',
        'latencyhigh' => '500',
        'latencylow' => '200',
        'loss_interval' => '2',
        'losshigh' => '20',
        'losslow' => '10',
        'time_period' => '60',
    );
}

function setup_gateways_monitor($verbose = false, $gwname = null)
{
    if ($verbose) {
        echo 'Setting up gateway monitors...';
        flush();
    }

    foreach (dpinger_processes() as $running_gwname => $process) {
        if (!empty($gwname) && $running_gwname != $gwname) {
            continue;
        }
        killbypid($process['pidfile'], 'TERM', true);
        @unlink($process['pidfile']);
        @unlink($process['socket']);
    }

    @mkdir('/var/db/rrd', 0775);
    @chown('/var/db/rrd', 'nobody');

    $gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());
    $monitor_ips = array();

    $dpinger_default = dpinger_defaults();
    foreach ($gateways->gatewaysIndexedByName(true) as $name => $gateway) {
        if (!empty($gwname) && $gwname != $name) {
            continue;
        } elseif (empty($gateway['monitor'])) {
            continue;
        } elseif (in_array($gateway['monitor'], $monitor_ips)) {
            /* if the monitor address is already used before, skip */
            continue;
        }

        /*
         * Interface IP is needed since dpinger will bind a socket to it.
         * However the config GUI should already have checked this and when
         * PPPoE is used the IP address is set to "dynamic". So using
         * is_ipaddrv4 * or is_ipaddrv6 to identify packet type would be
         * wrong, especially as further checks (that can cope with the
         * "dynamic" case) are present inside the if block. So using
         * $gateway['ipprotocol'] is the better option.
         */
        if ($gateway['ipprotocol'] == "inet") { // This is an IPv4 gateway...
            $gwifip = find_interface_ip($gateway['if']);
            if (!is_ipaddrv4($gwifip)) {
                continue; //Skip this target
            }

            /* flush the monitor unconditionally */
            if (is_ipaddrv4($gateway['gateway']) && $gateway['monitor'] != $gateway['gateway']) {
                log_error("Removing static route for monitor {$gateway['monitor']} via {$gateway['gateway']}");
                system_host_route($gateway['monitor'], $gateway['gateway'], true, false);
            }

            /* Do not monitor if such was requested */
            if (isset($gateway['disabled']) || isset($gateway['monitor_disable'])) {
                continue;
            }

            /*
             * If the gateway is the same as the monitor we do not add a
             * route as this will break the routing table.
             * Add static routes for each gateway with their monitor IP
             * not strictly necessary but is an added level of protection.
             */
            if (is_ipaddrv4($gateway['gateway']) && $gateway['monitor'] != $gateway['gateway']) {
                log_error("Adding static route for monitor {$gateway['monitor']} via {$gateway['gateway']}");
                system_host_route($gateway['monitor'], $gateway['gateway'], false, true);
            }
        } elseif ($gateway['ipprotocol'] == "inet6") { // This is an IPv6 gateway...
            if (is_linklocal($gateway['monitor'])) {
                /* link local monitor needs a link local address for the "src" part */
                $gwifip = find_interface_ipv6_ll($gateway['if']);
            } else {
                /* monitor is a routable address, so use a routable address for the "src" part */
                $gwifip = find_interface_ipv6($gateway['if']);
            }

            if (!is_ipaddrv6($gwifip)) {
                /* skip this target */
                continue;
            }

            /*
             * If gateway is a local link and 'monitor' is global routable
             * then the ICMP6 response would not find its way back home.
             */
            if (is_linklocal($gateway['monitor']) && strpos($gateway['monitor'], '%') === false) {
                $gateway['monitor'] .= "%{$gateway['if']}";
            }
            if (is_linklocal($gateway['gateway']) && strpos($gateway['gateway'], '%') === false) {
                $gateway['gateway'] .= "%{$gateway['if']}";
            }
            if (is_linklocal($gwifip) && strpos($gwifip, '%') === false) {
                $gwifip .= "%{$gateway['if']}";
            }

            /* flush the monitor unconditionally */
            if (is_ipaddrv6($gateway['gateway']) && $gateway['monitor'] != $gateway['gateway']) {
                log_error("Removing static route for monitor {$gateway['monitor']} via {$gateway['gateway']}");
                system_host_route($gateway['monitor'], $gateway['gateway'], true, false);
            }

            /* Do not monitor if such was requested */
            if (isset($gateway['disabled']) || isset($gateway['monitor_disable'])) {
                continue;
            }

            /*
             * If the gateway is the same as the monitor we do not add a
             * route as this will break the routing table.
             * Add static routes for each gateway with their monitor IP
             * not strictly necessary but is an added level of protection.
             */
            if (is_ipaddrv6($gateway['gateway']) && $gateway['monitor'] != $gateway['gateway']) {
                log_error("Adding static route for monitor {$gateway['monitor']} via {$gateway['gateway']}");
                system_host_route($gateway['monitor'], $gateway['gateway'], false, true);
            }
        } else {
            continue;
        }

        $monitor_ips[] = $gateway['monitor'];

        /*
         * Create custom RRD graph with suitable settings that
         * may differ from the daemon's standards.
         */
        rrd_create_gateway_quality("/var/db/rrd/{$gateway['name']}-quality.rrd");

        /* log warnings via syslog */
        $params  = '-S ';

        /* disable unused reporting thread */
        $params .= '-r 0 ';

        /* identifier */
        $params .= exec_safe('-i %s ', $name);

        /* bind src address */
        $params .= exec_safe('-B %s ', $gwifip);

        /* PID filename */
        $params .= exec_safe('-p %s ', "/var/run/dpinger_{$name}.pid");

        /* status socket */
        $params .= exec_safe('-u %s ', "/var/run/dpinger_{$name}.sock");

        /* command to run on alarm */
        $params .= '-C /usr/local/etc/rc.monitor ';

        $params .= exec_safe(
            '-s %ss ',
            isset($gateway['interval']) && is_numeric($gateway['interval']) ?
                $gateway['interval'] : $dpinger_default['interval']
        );

        $params .= exec_safe(
            '-l %ss ',
            isset($gateway['loss_interval']) && is_numeric($gateway['loss_interval']) ?
                $gateway['loss_interval'] : $dpinger_default['loss_interval']
        );

        $params .= exec_safe(
            '-t %ss ',
            isset($gateway['time_period']) && is_numeric($gateway['time_period']) ?
                $gateway['time_period'] : $dpinger_default['time_period']
        );

        $params .= exec_safe(
            '-A %ss ',
            isset($gateway['alert_interval']) && is_numeric($gateway['alert_interval']) ?
                $gateway['alert_interval'] : $dpinger_default['alert_interval']
        );

        $params .= exec_safe(
            '-D %s ',
            isset($gateway['latencyhigh']) && is_numeric($gateway['latencyhigh']) ?
                $gateway['latencyhigh'] : $dpinger_default['latencyhigh']
        );

        $params .= exec_safe(
            '-L %s ',
            isset($gateway['losshigh']) && is_numeric($gateway['losshigh']) ?
                $gateway['losshigh'] : $dpinger_default['losshigh']
        );

        $params .= exec_safe('%s ', $gateway['monitor']);

        /* daemonises forground mode because background mode does not work? */
        mwexec_bg("/usr/local/bin/dpinger -f {$params}");
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function return_gateways_status()
{
    $status = array();

    foreach (config_read_array('gateways', 'gateway_item') as $gwitem) {
        if (isset($gwitem['disabled'])) {
            continue;
        }

        $gwstatus = isset($gwitem['monitor_disable']) ? 'none' : 'down';

        if (isset($gwitem['force_down'])) {
            $gwstatus = 'force_down';
        }

        $status[$gwitem['name']] = array(
            'name' => $gwitem['name'],
            'status' => $gwstatus,
            'stddev' => '~',
            'delay' => '~',
            'loss' => '~',
        );
    }

    foreach (dpinger_processes() as $gwname => $proc) {
        if (!isset($status[$gwname])) {
            continue;
        }

        $fp = @stream_socket_client("unix://{$proc['socket']}", $errno, $errstr, 3);
        if (!$fp) {
            continue;
        }

        $dinfo = '';
        while (!feof($fp)) {
            $dinfo .= fgets($fp, 1024);
        }

        fclose($fp);

        $r = array();

        list($r['gwname'], $r['latency_avg'], $r['latency_stddev'], $r['loss']) =
            explode(' ', preg_replace('/\n/', '', $dinfo));

        /* not yet ready, act like nothing was returned */
        if ($r['latency_stddev'] == '0' && $r['loss'] == '0') {
            continue;
        }

        $r['latency_stddev'] = round($r['latency_stddev'] / 1000, 1);
        $r['latency_avg'] = round($r['latency_avg'] / 1000, 1);
        $r['status'] = $status[$gwname]['status'];

        if ($r['status'] != 'force_down') {
            $settings = dpinger_defaults();

            $keys = array('latencylow', 'latencyhigh', 'losslow', 'losshigh');

            /* Replace default values by user-defined */
            foreach ($keys as $key) {
                if (isset($gateways_arr[$gwname][$key]) && is_numeric($gateways_arr[$gwname][$key])) {
                    $settings[$key] = $gateways_arr[$gwname][$key];
                }
            }

            if ($r['latency_avg'] > $settings['latencyhigh']) {
                $r['status'] = 'down';
            } elseif ($r['loss'] > $settings['losshigh']) {
                $r['status'] = 'down';
            } elseif ($r['latency_avg'] > $settings['latencylow']) {
                $r['status'] = 'delay';
            } elseif ($r['loss'] > $settings['losslow']) {
                $r['status'] = 'loss';
            } else {
                $r['status'] = 'none';
            }
        }

        $status[$gwname] = array(
            'delay' => sprintf('%0.1f ms', empty($r['latency_avg']) ? 0.0 : round($r['latency_avg'], 1)),
            'stddev' => sprintf('%0.1f ms', empty($r['latency_stddev']) ? 0.0 : round($r['latency_stddev'], 1)),
            'loss' => sprintf('%0.1f %%', empty($r['loss']) ? 0.0 : round($r['loss'], 1)),
            'status' => $r['status'],
            'name' => $gwname,
        );
    }

    return $status;
}

function return_down_gateways()
{
    $result = array();
    foreach (return_gateways_status() as $gwname => $stat) {
        if (strpos($stat['status'], 'down') !== false) {
            $result[] = $gwname;
        }
    }
    return $result ;
}


/*
 * Return an array with all gateway groups with name as key
 * All gateway groups will be processed before returning the array.
 */
function return_gateway_groups_array()
{
    global $config;

    $gateways_status = return_gateways_status();
    $gateways_arr = (new \OPNsense\Routing\Gateways(legacy_interfaces_details()))->gatewaysIndexedByName();
    $gateway_groups_array = array();

    if (isset($config['gateways']['gateway_group'])) {
        $carplist = get_configured_carp_interface_list();
        foreach ($config['gateways']['gateway_group'] as $group) {
            /* create array with group gateways members separated by tier */
            $tiers = array();
            $tiers_online = 0;
            foreach ($group['item'] as $item) {
                list($gwname, $tier, $vipname) = explode("|", $item);
                if (!isset($tiers[$tier])) {
                    $tiers[$tier] = array();
                }

                $gwup = false;
                if (!empty($gateways_status[$gwname])) {
                    $error = get_gateway_error($gateways_status[$gwname]['status'], $gwname, $group);
                    if ($error === null) {
                        $tiers_online++;
                        $gwup = true;
                    }
                } elseif (isset($gateways_arr[$gwname]['monitor_disable'])) {
                    $gwup = true;
                }
                $vip = null;
                if (!empty($carplist[$vipname]) && is_ipaddr($carplist[$vipname])) {
                    $vip = $vipname;
                }

                $tiers[$tier][] = array("up" => $gwup, "gwname" => $gwname, "vip" => $vip);
            }

            /* sort the tiers array by the tier key */
            ksort($tiers);

            /* we do not really foreach the tiers as we stop after the first tier */
            foreach ($tiers as $tieridx => $tier) {
                /* process all gateways in this tier */
                foreach ($tier as $member_item) {
                    $member = $member_item['gwname'];
                    if (($member_item['up'] || $tiers_online == 0) && isset($gateways_arr[$member])) {
                        /* determine interface gateway */
                        $gateway = $gateways_arr[$member];
                        $gatewayip = '';
                        if (is_ipaddr($gateway['gateway'])) {
                            $gatewayip = $gateway['gateway'];
                        }

                        if (!empty($gateway['interface'])) {
                            if (empty($gateway_groups_array[$group['name']])) {
                                $gateway_groups_array[$group['name']] = array();
                            }
                            if (is_ipaddr($gatewayip)) {
                                $groupmember = array();
                                $groupmember['int']  = $gateway['interface'];
                                $groupmember['gwip']  = $gatewayip;
                                $groupmember['weight']  = isset($gateway['weight']) ? $gateway['weight'] : 1;
                                if (!empty($member_item['vip'])) {
                                    $groupmember['vip'] = $member_item['vip'];
                                }
                                $gateway_groups_array[$group['name']][] = $groupmember;
                            }
                        }
                    }
                }

                /* we should have the 1st available tier now, exit stage left */
                if (!empty($gateway_groups_array[$group['name']])) {
                    break;
                } else {
                    log_error("GATEWAYS: Group {$group['name']} did not have any gateways up on tier {$tieridx}!");
                }
            }
        }
    }

    return $gateway_groups_array;
}

function dpinger_processes()
{
    $result = array();

    $pidfiles = glob('/var/run/dpinger_*.pid');
    if ($pidfiles === false) {
        return $result;
    }

    foreach ($pidfiles as $pidfile) {
        if (!isvalidpid($pidfile)) {
            /* spare caller from trying to read a stale socket later on */
            continue;
        }
        if (preg_match('/^dpinger_(.+)\.pid$/', basename($pidfile), $matches)) {
            $socket_file = preg_replace('/\.pid$/', '.sock', $pidfile);
            $result[$matches[1]] = array('socket' => $socket_file, 'pidfile' => $pidfile);
        }
    }

    return $result;
}

/**
 * @param string $status
 * @param string $gwname
 * @param array $group
 * @return string|null A string detailing the error if there is one, null if there is no error.
 */
function get_gateway_error(string $status, string $gwname, array $group)
{
    if (stristr($status, 'down') !== false) {
        return sprintf(gettext('MONITOR: %s is down, removing from routing group %s'), $gwname, $group['name']);
    } elseif (stristr($status, 'loss') !== false && stristr($group['trigger'], 'loss') !== false) {
        return sprintf(gettext('MONITOR: %s has packet loss, removing from routing group %s'), $gwname, $group['name']);
    } elseif (stristr($status, 'delay') !== false && stristr($group['trigger'], 'latency') !== false) {
        return sprintf(gettext('MONITOR: %s has high latency, removing from routing group %s'), $gwname, $group['name']);
    } else {
        return null;
    }
}
