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

class Temperature extends Base
{
    /**
     * {@inheritdoc}
     */
    protected int $ds_heartbeat =  120;
    protected int $ds_min = -273;
    protected int $ds_max = 5000;
    protected static string $stdfilename = 'system-cputemp.rrd';

    /**
     * {@inheritdoc}
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->addDatasets($this->DATASETS(), 'GAUGE');
        $this->setRRA([
            ['MIN', 0.5, 1, 1200],
            ['MIN', 0.5, 5, 720],
            ['MIN', 0.5, 60, 1860],
            ['MIN', 0.5, 1440, 2284],
            ['AVERAGE', 0.5, 1, 1200],
            ['AVERAGE', 0.5, 5, 720],
            ['AVERAGE', 0.5, 60, 1860],
            ['AVERAGE', 0.5, 1440, 2284],
            ['MAX', 0.5, 1, 1200],
            ['MAX', 0.5, 5, 720],
            ['MAX', 0.5, 60, 1860],
            ['MAX', 0.5, 1440, 2284],
            ['LAST', 0.5, 1, 1200],
            ['LAST', 0.5, 5, 720],
            ['LAST', 0.5, 60, 1860],
            ['LAST', 0.5, 1440, 2284],
        ]);
    }

    /**
     * return data sets array of Cores/CPUs
     */
    private function DATASETS()
    {
        $count = $this->shellCmd('/sbin/sysctl -niq kern.smp.cores');
        if ($count[0] >= 1) { # Cores
            $type = 'core';
        } else {
            $count = $this->shellCmd('/sbin/sysctl -niq hw.ncpu');
            if ($count[0] >= 1) { # CPUs
                $type = 'cpu';
            } else { # CPU (fail safe)
                $type = 'cpu';
                $count[0] = 1;
            }
        }

        for ($i=1; $i<=$count[0]; $i++) {
            $DSet[] = $type.$i;
        }

        return $DSet;
    }
}
