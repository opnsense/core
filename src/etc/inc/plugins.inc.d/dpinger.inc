<?php

/*
 * Copyright (C) 2020 Deciso B.V.
 * Copyright (C) 2018 Martin Wasley <martin@team-rebellion.net>
 * Copyright (C) 2016-2023 Franco Fichtner <franco@opnsense.org>
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

function dpinger_services()
{
    $services = [];

    foreach (dpinger_instances() as $name => $gateway) {
        $pconfig = [];
        $pconfig['description'] = sprintf(gettext('Gateway monitor (%s)'), $gateway['name']);
        $pconfig['php']['restart'] = ['dpinger_configure_do'];
        $pconfig['php']['start'] = ['dpinger_configure_do'];
        $pconfig['pidfile'] = "/var/run/dpinger_{$gateway['name']}.pid";
        $pconfig['php']['args'] = ['verbose', 'id'];
        $pconfig['name'] = 'dpinger';
        $pconfig['verbose'] = false;
        $pconfig['id'] = $gateway['name'];
        $services[] = $pconfig;
    }

    if (count($services)) {
        $pconfig = [];
        $pconfig['description'] = gettext('Gateway monitor watcher');
        $pconfig['php']['restart'] = ['dpinger_configure_do'];
        $pconfig['php']['start'] = ['dpinger_configure_do'];
        $pconfig['pidfile'] = '/var/run/gateway_watcher.pid';
        $pconfig['php']['args'] = ['verbose', 'id'];
        $pconfig['name'] = 'dpinger';
        $pconfig['verbose'] = false;
        $pconfig['id'] = ':watcher:';
        $pconfig['locked'] = true;
        /* add as first entry which is used as "global" control */
        array_unshift($services, $pconfig);
    }

    return $services;
}

function dpinger_configure()
{
    return [
        'monitor' => ['dpinger_configure_do:2'],
    ];
}

function dpinger_syslog()
{
    $logfacilities = [];

    $logfacilities['gateways'] = ['facility' => ['dpinger']];

    return $logfacilities;
}

function dpinger_host_routes()
{
    $routes = [];

    foreach (dpinger_instances() as $gateway) {
        if (!empty($gateway['monitor_noroute'])) {
            /* no need to register */
            continue;
        } elseif (empty($gateway['gateway'])) {
            /* previously reported as empty */
            continue;
        } elseif (isset($routes[$gateway['monitor']])) {
            log_msg("Duplicated monitor route ignored for {$gateway['monitor']} on {$gateway['interface']}", LOG_WARNING);
            continue;
        }

        $routes[$gateway['monitor']] = $gateway['gateway'];
    }

    return $routes;
}

function dpinger_instances($extended = false)
{
    $instances = [];

    foreach ((new \OPNsense\Routing\Gateways())->gatewaysIndexedByName() as $name => $gateway) {
        if (!empty($gateway['monitor_disable']) && !$extended) {
            /* do not monitor if such was requested */
            continue;
        }

        foreach (['monitor', 'gateway'] as $key) {
            /* add link-local scope where missing */
            if (!empty($gateway[$key]) && is_linklocal($gateway[$key]) && strpos($gateway[$key], '%') === false) {
                $gateway[$key] .= '%' . $gateway['if'];
            }
        }

        $instances[$name] = $gateway;
    }

    return $instances;
}

function dpinger_configure_do($verbose = false, $gwname_map = null)
{
    if (!plugins_argument_map($gwname_map)) {
        return;
    }

    service_log(sprintf('Setting up gateway monitor%s...', empty($gwname_map) ? '' : ' for ' . join(', ', $gwname_map)), $verbose);

    foreach (dpinger_processes() as $running_gwname => $proc) {
        if (!empty($gwname_map) && !in_array($running_gwname, $gwname_map)) {
            continue;
        }
        if (isvalidpid($proc['pidfile'])) {
            killbypid($proc['pidfile']);
        }
    }

    if (!empty($gwname_map) && in_array(':watcher:', $gwname_map)) {
        /* allow the watcher to be restarted as well */
        killbypid('/var/run/gateway_watcher.pid');
    }

    $ifconfig_details = legacy_interfaces_details();
    $routes = [];

    foreach (dpinger_instances() as $name => $gateway) {
        if (!empty($gwname_map) && !in_array($name, $gwname_map)) {
            continue;
        }

        foreach (['monitor', 'gateway'] as $key) {
            if (empty($gateway[$key])) {
                log_msg("Skipping gateway {$name} due to empty '{$key}' property.", LOG_WARNING);
                continue;
            }
        }

        $gwifip = null;

        if ($gateway['ipprotocol'] == 'inet') {
            if (is_ipaddrv4($gateway['gateway'])) {
                foreach (interfaces_addresses($gateway['interface'], false, $ifconfig_details) as $addr) {
                    /* explicitly do not require $addr['alias'] to be true here */
                    if ($addr['family'] != 'inet') {
                        continue;
                    }

                    $network = gen_subnet($addr['address'], $addr['bits']) . "/{$addr['bits']}";

                    if (ip_in_subnet($gateway['gateway'], $network)) {
                        $gwifip = $addr['address'];
                        break;
                    }
                }
            }

            if (empty($gwifip)) {
                list ($gwifip) = interfaces_primary_address($gateway['interface'], $ifconfig_details);
                if (!empty($gwifip) && is_ipaddrv4($gateway['gateway'])) {
                    log_msg(sprintf('Chose to bind %s on %s since we could not find a proper match.', $name, $gwifip));
                }
            }

            if (empty($gwifip)) {
                log_msg(sprintf('The required %s IPv4 interface address could not be found, skipping.', $name), LOG_WARNING);
                continue;
            }
        } elseif ($gateway['ipprotocol'] == 'inet6') {
            if (is_linklocal($gateway['monitor'])) {
                /* link local monitor needs a link local address for the "src" part */
                list ($gwifip) = interfaces_scoped_address6($gateway['interface'], $ifconfig_details);
            } else {
                list ($gwifip) = interfaces_routed_address6($gateway['interface'], $ifconfig_details);
            }

            if (empty($gwifip) && is_ipaddrv6($gateway['gateway'])) {
                foreach (interfaces_addresses($gateway['interface'], false, $ifconfig_details) as $addr) {
                    if ($addr['family'] != 'inet6' || !$addr['alias']) {
                        continue;
                    }

                    $networkv6 = gen_subnetv6($addr['address'], $addr['bits']) . "/{$addr['bits']}";

                    if (ip_in_subnet($gateway['gateway'], $networkv6)) {
                        $gwifip = $addr['address'];
                        break;
                    }
                }
            }

            if (empty($gwifip)) {
                log_msg(sprintf('The required %s IPv6 interface address could not be found, skipping.', $name), LOG_WARNING);
                continue;
            }
        } else {
            log_msg(sprintf('Unknown address family "%s" during monitor setup', $gateway['ipprotocol']), LOG_ERR);
            continue;
        }

        if (!empty($gateway['gateway']) && empty($gateway['monitor_noroute'])) {
            if (isset($routes[$gateway['monitor']])) {
                log_msg("Duplicated monitor route ignored for {$gateway['monitor']} on {$gateway['interface']}", LOG_WARNING);
            } else {
                system_host_route($gateway['monitor'], $gateway['gateway']);
                $routes[$gateway['monitor']] = $gateway['gateway'];
            }
        }

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

        foreach (
            [
            'interval' => '-s %ss ',
            'loss_interval' => '-l %ss ',
            'time_period' => '-t %ss ',
            'data_length' => '-d %s '
            ] as $pname => $ppattern
        ) {
            $params .= exec_safe($ppattern, $gateway["current_" . $pname]);
        }
        $params .= exec_safe('%s ', $gateway['monitor']);

        /* foreground mode in background to deal with tentative connectivity */
        mwexec_bg("/usr/local/bin/dpinger -f {$params}");
    }

    if (count(dpinger_services())) {
        if (isvalidpid('/var/run/gateway_watcher.pid')) {
            /* indicate that the configuration needs a reload */
            killbypid('/var/run/gateway_watcher.pid', 'HUP');
        } else {
            /* use a separate script to produce the monitor alerts which runs forever */
            mwexecf(
                '/usr/sbin/daemon -f -p %s /usr/local/opnsense/scripts/routes/gateway_watcher.php %s',
                ['/var/run/gateway_watcher.pid', 'interface routes alarm']
            );
        }
    } else {
        killbypid('/var/run/gateway_watcher.pid');
    }

    service_log("done.\n", $verbose);
}

function dpinger_run()
{
    return [
        'host_routes' => 'dpinger_host_routes',
        'return_gateways_status' => 'dpinger_status',
        'return_gateways_watcher' => 'dpinger_watcher',
    ];
}

function dpinger_status()
{
    $procs = dpinger_processes();
    $status = [];

    foreach (dpinger_instances(true) as $gwitem) {
        /* we seem to be concerned about disabled monitors just because of force_down */
        $gwstatus = !empty($gwitem['monitor_disable']) ? 'none' : 'down';
        $gwname = $gwitem['name'];

        $status[$gwname] = $report = [
            'status' => !empty($gwitem['force_down']) ? 'force_down' : $gwstatus,
            /* grab the runtime monitor from the instance if available */
            'monitor' => !empty($gwitem['monitor']) ? $gwitem['monitor'] : '~',
            'gateway' => $gwitem['gateway'] ?? '',
            'monitor_killstates' => $gwitem['monitor_killstates'] ?? '0',
            'monitor_killstates_priority' => $gwitem['monitor_killstates_priority'] ?? '0',
            'priority' => $gwitem['priority'] ?? '255',
            'ipprotocol' => $gwitem['ipprotocol'],
            'name' => $gwname,
            'stddev' => '~',
            'delay' => '~',
            'loss' => '~',
        ];

        if (!isset($procs[$gwitem['name']])) {
            continue;
        }

        $fp = @stream_socket_client("unix://{$procs[$gwname]['socket']}", $errno, $errstr, 1);
        if (!$fp) {
            continue;
        }

        $dinfo = '';
        while (!feof($fp)) {
            $dinfo .= fgets($fp, 1024);
        }

        fclose($fp);

        list(, $latency_avg, $latency_stddev, $loss) = explode(' ', preg_replace('/\n/', '', $dinfo));
        if ($latency_stddev == '0' && $loss == '0') {
            continue;
        }

        $latency_stddev = round($latency_stddev / 1000, 1);
        $latency_avg = round($latency_avg / 1000, 1);

        if (isset($gwitem['current_losshigh']) && $report['status'] != 'force_down') {
            if ($latency_avg > $gwitem['current_latencyhigh'] || $loss > $gwitem['current_losshigh']) {
                $report['status'] = 'down';
            } elseif ($latency_avg > $gwitem['current_latencylow'] && $loss > $gwitem['current_losslow']) {
                $report['status'] = 'delay+loss';
            } elseif ($latency_avg > $gwitem['current_latencylow']) {
                $report['status'] = 'delay';
            } elseif ($loss > $gwitem['current_losslow']) {
                $report['status'] = 'loss';
            } else {
                $report['status'] = 'none';
            }
        }

        $report['delay'] = sprintf('%0.1f ms', empty($latency_avg) ? 0.0 : round($latency_avg, 1));
        $report['stddev'] = sprintf('%0.1f ms', empty($latency_stddev) ? 0.0 : round($latency_stddev, 1));
        $report['loss'] = sprintf('%0.1f %%', empty($loss) ? 0.0 : round($loss, 1));

        /* rewrite report using gathered information */
        $status[$gwname] = $report;
    }

    return $status;
}

function dpinger_watcher()
{
    $cache_file = '/tmp/gateways.status';

    if (file_exists($cache_file) && isvalidpid('/var/run/gateway_watcher.pid')) {
        try {
            $result = @unserialize(file_get_contents($cache_file));
            if ($result !== false) {
                return $result;
            }
        } catch (Throwable $e) {
            /* empty result */
        }
    }

    return [];
}

function dpinger_processes()
{
    $result = [];

    $pidfiles = glob('/var/run/dpinger_*.pid');
    if ($pidfiles === false) {
        return $result;
    }

    foreach ($pidfiles as $pidfile) {
        if (preg_match('/^dpinger_(.+)\.pid$/', basename($pidfile), $matches)) {
            $socket_file = preg_replace('/\.pid$/', '.sock', $pidfile);
            $result[$matches[1]] = [
                'socket' => $socket_file,
                'pidfile' => $pidfile,
            ];
        }
    }

    return $result;
}
