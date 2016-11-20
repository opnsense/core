<?php

/*
    Copyright (C) 2016 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2005-2008 Bill Marquette
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

function relayd_enabled()
{
    global $config;

    return isset($config['load_balancer']['lbpool']) && count($config['load_balancer']['lbpool']) &&
        isset($config['load_balancer']['virtual_server']) && count($config['load_balancer']['virtual_server']);
}

function relayd_firewall($fw)
{
    if (!relayd_enabled()) {
        return;
    }

    $fw->registerAnchor('relayd/*', 'rdr');
    $fw->registerAnchor('relayd/*', 'fw');
}

function relayd_services()
{
    $services = array();

    if (!relayd_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'relayd';
    $pconfig['description'] = gettext('Server load balancing daemon');
    $pconfig['php']['restart'] = array('relayd_configure_do');
    $pconfig['php']['start'] = array('relayd_configure_do');
    $services[] = $pconfig;

    return $services;
}

function relayd_syslog()
{
    $logfacilities = array();

    $logfacilities['relayd'] = array('facility' => array('relayd'), 'remote' => 'relayd');

    return $logfacilities;
}

function relayd_subnetv4_expand($subnet)
{
    $result = array();

    list ($ip, $bits) = explode("/", $subnet);

    $net  = ip2long($ip);
    $mask = (0xffffffff << (32 - $bits));
    $net &= $mask;
    $size = round(exp(log(2) * (32 - $bits)));

    for ($i = 0; $i < $size; $i += 1) {
        $result[] = long2ip($net | $i);
    }

    return $result;
}

function relayd_configure()
{
    return array(
        'bootup' => array('relayd_configure_do'),
        'remote' => array('relayd_configure_do'),
    );
}

function relayd_configure_do($kill_first = false)
{
    global $config;

    if (isset($config['load_balancer']['virtual_server']) && is_array($config['load_balancer']['virtual_server'])) {
        $vs_a = $config['load_balancer']['virtual_server'];
    } else {
        $vs_a = array();
    }
    if (isset($config['load_balancer']['lbpool']) && is_array($config['load_balancer']['lbpool'])) {
        $pool_a = $config['load_balancer']['lbpool'];
    } else {
        $pool_a = array();
    }
    if (isset($config['load_balancer']['setting']) && is_array($config['load_balancer']['setting'])) {
        $setting = $config['load_balancer']['setting'];
    } else {
        $setting = array();
    }
    if (isset($config['load_balancer']['monitor_type']) && is_array($config['load_balancer']['monitor_type'])) {
        $monitors_a = $config['load_balancer']['monitor_type'];
    } else {
        $monitors_a = array();
    }

    $check_a = array();

    foreach ($monitors_a as $type) {
        $type['options'] = isset($type['options']) ? $type['options'] : array();
        switch($type['type']) {
            case 'icmp':
            case 'tcp':
                $check_a[$type['name']] = 'check ' . $type['type'];
                break;
            case 'http':
            case 'https':
                $check_a[$type['name']] = 'check ' . $type['type']. " ";
                if (!empty($type['options']['path'])) {
                    $check_a[$type['name']] .= "'".$type['options']['path'] . "' ";
                }
                if (!empty($type['options']['host'])) {
                    $check_a[$type['name']] .= "host ".$type['options']['host'] . " ";
                }
                $check_a[$type['name']] .= "code " . $type['options']['code'];
                break;
            case 'send':
                $check_a[$type['name']] = "send  ";
                $check_a[$type['name']] .= !empty($type['options']['send']) ? "\"{$type['options']['send']}\"" : "\"\"" ;
                $check_a[$type['name']] .= " expect ";
                $check_a[$type['name']] .= !empty($type['options']['expect']) ? "\"{$type['options']['expect']}\"" : "\"\"" ;
                break;
        }
    }

    $fd = fopen('/var/etc/relayd.conf', 'w');
    $conf = "log updates \n";

    if (!empty($setting['timeout'])) {
        $conf .= "timeout ".$setting['timeout']." \n";
    } else {
        $conf .= "timeout 1000 \n";
    }

    if (!empty($setting['interval'])) {
        $conf .= "interval ".$setting['interval']." \n";
    }

    if (!empty($setting['prefork'])) {
        $conf .= "prefork ".$setting['prefork']." \n";
    }

    /* reindex pools by name as we loop through the pools array */
    $pools = array();
    /* Virtual server pools */
    for ($i = 0; isset($pool_a[$i]); $i++) {
        if (is_array($pool_a[$i]['servers'])) {
            if (!empty($pool_a[$i]['retry'])) {
                $retrytext = " retry {$pool_a[$i]['retry']}";
            } else {
                $retrytext = "";
            }
            $conf .= "table <{$pool_a[$i]['name']}> {\n";
            foreach ($pool_a[$i]['servers'] as $server) {
                if (is_subnetv4($server)) {
                  foreach (relayd_subnetv4_expand($server) as $ip) {
                      $conf .= "\t{$ip}{$retrytext}\n";
                  }
              } else {
                  $conf .= "\t{$server}{$retrytext}\n";
              }
            }
            $conf .= "}\n";
            /* Index by name for easier fetching when we loop through the virtual servers */
            $pools[$pool_a[$i]['name']] = $pool_a[$i];
        }
    }

    // collect used protocols
    $used_protocols = array();
    foreach ($vs_a as $vs) {
        if (isset($vs['relay_protocol']) && !in_array($vs['relay_protocol'], $used_protocols)) {
            $used_protocols[] = $vs['relay_protocol'];
            if (is_file('/usr/local/etc/inc/plugins.inc.d/relayd/'.basename($vs['relay_protocol']).'.proto')) {
                $conf .= file_get_contents('/usr/local/etc/inc/plugins.inc.d/relayd/'.basename($vs['relay_protocol']).'.proto')."\n";
            }
        }
    }

    for ($i = 0; isset($vs_a[$i]); $i++) {
        $append_port_to_name = false;
        if (is_alias($pools[$vs_a[$i]['poolname']]['port'])) {
            $dest_port_array = filter_expand_alias_array($pools[$vs_a[$i]['poolname']]['port']);
            $append_port_to_name = true;
        } else {
            $dest_port_array = array($pools[$vs_a[$i]['poolname']]['port']);
        }
        if (is_alias($vs_a[$i]['port'])) {
            $src_port_array = filter_expand_alias_array($vs_a[$i]['port']);
            $append_port_to_name = true;
        } elseif ($vs_a[$i]['port']) {
            $src_port_array = array($vs_a[$i]['port']);
        } else {
            $src_port_array = $dest_port_array;
        }

        $append_ip_to_name = false;
        if (is_alias($vs_a[$i]['ipaddr'])) {
            $ip_list = array();
            foreach (filter_expand_alias_array($vs_a[$i]['ipaddr']) as $item) {
                log_error("item is $item");
                if (is_subnetv4($item)) {
                    $ip_list = array_merge($ip_list, relayd_subnetv4_expand($item));
                } else {
                    $ip_list[] = $item;
                }
            }
            $append_ip_to_name = true;
        } elseif (is_subnetv4($vs_a[$i]['ipaddr'])) {
            $ip_list = relayd_subnetv4_expand($vs_a[$i]['ipaddr']);
            $append_ip_to_name = true;
        } else {
            $ip_list = array($vs_a[$i]['ipaddr']);
        }

        for ($j = 0; $j < count($ip_list); $j += 1) {
            $ip = $ip_list[$j];
            for ($k = 0; $k < count($src_port_array) && $k < count($dest_port_array); $k += 1) {
                $src_port  = $src_port_array[$k];
                $dest_port = $dest_port_array[$k];

                $name = $vs_a[$i]['name'];
                if ($append_ip_to_name) {
                    $name .= "_" . $j;
                }
                if ($append_port_to_name) {
                    $name .= "_" . $src_port;
                }

                if ($vs_a[$i]['mode'] == 'relay') {
                    // relay mode
                    $conf .= "relay \"{$name}\" {\n";
                    $conf .= "  listen on {$ip} port {$src_port} \n";
                    $conf .= "  protocol \"{$vs_a[$i]['relay_protocol']}\"\n";
                    $lbmode = "";
                    if ($pools[$vs_a[$i]['poolname']]['mode'] == "loadbalance") {
                        $lbmode = "mode loadbalance";
                    }

                    $conf .= "  forward to <{$vs_a[$i]['poolname']}> port {$dest_port} {$lbmode} {$check_a[$pools[$vs_a[$i]['poolname']]['monitor']]} \n";

                    if (isset($vs_a[$i]['sitedown']) &&  strlen($vs_a[$i]['sitedown']) > 0 && ($vs_a[$i]['relay_protocol'] != 'dns')) {
                        $conf .= "  forward to <{$vs_a[$i]['sitedown']}> port {$dest_port} {$lbmode} {$check_a[$pools[$vs_a[$i]['poolname']]['monitor']]} \n";
                    }
                    $conf .= "}\n";
                } else  {
                    // redirect mode
                    $conf .= "redirect \"{$name}\" {\n";
                    $conf .= "  listen on {$ip} port {$src_port}\n";
                    $conf .= "  forward to <{$vs_a[$i]['poolname']}> port {$dest_port} {$check_a[$pools[$vs_a[$i]['poolname']]['monitor']]} \n";

                    if (isset($config['load_balancer']['setting']['lb_use_sticky'])) {
                        $conf .= "  sticky-address\n";
                    }

                    /* sitedown MUST use the same port as the primary pool - sucks, but it's a relayd thing */
                    if (isset($vs_a[$i]['sitedown']) && strlen($vs_a[$i]['sitedown']) > 0 && ($vs_a[$i]['relay_protocol'] != 'dns')) {
                        $conf .= "  forward to <{$vs_a[$i]['sitedown']}> port {$dest_port} {$check_a[$pools[$vs_a[$i]['sitedown']]['monitor']]} \n";
                    }

                    $conf .= "}\n";
                }
            }
        }
    }
    fwrite($fd, $conf);
    fclose($fd);

    if (is_process_running('relayd')) {
        if (! empty($vs_a)) {
            if ($kill_first) {
                killbyname('relayd');
                /* Remove all active relayd anchors now that relayd is no longer running. */
                relayd_cleanup_lb_anchor('*');
                mwexec('/usr/local/sbin/relayd -f /var/etc/relayd.conf');
            } else {
                // it's running and there is a config, just reload
                mwexec('/usr/local/sbin/relayctl reload');
            }
        } else {
            /*
             * XXX: Something breaks our control connection with relayd
             * and makes 'relayctl stop' not work
             * rule reloads are the current suspect
             * mwexec('/usr/local/sbin/relayctl stop');
             *  returns "command failed"
             */
            killbyname('relayd');
            /* Remove all active relayd anchors now that relayd is no longer running. */
            relayd_cleanup_lb_anchor("*");
        }
    } elseif (!empty($vs_a)) {
          // not running and there is a config, start it
          /* Remove all active relayd anchors so it can start fresh. */
          relayd_cleanup_lb_anchor('*');
          mwexec('/usr/local/sbin/relayd -f /var/etc/relayd.conf');
    }
}

function relayd_get_lb_redirects()
{
/*
# relayctl show summary
Id   Type      Name                      Avlblty Status
1    redirect  testvs2                           active
5    table     test2:80                          active (3 hosts up)
11   host      192.168.1.2               91.55%  up
10   host      192.168.1.3               100.00% up
9    host      192.168.1.4               88.73%  up
3    table     test:80                           active (1 hosts up)
7    host      192.168.1.2               66.20%  down
6    host      192.168.1.3               97.18%  up
0    redirect  testvs                            active
3    table     test:80                           active (1 hosts up)
7    host      192.168.1.2               66.20%  down
6    host      192.168.1.3               97.18%  up
4    table     testvs-sitedown:80                active (1 hosts up)
8    host      192.168.1.4               84.51%  up
# relayctl show redirects
Id   Type      Name                      Avlblty Status
1    redirect  testvs2                           active
0    redirect  testvs                            active
# relayctl show redirects
Id   Type      Name                      Avlblty Status
1    redirect  testvs2                           active
           total: 2 sessions
           last: 2/60s 2/h 2/d sessions
           average: 1/60s 0/h 0/d sessions
0    redirect  testvs                            active
*/
    $rdr_a = array();
    exec('/usr/local/sbin/relayctl show redirects 2>&1', $rdr_a);
    $relay_a = array();
    exec('/usr/local/sbin/relayctl show relays 2>&1', $relay_a);
    $vs = array();
    $cur_entry = "";
    for ($i = 0; isset($rdr_a[$i]); $i++) {
        $line = $rdr_a[$i];
        if (preg_match("/^[0-9]+/", $line)) {
            $regs = array();
            if ($x = preg_match("/^[0-9]+\s+redirect\s+([^\s]+)\s+([^\s]+)/", $line, $regs)) {
                $cur_entry = trim($regs[1]);
                $vs[trim($regs[1])] = array();
                $vs[trim($regs[1])]['status'] = trim($regs[2]);
            }
        } elseif (($x = preg_match("/^\s+total:\s(.*)\ssessions/", $line, $regs)) && !empty($cur_entry)) {
            $vs[$cur_entry]['total'] = trim($regs[1]);
        } elseif (($x = preg_match("/^\s+last:\s(.*)\ssessions/", $line, $regs)) && !empty($cur_entry)) {
            $vs[$cur_entry]['last'] = trim($regs[1]);
        } elseif (($x = preg_match("/^\s+average:(.*)\ssessions/", $line, $regs)) && !empty($cur_entry)) {
            $vs[$cur_entry]['average'] = trim($regs[1]);
        }
    }
    $cur_entry = "";
    for ($i = 0; isset($relay_a[$i]); $i++) {
        $line = $relay_a[$i];
        if (preg_match("/^[0-9]+/", $line)) {
            $regs = array();
            if ($x = preg_match("/^[0-9]+\s+relay\s+([^\s]+)\s+([^\s]+)/", $line, $regs)) {
                $cur_entry = trim($regs[1]);
                $vs[trim($regs[1])] = array();
                $vs[trim($regs[1])]['status'] = trim($regs[2]);
            }
        } elseif (($x = preg_match("/^\s+total:\s(.*)\ssessions/", $line, $regs)) && !empty($cur_entry)) {
            $vs[$cur_entry]['total'] = trim($regs[1]);
        } elseif (($x = preg_match("/^\s+last:\s(.*)\ssessions/", $line, $regs)) && !empty($cur_entry)) {
            $vs[$cur_entry]['last'] = trim($regs[1]);
        } elseif (($x = preg_match("/^\s+average:(.*)\ssessions/", $line, $regs)) && !empty($cur_entry)) {
            $vs[$cur_entry]['average'] = trim($regs[1]);
        }
    }
    return $vs;
}

function relayd_get_lb_summary()
{
    $relayctl = array();
    exec('/usr/local/sbin/relayctl show summary 2>&1', $relayctl);
    $relay_hosts=Array();
    foreach( (array) $relayctl as $line) {
        $t = explode("\t", $line);
        if (isset($t[1])) {
            switch (trim($t[1])) {
                case "table":
                    $curpool=trim($t[2]);
                    break;
                case "host":
                    $curhost=trim($t[2]);
                    if (!isset($relay_hosts[$curpool])) {
                        $relay_hosts[$curpool] = array();
                    }
                    if (!isset($relay_hosts[$curpool][$curhost])) {
                        $relay_hosts[$curpool][$curhost]['avail'] = array();
                    }
                    $relay_hosts[$curpool][$curhost]['avail']=trim($t[3]);
                    $relay_hosts[$curpool][$curhost]['state']=trim($t[4]);
                    break;
            }
        }
    }
    return $relay_hosts;
}

/*
 * Remove NAT rules from a relayd anchor that is no longer in use.
 * $anchorname can either be "*" to clear all anchors or a specific
 * anchor name.
 */
function relayd_cleanup_lb_anchor($anchorname = "*")
{
    /* NOTE: These names come back prepended with "relayd/" e.g. "relayd/MyVSName" */
    $lbanchors = explode("\n", trim(`/sbin/pfctl -sA -a relayd | /usr/bin/awk '{print $1;}'`));
    foreach ($lbanchors as $lba) {
        if (($anchorname == "*") || ($lba == "relayd/{$anchorname}")) {
            /* Flush both the NAT and the Table for the anchor, so it will be completely removed by pf. */
            mwexecf('/sbin/pfctl -a %s -F nat', $lba);
            mwexecf('/sbin/pfctl -a %s -F Tables', $lba);
        }
    }
}

/* Mark an anchor for later cleanup. This will allow us to remove an old VS name */
function relayd_cleanup_lb_mark_anchor($name)
{
    /* Nothing to do! */
    if (empty($name)) {
        return;
    }

    $filename = '/tmp/relayd_anchors_remove';
    $cleanup_anchors = array();

    /* Read in any currently unapplied name changes */
    if (file_exists($filename)) {
        $cleanup_anchors = explode("\n", file_get_contents($filename));
    }

    /* Only add the anchor to the list if it's not already there. */
    if (!in_array($name, $cleanup_anchors)) {
        $cleanup_anchors[] = $name;
    }

    file_put_contents($filename, implode("\n", $cleanup_anchors));
}

function relayd_cleanup_lb_marked()
{
    global $config;

    $filename = '/tmp/relayd_anchors_remove';
    $cleanup_anchors = array();

    /* Nothing to do! */
    if (!file_exists($filename)) {
        return;
    } else {
        $cleanup_anchors = explode("\n", file_get_contents($filename));
        /* Nothing to do! */
        if (empty($cleanup_anchors)) {
            return;
        }
    }

    /* Load current names so we can make sure we don't remove an anchor that is still in use. */
    $active_vsnames = array();
    if (isset($config['load_balancer']['virtual_server'])) {
        foreach ($config['load_balancer']['virtual_server'] as $vs) {
            $active_vsnames[] = $vs['name'];
        }
    }

    foreach ($cleanup_anchors as $anchor) {
        /* Only cleanup an anchor if it is not still active. */
        if (!in_array($anchor, $active_vsnames)) {
            relayd_cleanup_lb_anchor($anchor);
        }
    }

    @unlink($filename);
}
