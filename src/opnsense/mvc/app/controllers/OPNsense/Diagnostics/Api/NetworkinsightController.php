<?php

/**
 *    Copyright (C) 2016-2025 Deciso B.V.
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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Diagnostics\Netflow;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Core\SanitizeFilter;

/**
 * Class NetworkinsightController
 * @package OPNsense\Netflow
 */
class NetworkinsightController extends ApiControllerBase
{
    /**
     * request timeserie data to use for reporting
     * @param string $provider provider class name
     * @param string $measure measure [octets, packets, octets_ps, packets_ps]
     * @param string $from_date from timestamp
     * @param string $to_date to timestamp
     * @param string $resolution resolution in seconds
     * @param string $field field name to aggregate
     * @param null $emulation
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
        $filter = new SanitizeFilter();
        $provider = $filter->sanitize($provider, "alnum");
        $measure = $filter->sanitize($measure, "string");
        $from_date = $filter->sanitize($from_date, "int");
        $to_date = $filter->sanitize($to_date, "int");
        $resolution = $filter->sanitize($resolution, "int");
        $field = $filter->sanitize($field, "string");
        $interfaces = $this->getInterfacesAction();

        $result = array();
        if ($this->request->isGet()) {
            $backend = new Backend();
            // request current data
            $response = $backend->configdRun(
                "netflow aggregate fetch {$provider} {$from_date} {$to_date} {$resolution} {$field}"
            );
            $graph_data = json_decode($response, true);
            if ($graph_data != null) {
                ksort($graph_data);
                $timeseries = array();
                // collect all measurement classes (interface and direction) as the output structure should
                // zero out unknown series.
                $timeserie_keys = [];
                foreach ($graph_data as $timeserie => $timeserie_data) {
                    foreach ($timeserie_data as $timeserie_key => $payload) {
                        if (!in_array($timeserie_key, $timeserie_keys)) {
                            $timeserie_keys[] = $timeserie_key;
                        }
                    }
                }
                foreach ($graph_data as $timeserie => $timeserie_data) {
                    foreach ($timeserie_keys as $timeserie_key) {
                        $payload = !empty($timeserie_data[$timeserie_key]) ? $timeserie_data[$timeserie_key] : [];
                        if (!isset($timeseries[$timeserie_key])) {
                            $timeseries[$timeserie_key] = array();
                        }
                        // measure value
                        $measure_val = 0;
                        if (empty($payload)) {
                            // missing measurement, return 0 for timeserie
                            $measure_val = 0;
                        } elseif ($measure == "octets") {
                            $measure_val = $payload['octets'];
                        } elseif ($measure == "packets") {
                            $measure_val = $payload['packets'];
                        } elseif ($measure == "octets_ps") {
                            $measure_val = $payload['octets'] / $payload['resolution'];
                        } elseif ($measure == "bps") {
                            $measure_val = ($payload['octets'] / $payload['resolution']) * 8;
                        } elseif ($measure == "packets_ps") {
                            $measure_val = $payload['packets'] / $payload['resolution'];
                        }
                        // add to timeseries
                        $timeseries[$timeserie_key][] = [(int)$timeserie * 1000, $measure_val];
                    }
                }
                foreach ($timeseries as $timeserie_key => $data) {
                    $record = [
                        "key" => $timeserie_key,
                        "values" => $data
                    ];
                    if (in_array($provider, ['FlowInterfaceTotals'])) {
                        $tmp = explode(',', $timeserie_key);
                        if (!empty($interfaces[$tmp[0]])) {
                            $record['interface'] = $interfaces[$tmp[0]];
                        }
                        $record['direction'] = $tmp[1] ?? '';
                    }

                    $result[] = $record;
                }
            }
        }
        return $result;
    }

    /**
     * request top usage data (for reporting), values can optionally be filtered using filter_field and filter_value
     * @param string $provider provider class name
     * @param string $from_date from timestamp
     * @param string $to_date to timestamp
     * @param string $field field name(s) to aggregate
     * @param string $measure measure [octets, packets]
     * @param string $max_hits maximum number of results
     * @return array timeseries
     */
    public function topAction(
        $provider = null,
        $from_date = null,
        $to_date = null,
        $field = null,
        $measure = null,
        $max_hits = null
    ) {
        // cleanse input
        $filter = new SanitizeFilter();
        $provider = $filter->sanitize($provider, "alnum");
        $from_date = $filter->sanitize($from_date, "int");
        $to_date = $filter->sanitize($to_date, "int");
        $field = $filter->sanitize($field, "string");
        $measure = $filter->sanitize($measure, "string");
        $max_hits = $filter->sanitize($max_hits, "int");

        if ($this->request->isGet()) {
            $protocols = $this->getProtocolsAction();
            $services = $this->getServicesAction();
            if ($this->request->get("filter_field") != null && $this->request->get("filter_value") != null) {
                $filter_fields = explode(',', $this->request->get("filter_field"));
                $filter_values = explode(',', $this->request->get("filter_value"));
                $data_filter = "";
                foreach ($filter_fields as $field_indx => $filter_field) {
                    if ($data_filter != '') {
                        $data_filter .= ',';
                    }
                    if (isset($filter_values[$field_indx])) {
                        $data_filter .= $filter_field . '=' . $filter_values[$field_indx];
                    }
                }
                $data_filter = "'{$data_filter}'";
            } else {
                // no filter, empty parameter
                $data_filter = "''";
            }
            $backend = new Backend();
            $configd_cmd = "netflow aggregate top {$provider} {$from_date} {$to_date} {$field}";
            $configd_cmd .= " {$measure} {$data_filter} {$max_hits}";
            $response = $backend->configdRun($configd_cmd);
            $graph_data = json_decode($response, true);
            if (is_array($graph_data)) {
                foreach ($graph_data as &$record) {
                    if (isset($record['dst_port']) || isset($record['service_port'])) {
                        $portnum = $record['dst_port'] ?? $record['service_port'];
                        $label = $portnum;
                        $protocol = '';
                        if (isset($record['protocol']) && isset($protocols[$record['protocol']])) {
                            $protocol = sprintf(" (%s)", $protocols[$record['protocol']]);
                        }
                        if (isset($services[$portnum])) {
                            $label = $services[$portnum];
                        }
                        $record['last_seen_str'] = '';
                        if (!empty($record['last_seen'])) {
                            $record['last_seen_str'] = date('Y-m-d H:i:s', $record['last_seen']);
                        }

                        $record['label'] = $label . $protocol;
                    }
                }
                return $graph_data;
            }
        }
        return array();
    }

    /**
     * get metadata from backend aggregation process
     * @return array timeseries
     */
    public function getMetadataAction()
    {
        if ($this->request->isGet()) {
            $backend = new Backend();
            $configd_cmd = "netflow aggregate metadata json";
            $response = $backend->configdRun($configd_cmd);
            $metadata = json_decode($response, true);
            if ($metadata != null) {
                return $metadata;
            }
        }
        return array();
    }

    /**
     * return interface map (device / name)
     * @return array interfaces
     */
    public function getInterfacesAction()
    {
        // map physical interfaces to description / name
        $configObj = Config::getInstance()->object();
        $allInterfaces = array();
        foreach ($configObj->interfaces->children() as $key => $intf) {
            $allInterfaces[(string)$intf->if] = empty($intf->descr) ? $key : (string)$intf->descr;
        }
        return $allInterfaces;
    }

    /**
     * return known protocols
     */
    public function getProtocolsAction()
    {
        $result = array();
        foreach (explode("\n", file_get_contents('/etc/protocols')) as $line) {
            if (strlen($line) > 1 && $line[0] != '#') {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 4) {
                    $result[$parts[1]] = $parts[0];
                }
            }
        }
        return $result;
    }

    /**
     * return known services
     */
    public function getServicesAction()
    {
        $result = array();
        foreach (explode("\n", file_get_contents('/etc/services')) as $line) {
            if (strlen($line) > 1 && $line[0] != '#') {
                // there a few ports which have different names for different protocols, but to not overcomplicate
                // things here, we ignore those exceptions.
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 2) {
                    $portnum = explode('/', trim($parts[1]))[0];
                    $result[$portnum] = $parts[0];
                }
            }
        }
        return $result;
    }

    /**
     * request timeserie data to use for reporting
     * @param string $provider provider class name
     * @param string $from_date from timestamp
     * @param string $to_date to timestamp
     * @param string $resolution resolution in seconds
     * @return string csv output
     */
    public function exportAction(
        $provider = null,
        $from_date = null,
        $to_date = null,
        $resolution = null
    ) {
        $this->response->setRawHeader("Content-Type: application/octet-stream");
        $this->response->setRawHeader("Content-Disposition: attachment; filename=" . $provider . ".csv");
        if ($this->request->isGet() && $provider != null && $resolution != null) {
            $backend = new Backend();
            $configd_cmd = "netflow aggregate export {$provider} {$from_date} {$to_date} {$resolution}";
            $response = $backend->configdRun($configd_cmd);
            return $response;
        } else {
            return "";
        }
    }
}
