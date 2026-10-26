<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\System;

use OPNsense\Core\Config;
use OPNsense\Core\FileObject;
use OPNsense\Core\Shell;
use OPNsense\Firewall\Util;
use OPNsense\Routing\Gateways;

/**
 * Wrapper class around static routes and the system representation of them.
 * Needs a trampoline for part of the functionality (root access)
 */
class StaticRoute
{
    /**
     * In progress queue
     */
    private static $todo_file = '/tmp/.static_routes.todo';

    /**
     *
     */
    private $gateways = null;

    private function getGateways()
    {
        if ($this->gateways === null) {
            $this->gateways = new Gateways();
        }
        return $this->gateways;
    }

    /**
     * When changing or removing networks via the user-interface we need to keep track of them
     * for simplicity we assume that a network touched via the UI is owned by it.
     *
     * This in practice means if we're adding a static network which also has an anchor via an advanced routing
     * or vpn deamon, we will cleanup those routes and install ours in place.
     *
     * In theory we could add a "safety fence" here by tagging all our routes with RTF_PROTO1 or RTF_PROTO2, but
     * that's also not a 100% guarantee. Previous implementations of our static routing code behaved simalarly.
     *
     * @param string $network network we claim ownership
     */
    public static function claim($network)
    {
        $fobj = new FileObject(self::$todo_file, 'a+', 0600, LOCK_EX, 'wwwonly:wheel');
        $data = $fobj->readJson() ?? [];
        $tmp = ['network' => $network];
        if (!in_array($tmp, $data)) {
            $data[] = $tmp;
        }
        $fobj->truncate(0)->writeJson($data);
    }

    /**
     * Backwards compatible cleanup of "claimed" networks
     */
    public static function cleanup()
    {
        $fobj = new FileObject(self::$todo_file, 'a+', 0600, LOCK_EX, 'wwwonly:wheel');
        foreach (($fobj->readJson() ?? []) as $record) {
            if (empty($record['network'])) {
                continue;
            }
            $inet = is_subnetv6($record['network']) ? 'inet6' : 'inet';
            Shell::run_safe('/sbin/route delete -%s %s', [$inet, $record['network']], true);
        }
        $fobj->truncate(0);
        unset($fobj); /* unlock */
    }

    /**
     * normalize network
     * @param $network ipv4/ipv6 network or address
     * @return string|bool
     */
    private static function normalize($network)
    {
        $tmp = explode('/', $network);
        $addr = inet_pton(explode('%', $tmp[0])[0]);
        if ($addr !== false) {
            if (count($tmp) == 2) {
                return inet_ntop($addr) . '/' . $tmp[1];
            } else {
                return inet_ntop($addr);
            }
        }
        return false;
    }

    /**
     * @param ?string $family inet/inet6 or empty for all
     * @return array installed routes
     */
    public static function installedRoutes($family = null)
    {
        $result = [];
        foreach (Shell::shell_safe('netstat -nrW', [], true) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 4) {
                continue;
            } elseif ($family === 'inet' && strpos($parts[1], ':') !== false) {
                continue;
            } elseif ($family === 'inet6' && strpos($parts[1], ':') === false) {
                continue;
            } elseif (strpos($parts[1], 'link#') === 0 && strpos($parts[2], 'B') === false) {
                continue; /* ignore link routes, except blackholed [NullX] */
            }
            /* if we would tag our routes with RTF_PROTO2, we could filter them here (Flags include 2) */

            if (($net = self::normalize($parts[0])) !== false) {
                if (!isset($result[$net])) {
                    $result[$net] = [];
                }
                $result[$net][] = self::normalize($parts[1]);
            }
        }
        return $result;
    }

    /**
     * @param ?string $family inet/inet6 or empty for all
     * @return array configured static routes, indexed by normalized network
     */
    public function staticRoutes($family = null)
    {
        $result = [];
        /* start with default gateways as static routes, which need to know the monitor status */
        $down_gateways = [];
        foreach ($this->getMonitorStatus() as $gwname => $gwstatus) {
            /* for clarity: this also matches 'force_down' */
            if (strpos($gwstatus['status'], 'down') !== false) {
                $down_gateways[] = $gwname;
            }
        }
        foreach (['inet' => 'ipv4', 'inet6' => 'ipv6'] as $ipproto => $type) {
            if ($family !== null && $family !== $ipproto) {
                continue;
            }
            $net = $ipproto === 'inet' ? '0.0.0.0/0' : '::/0';
            $gateway = $this->getGateways()->getDefaultGW($down_gateways, $ipproto);
            if (!Util::isIpAddress($gateway['gateway'] ?? '')) {
                continue; /* ignore invalid */
            } elseif (Util::isLinkLocal($gateway['gateway'])) {
                $result[$net] = [$gateway['gateway'] . '%' . $gateway['if']];
            } else {
                $result[$net] = [$gateway['gateway']];
            }
        }

        /* next the ones we configured in System: Routes */
        $gateways = $this->getGateways()->gatewaysIndexedByName(false, true);
        foreach (Config::getInstance()->object()?->staticroutes->children() as $route) {
            if (isset($gateways[(string)$route->gateway])) {
                $ipproto = strpos((string)$route->network, ':') !== false ? 'inet6' : 'inet';
                if (!Util::isSubnet((string)$route->network)) {
                    /* invalid network */
                    continue;
                } elseif ($family !== null && $family !== $ipproto) {
                    continue;
                }
                $net = self::normalize((string)$route->network);
                $gateway = $gateways[(string)$route->gateway];
                if (!isset($result[$net])) {
                    $result[$net] = [];
                }
                $result[$net][] = [
                    'gatewayip' => $gateway['gateway'] ?? '',
                    'interface' => $gateway['if'],
                    'fargw' => !empty($gateway['fargw']),
                    'gwipprotocol' => $gateway['ipprotocol'],
                    'network' => $net,
                    'ipproto' => $ipproto
                ];
            }
        }
        return $result;
    }

    /**
     * @return array list of monitors
     */
    public static function getMonitorProcesses()
    {
        $result = [];
        foreach (glob('/var/run/dpinger_*.pid') ?? [] as $pidfile) {
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

    /**
     * @return monitor status of configured gateways
     */
    public function getMonitorStatus()
    {
        foreach ($this->getGateways()->gatewaysIndexedByName() as $name => $gwitem) {
            foreach (['monitor', 'gateway'] as $key) {
                /* add link-local scope where missing */
                if (!empty($gwitem[$key]) && Util::isLinkLocal($gwitem[$key]) && strpos($gwitem[$key], '%') === false) {
                    $gwitem[$key] .= '%' . $gwitem['if'];
                }
            }
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
            if ($latency_avg == '0' && $latency_stddev == '0' && $loss == '0') {
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
}
