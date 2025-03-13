<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\RRD\Stats;
use SimpleXMLElement;

class Temperature extends Base
{
    public function run()
    {
        $data = $this->shellCmd(
            '/sbin/sysctl -ni ' . $this->SENSORS()
        );
        if (!empty($data)) {
            foreach ($data as $tmp) {
                $data_tmp[] = preg_replace('/[^0-9,.]/', '', $tmp);
            }
            return $data_tmp;
        }
        return [];
    }

    private function SENSORS()
    {
        $topology_spec = $this->shellCmd('/sbin/sysctl -niq kern.sched.topology_spec');
        $xml = new SimpleXMLElement(implode('', $topology_spec));

        $cpus = $xml->xpath('//children/group[not(children/group)]');

        /* get temperature sensor of each CPU core */
        foreach ($cpus as $core) {

            $cpu_ids = explode(', ', $core->cpu);
            $THREAD_group = ($core->flags->xpath('flag[@name = \'THREAD\']'))[0] == 'THREAD group' ? true : false;

            foreach ($cpu_ids as $cpu_id) {
                $temperature_sensors[] = 'dev.cpu.'.$cpu_id.'.temperature';
                if ($THREAD_group) { # get only the first one
                   break;
                }
            }
        }

        /* no cores identified, get all CPU temperature sensors */
        if (empty($temperature_sensors)) {
            $objects = $this->shellCmd('/sbin/sysctl -Niq dev.cpu');

            $pattern = '/dev\.cpu\.[0-9]+\.temperature/';
            foreach ($objects as $object) {
                if (preg_match($pattern, $object, $cpu_sensor)) {
                    $temperature_sensors[] = $cpu_sensor[0];
                }
            }

            /* no CPUs identified, get acpi zone 0 temperature sensor */
            if (empty($temperature_sensors)) {
                $temperature_sensors[] = 'hw.acpi.thermal.tz0.temperature';
            }

            /* no acpi zone 0 temperature sensor found, using default temperature sensor */
            if (empty($temperature_sensors)) {
                $temperature_sensors[] = 'hw.temperature.CPU';
            }
        }

        sort($temperature_sensors);
        $temperature_sensors = implode(' ', $temperature_sensors);

        return $temperature_sensors;
    }
}
