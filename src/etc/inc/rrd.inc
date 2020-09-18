<?php

/*
 * Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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

function rrd_create($rrdcreatecmd)
{
    @mkdir('/var/db/rrd', 0775);
    @chown('/var/db/rrd', 'nobody');

    $rrdcreateoutput = array();
    $rrdcreatereturn = 0;
    exec("$rrdcreatecmd 2>&1", $rrdcreateoutput, $rrdcreatereturn);
    if ($rrdcreatereturn != 0) {
        $rrdcreateoutput = implode(" ", $rrdcreateoutput);
        log_error(sprintf('RRD create failed exited with %s, the error is: %s', $rrdcreatereturn, $rrdcreateoutput));
    }
    unset($rrdcreateoutput);
    return $rrdcreatereturn;
}

function rrd_configure($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Generating RRD graphs...';
        flush();
    }

    $rrddbpath = "/var/db/rrd/";

    $traffic = "-traffic.rrd";
    $packets = "-packets.rrd";
    $states = "-states.rrd";
    $wireless = "-wireless.rrd";
    $proc = "-processor.rrd";
    $mem = "-memory.rrd";
    $mbuf = "-mbuf.rrd";
    $cellular = "-cellular.rrd";
    $vpnusers = "-vpnusers.rrd";
    $ntpd = "ntpd.rrd";
    $cputemp = "-cputemp.rrd";

    $rrdtool = "/usr/local/bin/rrdtool";
    $netstat = "/usr/bin/netstat";
    $awk = "/usr/bin/awk";
    $pfctl = "/sbin/pfctl";
    $sysctl = "/sbin/sysctl";
    $cpustats = "/usr/local/sbin/cpustats";
    $ifconfig = "/sbin/ifconfig";
    $ntpq = "/usr/local/sbin/ntpq";

    $rrdtrafficinterval = 60;
    $rrdwirelessinterval = 60;
    $rrdpacketsinterval = 60;
    $rrdstatesinterval = 60;
    $rrdlbpoolinterval = 60;
    $rrdprocinterval = 60;
    $rrdmeminterval = 60;
    $rrdmbufinterval = 60;
    $rrdcellularinterval = 60;
    $rrdvpninterval = 60;
    $rrdntpdinterval = 60;
    $rrdcputempinterval = 60;

    $trafficvalid = $rrdtrafficinterval * 2;
    $wirelessvalid = $rrdwirelessinterval * 2;
    $packetsvalid = $rrdpacketsinterval * 2;
    $statesvalid = $rrdstatesinterval * 2;
    $procvalid = $rrdlbpoolinterval * 2;
    $memvalid = $rrdmeminterval * 2;
    $mbufvalid = $rrdmbufinterval * 2;
    $cellularvalid = $rrdcellularinterval * 2;
    $vpnvalid = $rrdvpninterval * 2;
    $ntpdvalid = $rrdntpdinterval * 2;
    $cputempvalid = $rrdcputempinterval * 2;

    /* Assume 2*10GigE for now */
    $downstream = 2500000000;
    $upstream = 2500000000;

    if (isset($config['rrd']['enable'])) {
        /* create directory if needed */
        if (!is_dir($rrddbpath)) {
            mkdir($rrddbpath, 0775);
        }
        chown($rrddbpath, "nobody");

        /* db update script */
        $rrdupdatesh = "#!/bin/sh\n";
        $rrdupdatesh .= "\n";
        $rrdupdatesh .= "export TERM=dumb\n";
        $rrdupdatesh .= "\n";
        $rrdupdatesh .= 'echo $$ > /var/run/updaterrd.sh.pid';
        $rrdupdatesh .= "\n";
        $rrdupdatesh .= "counter=1\n";
        $rrdupdatesh .= "while [ \"\$counter\" -ne 0 ]\n";
        $rrdupdatesh .= "do\n";
        $rrdupdatesh .= "";

        $ifdescrs = get_configured_interface_with_descr();
        /* IPsec counters */
        $ifdescrs['ipsec'] = "IPsec";
        /* OpenVPN server counters */
        if (isset($config['openvpn']['openvpn-server'])) {
            foreach ($config['openvpn']['openvpn-server'] as $server) {
                $serverid = "ovpns" . $server['vpnid'];
                $ifdescrs[$serverid] = "{$server['description']}";
            }
        }

        /* process all real and pseudo interfaces */
        foreach ($ifdescrs as $ifname => $ifdescr) {
            $temp = get_real_interface($ifname);
            if ($temp != '') {
                $realif = $temp;
            }

            /* TRAFFIC, set up the rrd file */
            if (!file_exists("$rrddbpath$ifname$traffic")) {
                $rrdcreate = "$rrdtool create $rrddbpath$ifname$traffic --step $rrdtrafficinterval ";
                $rrdcreate .= "DS:inpass:COUNTER:$trafficvalid:0:$downstream ";
                $rrdcreate .= "DS:outpass:COUNTER:$trafficvalid:0:$upstream ";
                $rrdcreate .= "DS:inblock:COUNTER:$trafficvalid:0:$downstream ";
                $rrdcreate .= "DS:outblock:COUNTER:$trafficvalid:0:$upstream ";
                $rrdcreate .= "DS:inpass6:COUNTER:$trafficvalid:0:$downstream ";
                $rrdcreate .= "DS:outpass6:COUNTER:$trafficvalid:0:$upstream ";
                $rrdcreate .= "DS:inblock6:COUNTER:$trafficvalid:0:$downstream ";
                $rrdcreate .= "DS:outblock6:COUNTER:$trafficvalid:0:$upstream ";
                $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

                rrd_create($rrdcreate);
                unset($rrdcreate);
            }

            /* enter UNKNOWN values in the RRD so it knows we rebooted. */
            if (file_exists("/var/run/booting")) {
                mwexec("$rrdtool update $rrddbpath$ifname$traffic N:U:U:U:U:U:U:U:U");
            }

            $rrdupdatesh .= "\n";
            $rrdupdatesh .= "# polling traffic for interface $ifname $realif IPv4/IPv6 counters \n";
            $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$traffic N:";
            $rrdupdatesh .= "`$pfctl -vvsI -i {$realif} | awk '\\\n";
            $rrdupdatesh .= "/In4\/Pass/ { b4pi = \$6 };/Out4\/Pass/ { b4po = \$6 };/In4\/Block/ { b4bi = \$6 };/Out4\/Block/ { b4bo = \$6 };\\\n";
            $rrdupdatesh .= "/In6\/Pass/ { b6pi = \$6 };/Out6\/Pass/ { b6po = \$6 };/In6\/Block/ { b6bi = \$6 };/Out6\/Block/ { b6bo = \$6 };\\\n";
            $rrdupdatesh .= "END {print b4pi \":\" b4po \":\" b4bi \":\" b4bo \":\" b6pi \":\" b6po \":\" b6bi \":\" b6bo};'`\n";

            /* PACKETS, set up the rrd file */
            if (!file_exists("$rrddbpath$ifname$packets")) {
                $rrdcreate = "$rrdtool create $rrddbpath$ifname$packets --step $rrdpacketsinterval ";
                $rrdcreate .= "DS:inpass:COUNTER:$packetsvalid:0:$downstream ";
                $rrdcreate .= "DS:outpass:COUNTER:$packetsvalid:0:$upstream ";
                $rrdcreate .= "DS:inblock:COUNTER:$packetsvalid:0:$downstream ";
                $rrdcreate .= "DS:outblock:COUNTER:$packetsvalid:0:$upstream ";
                $rrdcreate .= "DS:inpass6:COUNTER:$packetsvalid:0:$downstream ";
                $rrdcreate .= "DS:outpass6:COUNTER:$packetsvalid:0:$upstream ";
                $rrdcreate .= "DS:inblock6:COUNTER:$packetsvalid:0:$downstream ";
                $rrdcreate .= "DS:outblock6:COUNTER:$packetsvalid:0:$upstream ";
                $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

                rrd_create($rrdcreate);
                unset($rrdcreate);
            }

            /* enter UNKNOWN values in the RRD so it knows we rebooted. */
            if (file_exists("/var/run/booting")) {
                mwexec("$rrdtool update $rrddbpath$ifname$packets N:U:U:U:U:U:U:U:U");
            }

            $rrdupdatesh .= "\n";
            $rrdupdatesh .= "# polling packets for interface $ifname $realif \n";
            $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$packets N:";
            $rrdupdatesh .= "`$pfctl -vvsI -i {$realif} | awk '\\\n";
            $rrdupdatesh .= "/In4\/Pass/ { b4pi = \$4 };/Out4\/Pass/ { b4po = \$4 };/In4\/Block/ { b4bi = \$4 };/Out4\/Block/ { b4bo = \$4 };\\\n";
            $rrdupdatesh .= "/In6\/Pass/ { b6pi = \$4 };/Out6\/Pass/ { b6po = \$4 };/In6\/Block/ { b6bi = \$4 };/Out6\/Block/ { b6bo = \$4 };\\\n";
            $rrdupdatesh .= "END {print b4pi \":\" b4po \":\" b4bi \":\" b4bo \":\" b6pi \":\" b6po \":\" b6bi \":\" b6bo};'`\n";

            /* WIRELESS, set up the rrd file */
            if (isset($config['interfaces'][$ifname]['wireless']['mode']) && $config['interfaces'][$ifname]['wireless']['mode'] == "bss") {
                if (!file_exists("$rrddbpath$ifname$wireless")) {
                    $rrdcreate = "$rrdtool create $rrddbpath$ifname$wireless --step $rrdwirelessinterval ";
                    $rrdcreate .= "DS:snr:GAUGE:$wirelessvalid:0:1000 ";
                    $rrdcreate .= "DS:rate:GAUGE:$wirelessvalid:0:1000 ";
                    $rrdcreate .= "DS:channel:GAUGE:$wirelessvalid:0:1000 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

                    rrd_create($rrdcreate);
                    unset($rrdcreate);
                }

                /* enter UNKNOWN values in the RRD so it knows we rebooted. */
                if (file_exists("/var/run/booting")) {
                    mwexec("$rrdtool update $rrddbpath$ifname$wireless N:U:U:U");
                }

                $rrdupdatesh .= "\n";
                $rrdupdatesh .= "# polling wireless for interface $ifname $realif \n";
                $rrdupdatesh .= "WIFI=`$ifconfig {$realif} list sta| $awk 'gsub(\"M\", \"\") {getline 2;print substr(\$5, 0, length(\$5)-2) \":\" $4 \":\" $3}'`\n";
                $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$wireless N:\${WIFI}\n";
            }

            /* OpenVPN, set up the rrd file */
            if (stristr($ifname, "ovpns")) {
                if (!file_exists("$rrddbpath$ifname$vpnusers")) {
                    $rrdcreate = "$rrdtool create $rrddbpath$ifname$vpnusers --step $rrdvpninterval ";
                    $rrdcreate .= "DS:users:GAUGE:$vpnvalid:0:10000 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

                    rrd_create($rrdcreate);
                    unset($rrdcreate);
                }

                /* enter UNKNOWN values in the RRD so it knows we rebooted. */
                if (file_exists("/var/run/booting")) {
                    mwexec("$rrdtool update $rrddbpath$ifname$vpnusers N:U");
                }

                if (isset($config['openvpn']['openvpn-server'])) {
                    foreach ($config['openvpn']['openvpn-server'] as $server) {
                        if ("ovpns{$server['vpnid']}" == $ifname) {
                            $port = $server['local_port'];
                            $vpnid = $server['vpnid'];
                        }
                    }
                }
                $rrdupdatesh .= "\n";
                $rrdupdatesh .= "# polling vpn users for interface $ifname $realif port $port\n";
                $rrdupdatesh .= "list_current_users() {\n";
                $rrdupdatesh .= " sleep 0.2\n";
                $rrdupdatesh .= " echo \"status 2\"\n";
                $rrdupdatesh .= " sleep 0.2\n";
                $rrdupdatesh .= " echo \"quit\"\n";
                $rrdupdatesh .= "}\n";
                $rrdupdatesh .= "OVPN=`list_current_users | nc -U /var/etc/openvpn/server{$vpnid}.sock | awk -F\",\" '/^CLIENT_LIST/ {print \$2}' | wc -l | awk '{print $1}'`\n";
                $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$vpnusers N:\${OVPN}\n";
            }

            /* 3G interfaces */
            if (preg_match("/ppp[0-9]+/i", $realif)) {
                if (!file_exists("$rrddbpath$ifname$cellular")) {
                    $rrdcreate = "$rrdtool create $rrddbpath$ifname$cellular --step $rrdcellularinterval ";
                    $rrdcreate .= "DS:rssi:GAUGE:$cellularvalid:0:100 ";
                    $rrdcreate .= "DS:upstream:GAUGE:$cellularvalid:0:100000000 ";
                    $rrdcreate .= "DS:downstream:GAUGE:$cellularvalid:0:100000000 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
                    $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";
                    rrd_create($rrdcreate);
                    unset($rrdcreate);
                }

                /* enter UNKNOWN values in the RRD so it knows we rebooted. */
                if (file_exists("/var/run/booting")) {
                    mwexec("$rrdtool update $rrddbpath$ifname$cellular N:U:U:U");
                }

                $rrdupdatesh .= "\n";
                $rrdupdatesh .= "# polling 3G\n";
                $rrdupdatesh .= "GSTATS=`awk -F, 'getline 2 {print \$2 \":\" \$8 \":\" \$9}' < /tmp/3gstats.$ifname`\n";
                $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$cellular N:\"\$GSTATS\"";
            }
        }

        /* System only statistics */
        $ifname = "system";

        /* STATES, create pf states database */
        if (! file_exists("$rrddbpath$ifname$states")) {
            $rrdcreate = "$rrdtool create $rrddbpath$ifname$states --step $rrdstatesinterval ";
            $rrdcreate .= "DS:pfrate:GAUGE:$statesvalid:0:10000000 ";
            $rrdcreate .= "DS:pfstates:GAUGE:$statesvalid:0:10000000 ";
            $rrdcreate .= "DS:pfnat:GAUGE:$statesvalid:0:10000000 ";
            $rrdcreate .= "DS:srcip:GAUGE:$statesvalid:0:10000000 ";
            $rrdcreate .= "DS:dstip:GAUGE:$statesvalid:0:10000000 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

            rrd_create($rrdcreate);
            unset($rrdcreate);
        }

        /* enter UNKNOWN values in the RRD so it knows we rebooted. */
        if (file_exists("/var/run/booting")) {
            mwexec("$rrdtool update $rrddbpath$ifname$states N:U:U:U:U:U");
        }

        /* the pf states gathering function. */
        $rrdupdatesh .= "\n";
        $rrdupdatesh .= "pfctl_si_out=\"` $pfctl -si > /tmp/pfctl_si_out `\"\n";
        $rrdupdatesh .= "pfctl_ss_out=\"` $pfctl -ss > /tmp/pfctl_ss_out`\"\n";
        $rrdupdatesh .= "pfrate=\"` cat /tmp/pfctl_si_out | egrep \"inserts|removals\" | awk '{ pfrate = \$3 + pfrate } {print pfrate}'|tail -1 `\"\n";
        $rrdupdatesh .= "pfstates=\"` cat /tmp/pfctl_ss_out | egrep -v \"<\\-.*?<\\-|\\->.*?\\->\" | wc -l|sed 's/ //g'`\"\n";
        $rrdupdatesh .= "pfnat=\"` cat /tmp/pfctl_ss_out | egrep '<\\-.*?<\\-|\\->.*?\\->' | wc -l|sed 's/ //g' `\"\n";
        $rrdupdatesh .= "srcip=\"` cat /tmp/pfctl_ss_out | egrep -v '<\\-.*?<\\-|\\->.*?\\->' | grep '\\->' | awk '{print \$3}' | awk -F: '{print \$1}' | sort -u|wc -l|sed 's/ //g' `\"\n";
        $rrdupdatesh .= "dstip=\"` cat /tmp/pfctl_ss_out | egrep -v '<\\-.*?<\\-|\\->.*?\\->' | grep '<\\-' | awk '{print \$3}' | awk -F: '{print \$1}' | sort -u|wc -l|sed 's/ //g' `\"\n";
        $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$states N:\$pfrate:\$pfstates:\$pfnat:\$srcip:\$dstip\n\n";
        /* End pf states statistics */

        /* CPU, create CPU statistics database */
        if (!file_exists("$rrddbpath$ifname$proc")) {
            $rrdcreate = "$rrdtool create $rrddbpath$ifname$proc --step $rrdprocinterval ";
            $rrdcreate .= "DS:user:GAUGE:$procvalid:0:10000000 ";
            $rrdcreate .= "DS:nice:GAUGE:$procvalid:0:10000000 ";
            $rrdcreate .= "DS:system:GAUGE:$procvalid:0:10000000 ";
            $rrdcreate .= "DS:interrupt:GAUGE:$procvalid:0:10000000 ";
            $rrdcreate .= "DS:processes:GAUGE:$procvalid:0:10000000 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

            rrd_create($rrdcreate);
            unset($rrdcreate);
        }

        /* enter UNKNOWN values in the RRD so it knows we rebooted. */
        if (file_exists("/var/run/booting")) {
            mwexec("$rrdtool update $rrddbpath$ifname$proc N:U:U:U:U:U");
        }

        /* the CPU stats gathering function. */
        $rrdupdatesh .= "CPU=`$cpustats | cut -f1-4 -d':'`\n";
        /* Using ps uxaH will count all processes including system threads. Top was undercounting. */
        $rrdupdatesh .= "PROCS=`ps uxaH | wc -l | awk '{print \$1;}'`\n";
        $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$proc N:\${CPU}:\${PROCS}\n";
        /* End CPU statistics */

        /* Memory, create Memory statistics database */
        if (!file_exists("$rrddbpath$ifname$mem")) {
            $rrdcreate = "$rrdtool create $rrddbpath$ifname$mem --step $rrdmeminterval ";
            $rrdcreate .= "DS:active:GAUGE:$memvalid:0:10000000 ";
            $rrdcreate .= "DS:inactive:GAUGE:$memvalid:0:10000000 ";
            $rrdcreate .= "DS:free:GAUGE:$memvalid:0:10000000 ";
            $rrdcreate .= "DS:cache:GAUGE:$memvalid:0:10000000 ";
            $rrdcreate .= "DS:wire:GAUGE:$memvalid:0:10000000 ";
            $rrdcreate .= "RRA:MIN:0.5:1:1200 ";
            $rrdcreate .= "RRA:MIN:0.5:5:720 ";
            $rrdcreate .= "RRA:MIN:0.5:60:1860 ";
            $rrdcreate .= "RRA:MIN:0.5:1440:2284 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";
            $rrdcreate .= "RRA:MAX:0.5:1:1200 ";
            $rrdcreate .= "RRA:MAX:0.5:5:720 ";
            $rrdcreate .= "RRA:MAX:0.5:60:1860 ";
            $rrdcreate .= "RRA:MAX:0.5:1440:2284";

            rrd_create($rrdcreate);
            unset($rrdcreate);
        }

        /* enter UNKNOWN values in the RRD so it knows we rebooted. */
        if (file_exists("/var/run/booting")) {
            mwexec("$rrdtool update $rrddbpath$ifname$mem N:U:U:U:U:U");
        }

        /* the Memory stats gathering function. */
        $rrdupdatesh .= "MEM=`$sysctl -n vm.stats.vm.v_page_count vm.stats.vm.v_active_count vm.stats.vm.v_inactive_count vm.stats.vm.v_free_count vm.stats.vm.v_cache_count vm.stats.vm.v_wire_count | ";
        $rrdupdatesh .= " $awk '{getline active;getline inactive;getline free;getline cache;getline wire;printf ";
        $rrdupdatesh .= "((active/$0) * 100)\":\"((inactive/$0) * 100)\":\"((free/$0) * 100)\":\"((cache/$0) * 100)\":\"(wire/$0 * 100)}'`\n";
        $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$mem N:\${MEM}\n";
        /* End Memory statistics */

        /* mbuf, create mbuf statistics database */
        if (!file_exists("$rrddbpath$ifname$mbuf")) {
            $rrdcreate = "$rrdtool create $rrddbpath$ifname$mbuf --step $rrdmbufinterval ";
            $rrdcreate .= "DS:current:GAUGE:$mbufvalid:0:10000000 ";
            $rrdcreate .= "DS:cache:GAUGE:$mbufvalid:0:10000000 ";
            $rrdcreate .= "DS:total:GAUGE:$mbufvalid:0:10000000 ";
            $rrdcreate .= "DS:max:GAUGE:$mbufvalid:0:10000000 ";
            $rrdcreate .= "RRA:MIN:0.5:1:1200 ";
            $rrdcreate .= "RRA:MIN:0.5:5:720 ";
            $rrdcreate .= "RRA:MIN:0.5:60:1860 ";
            $rrdcreate .= "RRA:MIN:0.5:1440:2284 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";
            $rrdcreate .= "RRA:MAX:0.5:1:1200 ";
            $rrdcreate .= "RRA:MAX:0.5:5:720 ";
            $rrdcreate .= "RRA:MAX:0.5:60:1860 ";
            $rrdcreate .= "RRA:MAX:0.5:1440:2284";

            rrd_create($rrdcreate);
            unset($rrdcreate);
        }

        /* enter UNKNOWN values in the RRD so it knows we rebooted. */
        if (file_exists("/var/run/booting")) {
            mwexec("$rrdtool update $rrddbpath$ifname$mbuf N:U:U:U:U");
        }

        /* the mbuf stats gathering function. */
        $rrdupdatesh .= "MBUF=`$netstat -m | ";
        $rrdupdatesh .= " $awk '/mbuf clusters in use/ { gsub(/\//, \":\", $1); print $1; }'`\n";
        $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$mbuf N:\${MBUF}\n";
        /* End mbuf statistics */

        /* End System statistics */

        /* NTP, set up the ntpd rrd file */
        if (isset($config['ntpd']['statsgraph'])) {
            /* set up the ntpd rrd file */
            if (!file_exists("$rrddbpath$ntpd")) {
                $rrdcreate = "$rrdtool create $rrddbpath$ntpd --step $rrdntpdinterval ";
                $rrdcreate .= "DS:offset:GAUGE:$ntpdvalid:-1000:1000 ";
                $rrdcreate .= "DS:sjit:GAUGE:$ntpdvalid:0:1000 ";
                $rrdcreate .= "DS:cjit:GAUGE:$ntpdvalid:0:1000 ";
                $rrdcreate .= "DS:wander:GAUGE:$ntpdvalid:0:1000 ";
                $rrdcreate .= "DS:freq:GAUGE:$ntpdvalid:0:1000 ";
                $rrdcreate .= "DS:disp:GAUGE:$ntpdvalid:0:1000 ";
                $rrdcreate .= "RRA:MIN:0.5:1:1200 ";
                $rrdcreate .= "RRA:MIN:0.5:5:720 ";
                $rrdcreate .= "RRA:MIN:0.5:60:1860 ";
                $rrdcreate .= "RRA:MIN:0.5:1440:2284 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
                $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";
                $rrdcreate .= "RRA:MAX:0.5:1:1200 ";
                $rrdcreate .= "RRA:MAX:0.5:5:720 ";
                $rrdcreate .= "RRA:MAX:0.5:60:1860 ";
                $rrdcreate .= "RRA:MAX:0.5:1440:2284 ";

                rrd_create($rrdcreate);
                unset($rrdcreate);
            }

            /* enter UNKNOWN values in the RRD so it knows we rebooted. */
            if (file_exists("/var/run/booting")) {
                mwexec("$rrdtool update $rrddbpath$ntpd N:U:U:U:U:U:U");
            }
            /* the ntp stats gathering function. */
            $rrdupdatesh .= "\n";
            $rrdupdatesh .= "$ntpq -c rv | $awk 'BEGIN{ RS=\",\"}{ print }' >> /tmp/ntp-rrdstats.$$\n";
            $rrdupdatesh .= "NOFFSET=`grep offset /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NFREQ=`grep frequency /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NSJIT=`grep sys_jitter /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NCJIT=`grep clk_jitter /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NWANDER=`grep clk_wander /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NDISPER=`grep rootdisp /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "$rrdtool update $rrddbpath$ntpd \N:\${NOFFSET}:\${NSJIT}:\${NCJIT}:\${NWANDER}:\${NFREQ}:\${NDISPER}\n";
            $rrdupdatesh .= "rm /tmp/ntp-rrdstats.$$\n";
            $rrdupdatesh .= "\n";
        }
        /* End NTP statistics */

        /* CPU Temperature */
        /* the CPU Temperature gathering Function. */
        /* Cpu Temp, create CPU Temperature database */
        if (!file_exists("$rrddbpath$ifname$cputemp")) {
            $rrdcreate = "$rrdtool create $rrddbpath$ifname$cputemp --step $rrdcputempinterval ";
            $rrdcreate .= "DS:cpu0temp:GAUGE:$cputempvalid:-273:5000 ";
            $rrdcreate .= "RRA:MIN:0.5:1:1000 ";
            $rrdcreate .= "RRA:MIN:0.5:5:1000 ";
            $rrdcreate .= "RRA:MIN:0.5:60:1000 ";
            $rrdcreate .= "RRA:MIN:0.5:720:3000 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:1:1000 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:5:1000 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:60:1000 ";
            $rrdcreate .= "RRA:AVERAGE:0.5:720:3000 ";
            $rrdcreate .= "RRA:MAX:0.5:1:1000 ";
            $rrdcreate .= "RRA:MAX:0.5:5:1000 ";
            $rrdcreate .= "RRA:MAX:0.5:60:1000 ";
            $rrdcreate .= "RRA:MAX:0.5:720:3000 ";
            $rrdcreate .= "RRA:LAST:0.5:1:1000 ";
            $rrdcreate .= "RRA:LAST:0.5:5:1000 ";
            $rrdcreate .= "RRA:LAST:0.5:60:1000 ";
            $rrdcreate .= "RRA:LAST:0.5:720:3000 ";

            rrd_create($rrdcreate);
        }

        /* enter UNKNOWN values in the RRD so it knows we rebooted. */
        if (file_exists("/var/run/booting")) {
            mwexec("$rrdtool update $rrddbpath$ifname$cputemp N:U");
        }

        /* the CPU Temperature gathering function */
        $rrdupdatesh .= "CPUTEMP=\$(/usr/local/opnsense/scripts/system/temperature.sh rrd)\n";
        $rrdupdatesh .= "$rrdtool update $rrddbpath$ifname$cputemp N:\${CPUTEMP}\n";
        /* end CPU Temp gathering */

        /* Start gateway quality */
        $rrdupdatesh .= <<<EOD

           # Gateway quality graphs
            for sock in /var/run/dpinger_*.sock;do
            if [ ! -S "\$sock" ]; then
                continue
            fi

            t=\$(/usr/bin/nc -U \$sock)
            if [ -z "\$t" ]; then
                continue
            fi

            gw=\$(echo "\$t" | awk '{ print \$1 }')
            delay=\$(echo "\$t" | awk '{ print \$2 }')
            stddev=\$(echo "\$t" | awk '{ print \$3 }')
            loss=\$(echo "\$t" | awk '{ print \$4 }')

            if echo "\$loss" | grep -Eqv '^[0-9]+\$'; then
                loss="U"
            fi
            if echo "\$delay" | grep -Eqv '^[0-9]+\$'; then
                delay="U"
            else
                # Convert delay from microseconds to seconds
                delay=\$(echo "scale=7; \$delay / 1000 / 1000" | /usr/bin/bc)
            fi
            if echo "\$stddev" | grep -Eqv '^[0-9]+\$'; then
                stddev="U"
            else
                # Convert stddev from microseconds to seconds
                stddev=\$(echo "scale=7; \$stddev / 1000 / 1000" | /usr/bin/bc)
            fi

            {$rrdtool} update {$rrddbpath}\$gw-quality.rrd -t loss:delay:stddev N:\$loss:\$delay:\$stddev
     done

EOD;

        $rrdupdatesh .= "sleep 60\n";
        $rrdupdatesh .= "done\n";

        /* write the rrd update script */
        $updaterrdscript = '/var/db/rrd/updaterrd.sh';
        $fd = fopen("$updaterrdscript", "w");
        fwrite($fd, "$rrdupdatesh");
        fclose($fd);

        unset($rrdupdatesh);

        /* kill off traffic collectors */
        rrd_stop();

        /* start traffic collector */
        mwexec_bg("/bin/sh $updaterrdscript");
    } else {
        /* kill off traffic collectors */
        rrd_stop();
    }

    $databases = glob("{$rrddbpath}/*.rrd");
    foreach ($databases as $database) {
        chown($database, "nobody");
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function rrd_create_gateway_quality($rrd_file)
{
    $unknown = file_exists('/var/run/booting');
    $rrdtool = '/usr/local/bin/rrdtool';

    $rrdinterval = 60;
    $valid = $rrdinterval * 2;

    /* GATEWAY QUALITY, set up the rrd file */
    if (!file_exists("$rrd_file")) {
        $rrdcreate = "$rrdtool create $rrd_file --step $rrdinterval ";
        $rrdcreate .= "DS:loss:GAUGE:$valid:0:100 ";
        $rrdcreate .= "DS:delay:GAUGE:$valid:0:100000 ";
        $rrdcreate .= "DS:stddev:GAUGE:$valid:0:100000 ";
        $rrdcreate .= "RRA:AVERAGE:0.5:1:1200 ";
        $rrdcreate .= "RRA:AVERAGE:0.5:5:720 ";
        $rrdcreate .= "RRA:AVERAGE:0.5:60:1860 ";
        $rrdcreate .= "RRA:AVERAGE:0.5:1440:2284 ";

        rrd_create($rrdcreate);
        $unknown = true;
    }

    /* enter UNKNOWN values in the RRD so it knows we rebooted. */
    if ($unknown) {
        mwexec("$rrdtool update $rrd_file N:U:U:U");
    }
}

function rrd_stop()
{
    killbypid('/var/run/updaterrd.sh.pid', 'TERM', true);
}

function rrd_export()
{
    $rrddbpath = '/var/db/rrd';

    $result = "\t<rrddata>\n";
    $rrd_files = glob("{$rrddbpath}/*.rrd");
    foreach ($rrd_files as $rrd_file) {
        $basename = basename($rrd_file);
        $xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
        exec("/usr/local/bin/rrdtool dump '{$rrd_file}' '{$xml_file}'");
        $xml_data = @file_get_contents($xml_file);
        @unlink($xml_file);
        if ($xml_data !== false) {
            $result .= "\t\t<rrddatafile>\n";
            $result .= "\t\t\t<filename>{$basename}</filename>\n";
            $result .= "\t\t\t<xmldata>" . base64_encode(gzdeflate($xml_data)) . "</xmldata>\n";
            $result .= "\t\t</rrddatafile>\n";
        }
    }
    $result .= "\t</rrddata>\n";
    return $result;
}

function rrd_import()
{
    global $config;

    foreach ($config['rrddata']['rrddatafile'] as $rrd) {
        if (!empty($rrd['xmldata'])) {
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
            if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
                log_error("Cannot write $xml_file");
                continue;
            }
            $output = array();
            $status = null;
            exec("/usr/local/bin/rrdtool restore -f '{$xml_file}' '{$rrd_file}'", $output, $status);
            if ($status) {
                log_error("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.");
                continue;
            }
            unlink($xml_file);
        } elseif (!empty($rrd['data'])) {
            /* rrd backup format */
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $rrd_fd = fopen($rrd_file, "w");
            if (!$rrd_fd) {
                log_error("Cannot write $rrd_file");
                continue;
            }
            $data = base64_decode($rrd['data']);
            /* Try to decompress the data. */
            $dcomp = @gzinflate($data);
            if ($dcomp) {
                /* If the decompression worked, write the decompressed data */
                if (fwrite($rrd_fd, $dcomp) === false) {
                    log_error("fwrite $rrd_file failed");
                    continue;
                }
            } elseif (fwrite($rrd_fd, $data) === false) {
                  /* If the decompression failed, it wasn't compressed, so write raw data */
                  log_error("fwrite $rrd_file failed");
                  continue;
            }
            if (fclose($rrd_fd) === false) {
                log_error("fclose $rrd_file failed");
                continue;
            }
        }
    }
}
