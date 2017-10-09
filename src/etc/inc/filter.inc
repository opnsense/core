<?php

/*
    Copyright (C) 2017 Deciso B.V.
    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
    Copyright (C) 2006 Peter Allgeyer <allgeyer@web.de>
    Copyright (C) 2008-2010 Ermal Luçi
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once('filter.lib.inc');

/* Create a global array to avoid errors on rulesets. */
$GatewaysList = array();

function alias_expand_urltable($name) {
    global $config;
    $urltable_prefix = "/var/db/aliastables/";
    $urltable_filename = $urltable_prefix . $name . ".txt";

    if (is_array($config['aliases']['alias'])) {
        foreach ($config['aliases']['alias'] as $alias) {
            if (preg_match("/urltable/i", $alias['type']) && ($alias['name'] == $name)) {
                if (is_URL($alias["url"]) && file_exists($urltable_filename) && filesize($urltable_filename)) {
                    return $urltable_filename;
                } elseif (process_alias_urltable($name, $alias["type"], $alias["url"], 0, true)) {
                    return $urltable_filename;
                }
            } elseif ($alias['type'] == 'geoip' && $alias['name'] == $name) {
                if (!file_exists($urltable_filename) || filesize($urltable_filename) == 0) {
                    if (download_alias_geoip($alias)) {
                        return $urltable_filename;
                    }
                } else {
                    // alias file found and not 0 bytes
                    return $urltable_filename;
                }
            }
        }
    }
    return null;
}

function fix_rule_label($descr)
{
    $descr = str_replace('"', '', $descr);
    if (strlen($descr) > 63) {
        return substr($descr, 0, 60) . "...";
    } else {
        return $descr;
    }
}

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

function download_file($url, $destination, $verify_ssl = false, $connect_timeout = 60, $timeout = 0)
{
    global $config, $g;

    $fp = fopen($destination, "wb");

    if (!$fp) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!$verify_ssl) {
        /* leave the curl defaults untouched when verify is used */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/usr/local/opnsense/version/opnsense")));

    @curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);
    curl_close($ch);

    /*
     * Crash reports indicated that this can fail on bad
     * lines, causing the download to be "ok", but not
     * actually writing the target file.  Make it a hard
     * failure...
     */
    if (!file_exists($destination)) {
        return false;
    }

    return ($http_code == 200);
}


function process_alias_urltable($name, $alias_type, $url, $freq, $forceupdate = false)
{
    global $config;

    $urltable_prefix = "/var/db/aliastables/";
    $urltable_filename = $urltable_prefix . $name . ".txt";

    // Make the aliases directory if it doesn't exist
    if (!file_exists($urltable_prefix)) {
        mkdir($urltable_prefix);
    } elseif (!is_dir($urltable_prefix)) {
        unlink($urltable_prefix);
        mkdir($urltable_prefix);
    }
    if (empty($freq)) {
        $freq = 1;
    }
    $update_freq = ($freq * 86400) - 90;
    // If the file doesn't exist or is older than update_freq days, fetch a new copy.
    if (!file_exists($urltable_filename) || ((time() - filemtime($urltable_filename)) > $update_freq) || $forceupdate) {
        // open file handle to output file, in case the process takes a lot of time, make sure there's a file before
        // filter starts to load. Also helps for tracking progress.
        //
        // todo: rethink alias_expand_urltable in filter.inc , its probably not a very good idea to download and
        //       process files during boot.
        $output_file_handle = fopen($urltable_filename, 'w');
        // Try to fetch the URL supplied
        @unlink("{$urltable_filename}.tmp");
        $verify_ssl = isset($config['system']['checkaliasesurlcert']);
        if (download_file($url, "{$urltable_filename}.tmp", $verify_ssl)) {
            foreach (preg_split('/[\n\r]+/', file_get_contents("{$urltable_filename}.tmp"), -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $line = trim($line);
                if (!strlen($line) || !strncmp($line, '#', 1) || !strncmp($line, '//', 2)) {
                    continue;
                }
                // cleanse line item
                $line = preg_split('/[\s,;|]+/', $line)[0];
                if ($alias_type == "urltable_ports") {
                    // todo: add proper validation for ports here
                    fwrite($output_file_handle, "{$line}\n");
                } else {
                    // validate or resolve line items, skip unparseable content
                    if (is_subnet($line) || is_ipaddr($line)) {
                        fwrite($output_file_handle, "{$line}\n");
                    } elseif (is_hostname($line)) {
                        foreach (array(DNS_AAAA, DNS_A) as $dns_type) {
                            // normally dns_get_record should be able to use DNS_A + DNS_AAAA
                            // but for some strange reason not on our platform...
                            $dns_records = @dns_get_record($line, $dns_type);
                            if ($dns_records) {
                                foreach ($dns_records as $dns_record) {
                                    if (!empty($dns_record['ipv6'])) {
                                        fwrite($output_file_handle, $dns_record['ipv6'] . "\n");
                                    } elseif (!empty($dns_record['ip'])) {
                                        fwrite($output_file_handle, $dns_record['ip'] . "\n");
                                    }
                                }
                            }
                        }
                    }
                    fflush($output_file_handle);
                }
            }
            fclose($output_file_handle);

            if ($alias_type == "urltable_ports") {
                $ports = explode("\n", file_get_contents($urltable_filename));
                $ports = group_ports($ports);
                file_put_contents($urltable_filename, implode("\n", $ports));
            }
            @unlink("{$urltable_filename}.tmp");
        } else {
          touch($urltable_filename);
        }
        return true;
    } else {
        // File exists, and it doesn't need updated.
        return -1;
    }
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
    usort($config['filter']['rule'], function($a, $b){
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
    if (isvalidpid('/var/run/filterlog.pid')) {
        return;
    }

    if ($verbose) {
        echo 'Starting PFLOG...';
        flush();
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
        if (!empty($config['hasync']['synchronizetoip'])) {
            configd_run('filter sync restart');
        }
    }
}

function filter_delete_states_for_down_gateways()
{
    global $config, $GatewaysList;

    $any_gateway_down = false;
    $a_gateways = return_gateways_status();
    if (is_array($GatewaysList)) {
        foreach ($GatewaysList as $gwname => $gateway) {
          if (empty($gateway['monitor'])) {
              continue;
          } elseif (!is_ipaddr($gateway['monitor'])) {
              continue;
          } elseif (strstr($gateway['monitor'], "127.0.0.")) {
              continue;
          } elseif (empty($a_gateways[$gateway['monitor']])) {
              continue;
          }
          $gwstatus =& $a_gateways[$gateway['monitor']];
          if (strstr($gwstatus['status'], "down")) {
              $any_gateway_down = true;
              break;
          }
        }
    }
    if ($any_gateway_down == true) {
        mwexec("/sbin/pfctl -Fs");
    }
}

function filter_configure_sync($verbose = false)
{
    global $config, $GatewaysList;
    $sched_kill_states = array(); // kill states for schedules

    // Temporary fill $GatewaysList, the global is still used by some old functions
    $GatewaysList = return_gateways_array(false, true) + return_gateway_groups_array();

    /* Use filter lock to not allow concurrent filter reloads during this run. */
    $filterlck = lock('filter', LOCK_EX);

    // initialize fw plugin object
    $fw = new \OPNsense\Firewall\Plugin();
    $cnfint = legacy_config_get_interfaces(array("enable" => true));
    // to set "reply-to" we need to know the gateway for our interface, let's collect it here and pass it on to
    // setInterfaceMapping()
    foreach ($cnfint as $key => &$value) {
        $value['gateway'] = get_interface_gateway($key);
        $value['gatewayv6'] = get_interface_gateway_v6($key);
    }
    // init interfaces and gateways
    $fw->setInterfaceMapping($cnfint);
    $fw->setGateways(return_gateways_array(false, true));
    $fw->setGatewayGroups(return_gateway_groups_array());
    filter_core_bootstrap($fw);

    plugins_firewall($fw);

    // register user rules
    foreach ($config['filter']['rule'] as $rule) {
        $sched = '';
        $descr = '';

        if (!empty($rule['sched'])) {
            $sched = "({$rule['sched']})";
        }
        if (!empty($rule['descr'])) {
            $descr = ": {$rule['descr']}";
        }

        $rule['label'] = fix_rule_label("USER_RULE{$sched}{$descr}") ;
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
                        $rule['label'] = "[FIN]".$rule['label'];
                    }
                    break;
                }
            }
        }
        $fw->registerFilterRule($prio, $rule);
    }

    filter_pflog_start();

    if ($verbose) {
        echo 'Configuring firewall.';
        flush();
    }

    /* XXX deprectated feature, only called on reconfigure */
    if (isset($config['system']['gw_switch_default'])) {
        $gateways_status = return_gateways_status(true);
        $gateways_arr = return_gateways_array();
        fixup_default_gateway($gateways_status, $gateways_arr);
    }

    $aliases = filter_generate_aliases($cnfint);

    if ($verbose) {
        echo '.';
        flush();
    }

    $FilterIflist = filter_generate_optcfg_array();
    $natrules = filter_nat_rules_generate($FilterIflist);

    if ($verbose) {
        echo '.';
        flush();
    }

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

    $limitrules = "";
    /* User defined maximum table entries in Advanced menu. */
    if (!empty($config['system']['maximumtableentries']) && is_numeric($config['system']['maximumtableentries'])) {
        $limitrules .= "set limit table-entries {$config['system']['maximumtableentries']}\n";
    }

    if (!empty($config['system']['rulesetoptimization'])) {
        $limitrules .= "set ruleset-optimization {$config['system']['rulesetoptimization']}\n";
    } else {
        $limitrules .= "set ruleset-optimization basic\n";
    }

    if ($config['system']['optimization'] <> "") {
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

    if (!empty($config['system']['maximumstates']) && is_numeric($config['system']['maximumstates'])) {
        /* User defined maximum states in Advanced menu. */
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

    unset($aliases, $natrules);

    // Copy rules.debug to rules.debug.old
    if (file_exists('/tmp/rules.debug')) {
        @copy('/tmp/rules.debug', '/tmp/rules.debug.old');
    }

    if (!@file_put_contents('/tmp/rules.debug', $rules, LOCK_EX)) {
        log_error("WARNING: Could not write new rules!");
        unlock($filterlck);
        return;
    }

    @file_put_contents('/tmp/rules.limits', $limitrules);
    mwexec('/sbin/pfctl -Of /tmp/rules.limits');
    unset($rules, $limitrules);

    unset($rules_loading, $rules_error);
    $_grbg = exec('/sbin/pfctl -o basic -f /tmp/rules.debug 2>&1', $rules_error, $rules_loading);

    foreach ($sched_kill_states as $label) {
        mwexecf('/sbin/pfctl -k label -k %s', $label);
    }


    /*
     * check for a error while loading the rules file.  if an error has occurred
     * then output the contents of the error to the caller
     */
    if ($rules_loading) {
        /* XXX ALTQ not loaded message compat */
        $altq_strip = 2;
        if (count($rules_error) >= $altq_strip) {
            foreach (range(1, $altq_strip) as $altq_unused) {
                if (strpos($rules_error[0], 'ALTQ') !== false) {
                    array_shift($rules_error);
                }
            }
        }

        $saved_line_error = $rules_error[0];
        $config_line_error = '';

        $line_error = explode(":", $rules_error[0]);
        if (isset($line_error[1])) {
            // only report issues with line numbers
            $line_number = $line_error[1];
            $line_split = file('/tmp/rules.debug');
            if (is_array($line_split)) {
                $config_line_error = sprintf(' - ' . gettext('The line in question reads [%d]: %s'), $line_number, $line_split[$line_number-1]);
            }
            unset($line_split);
        }

        /* Brutal ugly hack but required -- PF is stuck, unwedge */
        if (strstr("$rules_error[0]", "busy")) {
            exec('/sbin/pfctl -d; /sbin/pfctl -e; /sbin/pfctl -f /tmp/rules.debug');
            $error_msg = gettext("PF was wedged/busy and has been reset.");
            file_notice("pf_busy", $error_msg, "pf_busy", "");
        } else {
            $_grbg = exec('/sbin/pfctl -o basic -f /tmp/rules.debug.old 2>&1');
        }
        unset($rules_loading, $rules_error);

        file_notice('filter_load', sprintf(gettext('There were error(s) loading the rules: %s%s'), $saved_line_error, $config_line_error), 'Filter Reload', '');
        unlock($filterlck);
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
        $_grbg = exec('/sbin/pfctl -t bogonsv6 -T kill 2>/dev/null');
    }

    // collect all filterdns entries
    $filterdns = null;
    foreach (filter_expand_aliases() as $name => $aliascontent) {
        if (!empty($aliascontent['hostnames'])) {
            foreach ($aliascontent['hostnames'] as $address) {
                $filterdns .= "pf {$address} {$name}\n";
            }
        }
    }
    killbypid('/var/run/filterdns.pid', 'KILL', true);
    if (!empty($filterdns)) {
        @file_put_contents('/var/etc/filterdns.conf', $filterdns);
        /*
         * FilterDNS has three debugging levels. The default choosen is 1.
         * Availabe are level 2 and greater than 2.
         */
        if (isset($config['system']['aliasesresolveinterval']) && is_numeric($config['system']['aliasesresolveinterval'])) {
            $resolve_interval = $config['system']['aliasesresolveinterval'];
        } else {
            $resolve_interval = 300;
        }
        mwexec("/usr/local/sbin/filterdns -p /var/run/filterdns.pid -i {$resolve_interval} -c /var/etc/filterdns.conf -d 1");
    }

    if ($verbose) {
        echo '.';
        flush();
    }

    if (!isset($config['system']['kill_states'])) {
        filter_delete_states_for_down_gateways();
    }

    if ($verbose) {
        echo "done.\n";
    }

    unlock($filterlck);
    return 0;
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
                $scrub_rule_out .= !empty($scrub_rule['direction']) ? " " . $scrub_rule['direction'] : "" ;
                $scrub_rule_out .= " on ";
                $interfaces = array();
                foreach (explode(',', $scrub_rule['interface']) as $interface) {
                    if (!empty($FilterIflist[$interface]['if'])) {
                        $interfaces[] = $FilterIflist[$interface]['if'];
                    }
                }
                $scrub_rule_out .= count($interfaces) > 1 ? "{ ". implode(' ', $interfaces) . " } " : $interfaces[0];
                $scrub_rule_out .= $scrub_rule['proto'] != 'any' ? " proto " . $scrub_rule['proto'] : "";
                $scrub_rule_out .= " from ";
                if (is_alias($scrub_rule['src'])) {
                    $scrub_rule_out .= !empty($scrub_rule['srcnot']) ? "!" : "";
                    $scrub_rule_out .= '$'.$scrub_rule['src'];
                } elseif (is_ipaddr($scrub_rule['src'])) {
                    $scrub_rule_out .= !empty($scrub_rule['srcnot']) ? "!" : "";
                    $scrub_rule_out .= $scrub_rule['src'] . "/" . $scrub_rule['srcmask'];
                } else {
                    $scrub_rule_out .= "any";
                }
                $scrub_rule_out .= !empty($scrub_rule['srcport']) ?  " port " . $scrub_rule['srcport'] : "";
                $scrub_rule_out .= " to ";
                if (is_alias($scrub_rule['dst'])) {
                    $scrub_rule_out .= !empty($scrub_rule['dstnot']) ? "!" : "";
                    $scrub_rule_out .= '$'.$scrub_rule['dst'];
                } elseif (is_ipaddr($scrub_rule['dst'])) {
                    $scrub_rule_out .= !empty($scrub_rule['dstnot']) ? "!" : "";
                    $scrub_rule_out .= $scrub_rule['dst'] . "/" . $scrub_rule['dstmask'];
                } else {
                    $scrub_rule_out .= "any";
                }
                $scrub_rule_out .= !empty($scrub_rule['dstport']) ?  " port " . $scrub_rule['dstport'] : "";
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
        foreach ($FilterIflist as $scrubif => $scrubcfg) {
            if (isset($scrubcfg['virtual']) || empty($scrubcfg['descr'])) {
                continue;
            } else {
                /* set up MSS clamping */
                if (!empty($scrubcfg['mss']) && is_numeric($scrubcfg['mss']) &&
                    !in_array($scrubcfg['if'], array('pppoe', 'pptp', 'l2tp'))) {
                    $mssclamp = "max-mss " . (intval($scrubcfg['mss'] - 40));
                } else {
                    $mssclamp = '';
                }
                $scrubnodf = !empty($config['system']['scrubnodf']) ? "no-df" : "";
                $scrubrnid = !empty($config['system']['scrubrnid']) ? "random-id" : "";
                $scrubrules .= "scrub on \${$scrubif} all {$scrubnodf} {$scrubrnid} {$mssclamp}\n";
            }
        }
    }

    return $scrubrules;
}

/**
 * recursively collect alias(es) contents
 * @param $aliasname string|null alias name or null to fetch all aliases
 * @param $maxdepth maximum recursion depth
 * @param $depth current depth (internal use)
 * @return array containing addresses (or ports) and hostnames
 */
function filter_expand_aliases($aliasname=null, $maxdepth=10, $depth=0)
{
    global $config;

    $response = array();
    if (isset($config['aliases']['alias'])) {
        foreach ($config['aliases']['alias'] as $aliased) {
            if ($aliasname === null || $aliasname == $aliased['name']) {
                // new output structure
                $response[$aliased['name']] = array('addresses' => array(), 'hostnames' => array());
                if ($aliasname !== null && in_array($aliased['type'], array('urltable', 'urltable_ports'))) {
                    // only get contents when nested,
                    // it's functional, but fetching remote lists has disadvantages...
                    $urltable_contents = @file_get_contents(alias_expand_urltable($aliased['name']));
                    $response[$aliased['name']]['addresses'] = preg_split('/[\s]+/', $urltable_contents);
                } elseif (in_array($aliased['type'], array('host', 'network', 'port', 'url'))
                      && isset($aliased['address'])) {
                    // split alias contents and handle per type
                    foreach (explode(" ", $aliased['address']) as $address) {
                        if (is_alias($address)) {
                            if ($depth < $maxdepth) {
                                $tmp = filter_expand_aliases($address, $maxdepth, $depth+1);
                                foreach ($tmp as $aliascontent2) {
                                    foreach ($aliascontent2 as $aliastype_key => $aliastype) {
                                        $response[$aliased['name']][$aliastype_key] = array_merge(
                                            $response[$aliased['name']][$aliastype_key], $aliastype
                                        );
                                    }
                                }
                            }
                        } elseif (!is_ipaddr($address) && !is_subnet($address)
                              && !is_port($address) && !is_portrange($address) && is_hostname($address)
                              && in_array($aliased['type'], array('host'))) {
                            $response[$aliased['name']]['hostnames'][] = $address;
                        } elseif (strpos($address, '-') !== false) {
                            $tmp = explode('-', $address);
                            $response[$aliased['name']]['addresses'] = array_merge(
                              $response[$aliased['name']]['addresses'],
                              ip_range_to_subnet_array($tmp[0], $tmp[1])
                            );
                        } else {
                            $response[$aliased['name']]['addresses'][] = $address;
                        }
                    }
                }
            }
        }
    }
    // de-duplicate results
    foreach ($response as $alias_name => $alias) {
        foreach ($alias as $type => $value) {
            $response[$alias_name][$type] = array_filter(array_unique($value), 'strlen');
        }
    }
    return $response;
}

function filter_expand_alias($alias_name)
{
    $tmp = filter_expand_aliases($alias_name);
    if (!empty($tmp[$alias_name]['addresses'])) {
        return implode(' ', $tmp[$alias_name]['addresses']);
    } else {
        return '';
    }
}

function filter_expand_alias_array($alias_name) {
    $expansion = filter_expand_alias($alias_name);
    return explode(" ", preg_replace('/\s+/', ' ', trim($expansion)));
}

function filter_generate_aliases(&$FilterIflist)
{
    global $config;

    $aliases = "# System aliases\n";
    $aliases .= "loopback = \"{ lo0 }\"\n";

    foreach ($FilterIflist as $if => $ifcfg) {
        if (!empty($ifcfg['if'])) {
            if (isset($ifcfg['ipaddrv6']) && $ifcfg['ipaddrv6'] == '6rd') {
                $aliases .= "{$if} = \"{ {$ifcfg['if']} {$if}_stf";
            } elseif (isset($ifcfg['ipaddrv6']) && $ifcfg['ipaddrv6'] == '6to4') {
                $aliases .= "{$if} = \"{ {$ifcfg['if']} {$if}_stf";
            } else {
                $aliases .= "{$if} = \"{ {$ifcfg['if']}";
                if (isset($ifcfg['ipaddr']) && $ifcfg['ipaddr'] == 'pptp') {
                    foreach (get_parent_interface($ifcfg['if']) as $parent_if) {
                        if ($parent_if != $ifcfg['if']) {
                            $aliases .= " {$parent_if}";
                        }
                    }
                }
            }
            $aliases .= " }\"\n";
        }
    }

    $aliases .= "\n# SSH Lockout Table\n";
    $aliases .= "table <sshlockout> persist\n";
    $aliases .= "table <webConfiguratorlockout> persist\n";

    $aliases .= "# Other tables\n";
    $aliases .= "table <virusprot>\n";
    touch('/usr/local/etc/bogons');
    touch('/usr/local/etc/bogonsv6');
    $aliases .= "table <bogons> persist file \"/usr/local/etc/bogons\"\n";
    if (is_bogonsv6_used()) {
        $aliases .= "table <bogonsv6> persist file \"/usr/local/etc/bogonsv6\"\n";
    }

    $aliases .= "\n# User Aliases \n";
    /* Setup pf groups */
    if (isset($config['aliases']['alias'])) {
        $expanded_aliases = filter_expand_aliases();
        foreach ($config['aliases']['alias'] as $aliased) {
            $aliasnesting = array();
            $aliasaddrnesting = array();
            if (!empty($expanded_aliases[$aliased['name']]['addresses'])) {
                $addrlist = implode(' ', $expanded_aliases[$aliased['name']]['addresses']);
            } else {
                $addrlist = null;
            }
            switch ($aliased['type']) {
                case "host":
                case "network":
                case "url":
                    if (empty($addrlist)) {
                        $aliases .= "table <{$aliased['name']}> {} persist\n";
                    } else {
                        $aliases .= "table <{$aliased['name']}> { {$addrlist} } \n";
                    }
                    $aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
                    break;
                case "urltable":
                case "geoip":
                    $urlfn = alias_expand_urltable($aliased['name']);
                    if ($urlfn) {
                        $aliases .= "table <{$aliased['name']}> persist file \"{$urlfn}\"\n";
                        $aliases .= "{$aliased['name']} = \"<{$aliased['name']}>\"\n";
                    }
                    break;
                case "urltable_ports":
                    $urlfn = alias_expand_urltable($aliased['name']);
                    if ($urlfn) {
                        $aliases .= "{$aliased['name']} = \"{ " . preg_replace("/\n/", " ", file_get_contents($urlfn)) . " }\"\n";
                    }
                    break;
                case "port":
                case "url_ports":
                    $aliases .= "{$aliased['name']} = \"{ {$addrlist} }\"\n";
                    break;
                default:
                    $aliases .= "{$aliased['name']} = \"{ {$aliased['address']} }\"\n";
                    break;
            }
        }
    }

    return $aliases;
}

/* returns space separated list of directly connected networks
 * optionally returns an array instead, including friendly interface and gateway (if applicable)
 */
function filter_get_direct_networks_list(&$FilterIflist, $returnsubnetsonly = true)
{
    global $config, $GatewaysList;

    /* build list of directly connected interfaces and networks */
    $networks = "";
    $networks_arr = array();

    foreach ($FilterIflist as $ifent => $ifcfg) {
        $subnet = "{$ifcfg['sa']}/{$ifcfg['sn']}";
        if (is_subnet($subnet)) {
            if ($returnsubnetsonly) {
                $networks_arr[] = $subnet;
            } else {
                $networks_arr[] = array(
                  'subnet' => $subnet,
                  'if' => $ifent,
                  'ip' => $ifcfg['ip']);
            }
        }
    }
    foreach(get_configured_ip_aliases_list(true) as $vip) {
        $subnet = "{$vip['subnet']}/{$vip['subnet_bits']}";
        if (is_subnet($subnet) && !(is_subnetv4($subnet) && $vip['subnet_bits'] == 32) && !(is_subnetv6($subnet) && $vip['subnet_bits'] == 128)) {
            if (is_subnetv4($subnet)) {
                $subnet = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/{$vip['subnet_bits']}";
            } elseif (is_subnetv6($subnet)) {
                $subnet = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/{$vip['subnet_bits']}";
            }
            if ($returnsubnetsonly) {
                $networks_arr[] = $subnet;
            } else {
                $networks_arr[] = array(
                  'subnet' => $subnet,
                  'if' => $vip['interface'],
                  'ip' => $vip['subnet']);
            }
        }
    }
    foreach(get_staticroutes() as $netent) {
        if (is_subnet($netent['network'])) {
            if ($returnsubnetsonly) {
                $networks_arr[] = $netent['network'];
            } elseif (isset($GatewaysList[$netent['gateway']])) {
                $networks_arr[] = array(
                  'subnet' => $netent['network'],
                  'if' => $GatewaysList[$netent['gateway']]['friendlyiface'],
                  'gateway' => $GatewaysList[$netent['gateway']]['gateway']);
            }
        }
    }
    if ($returnsubnetsonly) {
        if (!empty($networks_arr)) {
            $networks = implode(" ", $networks_arr);
        }
        return $networks;
    } else {
        return $networks_arr;
    }
}

function filter_generate_optcfg_array()
{
    global $config;

    $FilterIflist = array();

    /* traverse interfaces */
    foreach (legacy_config_get_interfaces(array("enable" => true)) as $if => $ifdetail) {
        if (isset($ifdetail['internal_dynamic'])) {
            // transform plugin configuration
            $oic = array();
            $oic['internal_dynamic'] = true;
            $oic['vips'] = array();
            $oic['vips6'] = array();
            $oic['descr'] = $ifdetail['descr'];
            $oic['if'] = $ifdetail['if'];
            if (isset($ifdetail['virtual'])) {
                $oic['virtual'] = $ifdetail['virtual'];
            }
            if (!empty($ifdetail['networks'])) {
                foreach (isset($ifdetail['networks'][0]) ? $ifdetail['networks'] : array($ifdetail['networks']) as $indx => $network) {
                    if (is_ipaddrv4($network['network'])) {
                        if ($indx == 0) {
                            $oic['sa'] = $network['network'];
                            $oic['sn'] = $network['mask'];
                        } else {
                            $vip = array();
                            $vip['sa'] = $network['network'];
                            $vip['sn'] = $network['mask'];
                            $oic['vips'][] = $vip;
                        }
                    } elseif (is_ipaddrv6($network['network'])) {
                        if ($indx == 0) {
                            $oic['sav6'] = $network['network'];
                            $oic['snv6'] = $network['mask'];
                        } else {
                            $vip = array();
                            $vip['sa'] = $network['network'];
                            $vip['sn'] = $network['mask'];
                            $oic['vips6'][] = $vip;
                        }
                    }
                }
            }
            $FilterIflist[$if] = $oic;
        } else {
            // XXX needs cleanup, original content
            $oic = array();
            $oic['if'] = get_real_interface($if);
            if (!does_interface_exist($oic['if'])) {
                continue;
            }
            $oic['ifv6'] = get_real_interface($if, "inet6");
            $oic['ip'] = get_interface_ip($if);
            $oic['ipv6'] = get_interface_ipv6($if);
            if (!is_ipaddrv4($ifdetail['ipaddr']) && !empty($ifdetail['ipaddr'])) {
                $oic['type'] = $ifdetail['ipaddr'];
            }
            if (isset($ifdetail['ipaddrv6'])) {
                if ( !is_ipaddrv6($ifdetail['ipaddrv6']) && !empty($ifdetail['ipaddrv6'])) {
                    $oic['type6'] = $ifdetail['ipaddrv6'];
                }
            } else {
                $oic['type6'] = null;
            }
            if (!empty($ifdetail['track6-interface'])) {
                $oic['track6-interface'] = $ifdetail['track6-interface'];
            }
            $oic['sn'] = get_interface_subnet($if);
            $oic['snv6'] = get_interface_subnetv6($if);
            $oic['mtu'] = empty($ifdetail['mtu']) ? 1500 : $ifdetail['mtu'];
            $oic['mss'] = empty($ifdetail['mss']) ? '' : $ifdetail['mss'];
            $oic['descr'] = !empty($ifdetail['descr']) ? $ifdetail['descr'] : $if;
            $oic['sa'] = gen_subnet($oic['ip'], $oic['sn']);
            $oic['sav6'] = gen_subnetv6($oic['ipv6'], $oic['snv6']);
            if (isset($ifdetail['alias-address'])) {
                $oic['alias-address'] = $ifdetail['alias-address'];
            } else {
                $oic['alias-address'] = null;
            }
            if (isset($ifdetail['alias-subnet'])) {
                $oic['alias-subnet'] = $ifdetail['alias-subnet'];
            } else {
                $oic['alias-subnet'] = null;
            }
            if (isset($ifdetail['gateway'])) {
                $oic['gateway'] = $ifdetail['gateway'];
            } else {
                $oic['gateway'] = null ;
            }
            if (isset($ifdetail['gatewayv6'])) {
                $oic['gatewayv6'] = $ifdetail['gatewayv6'];
            } else {
                $oic['gatewayv6'] = null;
            }
            $oic['bridge'] = link_interface_to_bridge($if);
            $vips = link_interface_to_vips($if);
            if (!empty($vips)) {
                foreach ($vips as $vipidx => $vip) {
                    if (is_ipaddrv4($vip['subnet'])) {
                        if (!isset($oic['vips'])) {
                            $oic['vips'] = array();
                        }
                        $oic['vips'][$vipidx]['ip'] = $vip['subnet'];
                        if (empty($vip['subnet_bits'])) {
                            $oic['vips'][$vipidx]['sn'] = 32;
                        } else {
                            $oic['vips'][$vipidx]['sn'] = $vip['subnet_bits'];
                        }
                    } elseif (is_ipaddrv6($vip['subnet'])) {
                        if (!is_array($oic['vips6'])) {
                            $oic['vips6'] = array();
                        }
                        $oic['vips6'][$vipidx]['ip'] = $vip['subnet'];
                        if (empty($vip['subnet_bits'])) {
                            $oic['vips6'][$vipidx]['sn'] = 128;
                        } else {
                            $oic['vips6'][$vipidx]['sn'] = $vip['subnet_bits'];
                        }
                    }
                }
            }
            unset($vips);
            $FilterIflist[$if] = $oic;
        }
    }

    return $FilterIflist ;
}


function filter_get_reflection_interfaces(&$FilterIflist, $natif)
{
    $nat_if_list = array();
    foreach ($FilterIflist as $ifent => $ifname) {
        if ($ifname['if'] != $natif && !interface_has_gateway($ifent)) {
            $nat_if_list[] = $ifname['if'];
        }
    }
    return $nat_if_list;
}

function filter_generate_reflection_nat(&$FilterIflist, $rule, &$route_table, $nat_ifs, $protocol, $target, $target_ip, $target_subnet = '')
{
    // Initialize natrules holder string
    $natrules = '';

    /* TODO: Add this option to port forwards page. */
    if (isset($rule['staticnatport'])) {
        $static_port = " static-port";
    } else {
        $static_port = " port 1024:65535";
    }

    if (!empty($protocol)) {
        $protocol_text = " proto {$protocol}";
    } else {
        $protocol_text = "";
    }

    if (empty($target_subnet) || !is_numeric($target_subnet)) {
        $target_subnet = 32;
    }

    if (!is_array($route_table)) {
        /* get a simulated IPv4-only route table based on the config */
        $route_table = filter_get_direct_networks_list($FilterIflist, false);
        foreach($route_table as $rt_key => $rt_ent) {
            if (!is_subnetv4($rt_ent['subnet'])) {
                unset($route_table[$rt_key]);
            }
            if (isset($route_table[$rt_key]) && isset($FilterIflist[$rt_ent['if']]['if'])) {
                $route_table[$rt_key]['if'] = $FilterIflist[$rt_ent['if']]['if'];
            }
        }
    }

    /* Check if the target is accessed through a static route */
    foreach($route_table as $route) {
        if (isset($route['gateway']) && is_ipaddr($route['gateway'])) {
            $subnet_split = explode("/", $route['subnet']);
            if (in_array($route['if'], $nat_ifs) && check_subnets_overlap($target_ip, $target_subnet, $subnet_split[0], $subnet_split[1])) {
                $target_ip = $route['gateway'];
                $target_subnet = 32;
                break;
            }
        }
    }

    /* Search for matching subnets in the routing table */
    foreach($route_table as $route) {
        $subnet = $route['subnet'];
        $subnet_split = explode("/", $subnet);
        $subnet_if = $route['if'];
        if (in_array($subnet_if, $nat_ifs) && check_subnets_overlap($target_ip, $target_subnet, $subnet_split[0], $subnet_split[1])) {
            $ifsubnet_ip = "";
            /* Find interface IP to use for NAT */
            foreach ($route_table as $ifnetwork) {
                if (isset($ifnetwork['ip']) && is_ipaddr($ifnetwork['ip']) && $ifnetwork['if'] == $subnet_if && ip_in_subnet($ifnetwork['ip'], $subnet)) {
                    $ifsubnet_ip = $ifnetwork['ip'];
                    break;
                }
            }
            if (!empty($ifsubnet_ip)) {
                $subnets = array($subnet);
                /* Find static routes that also need to be referenced in the NAT rule */
                foreach($route_table as $rtentry) {
                    if (isset($rtentry['gateway']) && is_ipaddr($rtentry['gateway']) && $rtentry['if'] == $subnet_if && ip_in_subnet($rtentry['gateway'], $subnet)) {
                        $subnets[] = $rtentry['subnet'];
                    }
                }
                if (count($subnets) > 1) {
                    $subnet = "{ " . implode(" ", $subnets) . " }";
                }
                $natrules .= "no nat on {$subnet_if}{$protocol_text} from {$subnet_if} to {$target}\n";
                $natrules .= "nat on {$subnet_if}{$protocol_text} from {$subnet} to {$target} -> {$ifsubnet_ip}{$static_port}\n";
            }
        }
    }

    if (!empty($natrules)) {
        $natrules .= "\n";
    }

    return $natrules;
}


function filter_nat_rules_automatic_tonathosts(&$FilterIflist, $with_descr = false)
{
    global $config, $GatewaysList;

    $tonathosts = array("127.0.0.0/8");
    $descriptions = array(gettext("localhost"));

    foreach (get_staticroutes() as $route) {
        $netip = explode("/", $route['network']);
        if (isset($GatewaysList[$route['gateway']])) {
            $gateway =& $GatewaysList[$route['gateway']];
            if (!interface_has_gateway($gateway['interface']) && is_private_ip($netip[0])) {
                $tonathosts[] = $route['network'];
                $descriptions[] = gettext("static route");
            }
        }
    }

    /* create outbound nat entries for all local networks */
    foreach($FilterIflist as $ocname => $oc) {
        if (interface_has_gateway($ocname)) {
            continue;
        }
        if (isset($oc['alias-address']) && is_ipaddr($oc['alias-address'])) {
            $tonathosts[] = "{$oc['alias-address']}/{$oc['alias-subnet']}";
            $descriptions[] = $oc['descr'] . " " . gettext("DHCP alias address");
        }
        if (!empty($oc['sa'])) {
            $tonathosts[] = "{$oc['sa']}/{$oc['sn']}";
            $descriptions[] = $oc['descr'];
            if (!empty($oc['vips']) && !empty($oc['internal_dynamic'])) {
                foreach ($oc['vips'] as $vip) {
                    $tonathosts[] = "{$vip['sa']}/{$vip['sn']}";
                    $descriptions[] = $oc['descr'];
                }
            } elseif (isset($oc['vips']) && is_array($oc['vips'])) {
                $if_subnets = array("{$oc['sa']}/{$oc['sn']}");
                foreach ($oc['vips'] as $vip) {
                    if (!is_ipaddrv4($vip['ip'])) {
                        continue;
                    }
                    foreach ($if_subnets as $subnet) {
                        if (ip_in_subnet($vip['ip'], $subnet)) {
                            continue 2;
                        }
                    }
                    $network = gen_subnet($vip['ip'], $vip['sn']);
                    array_unshift($tonathosts, $network . '/' . $vip['sn']);
                    array_unshift($descriptions, "Virtual IP ({$oc['descr']})");
                    $if_subnets[] = $network . '/' . $vip['sn'];
                    unset($network);
                }
                unset($if_subnets);
            }
        }
    }

    /* add openvpn interfaces */
    if (isset($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as $ovpnsrv) {
            if (!isset($ovpnsrv['disable']) && !empty($ovpnsrv['tunnel_network'])) {
                $tonathosts[] = $ovpnsrv['tunnel_network'];
                $descriptions[] = gettext("OpenVPN server");
            }
        }
    }

    if (isset($config['openvpn']['openvpn-client'])) {
        foreach ($config['openvpn']['openvpn-client'] as $ovpncli) {
            if (!isset($ovpncli['disable']) && !empty($ovpncli['tunnel_network'])) {
                $tonathosts[] = $ovpncli['tunnel_network'];
                $descriptions[] = gettext("OpenVPN client");
            }
        }
    }

    /* IPsec mode_cfg subnet */
    if (isset($config['ipsec']['client']['enable']) &&
        !empty($config['ipsec']['client']['pool_address']) &&
        !empty($config['ipsec']['client']['pool_netbits'])) {
        $tonathosts[] = "{$config['ipsec']['client']['pool_address']}/{$config['ipsec']['client']['pool_netbits']}";
        $descriptions[] = gettext("IPsec client");
    }

    if ($with_descr) {
        $combined = array();
        foreach ($tonathosts as $idx => $subnet) {
            $combined[] = array(
              "subnet" => $subnet,
              "descr" => $descriptions[$idx]);
        }

        return $combined;
    } else {
        return $tonathosts;
    }
}

function filter_nat_rules_outbound_automatic(&$FilterIflist, $src)
{
    global $config ;

    $rules = array();

    foreach ($FilterIflist as $if => $ifcfg) {
        if (substr($ifcfg['if'], 0, 4) == 'ovpn') {
            continue;
        }

        if (!interface_has_gateway($if)) {
            continue;
        }

        $natent = array();
        $natent['interface'] = $if;
        $natent['source']['network'] = $src;
        $natent['dstport'] = '500';
        $natent['target'] = '';
        $natent['destination']['any'] = true;
        $natent['staticnatport'] = true;
        $natent['descr'] = gettext('Auto created rule for ISAKMP');
        $rules[] = $natent;

        $natent = array();
        $natent['interface'] = $if;
        $natent['source']['network'] = $src;
        $natent['sourceport'] = '';
        $natent['target'] = '';
        $natent['destination']['any'] = true;
        $natent['natport'] = '';
        $natent['descr'] = gettext('Auto created rule');
        $rules[] = $natent;
    }

    return $rules;
}

/* Generate a 'nat on' or 'no nat on' rule for given interface */
function filter_nat_rules_generate_if(&$FilterIflist, $if, $src = "any", $srcport = "", $dst = "any", $dstport = "", $natip = "", $natport = "", $nonat = false, $staticnatport = false, $proto = "", $poolopts = "", $log = false, $ipproto = "inet", $tag = '', $tagged = '')
{
    global $config;

    if ($natip != "") {
        if (is_subnet($natip)) {
            $tgt = $natip;
        } elseif (is_alias($natip)) {
            $tgt = "\${$natip}";
        } elseif (is_ipaddrv6($natip)) {
            $tgt = "{$natip}/128";
        } else {
            $tgt = "{$natip}/32";
        }
    } else {
        if ($ipproto == "inet6") {
            $natip = get_interface_ipv6($if);
        } else {
            $natip = get_interface_ip($if);
        }

        if (is_ipaddrv6($natip)){
            $tgt = "{$natip}/128";
        } elseif (is_ipaddr($natip)) {
            $tgt = "{$natip}/32";
        } else {
            $tgt = "(" . $FilterIflist[$if]['if'] . ")";
        }
    }
    $logtag = '';
    if ($log) {
        $logtag = 'log';
    }
    /* Add the protocol, if defined */
    if (!empty($proto) && $proto != "any") {
        if ($proto == "tcp/udp") {
            $protocol = " proto { tcp udp }";
        } else {
            $protocol = " proto {$proto}";
        }
    } else {
        $protocol = "";
    }

    /* Add the ip protocol */
    if (!empty($ipproto) && $ipproto != "inet") {
        $ipprotocol = " {$ipproto}";
    } else {
        $ipprotocol = "";
    }

    /* Add the hard set source port (useful for ISAKMP) */
    if ($natport != "") {
        $tgt .= " port {$natport}";
    }
    /* sometimes this gets called with "" instead of a value */
    if ($src == "") {
        $src = "any";
    }
    /* Match on this source port */
    if ($srcport != "") {
        $srcportexpand = alias_expand($srcport);
        if (!$srcportexpand) {
            $srcportexpand = $srcport;
        }
        $src .= " port {$srcportexpand}";
    }
    /* sometimes this gets called with "" instead of a value */
    if ($dst == "") {
        $dst = "any";
    }
    /* Match on this dest port */
    if ($dstport != "") {
        $dstportexpand = alias_expand($dstport);
        if (!$dstportexpand) {
            $dstportexpand = $dstport;
        }
        $dst .= " port {$dstportexpand}";
    }
    /* outgoing static-port option, hamachi, Grandstream, VOIP, etc */
    $staticnatport_txt = "";
    if ($staticnatport) {
        $staticnatport_txt = "static-port";
    } elseif (!$natport) {
        $tgt .= " port 1024:65535"; // set source port range
    }
    /* set and get tag handling */
    $tagging = '';
    if (!empty($tag)) {
        $tagging .= " tag {$tag}";
    }
    if (!empty($tagged)) {
        $tagging .= " tagged {$tagged}";
    }
    /* Allow for negating NAT entries */
    if ($nonat) {
        $nat = "no nat";
        $target = "";
        $staticnatport_txt = "";
        $poolopts = "";
    } else {
        $nat = "nat";
        $target = "-> {$tgt}";
    }
    /* Put all the pieces together */
    $natrule = "{$nat} {$logtag} on \${$if}{$ipprotocol}{$protocol} from {$src} to {$dst}${tagging} {$target} {$poolopts} {$staticnatport_txt}\n";

    return $natrule;
}

function filter_nat_rules_generate(&$FilterIflist)
{
    global $config, $GatewaysList;

    $natrules = "no nat proto carp\n";
    $natrules .= "no rdr proto carp\n";

    $reflection_txt = "";
    $route_table = "";

    /* any 1:1 mappings? */
    if (isset($config['nat']['onetoone']) && is_array($config['nat']['onetoone'])) {
        foreach ($config['nat']['onetoone'] as $rule) {
            if (isset($rule['disabled'])) {
                continue;
            }
            $sn = "";
            $sn1 = "";
            $target = alias_expand($rule['external']);
            if (!$target) {
                $natrules .= "# Unresolvable alias {$rule['target']}\n";
                continue;               /* unresolvable alias */
            }

            if (!$rule['interface']) {
                $natif = "wan";
            } else {
                $natif = $rule['interface'];
            }
            if (!isset($FilterIflist[$natif])) {
                continue;
            }
            $srcaddr = filter_generate_address($FilterIflist, $rule, 'source');
            $dstaddr = filter_generate_address($FilterIflist, $rule, 'destination');
            if (!$dstaddr) {
                $dstaddr = $FilterIflist[$natif]['ip'];
            }

            $srcaddr = trim($srcaddr);
            $dstaddr = trim($dstaddr);

            $tmp = explode('/', $srcaddr);
            $srcip = $tmp[0];
            if (!empty($tmp[1]) && is_numeric($tmp[1]) && strpos($target, '/') === false) {
                $sn = $tmp[1];
                $sn1 = "/{$sn}";
            }

            $natif = $FilterIflist[$natif]['if'];

            /*
             * If reflection is enabled, turn on extra redirections
             * for this rule by adding other interfaces to an rdr rule.
             */
            if ((isset($config['system']['enablebinatreflection']) || $rule['natreflection'] == "enable")
               && $rule['natreflection'] != "disable") {
                 $nat_if_list = filter_get_reflection_interfaces($FilterIflist, $natif);
            } else {
                $nat_if_list = array();
            }
            $nattype = empty($rule['type']) ? "binat" : $rule['type'];
            $natrules .= "{$nattype} on {$natif} from {$srcaddr} to {$dstaddr} -> {$target}{$sn1}\n";
            if (!empty($nat_if_list)) {
                $binat_if_list = implode(" ", $nat_if_list);
                $binat_if_list = "{ {$binat_if_list} }";
                $reflection_txt .= "rdr on {$binat_if_list} from {$dstaddr} to {$target}{$sn1} -> {$srcaddr} bitmask\n";
            }

            $nat_if_list = array_merge(array($natif), $nat_if_list);
            if (isset($config['system']['enablenatreflectionhelper'])) {
                $reflection_txt .= filter_generate_reflection_nat($FilterIflist, $rule, $route_table, $nat_if_list, '', $srcaddr, $srcip, $sn);
            }
        }
    }

    /* Add binat rules for Network Prefix translation */
    if (isset($config['nat']['npt']) && is_array($config['nat']['npt'])) {
        foreach ($config['nat']['npt'] as $rule) {
            if (isset($rule['disabled'])) {
                continue;
            }
            if (!$rule['interface']) {
                $natif = "wan";
            } else {
                $natif = $rule['interface'];
            }
            if (!isset($FilterIflist[$natif])) {
                continue;
            }
            $srcaddr = filter_generate_address($FilterIflist, $rule, 'source');
            $dstaddr = filter_generate_address($FilterIflist, $rule, 'destination');
            $srcaddr = trim($srcaddr);
            $dstaddr = trim($dstaddr);

            $natrules .= "binat on \${$natif} from {$srcaddr} to any -> {$dstaddr}\n";
            $natrules .= "binat on \${$natif} from {$dstaddr} to any -> {$srcaddr}\n";
        }
    }

    if (isset($config['nat']['outbound']['mode']) && $config['nat']['outbound']['mode'] == "disabled") {
        $natrules .= "\n# Outbound NAT rules are disabled\n";
    }

    if (isset($config['nat']['outbound']['mode']) && ($config['nat']['outbound']['mode'] == "advanced" || $config['nat']['outbound']['mode'] == "hybrid")) {
        $natrules .= "\n# Outbound NAT rules (manual)\n";
        /* advanced outbound rules */
        if (isset($config['nat']['outbound']['rule'])) {
            foreach ($config['nat']['outbound']['rule'] as $obent) {
                if (isset($obent['disabled'])) {
                    continue;
                }
                $src = alias_expand($obent['source']['network']);
                if (!$src) {
                    $src = $obent['source']['network'];
                }
                if (isset($obent['source']['not']) && !isset($obent['source']['any'])) {
                    $src = "! $src";
                }
                $dst = alias_expand($obent['destination']['address']);
                if (!$dst) {
                    $dst = $obent['destination']['address'];
                }
                if (isset($obent['destination']['not']) && !isset($obent['destination']['any'])) {
                    $dst = "! $dst";
                }

                if (!$obent['interface'] || !isset($FilterIflist[$obent['interface']])) {
                    continue;
                }
                $obtarget = ($obent['target'] == "other-subnet") ? $obent['targetip'] . '/' . $obent['targetip_subnet']: $obent['target'];
                $poolopts = (is_subnet($obtarget) || is_alias($obtarget)) ? $obent['poolopts'] : "";

                $natrules .= filter_nat_rules_generate_if(
                  $FilterIflist,
                  $obent['interface'],
                  $src,
                  $obent['sourceport'],
                  $dst,
                  $obent['dstport'],
                  $obtarget,
                  $obent['natport'],
                  isset($obent['nonat']),
                  isset($obent['staticnatport']),
                  $obent['protocol'],
                  $poolopts,
                  isset($obent['log']),
                  $obent['ipprotocol'],
                  $obent['tag'],
                  $obent['tagged']
                );
            }
        }
    }

    /* outbound rules */
    if (!isset($config['nat']['outbound']['mode']) ||
        $config['nat']['outbound']['mode'] == "automatic" ||
        $config['nat']['outbound']['mode'] == "hybrid") {
        $natrules .= "\n# Outbound NAT rules (automatic)\n";
        /* standard outbound rules (one for each interface) */
        $tonathosts_array = filter_nat_rules_automatic_tonathosts($FilterIflist);
        $tonathosts = implode(" ", $tonathosts_array);
        $numberofnathosts = count($tonathosts_array);

        $natrules .= "\n# Subnets to NAT \n";
        if ($numberofnathosts > 0) {
            if ($numberofnathosts > 4) {
                $natrules .= "table <tonatsubnets> { {$tonathosts} }\n";
                $macroortable = "<tonatsubnets>";
            } else {
                $natrules .= "tonatsubnets  = \"{ {$tonathosts} }\"\n";
                $macroortable = "\$tonatsubnets";
            }

            $a_outs = filter_nat_rules_outbound_automatic($FilterIflist, $macroortable);
            foreach ($a_outs as $a_out) {
                $natrules .= filter_nat_rules_generate_if(
                    $FilterIflist,
                    $a_out['interface'],
                    $a_out['source']['network'],
                    isset($a_out['sourceport']) ? $a_out['sourceport'] : null,
                    isset($a_out['destination']['address']) ? $a_out['destination']['address'] : null,
                    isset($a_out['dstport']) ? $a_out['dstport'] : null,
                    isset($a_out['target']) ? $a_out['target'] : null,
                    isset($a_out['natport']) ? $a_out['natport'] : null,
                    isset($a_out['nonat']),
                    isset($a_out['staticnatport'])
                );
            }
        }
        unset($tonathosts, $tonathosts_array, $numberofnathosts);
    }

    // prevent redirection on ports with "lock out" protection
    $lockoutprts = filter_core_antilockout_ports();
    if (count($lockoutprts) > 0) {
        $alports = implode(' ', $lockoutprts);
        if (!empty($FilterIflist['lan']['if']) ) {
            $alintrf = $FilterIflist['lan']['if'];
        } elseif (count($config['interfaces']) == 1 && !empty($FilterIflist['wan']['if']) ) {
            $alintrf = $FilterIflist['wan']['if'];
        } else {
            $alintrf = null;
        }
        if (!empty($alintrf) ) {
            $natrules .= "\n# Anti lockout, prevent redirects for protected ports to this interface ip \n";
            $natrules .= "no rdr on {$alintrf} proto tcp from any to ( {$alintrf} ) port { {$alports} }\n";
        }
        unset($alports);
    }

    if (isset($config['nat']['rule'])) {
        $natrules .= "\n# NAT Inbound Redirects\n";
          foreach ($config['nat']['rule'] as $rule) {
            if (isset($rule['disabled'])) {
                continue;
            }

            /* if item is an alias, expand */
            $dstport = "";
            $dstport[0] = alias_expand($rule['destination']['port']);
            if (!$dstport[0]) {
                $dstport = explode("-", $rule['destination']['port']);
            }


            if (!empty($rule['protocol']) && in_array($rule['protocol'], array('tcp/udp', 'udp', 'tcp'))) {
                $localport = alias_expand($rule['local-port']);
                if (!$localport || $dstport[0] == $localport) {
                    $localport = "";
                } elseif (is_alias($rule['local-port'])) {
                    $localport = filter_expand_alias($rule['local-port']);
                    if ($localport) {
                        $localport = explode(" ", trim($localport));
                        $localport = $localport[0];
                        $localport = " port {$localport}";
                    }
                } elseif (is_alias($rule['destination']['port'])) {
                    $localport = " port {$localport}";
                } else {
                    if (($dstport[1]) && ($dstport[0] != $dstport[1])) {
                        $localendport = $localport + ($dstport[1] - $dstport[0]);
                        $localport .= ":$localendport";
                    }
                    $localport = " port {$localport}";
                }
            } else {
                $localport = "";
            }

            $address_family = !empty($rule['ipprotocol']) ? $rule['ipprotocol'] : "";
            switch(strtolower(!empty($rule['protocol']) ? $rule['protocol'] : "")) {
                case "tcp/udp":
                    $protocol = "{ tcp udp }";
                    break;
                default:
                    $protocol = strtolower($rule['protocol']);
                    break;
            }

            $target = alias_expand($rule['target']);
            if (!$target && !isset($rule['nordr'])) {
                $natrules .= "# Unresolvable alias {$rule['target']}\n";
                continue;    /* unresolvable alias */
            }

            if (is_alias($rule['target'])) {
                $target_ip = filter_expand_alias($rule['target']);
            } elseif (is_ipaddr($rule['target'])) {
                $target_ip = $rule['target'];
            } elseif (is_ipaddr($FilterIflist[$rule['target']]['ip'])) {
                $target_ip = $FilterIflist[$rule['target']]['ip'];
            } else {
                $target_ip = $rule['target'];
            }
            $target_ip = trim($target_ip);

            if ($rule['associated-rule-id'] == "pass") {
                $rdrpass = "pass ";
            } else {
                $rdrpass = "";
            }

            if (isset($rule['nordr'])) {
                $nordr = "no ";
                $rdrpass = "";
            } else {
                $nordr = "";
            }

            if (!$rule['interface']) {
                $natif = "wan";
            } else {
                $natif = $rule['interface'];
            }

            if (!isset($FilterIflist[$natif])) {
                continue;
            }
            $srcaddr = trim(filter_generate_address($FilterIflist, $rule, 'source', true));
            $dstaddr = trim(filter_generate_address($FilterIflist, $rule, 'destination', true));

            if (!$dstaddr) {
                $dstaddr = $FilterIflist[$natif]['ip'];
            }

            $dstaddr_port = explode(" ", $dstaddr);
            if (empty($dstaddr_port[0]) || strtolower(trim($dstaddr_port[0])) == "port") {
                continue; // Skip port forward if no destination address found
            }
            $dstaddr_reflect = $dstaddr;
            if (isset($rule['destination']['any'])) {
                /* With reflection enabled, destination of 'any' has side effects
                 * that most people would not expect, so change it on reflection rules. */
                $dstaddr_reflect = $FilterIflist[$natif]['ip'];
                if (!empty($FilterIflist[$natif]['sn'])) {
                    $dstaddr_reflect = gen_subnet($dstaddr_reflect, $FilterIflist[$natif]['sn']) . '/' . $FilterIflist[$natif]['sn'];
                }
                if ($dstaddr_port[2]) {
                    $dstaddr_reflect .= " port " . $dstaddr_port[2];
                }
            }

            $natif = $FilterIflist[$natif]['if'];

            $localport_nat = $localport;
            if (empty($localport_nat) && $dstaddr_port[2]) {
                $localport_nat = " port " . $dstaddr_port[2];
            }

            $tagging = '';
            if (!empty($rule['tag'])) {
                $tagging .= " tag {$rule['tag']}";
            }
            if (!empty($rule['tagged'])) {
                $tagging .= " tagged {$rule['tagged']}";
            }

            $poolopts = '';
            if (!empty($rule['poolopts']) && (is_subnet($rule['target']) || is_alias($rule['target']))) {
                $poolopts = " {$rule['poolopts']}";
            }

            if ($srcaddr <> "" && $dstaddr <> "" && $natif) {
                $protocol_keyword = !empty($protocol) ? "proto" : "";
                $natrules .= "{$nordr}rdr {$rdrpass}on {$natif} {$address_family} {$protocol_keyword} {$protocol} from {$srcaddr} to {$dstaddr}{$tagging}" . ($nordr == "" ? " -> {$target}{$localport}{$poolopts}" : '');
                /* Does this rule redirect back to a internal host? */
                if (isset($rule['destination']['any']) && !isset($rule['nordr']) && !isset($config['system']['enablenatreflectionhelper'])) {
                    if ($address_family == 'inet6' && !interface_has_gatewayv6($rule['interface'])) {
                        $rule_interface_subnet = find_interface_subnet6($natif);
                        $rule_interface_ip = find_interface_ipv6($natif);
                        $rule_subnet = gen_subnetv6($rule_interface_ip, $rule_interface_subnet);
                    } elseif (!interface_has_gateway($rule['interface'])) {
                        $rule_interface_subnet = find_interface_subnet($natif);
                        $rule_interface_ip = find_interface_ip($natif);
                        $rule_subnet = gen_subnet($rule_interface_ip, $rule_interface_subnet);
                    }
                    if (!empty($rule_interface_ip) && !empty($rule_interface_subnet)) {
                        $natrules .= "\n";
                        $natrules .= "no nat on {$natif} {$address_family} proto tcp from ({$natif}) to {$rule_subnet}/{$rule_interface_subnet}\n";
                        $natrules .= "nat on {$natif} {$address_family} proto tcp from {$rule_subnet}/{$rule_interface_subnet} to {$target} port {$dstport[0]} -> ({$natif})\n";
                    }
                }

                $nat_if_list = array();
                if ($rule['natreflection'] != "disable" && $dstaddr_port[0] != "0.0.0.0" && !is_ipaddrv6($target)) {
                    $nat_if_list = filter_get_reflection_interfaces($FilterIflist, $natif);
                    if (!empty($nat_if_list)) {
                        if (in_array($rule['natreflection'], array("purenat", "enable")) || !isset($config['system']['disablenatreflection'])) {
                            $rdr_if_list = implode(" ", $nat_if_list);
                            if (count($nat_if_list) > 1) {
                                $rdr_if_list = "{ {$rdr_if_list} }";
                            }
                            $natrules .= "\n# Reflection redirect\n";
                            $protocol_keyword = !empty($protocol) ? "proto" : "";
                            $natrules .= "{$nordr}rdr {$rdrpass}on {$rdr_if_list} {$address_family} {$protocol_keyword} {$protocol} from {$srcaddr} to {$dstaddr_reflect}" . ($nordr == "" ? " -> {$target}{$localport}" : "");
                            $nat_if_list = array_merge(array($natif), $nat_if_list);
                        }
                    }
                }

                if (empty($nat_if_list)) {
                    $nat_if_list = array($natif);
                }

                $natrules .= "\n";

                if (!isset($rule['nordr']) && isset($config['system']['enablenatreflectionhelper'])) {
                    $natrules .= filter_generate_reflection_nat($FilterIflist, $rule, $route_table, $nat_if_list, $protocol, "{$target}{$localport_nat}", $target_ip);
                }
            }
        }
    }

    if (!empty($reflection_txt)) {
        $natrules .= "\n# Reflection redirects and NAT for 1:1 mappings\n" . $reflection_txt;
    }

    return $natrules;
}

function filter_generate_port(& $rule, $target = "source", $isnat = false) {
    $src = "";

    if (isset($rule['protocol'])) {
        $rule['protocol'] = strtolower($rule['protocol']);
    }
    if (isset($rule['protocol']) && in_array($rule['protocol'], array("tcp","udp","tcp/udp"))) {
        if (!empty($rule[$target]['port'])) {
            $port = alias_expand(str_replace('-', ':', $rule[$target]['port']));
            if (!empty($port)) {
                $src = " port " . $port;
            }
        }
    }

    return $src;
}

function filter_generate_address(&$FilterIflist, &$rule, $target = 'source', $isnat = false)
{
    global $config;

    $src = '';

    if (isset($rule[$target]['any'])) {
        $src = "any";
    } elseif (!empty($rule[$target]['network'])) {
        $network_name = $rule[$target]['network'];
        $matches = "";
        if ($network_name == '(self)') {
            $src = "(self)";
        } elseif (preg_match("/^(wan|lan|opt[0-9]+)ip$/", $network_name, $matches)) {
            if (empty($FilterIflist[$matches[1]]['if'])) {
                // interface non-existent or in-active
                return null;
            }
            $src = "({$FilterIflist["{$matches[1]}"]['if']})";
        } else {
            if (empty($FilterIflist[$network_name]['if'])) {
                // interface non-existent or in-active
                return null;
            }
            $src = "({$FilterIflist[$network_name]['if']}:network)";
        }
        if (isset($rule[$target]['not'])) {
            $src = " !{$src}";
        }
    } elseif ($rule[$target]['address']) {
        $expsrc = alias_expand($rule[$target]['address']);
        if (isset($rule[$target]['not'])) {
            $not = "!";
        } else {
            $not = "";
        }
        $src = " {$not} {$expsrc}";
    }
    $src .= filter_generate_port($rule, $target, $isnat);

    return $src;
}

function filter_rules_legacy(&$FilterIflist)
{
    global $config;
    $log = array();
    $log['block'] = !isset($config['syslog']['nologdefaultblock']) ? "log" : "";

    $ipfrules = "";
    $isbridged = false;
    if (isset($config['bridges']['bridged'])) {
        foreach ($config['bridges']['bridged'] as $oc2) {
            if (stristr($oc2['members'], $on)) {
                $isbridged = true;
                break;
            }
        }
    }
    foreach ($FilterIflist as $on => $oc) {
        if (!$isbridged && !isset($oc['internal_dynamic'])) {
            $ipfrules .= "antispoof {$log['block']} for {$oc['if']} \n";
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
function filter_get_time_based_rule_status($schedule) {
    /* no schedule? rule should be installed */
    if (empty($schedule))
      return true;
    /*
     * iterate through time blocks and determine
     * if the rule should be installed or not.
     */
    foreach($schedule['timerange'] as $timeday) {
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
    foreach($defined_days as $dd) {
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
    foreach($schedule_days as $day) {
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
    foreach($months as $month) {
        if ($month == $todays_month) {
            return true;
        }
    }
    return false;
}

function filter_setup_logging_interfaces(&$FilterIflist)
{
    global $config;

    $rules = '';
    if (isset($FilterIflist['lan'])) {
        $rules .= "set loginterface {$FilterIflist['lan']['if']}\n";
    } elseif (isset($FilterIflist['wan'])) {
        $rules .= "set loginterface {$FilterIflist['wan']['if']}\n";
    }
    return $rules;
}

function default_state_size()
{
    /* get system memory amount */
    $memory = get_memory();
    $physmem = $memory[0];

    /* Be cautious and only allocate 10% of system memory to the state table */
    $max_states = (int) ($physmem/10)*1000;

    return $max_states;
}

function get_protocols()
{
    $protocols = array('any', 'TCP', 'UDP', 'TCP/UDP', 'ICMP', 'ESP', 'AH', 'GRE', 'IGMP', 'PIM', 'OSPF');

    /* IPv6 extension headers are skipped by the packet filter, we cannot police them */
    $ipv6_ext = array('IPV6-ROUTE', 'IPV6-FRAG', 'IPV6-OPTS', 'IPV6-NONXT', 'MOBILITY-HEADER');

    foreach (explode("\n", file_get_contents('/etc/protocols')) as $line) {
        if (substr($line,0, 1) != "#") {
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
