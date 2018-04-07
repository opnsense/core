<?php

/**
 *    Copyright (C) 2018 Fabian Franz
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Firewall;

/**
* Class to dynamically update PF tables (aliases) at runtime. Requires the
* privilege to update the firewall.
*/
class PfctlTable {
    var $table;
    /**
     * init the Class
     * @param string $tbl name of the table
     */
    function __construct($tbl) {
        $this->table = escapeshellarg($tbl);
    }

    /**
     * add an array of IP addresses to the thame
     * @param array $ips Array of IPs as strings
     * @return string output of the command
     */
    function add($ips) {
        $ips = implode(' ', array_map(escapeshellarg, $ips));
        exec("pfctl -t {$this->table} -T add " . $ips . ' 2>&1', $data);
        return $data[0];
    }

    /**
     * list the IP addresses in a table
     * @return array of IP addressed (v4 and v6)
     */
    function show() {
        exec("pfctl -t {$this->table} -T show 2>&1", $data);
        return array_map(trim,$data);
    }

    /**
     * add an array of IP addresses to the thame
     * @param array $ips Array of IPs as strings
     * @return output of the command
     */
    function del($ips) {
        $ips = implode(' ', array_map(escapeshellarg, $ips));
        exec("pfctl -t {$this->table} -T delete " . $ips . ' 2>&1', $data);
        return $data[0];
    }

    /**
     * flush the table
     * @return output of the command
     */
    function flush_table() {
        exec("pfctl -t {$this->table} -T flush 2>&1", $data);
        return $data[0];
    }
}
