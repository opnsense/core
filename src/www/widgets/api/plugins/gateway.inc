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
 * widget gateway data
 */
function gateway_api()
{
    $result = array();
    $gateways_status = return_gateways_status(true);
    foreach(return_gateways_array() as $gname => $gw) {
        $gatewayItem = array("name" => $gname);
        if (!empty($gateways_status[$gname])) {
            $gatewayItem['address'] = lookup_gateway_ip_by_name($gname);
            $gatewayItem['status'] = strtolower($gateways_status[$gname]['status']);
            $gatewayItem['loss'] = $gateways_status[$gname]['loss'];
            $gatewayItem['delay'] = $gateways_status[$gname]['delay'];
            $gatewayItem['stddev'] = $gateways_status[$gname]['stddev'];
            switch ($gatewayItem['status']) {
                case "none":
                    $gatewayItem['status_translated'] = gettext("Online");
                    break;
                case "down":
                    $gatewayItem['status_translated'] = gettext("Offline");
                    break;
                case "delay":
                    $gatewayItem['status_translated'] = gettext("Latency");
                    break;
                case "stdev": /* XXX this does not exist? */
                    $gatewayItem['status_translated'] = gettext("Deviation");
                    break;
                case "loss":
                    $gatewayItem['status_translated'] = gettext("Packetloss");
                    break;
                default:
                    $gatewayItem['status_translated'] = gettext("Pending");
                    break;
            }
        } else {
            $gatewayItem['address'] = "";
            $gatewayItem['status'] = "~";
            $gatewayItem['status_translated'] =  gettext("Unknown");
            $gatewayItem['loss'] = "~";
            $gatewayItem['stddev'] = "~";
            $gatewayItem['delay'] = "unknown";
        }
        $result[] = $gatewayItem;
    }
    return $result;
}
