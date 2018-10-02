<?php

/*
 *    Copyright (C) 2016 Deciso B.V.
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
 */

function create_OR_FR_Credentials($userID, $password, $livebox_ID)
{
    $useridhex = "";
    $passwordhex = "";
    $i = 0;
    do {
        $useridhex .= sprintf("%02x:", ord($userID{$i}));
        $i++;
    } while ($i < strlen($userID));
    $useridhex = substr($useridhex, 0, -1);
    
    $i = 0;
    do {
        $passwordhex .= sprintf("%02x:", ord($password{$i}));
        $i++;
    } while ($i < strlen($password));
    $passwordhex = substr($passwordhex, 0, -1);
    
    
    // need to add some salt and pepper here.
    
    $send_options = array(
        'dhcp4_send_options' => 'dhcp-class-identifier "sagem", user-class "+FSVDSL_livebox.Internet.softathome.Livebox'.$livebox_ID.', '
                                .'option-90 00:00:00:00:00:00:00:00:00:00:00:'.$useridhex.'; ',
        'dhcp4_request_options' => 'subnet-mask, broadcast-address, dhcp-lease-time, dhcp-renewal-time, dhcp-rebinding-time, domain-search, routers, domain-name-servers, '
                                    .'option-90, option-120, option-125',                                    
        'dhcp6_send_options' => 'ia-pd 0, '
                                 .'raw-option 6 00:0b:00:11:00:17:00:18, '
                                 .'raw-option 15 00:2b:46:53:56:44:53:4c:5f:6c:69:76:65:62:6f:78:2e:49:6e:74:65:72:6e:65:74:2e:73:6f:66:74:61:74:68:6f:6d:65:2e:6c:69:76:65:62:6f:78:'.bin2hex($livebox_ID).', '
                                 .'raw-option 16 00:00:04:0e:00:05:73:61:67:65:6d, '
                                 .'raw-option 11 00:00:00:00:00:00:00:00:00:00:00:'.$useridhex,''
     );

    return ( $send_options );
}