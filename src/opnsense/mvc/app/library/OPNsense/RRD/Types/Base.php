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

class Base
{
    /**
     * standard dataset values when undefined.
     */
    protected int $ds_heartbeat = 20;
    protected int $ds_min = 0;
    protected int $ds_max = 1000000000;
    private string $filename;

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
     * @param string $name dataset name
     * @param string $opp operation to use
     * @param int $heartbeat heartbeat to use, default when null
     * @param int $min min value to use, default when null
     * @param int $max max value to use, default when null
     */
    public function addDataset(
        string $name,
        string $opp,
        ?int $heartbeat=null,
        ?int $min=null,
        ?int $max=null
    ){
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
     */
    public function addDatasets(
        array $names,
        string $opp,
        ?int $heartbeat=null,
        ?int $min=null,
        ?int $max=null
    ){
        foreach ($names as $name) {
            $this->addDataset($name, $opp, $heartbeat,  $min,  $max);
        }
        return $this;
    }

    /**
     * create or replace RRD database
     * @param bool $overwrite overwrite when file exists
     */
    public function create($overwrite=false)
    {
        if (!$overwrite && file_exists($this->filename)) {
            return $this;
        }
        $cmd_text = sprintf('/usr/local/bin/rrdtool create %s  --step %d ', $this->filename, 60);
        foreach ($this->datasets as $dataset) {
            $cmd_text .= 'DS:' . implode(':', $dataset) . ' ';
        }
        foreach ($this->round_robin_archives as $rra)
        {
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
     */
    public function update(?array $dataset = null)
    {
        $values = [];
        for ($i=0 ; $i < count($this->datasets) ; ++$i) {
            $values[] = !empty($dataset) && isset($dataset[$i]) ? $dataset[$i] : 'U';
        }
        $cmd_text = sprintf('/usr/local/bin/rrdtool update %s N:%s 2>&1', $this->filename, implode(':', $values));
        exec($cmd_text, $rrdcreateoutput, $rrdcreatereturn);
        return $this;
    }

    /**
     * temporary wrapper to return rrd update command (without measurements)
     */
    public function update_cmd()
    {
        return sprintf('/usr/local/bin/rrdtool update %s N:', $this->filename);
    }
}