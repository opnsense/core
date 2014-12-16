<?php
/*
    # Copyright (C) 2014 Deciso B.V.
    #
    # All rights reserved.
    #
    # Redistribution and use in source and binary forms, with or without
    # modification, are permitted provided that the following conditions are met:
    #
    # 1. Redistributions of source code must retain the above copyright notice,
    #    this list of conditions and the following disclaimer.
    #
    # 2. Redistributions in binary form must reproduce the above copyright
    #    notice, this list of conditions and the following disclaimer in the
    #    documentation and/or other materials provided with the distribution.
    #
    # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    # POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    package : Captive portal
    function: provides access to the systems ARP table

*/


namespace Captiveportal;


class ARP {

    /**
     * pointer to shell object
     * @var \Core\Shell
     */
    private $shell ;

    /**
     * construct new ARP table handlers
     */
    function __construct()
    {
        $this->shell = new \Core\Shell();
    }

    /**
     * set static arp entry
     * @param $ipaddress hosts ipaddress
     * @param $mac  hosts physical address
     */
    function setStatic($ipaddress,$mac){
        // validate input, only set static entries for valid addresses
        if (preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/', trim($mac))){
            if ( filter_var($ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6) ){
                $this->shell->exec("/usr/sbin/arp -s ".trim($ipaddress)." ".trim($mac));
            }
        }
    }

    /**
     * drop static arp entry
     * @param $ipaddress hosts ipaddress
     */
    function dropStatic($ipaddress){
        // validate input, drop arp entry
        if ( filter_var($ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6) ){
            $this->shell->exec("/usr/sbin/arp -d ".trim($ipaddress) );
        }
    }

    /**
     * Return arp table hashed by mac address
     */
    function getMACs(){
        $result = array();
        $shell_output = array();
        // execute arp shell command and collect (only valid) info into named array
        if ($this->shell->exec("arp -an",false,false,$shell_output) == 0 ){
            foreach($shell_output as $line){
                $line_parts = explode(" ",$line) ;
                if ( sizeof($line_parts) >= 4 ) {
                    $ipaddress = substr($line_parts[1],1,strlen($line_parts[1])-2 ) ;
                    // reformat mac addresses, sometimes arp return segments without trailing zero's
                    $mac_raw = strtolower($line_parts[3]);
                    $mac = "";
                    foreach(explode(":",$mac_raw) as $segment ){
                        if ( $mac != "") $mac .= ":";
                        if (strlen($segment) == 1) $mac .= "0".$segment;
                        else $mac .= $segment ;
                    }
                    if (preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/', trim($mac))){
                        $result[$mac]= array('ip'=>$ipaddress);
                    }
                }
            }
        }
        return $result;
    }


} 