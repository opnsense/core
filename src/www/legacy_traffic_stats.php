<?php

/*
    Copyright (C) 2016 Deciso B.V.
    Copyright (C) 2004-2014 by Electric Sheep Fencing LLC
    Copyright (C) 2005-2006 Scott Ullrich <sullrich@gmail.com>
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

require_once('guiconfig.inc');
require_once('interfaces.inc');
require_once('pfsense-utils.inc');

//get interface IP and break up into an array
$real_interface = get_real_interface($_GET['if']);
if (!does_interface_exist($real_interface)) {
    echo gettext("Wrong Interface");
    exit;
} elseif (!empty($_GET['act']) && $_GET['act'] == "top") {
    //
    // find top bandwitdh users
    // (parts copied from bandwidth_by_ip.php)
    //

    //get interface subnet
    $netmask = find_interface_subnet($real_interface);
    $intsubnet = gen_subnet(find_interface_ip($real_interface), $netmask) . "/$netmask";
    $cmd_args = "";
    switch (!empty($_GET['filter']) ? $_GET['filter'] : "") {
        case "local":
            $cmd_args .= " -c " . $intsubnet . " ";
            break;
        case "remote":
        default:
            $cmd_args .= " -lc 0.0.0.0/0 ";
            break;
    }
    if (!empty($_GET['sort']) && $_GET['sort'] == "out") {
        $cmd_args .= " -T ";
    } else {
        $cmd_args .= " -R ";
    }

    $listedIPs = array();
    $cmd_action = "/usr/local/bin/rate -i {$real_interface} -nlq 1 -Aba 20 {$cmd_args} | tr \"|\" \" \" | awk '{ printf \"%s:%s:%s:%s:%s\\n\", $1,  $2,  $4,  $6,  $8 }'";
    exec($cmd_action, $listedIPs);

    $result = array();
    for ($idx = 2 ; $idx < count($listedIPs) ; ++$idx) {
        $fields = explode(':', $listedIPs[$idx]);
        if (!empty($_GET['hostipformat'])) {
            $addrdata = gethostbyaddr($fields[0]);
            if ($_GET['hostipformat'] == 'hostname' && $addrdata != $fields[0]){
                $addrdata = explode(".", $addrdata)[0];
            }
        } else {
            $addrdata = $fields[0];
        }
        $result[] = array('host' => $addrdata, 'in' => $fields[1], 'out' => $fields[2]);
    }
    // return json traffic
    echo json_encode($result);
} else {
    // collect interface statistics
    // original source ifstats.php
    $ifinfo = legacy_interface_stats($real_interface);
    $temp = gettimeofday();
    $timing = (double)$temp["sec"] + (double)$temp["usec"] / 1000000.0;
    echo "$timing|" . $ifinfo['bytes received'] . "|" . $ifinfo['bytes transmitted'] . "\n";
}


exit;
