<?php
/*
    Copyright (C) 2016 Deciso B.V.
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

/**
 * widget interfaces data
 */
function interfaces_api()
{
    $result = array();
    $ifsinfo = get_interfaces_info();
    foreach (get_configured_interface_with_descr() as $ifdescr => $ifname) {
        $ifinfo = $ifsinfo[$ifdescr];
        $interfaceItem = array();
        $interfaceItem['inpkts'] = $ifinfo['inpkts'];
        $interfaceItem['outpkts'] = $ifinfo['outpkts'];
        $interfaceItem['inbytes'] = $ifinfo['inbytes'];
        $interfaceItem['outbytes'] = $ifinfo['outbytes'];
        $interfaceItem['inbytes_frmt'] = format_bytes($ifinfo['inbytes']);
        $interfaceItem['outbytes_frmt'] = format_bytes($ifinfo['outbytes']);
        $interfaceItem['inerrs'] = isset($ifinfo['inerrs']) ? $ifinfo['inerrs'] : "0";
        $interfaceItem['outerrs'] = isset($ifinfo['outerrs']) ? $ifinfo['outerrs'] : "0";
        $interfaceItem['collisions'] = isset($ifinfo['collisions']) ? $ifinfo['collisions'] : "0";
        $interfaceItem['descr'] = $ifdescr;
        $interfaceItem['name'] = $ifname;
        switch ($ifinfo['status']) {
            case 'down':
            case 'no carrier':
            case 'up':
                $interfaceItem['status'] = $ifinfo['status'];
                break;
            case 'associated':
                $interfaceItem['status'] = 'up';
                break;
            default:
                $interfaceItem['status'] = '';
                break;
        }
        $interfaceItem['ipaddr'] = empty($ifinfo['ipaddr']) ? "" : $ifinfo['ipaddr'];
        $interfaceItem['media'] = empty($ifinfo['media']) ? "" : $ifinfo['media'];
        $result[] = $interfaceItem;
    }
    return $result;
}
