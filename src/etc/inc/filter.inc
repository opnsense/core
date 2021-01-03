<?php

/*
 * Copyright (C) 2017 Deciso B.V.
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2006 Peter Allgeyer <allgeyer@web.de>
 * Copyright (C) 2008-2010 Ermal Luçi
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

require_once('filter.lib.inc');

function is_bogonsv6_used()
{
    global $config;

    /*
     * Only use bogonsv6 table if IPv6 Allow is on, and at least
     * one enabled interface also has "blockbogons" enabled.
     */
    $usebogonsv6 = false;

    if (isset($config['system']['ipv6allow']) && isset($config['interfaces'])) {
        foreach ($config['interfaces'] as $ifacedata) {
            if (isset($ifacedata['enable']) && isset($ifacedata['blockbogons'])) {
                $usebogonsv6 = true;
                break;
            }
        }
    }

    return $usebogonsv6;
}

/* sort by interface only, retain the original order of rules that apply to
   the same interface */
function filter_rules_sort()
{
    global $config;

    /* mark each rule with the sequence number (to retain the order while sorting) */
    for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
        $config['filter']['rule'][$i]['seq'] = $i;
    }
    usort($config['filter']['rule'], function ($a, $b) {
        if (isset($a['floating']) && isset($b['floating'])) {
            return $a['seq'] - $b['seq'];
        } elseif (isset($a['floating'])) {
            return -1;
        } elseif (isset($b['floating'])) {
            return 1;
        } elseif ($a['interface'] == $b['interface']) {
            return $a['seq'] - $b['seq'];
        } elseif ($a['interface'] == $b['interface']) {
            return 0;
        } elseif ($a['interface'] == 'wan') {
            return -1;
        } elseif ($b['interface'] == 'wan') {
            return 1;
        } elseif ($a['interface'] == 'lan') {
            return -1;
        } elseif ($b['interface'] == 'lan') {
            return 1;
        } else {
            return strnatcmp($a['interface'], $b['interface']);
        }
    });
    /* strip the sequence numbers again */
    for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
        unset($config['filter']['rule'][$i]['seq']);
    }
}

function filter_pflog_start($verbose = false)
{
    if ($verbose) {
        echo 'Starting PFLOG...';
        flush();
    }

    if (isvalidpid('/var/run/filterlog.pid')) {
        killbypid('/var/run/filterlog.pid', 'TERM', true);
    }

    mwexec('/usr/local/sbin/filterlog -i pflog0 -p /var/run/filterlog.pid');

    if ($verbose) {
        echo "done.\n";
    }
}

function filter_configure()
{
    global $config;

    /*
     * NOTE: Check here for bootup status since this should not be triggered during bootup.
     *   The reason is that rc.bootup calls filter_configure_sync directly which does this too.
     */
    if (!file_exists("/var/run/booting")) {
        configd_run('filter reload');
    }
}

function filter_should_trigger_kill_states()
{
    global $config;
    if (empty($config['system']['kill_states'])) {
        $a_gateways = return_gateways_status();
        $ifdetails = legacy_interfaces_details();
        $any_gateway_down = false;
        foreach ((new \OPNsense\Routing\Gateways($ifdetails))->gatewaysIndexedByName(false, true) as $gateway) {
            if (empty($gateway['monitor']) || empty($a_gateways[$gateway['name']])) {
                continue;
            } elseif (!is_ipaddr($gateway['monitor']) || strstr($gateway['monitor'], '127.0.0.')) {
                continue;
            }
            if (stristr($a_gateways[$gateway['name']]['status'], 'down')) {
                $any_gateway_down = true;
                break;
            }
        }
        return $any_gateway_down;
    }
    return false;
}

/**
 * sync interface groups, but leave the ones not managed by us intact.
 */
function ifgroup_setup()
{
    global $config;
    $all_ifgroups = array();
    $all_ifs = array();
    $interface_details = legacy_interfaces_details();
    if (isset($config['ifgroups']['ifgroupentry'])) {
        foreach ($config['ifgroups']['ifgroupentry'] as $group) {
            $all_ifgroups[$group['ifname']] = array();
            foreach (explode(" ", $group['members']) as $member) {
                if (!empty($config['interfaces'][$member])) {
                    $if = $config['interfaces'][$member]['if'];
                    if (!isset($all_ifs[$if])) {
                        $all_ifs[$if] = array();
                    }
                    $all_ifs[$if][] = $group['ifname'];
                    $all_ifgroups[$group['ifname']][] = $if;
                }
            }
        }
    }
    foreach ($interface_details as $intf => $details) {
        $thisifgroups = !empty($details['groups']) ? $details['groups'] : array();
        foreach ($thisifgroups as $ifgroup) {
            if (isset($all_ifgroups[$ifgroup]) && !in_array($intf, $all_ifgroups[$ifgroup])) {
                // detach
                mwexecf('/sbin/ifconfig %s -group %s', array($intf, $ifgroup));
            }
        }
        if (!empty($all_ifs[$intf])) {
            foreach ($all_ifs[$intf] as $ifgroup) {
                if (!in_array($ifgroup, $thisifgroups)) {
                    // attach
                    mwexecf('/sbin/ifconfig %s group %s', array($intf, $ifgroup));
                }
            }
        }
    }
}

/**
 * XXX: replace with check on interfaces section (see pf_interfaces)
 */
function is_interface_group($if)
{
    global $config;

    if (isset($config['ifgroups']['ifgroupentry'])) {
        foreach ($config['ifgroups']['ifgroupentry'] as $groupentry) {
            if ($groupentry['ifname'] === $if) {
                return true;
            }
        }
    }

    return false;
}

function filter_configure_sync($verbose = false, $flush_states = false, $load_aliases = true)
{
    global $config;
    $sched_kill_states = array(); // kill states for schedules

    if ($verbose) {
        echo 'Configuring firewall.';
        flush();
    }

    /* Use filter lock to not allow concurrent filter reloads during this run. */
    $filterlck = lock('filter', LOCK_EX);

    ifgroup_setup();
    if ($verbose) {
        echo '.';
        flush();
    }

    // initialize fw plugin object
    $fw = filter_core_get_initialized_plugin_system();
    filter_core_bootstrap($fw);
    $cnfint = iterator_to_array($fw->getInterfaceMapping());
    plugins_firewall($fw);

    if (isset($config['filter']['rule'])) {
        // register user rules
        foreach ($config['filter']['rule'] as $rule) {
            // calculate a hash for this area so we can track this rule, we should replace this
            // with uuid's on the rules like the new style models do eventually.
            $rule_hash = OPNsense\Firewall\Util::calcRuleHash($rule);
            $rule['label'] = $rule_hash;
            $sched = '';
            $descr = '';

            if (!empty($rule['sched'])) {
                $sched = "({$rule['sched']})";
            }
            if (!empty($rule['descr'])) {
                $descr = ": {$rule['descr']}";
            }

            $rule['descr'] = "{$sched}{$descr}";
            if (isset($rule['floating'])) {
                $prio = 200000;
            } elseif (is_interface_group($rule['interface']) || in_array($rule['interface'], array("l2tp", "pptp", "pppoe", "enc0", "openvpn"))) {
                $prio = 300000;
            } else {
                $prio = 400000;
            }
            /* is a time based rule schedule attached? */
            if (!empty($rule['sched']) && !empty($config['schedules'])) {
                foreach ($config['schedules']['schedule'] as $sched) {
                    if ($sched['name'] == $rule['sched']) {
                        if (!filter_get_time_based_rule_status($sched)) {
                            if (!isset($config['system']['schedule_states'])) {
                                $sched_kill_states[] = $rule['label'];
                            }
                            /* disable rule, suffix label to mark end of schedule */
                            $rule['disabled'] = true;
                            $rule['descr'] = "[FIN]" . $rule['descr'];
                        }
                        break;
                    }
                }
            }
            $fw->registerFilterRule($prio, $rule);
        }
    }

    // manual outbound nat rules
    if (
        !empty($config['nat']['outbound']['mode']) &&
          in_array($config['nat']['outbound']['mode'], array("advanced", "hybrid"))
    ) {
        if (!empty($config['nat']['outbound']['rule'])) {
            foreach ($config['nat']['outbound']['rule'] as $rule) {
                $fw->registerSNatRule(100, $rule);
            }
        }
    }

    if (
        empty($config['nat']['outbound']['mode']) ||
          in_array($config['nat']['outbound']['mode'], array("automatic", "hybrid"))
    ) {
        // generate standard outbound rules when mode is automatic ot hybrid
        $intfv4 = array();
        foreach ($fw->getInterfaceMapping() as $intf => $intfcf) {
            if (!empty($intfcf['ifconfig']['ipv4']) && empty($intfcf['gateway'])) {
                $intfv4[] =  $intf;
            }
        }
        // add VPN and local networks
        $intfv4 = array_merge($intfv4, filter_core_get_default_nat_outbound_networks());
        foreach ($fw->getInterfaceMapping() as $intf => $ifcfg) {
            if (substr($ifcfg['if'], 0, 4) != 'ovpn' && !empty($ifcfg['gateway'])) {
                foreach (array(500, null) as $dstport) {
                    $rule = array(
                        "interface" => $intf,
                        "dstport" => $dstport,
                        "staticnatport" => !empty($dstport),
                        "destination" => array("any" => true),
                        "ipprotocol" => 'inet',
                        "descr" => "Automatic outbound rule"
                    );
                    foreach ($intfv4 as $network) {
                        $rule['source'] = array("network" => $network);
                        $fw->registerSNatRule(200, $rule);
                    }
                }
            }
        }
    }

    // prevent redirection on ports with "lock out" protection
    foreach (filter_core_get_antilockout() as $lockoutif => $lockoutprts) {
        foreach ($lockoutprts as $port) {
            $rule = array(
                'interface' => $lockoutif,
                "nordr" => true,
                "protocol" => "tcp",
                'destination' => array('network' => "{$lockoutif}ip", 'port' => $port),
                "descr" => "Anti lockout, prevent redirects for protected ports to this interface ip"
            );
            $fw->registerForwardRule(300, $rule);
        }
    }

    if (!empty($config['nat']['npt'])) {
        // register user npt rules
        foreach ($config['nat']['npt'] as $rule) {
            $fw->registerNptRule(400, $rule);
        }
    }

    if (!empty($config['nat']['onetoone'])) {
        // register user 1:1 mappings
        foreach ($config['nat']['onetoone'] as $rule) {
            $fw->registerDNatRule(500, $rule);
        }
    }
    if (!empty($config['nat']['rule'])) {
        // register user forward rules
        foreach ($config['nat']['rule'] as $rule) {
            $fw->registerForwardRule(600, $rule);
        }
    }

    if (isset($config['system']['gw_switch_default'])) {
        // When gateway switching is enabled, we might consider a different default gateway.
        // although this isn't really the right spot for the feature (it's a monitoring/routing decision),
        // we keep it here for now (historical reasons).
        $down_gateways = return_down_gateways();
        foreach (array("inet", "inet6") as $ipprotocol) {
            if (!empty($down_gateways)) {
                log_error(sprintf("Ignore down %s gateways : %s", $ipprotocol, implode(",", $down_gateways)));
            }
            $default_gw = $fw->getGateways()->getDefaultGW($down_gateways, $ipprotocol);
            if ($default_gw !== null) {
                system_default_route(
                    $default_gw['gateway'],
                    $ipprotocol,
                    $default_gw['if'],
                    isset($default_gw['fargw'])
                );
            }
        }
    }

    $aliases = filter_generate_aliases();
    $aliases .= "\n# Plugins tables\n";
    $aliases .= $fw->tablesToText();

    if ($verbose) {
        echo '.';
        flush();
    }

    $natrules = "\n# NAT Redirects\n";
    $natrules .= "no nat proto carp all\n";
    $natrules .= "no rdr proto carp all\n";
    $natrules .= $fw->outputNatRules();

    if ($verbose) {
        echo '.';
        flush();
    }

    /* enable pf if we need to, otherwise disable */
    if (!isset($config['system']['disablefilter'])) {
        mwexec("/sbin/pfctl -e", true);
    } else {
        mwexec("/sbin/pfctl -d", true);
        if ($verbose) {
            echo "done.\n";
        }
        unlock($filterlck);
        return;
    }

    if ($verbose) {
        echo '.';
        flush();
    }

    $limitrules = '';

    if (!empty($config['system']['maximumtableentries'])) {
        $limitrules .= "set limit table-entries {$config['system']['maximumtableentries']}\n";
        set_single_sysctl('net.pf.request_maxcount', $config['system']['maximumtableentries']);
    } elseif (is_bogonsv6_used()) {
        $max_table_entries = default_table_entries_size();
        $req_table_entries = 1000000;
        if ($max_table_entries <= $req_table_entries) {
            $limitrules .= "set limit table-entries {$req_table_entries}\n";
            set_single_sysctl('net.pf.request_maxcount', $req_table_entries);
        } else {
            set_single_sysctl('net.pf.request_maxcount', $max_table_entries);
        }
    }

    if (!empty($config['system']['rulesetoptimization'])) {
        $limitrules .= "set ruleset-optimization {$config['system']['rulesetoptimization']}\n";
    } else {
        $limitrules .= "set ruleset-optimization basic\n";
    }

    if ($config['system']['optimization'] != '') {
        $limitrules .= "set optimization {$config['system']['optimization']}\n";
        if ($config['system']['optimization'] == "conservative") {
            $limitrules .= "set timeout { udp.first 300, udp.single 150, udp.multiple 900 }\n";
        }
    } else {
        $limitrules .= "set optimization normal\n";
    }

    if (!empty($config['system']['adaptivestart']) && !empty($config['system']['adaptiveend'])) {
        $limitrules .= "set timeout { adaptive.start {$config['system']['adaptivestart']}, adaptive.end {$config['system']['adaptiveend']} }\n";
    } else {
        $limitrules .= "set timeout { adaptive.start 0, adaptive.end 0 }\n";
    }
    if (!empty($config['system']['state-policy'])) {
        $limitrules .= "set state-policy if-bound\n";
    }

    if (!empty($config['system']['maximumstates'])) {
        $limitrules .= "set limit states {$config['system']['maximumstates']}\n";
        $limitrules .= "set limit src-nodes {$config['system']['maximumstates']}\n";
    } else {
        $max_states = default_state_size();
        $limitrules .= "set limit states {$max_states}\n";
        $limitrules .= "set limit src-nodes {$max_states}\n";
    }
    if (!empty($config['system']['maximumfrags'])) {
        $limitrules .= "set limit frags {$config['system']['maximumfrags']}\n";
    }

    if (isset($config['system']['lb_use_sticky']) && is_numeric($config['system']['srctrack']) && ($config['system']['srctrack'] > 0)) {
        $limitrules .= "set timeout src.track {$config['system']['srctrack']}\n";
    }

    $rules = "{$limitrules}\n";
    $rules .= "{$aliases} \n";
    $rules .= filter_setup_logging_interfaces($cnfint);
    $rules .= "\n";
    $rules .= "set skip on pfsync0\n";
    $rules .= "\n";
    $rules .= filter_generate_scrubing($cnfint);
    $rules .= "\n";
    $rules .= $fw->anchorToText('nat,binat,rdr', 'head');
    $rules .= "{$natrules}\n";
    $rules .= $fw->anchorToText('nat,binat,rdr', 'tail');
    $rules .= $fw->anchorToText('fw', 'head');
    $rules .= filter_rules_legacy($cnfint);
    $rules .= $fw->outputFilterRules();
    $rules .= $fw->anchorToText('fw', 'tail');

    // Copy rules.debug to rules.debug.old
    if (file_exists('/tmp/rules.debug')) {
        @copy('/tmp/rules.debug', '/tmp/rules.debug.old');
    }

    if (!@file_put_contents('/tmp/rules.debug', $rules, LOCK_EX)) {
        log_error("WARNING: Could not write new rules!");
        unlock($filterlck);
        if ($verbose) {
            echo "failed.\n";
        }
        return;
    }

    @file_put_contents('/tmp/rules.limits', $limitrules);
    mwexec('/sbin/pfctl -Of /tmp/rules.limits');
    exec('/sbin/pfctl -o basic -f /tmp/rules.debug 2>&1', $rules_error, $rules_loading);

    foreach ($sched_kill_states as $label) {
        mwexecf('/sbin/pfctl -k label -k %s', $label);
    }

    /*
     * check for a error while loading the rules file.  if an error has occurred
     * then output the contents of the error to the caller
     */
    if ($rules_loading) {
        $config_line = '';

        /* only report issues with line numbers */
        $line_error = explode(':', $rules_error[0]);
        if (isset($line_error[1]) && (string)((int)$line_error[1]) == $line_error[1] && $line_error[1] > 0) {
            $line_number = $line_error[1];
            $line_split = file('/tmp/rules.debug');
            if (is_array($line_split)) {
                $config_line = sprintf(' - ' . gettext('The line in question reads [%s]: %s'), $line_number, $line_split[$line_number - 1]);
            }
        }

        /* Brutal ugly hack but required -- PF is stuck, unwedge */
        if (strstr("$rules_error[0]", "busy")) {
            exec('/sbin/pfctl -d; /sbin/pfctl -e; /sbin/pfctl -f /tmp/rules.debug');
            log_error('PF was wedged/busy and has been reset.');
            file_notice(gettext('PF was wedged/busy and has been reset.'));
        } else {
            exec('/sbin/pfctl -o basic -f /tmp/rules.debug.old 2>&1');
        }

        log_error(sprintf('There were error(s) loading the rules: %s%s', $rules_error[0], $config_line));
        file_notice(sprintf(gettext('There were error(s) loading the rules: %s%s'), $rules_error[0], $config_line));
        unlock($filterlck);

        if ($verbose) {
            echo "failed.\n";
        }

        return;
    }

    /* set shared forwarding according to config option */
    set_single_sysctl('net.pf.share_forward', !empty($config['system']['pf_share_forward']) ? '1' : '0');
    set_single_sysctl('net.pf.share_forward6', !empty($config['system']['pf_share_forward']) ? '1' : '0');

    /*
     * If we are not using bogonsv6 then we can remove any
     * bogonsv6 table from the running pf (if the table is
     * not there, the kill is still fine).
     */
    if (!is_bogonsv6_used()) {
        mwexec('/sbin/pfctl -t bogonsv6 -T kill');
    }

    if ($verbose) {
        echo '.';
        flush();
    }

    if ($verbose) {
        echo '.';
        flush();
    }

    if ($flush_states) {
        mwexec('/sbin/pfctl -Fs');
    }

    if ($verbose) {
        echo '.';
        flush();
    }

    if ($load_aliases) {
        configd_run('template reload OPNsense/Filter');
        configd_run('filter refresh_aliases', true);
    }

    if ($verbose) {
        echo "done.\n";
    }

    filter_pflog_start($verbose);

    unlock($filterlck);
}

function filter_generate_scrubing(&$FilterIflist)
{
    global $config;

    $scrubrules = '';

    /* custom rules must be first */
    if (!empty($config['filter']['scrub']['rule'])) {
        foreach ($config['filter']['scrub']['rule'] as $scrub_rule) {
            if (!isset($scrub_rule['disabled'])) {
                $scrub_rule_out = "scrub";
                $scrub_rule_out .= !empty($scrub_rule['direction']) ? " " . $scrub_rule['direction'] : "";
                $scrub_rule_out .= " on ";
                $interfaces = array();
                foreach (explode(',', $scrub_rule['interface']) as $interface) {
                    if (!empty($FilterIflist[$interface]['if'])) {
                        $interfaces[] = $FilterIflist[$interface]['if'];
                    }
                }
                $scrub_rule_out .= count($interfaces) > 1 ? "{ " . implode(' ', $interfaces) . " } " : $interfaces[0];
                switch ($scrub_rule['proto']) {
                    case 'any':
                        break;
                    case 'tcp/udp':
                        $scrub_rule_out .= " proto {tcp udp}";
                        break;
                    default:
                        $scrub_rule_out .= " proto " . $scrub_rule['proto'];
                        break;
                }
                $scrub_rule_out .= " from ";
                if (is_alias($scrub_rule['src'])) {
                    $scrub_rule_out .= !empty($scrub_rule['srcnot']) ? "!" : "";
                    $scrub_rule_out .= '$' . $scrub_rule['src'];
                } elseif (is_ipaddr($scrub_rule['src'])) {
                    $scrub_rule_out .= !empty($scrub_rule['srcnot']) ? "!" : "";
                    $scrub_rule_out .= $scrub_rule['src'] . "/" . $scrub_rule['srcmask'];
                } else {
                    $scrub_rule_out .= "any";
                }
                if (!empty($scrub_rule['srcport']) && is_alias($scrub_rule['srcport'])) {
                    $scrub_rule_out .=  " port $" . $scrub_rule['srcport'];
                } else {
                    $scrub_rule_out .= !empty($scrub_rule['srcport']) ?  " port " . $scrub_rule['srcport'] : "";
                }
                $scrub_rule_out .= " to ";
                if (is_alias($scrub_rule['dst'])) {
                    $scrub_rule_out .= !empty($scrub_rule['dstnot']) ? "!" : "";
                    $scrub_rule_out .= '$' . $scrub_rule['dst'];
                } elseif (is_ipaddr($scrub_rule['dst'])) {
                    $scrub_rule_out .= !empty($scrub_rule['dstnot']) ? "!" : "";
                    $scrub_rule_out .= $scrub_rule['dst'] . "/" . $scrub_rule['dstmask'];
                } else {
                    $scrub_rule_out .= "any";
                }
                if (!empty($scrub_rule['dstport']) && is_alias($scrub_rule['dstport'])) {
                    $scrub_rule_out .= " port $" . $scrub_rule['dstport'];
                } else {
                    $scrub_rule_out .= !empty($scrub_rule['dstport']) ?  " port " . $scrub_rule['dstport'] : "";
                }
                $scrub_rule_out .= !empty($scrub_rule['no-df']) ? " no-df " : "";
                $scrub_rule_out .= !empty($scrub_rule['random-id']) ? " random-id " : "";
                $scrub_rule_out .= !empty($scrub_rule['max-mss']) ? " max-mss " . $scrub_rule['max-mss'] .  " " : "";
                $scrub_rule_out .= !empty($scrub_rule['min-ttl']) ? " min-ttl " . $scrub_rule['min-ttl'] .  " " : "";
                $scrub_rule_out .= !empty($scrub_rule['set-tos']) ? " set-tos " . $scrub_rule['set-tos'] .  " " : "";
                $scrub_rule_out .= "\n";
                if (count($interfaces) == 0) {
                    # unknown interface, skip rule
                    $scrubrules .= "#";
                }
                $scrubrules .= $scrub_rule_out;
            }
        }
    }

    /* scrub per interface options */
    if (empty($config['system']['scrub_interface_disable'])) {
        foreach ($FilterIflist as $scrubcfg) {
            if (isset($scrubcfg['virtual']) || empty($scrubcfg['descr'])) {
                continue;
            }

            $mssclampv4 = '';
            $mssclampv6 = '';
            if (
                !empty($scrubcfg['mss']) && is_numeric($scrubcfg['mss']) &&
                !in_array($scrubcfg['if'], array('pppoe', 'pptp', 'l2tp'))
            ) {
                $mssclampv4 = 'max-mss ' . (intval($scrubcfg['mss'] - 40));
                $mssclampv6 = 'max-mss ' . (intval($scrubcfg['mss'] - 60));
            }

            $scrubnodf = !empty($config['system']['scrubnodf']) ? 'no-df' : '';
            $scrubrnid = !empty($config['system']['scrubrnid']) ? 'random-id' : '';
            if (!empty($mssclampv4)) {
                $scrubrules .= "scrub on {$scrubcfg['if']} inet all {$scrubnodf} {$scrubrnid} {$mssclampv4}\n";
                $scrubrules .= "scrub on {$scrubcfg['if']} inet6 all {$scrubnodf} {$scrubrnid} {$mssclampv6}\n";
            } else {
                $scrubrules .= "scrub on {$scrubcfg['if']} all {$scrubnodf} {$scrubrnid}\n";
            }
        }
    }

    return $scrubrules;
}

function filter_generate_aliases()
{
    $aliases = "\n# Lockout tables\n";
    $aliases .= "table <sshlockout> persist\n";
    $aliases .= "\n# Other tables\n";
    $aliases .= "table <virusprot>\n";

    $aliases .= "\n# User Aliases\n";
    $aliasObject = new \OPNsense\Firewall\Alias();
    foreach ($aliasObject->aliasIterator() as $aliased) {
        switch ($aliased['type']) {
            case "urltable_ports":
            case "url_ports":
                # a bit of a hack, but prevents the ruleset from not being able to load if these types are in
                # the configuration.
                $aliases .= "{$aliased['name']} = \"{ 0 <> 65535 }\"\n";
                log_error(sprintf('URL port aliases types not supported [%s]', $aliased['name']));
                file_notice(sprintf(gettext('URL port aliases types not supported [%s]'), $aliased['name']));
                break;
            case "port":
                $tmp_ports = implode(" ", filter_core_get_port_alias($aliased['name'], array(), $aliasObject));
                $aliases .= "{$aliased['name']} = \"{ {$tmp_ports} }\"\n";
                break;
            default:
                $tblopt = (!empty($aliased['counters']) ? 'counters' : '') . " persist ";
                $aliases .= "table <{$aliased['name']}> {$tblopt} \n";
                $aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
                break;
        }
    }

    $aliases .= "table <bogons> persist file \"/usr/local/etc/bogons\"\n";
    if (is_bogonsv6_used()) {
        $aliases .= "table <bogonsv6> persist file \"/usr/local/etc/bogonsv6\"\n";
    }

    return $aliases;
}

function filter_rules_legacy(&$FilterIflist)
{
    global $config;
    $log = !isset($config['syslog']['nologdefaultblock']) ? "log" : "";

    $ipfrules = "";
    $bridge_interfaces = [];
    if (!empty($config['bridges']['bridged'])) {
        foreach ($config['bridges']['bridged'] as $oc2) {
            $bridge_interfaces = array_merge(explode(',', $oc2['members']), $bridge_interfaces);
        }
    }
    foreach ($FilterIflist as $on => $oc) {
        if (!in_array($on, $bridge_interfaces) && !isset($oc['internal_dynamic']) && $oc['if'] != 'lo0') {
            $ipfrules .= "antispoof {$log} for {$oc['if']} \n";
        }
    }
    return $ipfrules;
}

/****f* filter/filter_get_time_based_rule_status
 * NAME
 *   filter_get_time_based_rule_status
 * INPUTS
 *   xml schedule block
 * RESULT
 *   true/false - true if the rule should be installed
 ******/
/*
 <schedules>
   <schedule>
     <name>ScheduleMultipleTime</name>
     <descr>main descr</descr>
     <time>
       <position>0,1,2</position>
       <hour>0:0-24:0</hour>
       <desc>time range 2</desc>
     </time>
     <time>
       <position>4,5,6</position>
       <hour>0:0-24:0</hour>
       <desc>time range 1</desc>
     </time>
   </schedule>
 </schedules>
*/
function filter_get_time_based_rule_status($schedule)
{
    /* no schedule? rule should be installed */
    if (empty($schedule)) {
        return true;
    }
    /*
     * iterate through time blocks and determine
     * if the rule should be installed or not.
     */
    foreach ($schedule['timerange'] as $timeday) {
        if (empty($timeday['month'])) {
            $monthstatus = true;
        } else {
            $monthstatus = filter_tdr_month($timeday['month']);
        }
        if (empty($timeday['day'])) {
            $daystatus = true;
        } else {
            $daystatus = filter_tdr_day($timeday['day']);
        }
        if (empty($timeday['hour'])) {
            $hourstatus = true;
        } else {
            $hourstatus = filter_tdr_hour($timeday['hour']);
        }
        if (empty($timeday['position'])) {
            $positionstatus = true;
        } else {
            $positionstatus = filter_tdr_position($timeday['position']);
        }

        if ($monthstatus == true && $daystatus == true && $positionstatus == true && $hourstatus == true) {
            return true;
        }
    }
    return false;
}

function filter_tdr_day($schedule)
{
    /*
     * Calculate day of month.
     * IE: 29th of may
     */
    $date = date("d");
    $defined_days = explode(",", $schedule);
    foreach ($defined_days as $dd) {
        if ($date == $dd) {
            return true;
        }
    }
    return false;
}

function filter_tdr_hour($schedule)
{
    /* $schedule should be a string such as 16:00-19:00 */
    $tmp = explode("-", $schedule);
    $starting_time = strtotime($tmp[0]);
    $ending_time = strtotime($tmp[1]);
    $now = strtotime("now");
    if ($now >= $starting_time && $now < $ending_time) {
        return true;
    }
    return false;
}

function filter_tdr_position($schedule)
{
    /*
     * Calculate position, ie: day of week.
     * Sunday = 7, Monday = 1, Tuesday = 2
     * Weds = 3, Thursday = 4, Friday = 5,
     * Saturday = 6
     * ...
     */
    $weekday = date("w");
    if ($weekday == 0) {
        $weekday = 7;
    }
    $schedule_days = explode(",", $schedule);
    foreach ($schedule_days as $day) {
        if ($day == $weekday) {
            return true;
        }
    }
    return false;
}

function filter_tdr_month($schedule)
{
    /*
     * Calculate month
     */
    $todays_month = date("n");
    $months = explode(",", $schedule);
    foreach ($months as $month) {
        if ($month == $todays_month) {
            return true;
        }
    }
    return false;
}

function filter_setup_logging_interfaces(&$FilterIflist)
{
    $rules = '';

    if (isset($FilterIflist['lan'])) {
        $rules .= "set loginterface {$FilterIflist['lan']['if']}\n";
    } elseif (isset($FilterIflist['wan'])) {
        $rules .= "set loginterface {$FilterIflist['wan']['if']}\n";
    }

    return $rules;
}

function default_table_entries_size()
{
    $current = `pfctl -sm | grep table-entries | awk '{print $4};'`;

    return $current;
}

function default_state_size()
{
    /* get system memory amount */
    $memory = get_memory();
    $physmem = $memory[0];

    /* Be cautious and only allocate 10% of system memory to the state table */
    $max_states = (int) ($physmem / 10) * 1000;

    return $max_states;
}

function get_protocols()
{
    $protocols = array('any', 'TCP', 'UDP', 'TCP/UDP', 'ICMP', 'ESP', 'AH', 'GRE', 'IGMP', 'PIM', 'OSPF');

    /* IPv6 extension headers are skipped by the packet filter, we cannot police them */
    $ipv6_ext = array('IPV6-ROUTE', 'IPV6-FRAG', 'IPV6-OPTS', 'IPV6-NONXT', 'MOBILITY-HEADER');

    foreach (explode("\n", file_get_contents('/etc/protocols')) as $line) {
        if (substr($line, 0, 1) != "#") {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 4 && $parts[1] > 0) {
                $protocol = trim(strtoupper($parts[0]));
                if (!in_array($protocol, $ipv6_ext) && !in_array($protocol, $protocols)) {
                    $protocols[] = $protocol;
                }
            }
        }
    }
    return $protocols;
}
