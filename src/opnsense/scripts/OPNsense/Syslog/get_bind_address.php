#!/usr/local/bin/php
<?php

// Use legacy code to get bind address

// TODO: make address detection in MVC code and remove this script

require_once("config.inc");
require_once("util.inc");
require_once("services.inc");
require_once("interfaces.inc");

if($argc < 2)
    return;

$source = $argv[1];
$proto = isset($argv[2]) ? $argv[2] : "ipv4";

if (!empty($source)) {
    if ($proto == "ipv6") {
        $ifaddr = is_ipaddr($source) ? $source : get_interface_ipv6($source);
        if (!is_ipaddr($ifaddr)) {
            $ifaddr = get_interface_ip($source);
        }
    } else {
        $ifaddr = is_ipaddr($source) ? $source : get_interface_ip($source);
        if (!is_ipaddr($ifaddr)) {
            $ifaddr = get_interface_ipv6($source);
        }
    }
    if (is_ipaddr($ifaddr)) {
        echo $ifaddr;
    }
}

return;