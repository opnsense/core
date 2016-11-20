<?php

/*
    Copyright (C) 2014-2016 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2010 Ermal Luci
    Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
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

function bsnmpd_enabled()
{
    global $config;

    return isset($config['snmpd']['enable']);
}

function bsnmpd_services()
{
    $services = array();

    if (!bsnmpd_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'bsnmpd';
    $pconfig['description'] = gettext('SNMP Service');
    $pconfig['php']['restart'] = array('bsnmpd_configure_do');
    $pconfig['php']['start'] = array('bsnmpd_configure_do');
    $pconfig['pidfile'] = '/var/run/snmpd.pid';
    $services[] = $pconfig;

    return $services;
}

function bsnmpd_configure()
{
    return array(
        'bootup' => array('bsnmpd_configure_do'),
        'interface' => array('bsnmpd_configure_do'),
    );
}

function bsnmpd_configure_do($verbose = false)
{
    global $config, $g;

    killbyname('bsnmpd');

    if (!bsnmpd_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting SNMP daemon...';
        flush();
    }

    $snmpdconf = <<<EOD
location := "{$config['snmpd']['syslocation']}"
contact := "{$config['snmpd']['syscontact']}"
read := "{$config['snmpd']['rocommunity']}"

EOD;

    if (isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])){
        $snmpdconf .= <<<EOD
# SNMP Trap support.
traphost := {$config['snmpd']['trapserver']}
trapport := {$config['snmpd']['trapserverport']}
trap := "{$config['snmpd']['trapstring']}"


EOD;
    }

    $version = trim(file_get_contents('/usr/local/opnsense/version/opnsense'));
    $platform = $g['product_name'];
    $sysDescr = "{$g['product_name']} " . php_uname("n") .
                " {$version} {$platform} " . php_uname("s") .
                " " . php_uname("r") . " " . trim(shell_exec('uname -p'));

    $snmpdconf .= <<<EOD
system := 1
%snmpd
sysDescr      = "{$sysDescr}"
begemotSnmpdDebugDumpPdus       = 2
begemotSnmpdDebugSyslogPri      = 7
begemotSnmpdCommunityString.0.1 = $(read)

EOD;

    if (isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])){
        $snmpdconf .= <<<EOD
begemotTrapSinkStatus.[$(traphost)].$(trapport) = 4
begemotTrapSinkVersion.[$(traphost)].$(trapport) = 2
begemotTrapSinkComm.[$(traphost)].$(trapport) = $(trap)

EOD;
    }

    $snmpdconf .= <<<EOD
begemotSnmpdCommunityDisable    = 1

EOD;

    $bind_to_ip = "0.0.0.0";
    if (isset($config['snmpd']['bindip'])) {
        if (is_ipaddr($config['snmpd']['bindip'])) {
            $bind_to_ip = $config['snmpd']['bindip'];
        } else {
            $if = get_real_interface($config['snmpd']['bindip']);
            if (does_interface_exist($if)) {
                $bind_to_ip = find_interface_ip($if);
            }
        }
    }

    if (is_port( $config['snmpd']['pollport'] )) {
        $snmpdconf .= <<<EOD
begemotSnmpdPortStatus.{$bind_to_ip}.{$config['snmpd']['pollport']} = 1

EOD;

    }

    $snmpdconf .= <<<EOD
begemotSnmpdLocalPortStatus."/var/run/snmpd.sock" = 1
begemotSnmpdLocalPortType."/var/run/snmpd.sock" = 4

# These are bsnmp macros not php vars.
sysContact      = $(contact)
sysLocation     = $(location)
sysObjectId     = 1.3.6.1.4.1.12325.1.1.2.1.$(system)

snmpEnableAuthenTraps = 2

EOD;

    if (is_array( $config['snmpd']['modules'] )) {
        if (isset($config['snmpd']['modules']['mibii'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."mibII"  = "/usr/lib/snmp_mibII.so"

EOD;
        }

        if (isset($config['snmpd']['modules']['netgraph'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."netgraph" = "/usr/lib/snmp_netgraph.so"
%netgraph
begemotNgControlNodeName = "snmpd"

EOD;
        }

        if (isset($config['snmpd']['modules']['pf'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."pf"     = "/usr/lib/snmp_pf.so"

EOD;
        }

        if (isset($config['snmpd']['modules']['hostres'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."hostres"     = "/usr/lib/snmp_hostres.so"

EOD;
        }
        if (isset($config['snmpd']['modules']['bridge'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."bridge"     = "/usr/lib/snmp_bridge.so"
# config must end with blank line

EOD;
        }
        if (isset($config['snmpd']['modules']['ucd'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."ucd"     = "/usr/local/lib/snmp_ucd.so"

EOD;
        }
        if (isset($config['snmpd']['modules']['regex'])) {
            $snmpdconf .= <<<EOD
begemotSnmpdModulePath."regex"     = "/usr/local/lib/snmp_regex.so"

EOD;
        }
    }

    file_put_contents('/var/etc/snmpd.conf', $snmpdconf);

    mwexec("/usr/sbin/bsnmpd -c /var/etc/snmpd.conf -p /var/run/snmpd.pid");

    if ($verbose) {
        echo "done.\n";
    }
}
