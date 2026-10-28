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
use OPNsense\Core\Syslog;
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

    /**
     * XXX: config.inc product needs to be migrated to MVC scope in order to reuse it here and in SystemBootingStatus
     */
    private function isBooting()
    {
        $result = false;
        $fp = fopen('/var/run/booting', 'a+e');
        if ($fp) {
            $result = !flock($fp, LOCK_SH | LOCK_NB);
            fclose($fp);
        }
        return $result;
    }

    /**
     * @param string $ifname device name
     * @param string $ipproto [inet|inet6] to check
     * @return bool true when requested interface has an address set for the requested protocol
     */
    private function interfaceHasAddress($ifname, $ipproto)
    {
        $tmp = Shell::shell_safe('/sbin/ifconfig %s 2>/dev/null', [$ifname], true);
        foreach ($tmp as $line) {
            if (strpos($line, "\t".$ipproto." ") !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool if gateway switching is enabled
     */
    private function gwSwitchEnabled()
    {
        return !empty((string)Config::getInstance()->object()?->system?->gw_switch_default);
    }


    /**
     * @return OPNsense\Routing\Gateways
     */
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
     * XXX: to be removed
     */
    public static function cleanup()
    {
        $fobj = new FileObject(self::$todo_file, 'a+', 0600, LOCK_EX, 'wwwonly:wheel');
        foreach (($fobj->readJson() ?? []) as $record) {
            if (empty($record['network'])) {
                continue;
            }
            $inet = strpos($record['network'], ':') !== false ? 'inet6' : 'inet';
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
        $parts = explode('%', $tmp[0]);
        $addr = inet_pton($parts[0]);
        if ($addr !== false) {
            if (count($tmp) == 2) {
                return inet_ntop($addr) . '/' . $tmp[1];
            } elseif (count($parts) == 2) {
                /* ipv6 gateways require interface */
                return inet_ntop($addr) . '%' . $parts[1];
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
            } elseif ($parts[0] === 'default') {
                $parts[0] = strpos($parts[1], ':') === false ? '0.0.0.0/0' : '::/0';
            }
            /* if we would tag our routes with RTF_PROTO2, we could filter them here (Flags include 2) */

            if (($net = self::normalize($parts[0])) !== false) {
                if (strpos($net, '/') === false) {
                    $net .= strpos($net, ':') === false ? '/32' : '/128'; /* host to net */
                }
                if (!isset($result[$net])) {
                    $result[$net] = [];
                }
                if (($addr = self::normalize($parts[1])) !== false) {
                    $result[$net][] = $addr;
                } else {
                    $result[$net][] = $parts[5];
                }
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
        /**
         * start with default gateways as static routes, which need to know the monitor status when
         * default gateway switching is enabled and we're not booting.
         */
        $down_gateways = [];
        if ($this->gwSwitchEnabled() && !$this->isBooting()) {
            foreach ($this->getMonitorStatus() as $gwname => $gwstatus) {
                /* for clarity: this also matches 'force_down' */
                if (strpos($gwstatus['status'], 'down') !== false) {
                    $down_gateways[] = $gwname;
                }
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
                $result[$net] = [['gatewayip' => $gateway['gateway'] . '%' . $gateway['if']]];
            } else {
                $result[$net] = [['gatewayip' => $gateway['gateway']]];
            }
            $result[$net][0]['network'] = $net;
            $result[$net][0]['interface'] = $gateway['interface'];
            $result[$net][0]['if'] = $gateway['if'];
            $result[$net][0]['ipproto'] = $ipproto;
            $result[$net][0]['type'] = 'default';
        }

        /* next the ones we configured in System: Routes */
        $gateways = $this->getGateways()->gatewaysIndexedByName(false, true);
        foreach (Config::getInstance()->object()?->staticroutes->children() as $route) {
            if ((string)$route->enabled !== '1') {
                continue;
            } elseif (isset($gateways[(string)$route->gateway])) {
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
                    'ipproto' => $ipproto,
                    'gwname' => (string)$route->gateway,
                    'type' => 'static'
                ];
            }
        }
        /* XXX: if we do want to plug routes from other sources, we likely need to add some sort of a hook here */

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

    /**
     * sync expected configured route state with actual kernel routes
     * @param ?string $family [inet|inet6] selective sync when requested
     * @param ?array list of interfaces to consider
     */
    public function syncRoutes($family=null, $interface_map = null)
    {
        $syslog = new Syslog('routing', null, LOG_LOCAL2);
        $planned = $this->staticRoutes($family);
        if ($family === null) {
            /**
             * Stash routes to consider owned by us, only hold the lock while processing the list.
             */
            $fobj = new FileObject(self::$todo_file, 'a+', 0600, LOCK_EX, 'wwwonly:wheel');
            foreach (($fobj->readJson() ?? []) as $record) {
                if (($net = self::normalize($record['network'] ?? '')) && !isset($planned[$net])) {
                    $planned[$net] = [];
                }
            }
            $fobj->truncate(0);
            unset($fobj); /* unlock */
        }
        $current = $this->installedRoutes($family);

        foreach ($planned as $net => $routes) {
            $gwips = [];
            foreach ($routes as $route) {
                $gwips[] = empty($route['fargw']) ? $route['gatewayip'] : $route['interface'];
            }
            if (isset($current[$net])) {
                foreach ($current[$net] as $iroute) {
                    if (count($routes) == 1 && $routes[0]['type'] == 'default') {
                        /**
                         * We only "own" the default gateway for configured interfaces, without configuration,
                         * the default is excluded, which logically means (when no other route is overlapping)
                         * the existing value will be kept.
                         */
                        if (!$this->interfaceHasAddress($routes[0]['if'], $routes[0]['ipproto'])) {
                            unset($planned[$net]);
                            continue;
                        } elseif (
                            !$this->gwSwitchEnabled() &&
                            !empty($interface_map) &&
                            !in_array($route['interface'], $interface_map)
                        ) {
                            unset($planned[$net]);
                            continue; /* specific interface requested, but not this one */
                        }
                    }
                    if (!in_array($iroute, $gwips)) {
                        /* destination found, but not requested */
                        $syslog->info(sprintf("delete unconfigured route %s via %s", $net, $iroute));
                        if (strpos($iroute, ':') !== false) {
                            Shell::run_safe('/sbin/route delete -inet6 %s %s',[$net, $iroute]);
                        } elseif (strpos($iroute, '.') !== false) {
                            Shell::run_safe('/sbin/route delete -inet %s %s',[$net, $iroute]);
                        } else {
                            Shell::run_safe('/sbin/route delete %s -interface %s',[$net, $iroute]);
                        }
                    }
                }
            }
            $cgateways = $current[$net] ?? [];
            foreach ($routes as $route) {
                if (!empty($interface_map) && !in_array($route['interface'], $interface_map)) {
                    continue; /* not requested */
                } elseif (
                    in_array($route['gatewayip'], $cgateways) ||
                    (!empty($route['interface']) && in_array($route['interface'], $cgateways))
                ) {
                    continue; /* route already installed */
                }
                /* RFC 5549: gateway protocol may differ, gateway determines proto */
                $ipproto = strpos($route['gatewayip'], ':') !== false ? 'inet6' : 'inet';
                if (in_array($route['gwname'] ?? '', ['Null4', 'Null6'])) {
                    $syslog->info(sprintf("blackhole %s route %s", $ipproto, $net));
                    Shell::run_safe('/sbin/route add -%s %s -blackhole',[$ipproto, $net]); /* blackhole */
                } elseif (!empty($route['fargw'])) {
                    $syslog->info(sprintf("add %s route %s via %s (fargw)", $ipproto, $net, $route['interface']));
                    Shell::run_safe('/sbin/route add -%s %s -interface %s',[$ipproto, $net, $route['interface']]);
                } else {
                    $syslog->info(sprintf("add %s route %s via %s", $ipproto, $net, $route['gatewayip']));
                    Shell::run_safe('/sbin/route add -%s %s %s',[$ipproto, $net, $route['gatewayip']]);
                }
            }
        }
    }
}
