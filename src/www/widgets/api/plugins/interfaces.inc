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
    $result = [];
    $ifsinfo = json_decode((new OPNsense\Core\Backend())->configdpRun('interface list info', [null, true]), true);
    foreach ($ifsinfo as $if => $ifinfo) {
        if (empty($ifinfo['config']) || (!empty($ifinfo['config']['virtual']))) {
            /* not configured */
            continue;
        }
        $config = $ifinfo['config'];
        $stats = $ifinfo['statistics'];
        $interfaceItem = [
            'inpkts' => $stats['packets received'],
            'outpkts' => $stats['packets transmitted'],
            'inbytes' => $stats['bytes received'],
            'outbytes' => $stats['bytes transmitted'],
            'inbytes_frmt' => format_bytes($stats['bytes received']),
            'outbytes_frmt' => format_bytes($stats['bytes transmitted']),
            'inerrs' => $stats['input errors'],
            'outerrs' => $stats['output errors'],
            'collisions' => $stats['collisions'],
            'descr' => $config['identifier'],
            'name' => !empty($config['descr']) ? $config['descr'] : strtoupper($config['identifier']),
            'status' => (!empty($ifinfo['flags']) && in_array('up', $ifinfo['flags'])) ? 'up' : 'down',
            'media' => !empty($ifinfo['media']) ? $ifinfo['media'] : '',
            'ipaddr' => $ifinfo['ipaddr']
        ];
        $result[] = $interfaceItem;
    }
    return $result;
}
