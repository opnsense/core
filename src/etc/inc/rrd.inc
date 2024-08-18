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
        log_msg(sprintf('RRD create failed exited with %s, the error is: %s', $rrdcreatereturn, $rrdcreateoutput), LOG_ERR);
    }
    unset($rrdcreateoutput);
    return $rrdcreatereturn;
}

function rrd_configure($verbose = false, $bootup = false)
{
    global $config;

    service_log('Generating RRD graphs...', $verbose);

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

    $awk = "/usr/bin/awk";
    $pfctl = "/sbin/pfctl";

    /* XXX: kill off traffic collectors, remove when we know the update required a reboot */
    killbypid('/var/run/updaterrd.pid');

    if (isset($config['rrd']['enable'])) {
        /* create directory if needed */
        if (!is_dir($rrddbpath)) {
            mkdir($rrddbpath, 0775);
        }
        chown($rrddbpath, "nobody");
        /* open file handle to update script and lock it in exclusive mode */
        @touch('/var/db/rrd/updaterrd.sh');
        $fobj = new \OPNsense\Core\FileObject('/var/db/rrd/updaterrd.sh', 'r+', 0755, LOCK_EX);
        $rrd_factory = new \OPNsense\RRD\Factory();

        /* db update script */
        $rrdupdatesh = "#!/bin/sh\n";
        $rrdupdatesh .= "\n";
        $rrdupdatesh .= "export TERM=dumb\n";
        $rrdupdatesh .= "\n";

        $ifdescrs = get_configured_interface_with_descr();
        /* IPsec counters */
        $ifdescrs['ipsec'] = "IPsec";
        $ovpn_servers = (new OPNsense\OpenVPN\OpenVPN())->serverDevices();
        foreach ($ovpn_servers as $ifname => $data) {
            $ifdescrs[$ifname] = $data['descr'];
        }

        /* process all real and pseudo interfaces */
        foreach ($ifdescrs as $ifname => $ifdescr) {
            $temp = get_real_interface($ifname);
            if ($temp != '') {
                $realif = $temp;
            }

            /* TRAFFIC, set up the rrd file */
            $rrd = $rrd_factory->get('Traffic', "$rrddbpath$ifname$traffic");
            $rrd->create()->update();

            $rrdupdatesh .= "\n";
            $rrdupdatesh .= "# polling traffic for interface $ifname $realif IPv4/IPv6 counters \n";
            $rrdupdatesh .= $rrd->update_cmd() ;
            $rrdupdatesh .= "`$pfctl -vvsI -i {$realif} | awk '\\\n";
            $rrdupdatesh .= "/In4\/Pass/ { b4pi = \$6 };/Out4\/Pass/ { b4po = \$6 };/In4\/Block/ { b4bi = \$6 };/Out4\/Block/ { b4bo = \$6 };\\\n";
            $rrdupdatesh .= "/In6\/Pass/ { b6pi = \$6 };/Out6\/Pass/ { b6po = \$6 };/In6\/Block/ { b6bi = \$6 };/Out6\/Block/ { b6bo = \$6 };\\\n";
            $rrdupdatesh .= "END {print b4pi \":\" b4po \":\" b4bi \":\" b4bo \":\" b6pi \":\" b6po \":\" b6bi \":\" b6bo};'`\n";

            /* PACKETS, set up the rrd file */
            $rrd = $rrd_factory->get('Packets', "$rrddbpath$ifname$packets");
            $rrd->create()->update();

            $rrdupdatesh .= "\n";
            $rrdupdatesh .= "# polling packets for interface $ifname $realif \n";
            $rrdupdatesh .= $rrd->update_cmd();
            $rrdupdatesh .= "`$pfctl -vvsI -i {$realif} | awk '\\\n";
            $rrdupdatesh .= "/In4\/Pass/ { b4pi = \$4 };/Out4\/Pass/ { b4po = \$4 };/In4\/Block/ { b4bi = \$4 };/Out4\/Block/ { b4bo = \$4 };\\\n";
            $rrdupdatesh .= "/In6\/Pass/ { b6pi = \$4 };/Out6\/Pass/ { b6po = \$4 };/In6\/Block/ { b6bi = \$4 };/Out6\/Block/ { b6bo = \$4 };\\\n";
            $rrdupdatesh .= "END {print b4pi \":\" b4po \":\" b4bi \":\" b4bo \":\" b6pi \":\" b6po \":\" b6bi \":\" b6bo};'`\n";

            /* WIRELESS, set up the rrd file */
            if (isset($config['interfaces'][$ifname]['wireless']['mode']) && $config['interfaces'][$ifname]['wireless']['mode'] == "bss") {
                $rrd = $rrd_factory->get('Wireless', "$rrddbpath$ifname$wireless");
                $rrd->create()->update();

                $rrdupdatesh .= "\n";
                $rrdupdatesh .= "# polling wireless for interface $ifname $realif \n";
                $rrdupdatesh .= "WIFI=`/sbin/ifconfig {$realif} list sta| $awk 'gsub(\"M\", \"\") {getline 2;print substr(\$5, 0, length(\$5)-2) \":\" $4 \":\" $3}'`\n";
                $rrdupdatesh .= $rrd->update_cmd() . "\${WIFI}\n";
            }

            /* OpenVPN, set up the rrd file */
            if (isset($ovpn_servers[$ifname])) {
                $rrd = $rrd_factory->get('OpenVPN', "$rrddbpath$ifname$vpnusers");
                $rrd->create()->update();
                $rrdupdatesh .= "\n";
                $rrdupdatesh .= "# polling vpn users for interface $ifname\n";
                $rrdupdatesh .= "list_current_users() {\n";
                $rrdupdatesh .= " sleep 0.2\n";
                $rrdupdatesh .= " echo \"status 2\"\n";
                $rrdupdatesh .= " sleep 0.2\n";
                $rrdupdatesh .= " echo \"quit\"\n";
                $rrdupdatesh .= "}\n";
                $rrdupdatesh .= "OVPN=`list_current_users | nc -U {$ovpn_servers[$ifname]['sockFilename']} | awk -F\",\" '/^CLIENT_LIST/ {print \$2}' | wc -l | awk '{print $1}'`\n";
                $rrdupdatesh .= $rrd->update_cmd() . "\${OVPN}\n";
            }
        }

        /* System only statistics */
        $ifname = "system";

        /* STATES, create pf states database */
        $rrd = $rrd_factory->get('States', "$rrddbpath$ifname$states");
        $rrd->create()->update();

        /* the pf states gathering function. */
        $rrdupdatesh .= "\n";
        $rrdupdatesh .= $rrd->update_cmd() . "`/usr/local/opnsense/scripts/system/rrd_pfstate_info.py`\n\n";
        /* End pf states statistics */

        /* CPU, create CPU statistics database */
        $rrd = $rrd_factory->get('Processor', "$rrddbpath$ifname$proc");
        $rrd->create()->update();

        /* the CPU stats gathering function. */
        $rrdupdatesh .= "CPU=`/usr/local/sbin/cpustats | cut -f1-4 -d':'`\n";
        /* Using ps uxaH will count all processes including system threads. Top was undercounting. */
        $rrdupdatesh .= "PROCS=`ps uxaH | wc -l | awk '{print \$1;}'`\n";
        $rrdupdatesh .= $rrd->update_cmd() . "\${CPU}:\${PROCS}\n";
        /* End CPU statistics */

        /* Memory, create Memory statistics database */
        $rrd = $rrd_factory->get('Memory', "$rrddbpath$ifname$mem");
        $rrd->create()->update();

        /* the Memory stats gathering function. */
        $rrdupdatesh .= "MEM=`/sbin/sysctl -n vm.stats.vm.v_page_count vm.stats.vm.v_active_count vm.stats.vm.v_inactive_count vm.stats.vm.v_free_count vm.stats.vm.v_cache_count vm.stats.vm.v_wire_count | ";
        $rrdupdatesh .= " $awk '{getline active;getline inactive;getline free;getline cache;getline wire;printf ";
        $rrdupdatesh .= "((active/$0) * 100)\":\"((inactive/$0) * 100)\":\"((free/$0) * 100)\":\"((cache/$0) * 100)\":\"(wire/$0 * 100)}'`\n";
        $rrdupdatesh .= $rrd->update_cmd() . "\${MEM}\n";
        /* End Memory statistics */

        /* mbuf, create mbuf statistics database */
        $rrd = $rrd_factory->get('Mbuf', "$rrddbpath$ifname$mbuf");
        $rrd->create()->update();

        /* the mbuf stats gathering function. */
        $rrdupdatesh .= "MBUF=`/usr/bin/netstat -m | ";
        $rrdupdatesh .= " $awk '/mbuf clusters in use/ { gsub(/\//, \":\", $1); print $1; }'`\n";
        $rrdupdatesh .= $rrd->update_cmd() ."\${MBUF}\n";
        /* End mbuf statistics */

        /* End System statistics */

        /* NTP, set up the ntpd rrd file */
        if (isset($config['ntpd']['statsgraph'])) {
            $rrd = $rrd_factory->get('Ntp', "$rrddbpath$ntpd");
            $rrd->create()->update();

            /* the ntp stats gathering function. */
            $rrdupdatesh .= "\n";
            $rrdupdatesh .= "/usr/local/sbin/ntpq -c rv | $awk 'BEGIN{ RS=\",\"}{ print }' >> /tmp/ntp-rrdstats.$$\n";
            $rrdupdatesh .= "NOFFSET=`grep offset /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NFREQ=`grep frequency /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NSJIT=`grep sys_jitter /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NCJIT=`grep clk_jitter /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NWANDER=`grep clk_wander /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= "NDISPER=`grep rootdisp /tmp/ntp-rrdstats.$$ | awk 'BEGIN{FS=\"=\"}{print $2}'`\n";
            $rrdupdatesh .= $rrd->update_cmd() . "\${NOFFSET}:\${NSJIT}:\${NCJIT}:\${NWANDER}:\${NFREQ}:\${NDISPER}\n";
            $rrdupdatesh .= "rm /tmp/ntp-rrdstats.$$\n";
            $rrdupdatesh .= "\n";
        }
        /* End NTP statistics */

        /* CPU Temperature */
        /* the CPU Temperature gathering Function. */
        $rrd = $rrd_factory->get('Temperature', "$rrddbpath$ifname$cputemp");
        $rrd->create()->update();

        /* the CPU Temperature gathering function */
        $rrdupdatesh .= "CPUTEMP=\$(/usr/local/opnsense/scripts/system/temperature.sh rrd)\n";
        $rrdupdatesh .= $rrd->update_cmd() . "\${CPUTEMP}\n";
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

            /usr/local/bin/rrdtool update {$rrddbpath}\$gw-quality.rrd -t loss:delay:stddev N:\$loss:\$delay:\$stddev
     done

EOD;

        /* write the rrd update script */
        $fobj->seek(0)->truncate(0)->write($rrdupdatesh);
        unset($fobj);
    } elseif (file_exists('/var/db/rrd/updaterrd.sh')) {
        /* remove script, stop collecting. cron checks for existence if this file */
        unlink('/var/db/rrd/updaterrd.sh');
    }

    $databases = glob("{$rrddbpath}/*.rrd");
    foreach ($databases as $database) {
        chown($database, "nobody");
    }

    service_log("done.\n", $verbose);
}

function rrd_create_gateway_quality($rrd_file, $unknown = false)
{
    $rrd = (new \OPNsense\RRD\Factory())->get('GatewayQuality', $rrd_file);
    $rrd->create()->update();
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
                log_msg("Cannot write $xml_file", LOG_ERR);
                continue;
            }
            $output = array();
            $status = null;
            exec("/usr/local/bin/rrdtool restore -f '{$xml_file}' '{$rrd_file}'", $output, $status);
            if ($status) {
                log_msg("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.", LOG_ERR);
                continue;
            }
            unlink($xml_file);
        } elseif (!empty($rrd['data'])) {
            /* rrd backup format */
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $rrd_fd = fopen($rrd_file, "w");
            if (!$rrd_fd) {
                log_msg("Cannot write $rrd_file", LOG_ERR);
                continue;
            }
            $data = base64_decode($rrd['data']);
            /* Try to decompress the data. */
            $dcomp = @gzinflate($data);
            if ($dcomp) {
                /* If the decompression worked, write the decompressed data */
                if (fwrite($rrd_fd, $dcomp) === false) {
                    log_msg("fwrite $rrd_file failed", LOG_ERR);
                    continue;
                }
            } elseif (fwrite($rrd_fd, $data) === false) {
                  /* If the decompression failed, it wasn't compressed, so write raw data */
                  log_msg("fwrite $rrd_file failed", LOG_ERR);
                  continue;
            }
            if (fclose($rrd_fd) === false) {
                log_msg("fclose $rrd_file failed", LOG_ERR);
                continue;
            }
        }
    }
}
