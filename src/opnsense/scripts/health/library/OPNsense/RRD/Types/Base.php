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

namespace OPNsense\RRD\Types;

abstract class Base
{
    /**
     * standard dataset values when undefined.
     */
    protected int $ds_heartbeat = 20;
    protected int $ds_min = 0;
    protected int $ds_max = 1000000000;
    private string $filename;
    protected static string $basedir = '/var/db/rrd/';
    protected static string $stdfilename = '';

    /**
     * DS:ds-name:{GAUGE | COUNTER | DERIVE | DCOUNTER | DDERIVE | ABSOLUTE}:heartbeat:min:max
     */
    private array $datasets = [];

    /**
     * RRA:{AVERAGE | MIN | MAX | LAST}:xff:steps:rows
     */
    private array $round_robin_archives = [
        ['AVERAGE', 0.5, 1, 1200],
        ['AVERAGE', 0.5, 5, 720],
        ['AVERAGE', 0.5, 60, 1860],
        ['AVERAGE', 0.5, 1440, 2284],
    ];

    /**
     * @param array $ds datasets
     */
    protected function setDatasets($ds)
    {
        $this->datasets = $ds;
    }

    /**
     * @param array $rra datasets
     */
    protected function setRRA($rra)
    {
        $this->round_robin_archives = $rra;
    }

    /**
     * @param string $filename filename to link to this collection
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * Maps datasets (stats) to input a new type object requires
     *
     * @param array collected stats for the type
     * @return \Iterator<string, array>
     */
    public static function payloadSplitter(array $payload)
    {
        if (!empty(static::$stdfilename)) {
            yield static::$basedir . static::$stdfilename => $payload;
        }
    }

    /**
     * @param string $name dataset name
     * @param string $opp operation to use
     * @param int $heartbeat heartbeat to use, default when null
     * @param int $min min value to use, default when null
     * @param int $max max value to use, default when null
     * @return $this
     */
    public function addDataset(
        string $name,
        string $opp,
        ?int $heartbeat = null,
        ?int $min = null,
        ?int $max = null
    ) {
        $this->datasets[] = [
            $name,
            $opp,
            $heartbeat != null ? $heartbeat : $this->ds_heartbeat,
            $min != null ? $min : $this->ds_min,
            $max != null ? $max : $this->ds_max
        ];
        return $this;
    }

    /**
     * @param array $names list of names to add
     * @param string $opp operation to use
     * @param int $heartbeat heartbeat to use, default when null
     * @param int $min min value to use, default when null
     * @param int $max max value to use, default when null
     * @return $this
     */
    public function addDatasets(
        array $names,
        string $opp,
        ?int $heartbeat = null,
        ?int $min = null,
        ?int $max = null
    ) {
        foreach ($names as $name) {
            $this->addDataset($name, $opp, $heartbeat, $min, $max);
        }
        return $this;
    }

    /**
     * create or replace RRD database
     * @param bool $overwrite overwrite when file exists
     * @return $this
     */
    public function create($overwrite = false)
    {
        if (!$overwrite && file_exists($this->filename)) {
            return $this;
        }
        $cmd_text = sprintf('/usr/local/bin/rrdtool create %s  --step %d ', $this->filename, 60);
        foreach ($this->datasets as $dataset) {
            $cmd_text .= 'DS:' . implode(':', $dataset) . ' ';
        }
        foreach ($this->round_robin_archives as $rra) {
            $cmd_text .=  'RRA:' . implode(':', $rra) . ' ';
        }
        $cmd_text .= ' 2>&1';
        exec($cmd_text, $rrdcreateoutput, $rrdcreatereturn);
        if ($rrdcreatereturn != 0) {
            $rrdcreateoutput = implode(" ", $rrdcreateoutput);
            syslog(
                LOG_ERR,
                sprintf('RRD create failed exited with %s, the error is: %s', $rrdcreatereturn, $rrdcreateoutput)
            );
        }
        return $this;
    }

    /**
     * update the dataset
     * @param array $dataset [named] dataset to use
     * @param bool $debug throw debug messages to stdout
     * @return $this
     */
    public function update(array $dataset = [], bool $debug = false)
    {
        $values = [];
        $map_by_name = count($dataset) > 0 && !isset($dataset[0]);
        foreach ($this->datasets as $idx => $ds) {
            if ($map_by_name) {
                $value = isset($dataset[$ds[0]]) ? $dataset[$ds[0]] : 'U';
            } else {
                $value = !empty($dataset) && isset($dataset[$idx]) ? $dataset[$idx] : 'U';
            }
            $values[] = $value;
            if ($value == 'U' && $debug) {
                echo sprintf("[%s] '%s' missing in datafeed\n", get_class($this), $ds[0]);
            }
        }
        $cmd_text = sprintf('/usr/local/bin/rrdtool update %s N:%s 2>&1', $this->filename, implode(':', $values));
        exec($cmd_text, $rrdcreateoutput, $rrdcreatereturn);
        if ($rrdcreatereturn != 0 && $debug) {
            echo sprintf("[cmd failed] %s\n", $cmd_text);
        }
        return $this;
    }

    /**
     * @return string name of the Stats class this type requires data from.
     */
    public static function wantsStats()
    {
        $tmp = explode('\\', static::class);
        return array_pop($tmp);
    }
}
