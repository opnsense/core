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

/* lock configuration file */
function filter_lock($lock = 'filter', $op = LOCK_EX)
{
    if (!file_exists("{/tmp/{$lock}.lock")) {
        @touch("/tmp/{$lock}.lock");
    }

    if ($fp = fopen("/tmp/{$lock}.lock", 'a+e')) {
        if (flock($fp, $op)) {
            return $fp;
        } else {
            fclose($fp);
        }
    }
}

/* unlock configuration file */
function filter_unlock($fp)
{
    flock($fp, LOCK_UN);
    fclose($fp);
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
    /* strip the sequence numbers again, add uuid's where not available */
    for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
        unset($config['filter']['rule'][$i]['seq']);
        if (
            empty($config['filter']['rule'][$i]['@attributes'])
            && empty($config['filter']['rule'][$i]['@attributes']['uuid'])
        ) {
            $config['filter']['rule'][$i]['@attributes'] = ['uuid' => generate_uuid()];
        }
    }
}

function filter_configure()
{
    /*
     * Defer this to configd which will avoid this call on bootup when
     * this should not be triggered.  The reason is that rc.bootup calls
     * filter_configure_sync() directly which does this too.
     */
    configd_run('filter reload');
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
            foreach (preg_split('/[ |,]+/', $group['members']) as $member) {
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

function filter_configure_sync($verbose = false, $load_aliases = true)
{
    global $config;

    service_log('Configuring firewall.', $verbose);

    /* Use filter lock to not allow concurrent filter reloads during this run. */
    $locked = filter_lock();

    ifgroup_setup();

    service_log('.', $verbose);

    // initialize fw plugin object
    $fw = filter_core_get_initialized_plugin_system();
    filter_core_bootstrap($fw);
    $cnfint = iterator_to_array($fw->getInterfaceMapping());
    plugins_firewall($fw);
    // register user rules, returns kill states for schedules
    $sched_kill_states = filter_core_rules_user($fw);

    // manual outbound nat rules
    if (
        !empty($config['nat']['outbound']['mode']) &&
          in_array($config['nat']['outbound']['mode'], array("advanced", "hybrid"))
    ) {
        if (!empty($config['nat']['outbound']['rule'])) {
            foreach ($config['nat']['outbound']['rule'] as $rule) {
                if (is_array($rule)) {
                    $fw->registerSNatRule(100, $rule);
                }
            }
        }
    }

    if (
        empty($config['nat']['outbound']['mode']) ||
          in_array($config['nat']['outbound']['mode'], array("automatic", "hybrid"))
    ) {
        // generate standard outbound rules when mode is automatic or hybrid
        $intfv4 = array();
        foreach ($fw->getInterfaceMapping() as $intf => $intfcf) {
            if (!empty($intfcf['ifconfig']['ipv4']) && empty($intfcf['gateway'])) {
                $intfv4[] = $intf;
            }
        }
        // add VPN and local networks
        $intfv4 = array_merge($intfv4, filter_core_get_default_nat_outbound_networks());
        foreach ($fw->getInterfaceMapping() as $intf => $ifcfg) {
            /* XXX VPN is allowed but not OpenVPN dropping /tmp/ovpnxy_router(v6) file */
            if (substr($ifcfg['if'], 0, 4) != 'ovpn' && !empty($ifcfg['gateway'])) {
                foreach (array(500, null) as $dstport) {
                    $rule = array(
                        'descr' => 'Automatic outbound rule',
                        'destination' => array('any' => true),
                        'dstport' => $dstport,
                        'interface' => $intf,
                        'ipprotocol' => 'inet',
                        'log' => !empty($config['syslog']['logoutboundnat']),
                        'staticnatport' => !empty($dstport),
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

    if (!empty($config['nat']['rule'])) {
        // register user forward rules
        foreach ($config['nat']['rule'] as $rule) {
            if (is_array($rule)) {
                $fw->registerForwardRule(600, $rule);
            }
        }
    }

    openlog("firewall", LOG_DAEMON, LOG_LOCAL4);

    $aliases = filter_generate_aliases();
    $aliases .= "\n# Plugins tables\n";
    $aliases .= $fw->tablesToText();

    service_log('.', $verbose);

    $natrules = "\n# NAT Redirects\n";
    $natrules .= "no nat proto carp all\n";
    $natrules .= "no rdr proto carp all\n";
    $natrules .= $fw->outputNatRules();

    service_log('.', $verbose);

    /* enable pf if we need to, otherwise disable */
    if (!isset($config['system']['disablefilter'])) {
        mwexec("/sbin/pfctl -e", true);
    } else {
        mwexec("/sbin/pfctl -d", true);

        filter_unlock($locked);
        closelog();
        reopenlog();

        service_log("done.\n", $verbose);

        return;
    }

    service_log('.', $verbose);

    $limitrules = '';

    if (!empty($config['system']['maximumtableentries'])) {
        $limitrules .= "set limit table-entries {$config['system']['maximumtableentries']}\n";
        set_single_sysctl('net.pf.request_maxcount', $config['system']['maximumtableentries']);
    } else {
        $max_table_entries = default_table_entries_size();
        $req_table_entries = 1000000;
        if ($max_table_entries <= $req_table_entries) {
            $limitrules .= "set limit table-entries {$req_table_entries}\n";
            set_single_sysctl('net.pf.request_maxcount', $req_table_entries);
        } else {
            set_single_sysctl('net.pf.request_maxcount', $max_table_entries);
        }
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

    if (isset($config['system']['lb_use_sticky']) && is_numeric($config['system']['srctrack'] ?? null) && ($config['system']['srctrack'] > 0)) {
        $limitrules .= "set timeout src.track {$config['system']['srctrack']}\n";
    }

    if (!empty($config['system']['syncookies'])) {
        $arange = "";
        if ($config['system']['syncookies'] == "adaptive") {
            $arange = "(start {$config['system']['syncookies_adaptstart']}%, end {$config['system']['syncookies_adaptend']}%)";
        }
        $limitrules .= "set syncookies {$config['system']['syncookies']} {$arange}\n";
    }

    /* if pf already generated a hostid, reuse it */
    $current_hostid = get_current_hostid();
    if (!empty($current_hostid)) {
        $limitrules .= "set hostid {$current_hostid}\n";
    }

    if (!empty($config['system']['pfdebug'])) {
        $limitrules .= "set debug {$config['system']['pfdebug']}\n";
    }

    if (!empty($config['system']['keepcounters'])) {
        $limitrules .= "set keepcounters\n";
    }

    $rules = "{$limitrules}\n";
    $rules .= "{$aliases} \n";
    $rules .= filter_setup_logging_interfaces($cnfint);
    $rules .= "\n";
    $rules .= "set skip on pfsync0\n";
    $rules .= "\n";
    $rules .= filter_generate_scrubbing($cnfint);
    $rules .= "\n";
    $rules .= $fw->anchorToText('nat,binat,rdr', 'head');
    $rules .= "{$natrules}\n";
    $rules .= $fw->anchorToText('nat,binat,rdr', 'tail');
    $rules .= $fw->anchorToText('fw', 'head');
    $rules .= filter_rules_legacy($cnfint);
    $rules .= $fw->outputFilterRules();
    $rules .= $fw->anchorToText('fw', 'tail');

    @copy('/tmp/rules.debug', '/tmp/rules.debug.old');
    @copy('/tmp/ifconfig.debug', '/tmp/ifconfig.debug.old');

    mwexecf('ifconfig > %s', '/tmp/ifconfig.debug');

    if (!@file_put_contents('/tmp/rules.debug', $rules, LOCK_EX)) {
        syslog(LOG_ERR, 'ERROR: Could not write new rules!');
        file_put_contents('/tmp/rules.error', gettext('ERROR: Could not write new rules!'));

        filter_unlock($locked);
        closelog();
        reopenlog();

        service_log("failed.\n", $verbose);

        return;
    }

    @file_put_contents('/tmp/rules.limits', $limitrules);
    mwexec('/sbin/pfctl -Of /tmp/rules.limits');
    exec('/sbin/pfctl -f /tmp/rules.debug 2>&1', $rules_error, $rules_loading);

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

        mwexec('/sbin/pfctl -f /tmp/rules.debug.old');

        syslog(LOG_ERR, trim(sprintf('There were error(s) loading the rules: %s%s', $rules_error[0], $config_line)));
        file_put_contents(
            '/tmp/rules.error',
            sprintf(gettext('There were error(s) loading the rules: %s%s'), $rules_error[0], $config_line)
        );

        filter_unlock($locked);
        closelog();
        reopenlog();

        service_log("failed.\n", $verbose);

        return;
    }

    /*
     * If we are not using bogonsv6 then we can remove any
     * bogonsv6 table from the running pf (if the table is
     * not there, the kill is still fine).
     */
    if (!is_bogonsv6_used()) {
        mwexec('/sbin/pfctl -t bogonsv6 -T kill');
    }

    service_log('.', $verbose);

    if ($load_aliases) {
        configd_run('template reload OPNsense/Filter');
        configd_run('filter refresh_aliases', true);
    }

    service_log('.', $verbose);

    /* enable permanent promiscuous mode to avoid dmesg noise */
    mwexec('/sbin/ifconfig pflog0 promisc');

    /* bring up new instance of filterlog to load new rules */
    killbypid('/var/run/filterlog.pid');
    mwexec('/usr/local/sbin/filterlog -i pflog0 -p /var/run/filterlog.pid');

    filter_unlock($locked);
    closelog();
    reopenlog();

    service_log("done.\n", $verbose);
}

function filter_generate_scrubbing(&$FilterIflist)
{
    global $config;

    $scrubrules = '';

    /* custom rules must be first */
    if (!empty($config['filter']['scrub']['rule'])) {
        foreach ($config['filter']['scrub']['rule'] as $scrub_rule) {
            if (!isset($scrub_rule['disabled'])) {
                $scrub_rule_out = !empty($scrub_rule['noscrub']) ? "no " : "";
                $scrub_rule_out .= "scrub";
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
                    $scrub_rule_out .= " port $" . $scrub_rule['srcport'];
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
                if (empty($scrub_rule['noscrub'])) {
                    $scrub_rule_out .= !empty($scrub_rule['no-df']) ? " no-df " : "";
                    $scrub_rule_out .= !empty($scrub_rule['random-id']) ? " random-id " : "";
                    $scrub_rule_out .= !empty($scrub_rule['max-mss']) ? " max-mss " . $scrub_rule['max-mss'] .  " " : "";
                    $scrub_rule_out .= !empty($scrub_rule['min-ttl']) ? " min-ttl " . $scrub_rule['min-ttl'] .  " " : "";
                    $scrub_rule_out .= !empty($scrub_rule['set-tos']) ? " set-tos " . $scrub_rule['set-tos'] .  " " : "";
                }
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
        /* scrub generic options, appended to all default rules */
        $scrub_gen_opts = !empty($config['system']['scrubnodf']) ? ' no-df ' : '';
        $scrub_gen_opts .= (!empty($config['system']['scrubrnid']) ? ' random-id ' : '');
        foreach ($FilterIflist as $scrubcfg) {
            if (is_numeric($scrubcfg['mss'] ?? '') && !in_array($scrubcfg['if'], ['pppoe', 'pptp', 'l2tp'])) {
                /**
                 * Legacy MSS clamping on interface expects outbound packets to be scrubbed in order to work.
                 * https://github.com/pfsense/pfsense/commit/7c382a8
                 *
                 * In a future release we might want to consider to move the MSS option from the interface into a
                 * manual scrubbing rule, this is a bit intransparant.
                 */
                $mssclampv4 = 'max-mss ' . (intval($scrubcfg['mss'] - 40));
                $mssclampv6 = 'max-mss ' . (intval($scrubcfg['mss'] - 60));
                $scrubrules .= "scrub on {$scrubcfg['if']} inet all {$scrub_gen_opts} {$mssclampv4}\n";
                $scrubrules .= "scrub on {$scrubcfg['if']} inet6 all {$scrub_gen_opts} {$mssclampv6}\n";
            }
        }
        $scrubrules .= "scrub in all {$scrub_gen_opts}\n";
    }

    return $scrubrules;
}

function filter_generate_aliases()
{
    $aliases = "# User Aliases\n";
    $aliasObject = new \OPNsense\Firewall\Alias();
    // list of registered aliases for faster is_alias() lookup
    $all_aliases = [];
    foreach ($aliasObject->aliasIterator() as $aliased) {
        $all_aliases[] = $aliased['name'];
    }
    foreach ($aliasObject->aliasIterator() as $aliased) {
        switch ($aliased['type']) {
            case "urltable_ports":
            case "url_ports":
                # a bit of a hack, but prevents the ruleset from not being able to load if these types are in
                # the configuration.
                $aliases .= "{$aliased['name']} = \"{ 0 <> 65535 }\"\n";
                syslog(LOG_ERR, sprintf('URL port aliases types not supported [%s]', $aliased['name']));
                file_put_contents(
                    '/tmp/rules.error',
                    sprintf(gettext('URL port aliases types not supported [%s]'), $aliased['name'])
                );
                break;
            case "port":
                $tmp_ports = implode(" ", filter_core_get_port_alias($aliased['name'], [], $aliasObject, $all_aliases));
                if (empty($tmp_ports)) {
                    // we can't create empty port tables, so when it's empty we should make sure it can't match
                    $tmp_ports = "0 <> 65535";
                }
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
        foreach ($config['bridges']['bridged'] as $bridge) {
            $bridge_interfaces = array_merge(explode(',', $bridge['members'] ?? ''), $bridge_interfaces);
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
        $matched_time = true; /* keep original behavior, no time set allows the whole day */
        if (!empty($timeday['hour'])) {
            $tmp = explode("-", $timeday['hour']);
            $now = strtotime("now");
            $matched_time = $now >= strtotime($tmp[0]) && $now < strtotime($tmp[1]);
        }
        if ($matched_time) {
            if (!empty($timeday['position'])) {
                $this_weekday = date('w') == 0 ? 7 : date('w');

                foreach (explode(",", $timeday['position']) as $day) {
                    if ($day == $this_weekday) {
                        return true;
                    }
                }
            } else {
                $months = explode(',', $timeday['month'] ?? '');
                $days = explode(',', $timeday['day'] ?? '');
                if (empty($months) || empty($days) || count($days) != count($months)) {
                    /* invalid data */
                    continue;
                }
                $today = date("dm");
                for ($i = 0; $i < count($days); ++$i) {
                    if (sprintf("%02d%02d", $days[$i], $months[$i]) == $today) {
                        return true;
                    }
                }
            }
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

function get_current_hostid()
{
    exec('/sbin/pfctl -si -v 2>/dev/null', $output);

    foreach ($output as $line) {
        if (strpos($line, 'Hostid:') !== false) {
            return preg_split('/\s+/', $line)[1];
        }
    }

    return null;
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

/**
 * Return array of possible TOS values
 */
function get_tos_values($blank_label = '')
{
    $ret = array(
        '' => $blank_label,
        'lowdelay' => gettext('lowdelay'),
        'critical' => gettext('critical'),
        'inetcontrol' => gettext('inetcontrol'),
        'netcontrol' => gettext('netcontrol'),
        'throughput' => gettext('throughput'),
        'reliability' => gettext('reliability'),
        'ef' => 'EF',
    );

    foreach (array(11, 12, 13, 21, 22, 23, 31, 32, 33, 41 ,42, 43) as $val) {
        $ret["af$val"] = "AF$val";
    }

    foreach (range(0, 7) as $val) {
        $ret["cs$val"] = "CS$val";
    }

    foreach (range(0, 255) as $val) {
        $ret['0x' . dechex($val)] = sprintf('0x%02X', $val);
    }

    return $ret;
}
