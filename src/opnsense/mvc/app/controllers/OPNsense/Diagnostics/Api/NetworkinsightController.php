<?php
/**
 *    Copyright (C) 2016 Deciso B.V.
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


namespace OPNsense\Diagnostics\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Diagnostics\Netflow;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;
use \Phalcon\Filter;

/**
 * Class NetworkinsightController
 * @package OPNsense\Netflow
 */
class NetworkinsightController extends ApiControllerBase
{
    /**
     * request timeserie data to use for reporting
     * @param $provider provider class name
     * @param $measure measure [octets, packets, octets_ps, packets_ps]
     * @param $from_date from timestamp
     * @param $to_date to timestamp
     * @param $resolution resolution in seconds
     * @param $field field name to aggregate
     * @return array timeseries
     */
     public function timeserieAction(
        $provider = null,
        $measure = null,
        $from_date = null,
        $to_date = null,
        $resolution = null,
        $field = null,
        $emulation = null
    ) {
        // cleanse input
        $filter = new Filter();
        $provider = $filter->sanitize($provider, "alphanum");
        $measure = $filter->sanitize($measure, "string");
        $from_date = $filter->sanitize($from_date, "int");
        $to_date = $filter->sanitize($to_date, "int");
        $resolution = $filter->sanitize($resolution, "int");
        $field = $filter->sanitize($field, "string");

        // map physical interfaces to description / name
        $configObj = Config::getInstance()->object();
        $allInterfaces = array();
        foreach ($configObj->interfaces->children() as $key => $intf) {
            $allInterfaces[(string)$intf->if] = empty($intf->descr) ? $key : (string)$intf->descr;
        }

        $result = array();
        if ($this->request->isGet()) {
            $backend = new Backend();
            // request current data
            $response = $backend->configdRun("netflow aggregate fetch {$provider} {$from_date} {$to_date} {$resolution} {$field}"); //
            // for test, request random data
            //$response = $backend->configdRun("netflow aggregate fetch {$provider} {$from_date} {$to_date} {$resolution} {$field} em0,em1,em2,em3,em4"); //
            $graph_data = json_decode($response, true);
            if ($graph_data != null) {
                ksort($graph_data);
                $timeseries = array();
                foreach ($graph_data as $timeserie => $interfaces) {
                    foreach ($interfaces as $interface => $payload) {
                        if (!isset($timeseries[$interface])) {
                            $timeseries[$interface] = array();
                        }
                        // measure value
                        $measure_val = 0;
                        if ($measure == "octets") {
                            $measure_val = $payload['octets'];
                        } elseif ($measure == "packets") {
                            $measure_val = $payload['packets'];
                        } elseif ($measure == "octets_ps") {
                            $measure_val = $payload['octets'] / $payload['resolution'];
                        } elseif ($measure == "packets_ps") {
                            $measure_val = $payload['packets'] / $payload['resolution'];
                        }
                        // add to timeseries
                        $timeseries[$interface][] = array((int)$timeserie*1000, $measure_val);
                    }
                }
                foreach ($timeseries as $interface => $data) {
                    if (isset($allInterfaces[$interface])) {
                        $result[] = array("key" => $allInterfaces[$interface], "values" => $data);
                    } else {
                        $result[] = array("key" => $interface, "values" => $data);
                    }
                }
            }
        }
        return $result;
    }
}
