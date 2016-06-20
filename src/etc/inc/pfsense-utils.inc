<?php

/*
    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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
    RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/



/* Returns the calculated bit length of the prefix delegation from the WAN interface */
/* DHCP-PD is variable, calculate from the prefix-len on the WAN interface */
/* 6rd is variable, calculate from 64 - (v6 prefixlen - (32 - v4 prefixlen)) */
/* 6to4 is 16 bits, e.g. 65535 */
function calculate_ipv6_delegation_length($if) {
    global $config;

    if(!isset($config['interfaces'][$if]) || !is_array($config['interfaces'][$if])) {
        return false;
    } elseif (!isset($config['interfaces'][$if]['ipaddrv6'])) {
        return 0;
    }

    switch($config['interfaces'][$if]['ipaddrv6']) {
        case "6to4":
            $pdlen = 16;
            break;
        case "6rd":
            $rd6cfg = $config['interfaces'][$if];
            $rd6plen = explode("/", $rd6cfg['prefix-6rd']);
            $pdlen = (64 - ($rd6plen[1] + (32 - $rd6cfg['prefix-6rd-v4plen'])));
            break;
        case "dhcp6":
            $dhcp6cfg = $config['interfaces'][$if];
            $pdlen = $dhcp6cfg['dhcp6-ia-pd-len'];
            break;
        default:
            $pdlen = 0;
            break;
    }
    return $pdlen;
}
